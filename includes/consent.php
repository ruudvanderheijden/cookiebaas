<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   FRONTEND-AJAX — consent loggen en de geo-check. Via admin-ajax, dus
   nooit in de paginacache. In 3.0 verhuisd uit de oude includes/admin.php
   en beveiligd: echt IP, herkomstcontrole, ingekorte IP-hash, alleen het
   pad van de pagina.
================================================================ */
/* ================================================================
   AJAX — GEO-CHECK
   Bepaalt per bezoeker (op basis van het IP-land) of de banner getoond moet
   worden. Dit gebeurt bewust via admin-ajax — dat wordt niet door paginacaches
   gecachet — zodat de per-bezoeker beslissing nooit in gedeelde HTML belandt.
================================================================ */
add_action( 'wp_ajax_nopriv_cm_geo_check', 'cm_ajax_geo_check' );
add_action( 'wp_ajax_cm_geo_check',        'cm_ajax_geo_check' );
function cm_ajax_geo_check() {
    nocache_headers();
    wp_send_json_success( array( 'requires_consent' => cm_requires_consent_banner() ) );
}

/* ================================================================
   AJAX — CONSENT LOGGING
================================================================ */

/** Automatische keuzes krijgen een eigen methode, zodat het bewijs niet "zelf gekozen" suggereert. */
function cm_log_methods() {
    return array( 'accept-all', 'reject-all', 'custom', 'pageload', 'embed-accept', 'geo-auto', 'dnt', 'gpc' );
}

/**
 * Het IP-adres van de bezoeker. Basis is REMOTE_ADDR: een door de client
 * meegestuurde X-Forwarded-For is te vervalsen en omzeilde de rate-limit.
 * Alleen als REMOTE_ADDR zelf een lokaal of privé-adres is (een reverse proxy
 * op dezelfde server of in hetzelfde netwerk, zoals nginx, Varnish of Docker)
 * gebruiken we de laatste hop uit X-Forwarded-For: die zette de proxy zelf.
 * Anders zouden alle bezoekers één IP delen en na 20 registraties niets meer
 * gelogd worden. Voor bijv. Cloudflare zonder real-IP-herstel kan een site het
 * echte adres via de filter cm_client_ip doorgeven.
 */
function cm_client_ip() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? trim( $_SERVER['REMOTE_ADDR'] ) : '';
    $is_public = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
    if ( ! $is_public && isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && is_string( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $hops = array_map( 'trim', explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        $last = end( $hops );
        if ( filter_var( $last, FILTER_VALIDATE_IP ) ) $ip = $last;
    }
    return (string) apply_filters( 'cm_client_ip', $ip );
}

/** Sleutel voor de rate-limit: het volledige IPv4-adres, of het /64-netwerk bij IPv6 (één aansluiting heeft een hele /64). */
function cm_rate_limit_ip( $ip ) {
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        return inet_ntop( substr( inet_pton( $ip ), 0, 8 ) . str_repeat( "\0", 8 ) );
    }
    return (string) $ip;
}

/** IP inkorten vóór het hashen (AVG): IPv4 tot /24, IPv6 tot /48. Ongeldig → ''. */
function cm_ip_truncate( $ip ) {
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) return preg_replace( '/\.\d+$/', '.0', $ip );
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        return inet_ntop( substr( inet_pton( $ip ), 0, 6 ) . str_repeat( "\0", 10 ) );
    }
    return '';
}

/** Eigen sleutel voor de log-hashes (niet de auth-salt van WordPress hergebruiken). */
function cm_log_hash_key() {
    $key = get_option( 'cm_log_hash_key' );
    if ( ! is_string( $key ) || strlen( $key ) < 32 ) {
        $key = bin2hex( random_bytes( 32 ) );
        update_option( 'cm_log_hash_key', $key, false );
    }
    return $key;
}

/** Gepseudonimiseerd IP voor de log: ingekort en gehasht met de eigen sleutel. */
function cm_ip_hash( $ip ) {
    $short = cm_ip_truncate( $ip );
    return $short === '' ? '' : hash_hmac( 'sha256', $short, cm_log_hash_key() );
}

/** Alleen het pad van de pagina bewaren: een querystring kan e-mailadressen of tokens bevatten. */
function cm_log_clean_url( $raw ) {
    $raw  = is_string( $raw ) ? $raw : '';
    $path = strtok( $raw, '?#' );
    return substr( esc_url_raw( $path === false ? '' : $path ), 0, 500 );
}

/**
 * Komt het verzoek van deze website? Een andere site mag geen registraties uit
 * naam van bezoekers insturen. Browsers sturen Sec-Fetch-Site mee (niet te
 * vervalsen door een pagina); anders de Origin. Zonder beide (oude browsers,
 * scripts) valt het terug op de rate-limit per IP. Sites met meerdere domeinen
 * (bijv. een domein per taal) kunnen extra hosts toestaan via de filter
 * cm_log_allowed_hosts.
 */
function cm_log_origin_allowed() {
    $site   = isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) && is_string( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ? strtolower( trim( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) : '';
    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) && is_string( $_SERVER['HTTP_ORIGIN'] ) ? trim( $_SERVER['HTTP_ORIGIN'] ) : '';
    if ( $site === 'same-origin' || $site === 'same-site' ) return true;
    if ( $site === '' && $origin === '' ) return true;
    $norm  = function ( $url ) { return preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ); };
    $hosts = array_map( $norm, (array) apply_filters( 'cm_log_allowed_hosts', array( home_url(), site_url() ) ) );
    $host  = $norm( $origin );
    return $host !== '' && in_array( $host, $hosts, true );
}

add_action( 'wp_ajax_nopriv_cm_log_consent', 'cm_ajax_log_consent' );
add_action( 'wp_ajax_cm_log_consent',        'cm_ajax_log_consent' );
function cm_ajax_log_consent() {
    global $wpdb;
    $table = $wpdb->prefix . 'cm_consent_log';

    // Alleen tekstvelden; een array (url[]=x) liet dit endpoint crashen
    $post = function ( $key ) { return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; };

    if ( ! cm_log_origin_allowed() ) {
        wp_send_json_success( array( 'skipped' => 'origin' ) );
        return;
    }

    // Anti-spam: alleen loggen als het verzoek van een echte bezoeker komt
    // die de banner heeft gezien (pageload-methode mag altijd)
    $method_raw = sanitize_text_field( $post( 'method' ) );
    if ( $method_raw !== 'pageload' && empty( $_COOKIE['cc_cm_consent'] ) ) {
        wp_send_json_success( array( 'skipped' => 'no_cookie' ) );
        return;
    }

    // Tabel aanmaken als die nog niet bestaat (bestaande installaties)
    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( ! $exists ) {
        cm_create_log_table();
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( ! $exists ) {
            wp_send_json_error( array( 'msg' => 'Tabel kon niet aangemaakt worden' ) );
            return;
        }
    }

    $raw_a      = $post( 'analytics' );
    $raw_m      = $post( 'marketing' );
    $analytics  = ( $raw_a === '1' || $raw_a === 'true' ) ? 1 : 0;
    $marketing  = ( $raw_m === '1' || $raw_m === 'true' ) ? 1 : 0;
    $method     = $method_raw;
    $session_id = substr( sanitize_text_field( $post( 'session_id' ) ), 0, 64 );
    $url        = cm_log_clean_url( $post( 'url' ) );
    // User agent anonimiseren (AVG) — alleen browser-familie bewaren, geen versienummers of OS
    $ua_raw    = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $ua_family = 'Overig';
    if ( stripos($ua_raw, 'Edg/')    !== false ) $ua_family = 'Edge';
    elseif ( stripos($ua_raw, 'OPR/') !== false || stripos($ua_raw, 'Opera') !== false ) $ua_family = 'Opera';
    elseif ( stripos($ua_raw, 'Chrome') !== false ) $ua_family = 'Chrome';
    elseif ( stripos($ua_raw, 'Safari') !== false && stripos($ua_raw, 'Chrome') === false ) $ua_family = 'Safari';
    elseif ( stripos($ua_raw, 'Firefox') !== false ) $ua_family = 'Firefox';
    elseif ( stripos($ua_raw, 'MSIE') !== false || stripos($ua_raw, 'Trident') !== false ) $ua_family = 'Internet Explorer';
    // Apparaattype toevoegen (Mobile/Tablet/Desktop) — niet herleidbaar
    if ( stripos($ua_raw, 'Tablet') !== false || stripos($ua_raw, 'iPad') !== false ) $ua_family .= ' (Tablet)';
    elseif ( stripos($ua_raw, 'Mobile') !== false || stripos($ua_raw, 'Android') !== false && stripos($ua_raw, 'Mobile') !== false ) $ua_family .= ' (Mobiel)';
    else $ua_family .= ' (Desktop)';

    // IP pseudonimiseren (AVG): ingekort en gehasht met een eigen sleutel
    $ip_raw  = cm_client_ip();
    $ip_hash = cm_ip_hash( $ip_raw );

    // Rate limit per volledig IP (alleen als kortlevende transient-sleutel, niet
    // bewaard): max 20 logs per 10 min. De sessie-limiet hieronder is te omzeilen
    // (session_id komt uit de request body); deze limiet niet.
    // Geen nonce-check: page caching serveert verouderde nonces waardoor
    // legitieme consents niet meer gelogd zouden worden.
    $rl_key   = 'cm_rl_' . substr( hash_hmac( 'sha256', cm_rate_limit_ip( $ip_raw ), cm_log_hash_key() ), 0, 32 );
    $rl_count = (int) get_transient( $rl_key );
    if ( $rl_count >= 20 ) {
        wp_send_json_success( array( 'skipped' => 'ip_rate_limit' ) );
        return;
    }
    set_transient( $rl_key, $rl_count + 1, 10 * MINUTE_IN_SECONDS );

    // Rate limit: max 5 logs per sessie per 10 min (niet voor pageload)
    if ( $session_id && $method !== 'pageload' ) {
        $recent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE session_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            $session_id
        ));
        if ( (int) $recent >= 5 ) {
            wp_send_json_success( array( 'skipped' => 'rate_limit' ) );
            return;
        }
    }

    $method_clean = in_array( $method, cm_log_methods(), true ) ? $method : 'custom';
    // Terugkerend bezoek: alleen dat het gebeurde, zonder pagina of IP (dataminimalisatie)
    if ( $method_clean === 'pageload' ) {
        $url     = '';
        $ip_hash = '';
    }

    // Genereer uniek Consent ID (UUID v4 formaat)
    $consent_id = sprintf( '%08x-%04x-4%03x-%04x-%012x',
        random_int(0, 0xffffffff),
        random_int(0, 0xffff),
        random_int(0, 0xfff),
        random_int(0x8000, 0xbfff),
        random_int(0, 0xffffffffffff)
    );

    // Config hash — korte hash als bewijs van de banner-inhoud op het moment van consent
    // Bevat: consent-versie, bannertitel (begin), aantal cookies. Niet herleidbaar, wel uniek per configuratie.
    $config_snap = array(
        'v'  => get_option( 'cm_consent_version', 1 ),
        'ta' => cm_get('txt_banner_title'),
        'tb' => substr( cm_get('txt_banner_body'), 0, 200 ),
        'ck' => count( cm_get_cookie_list() ),
    );
    $config_hash = substr( hash( 'sha256', json_encode( $config_snap ) ), 0, 16 );

    $result = $wpdb->insert( $table, array(
        'consent_id'     => $consent_id,
        'session_id'     => $session_id ?: substr( md5( uniqid('', true) ), 0, 32 ),
        'analytics'      => $analytics,
        'marketing'      => $marketing,
        'method'         => $method_clean,
        'ip_hash'        => $ip_hash,
        'user_agent'     => $ua_family,
        'url'            => $url,
        'config_hash'    => $config_hash,
        'plugin_version' => CM_VERSION,
        'created_at'     => current_time( 'mysql', false ),
    ), array('%s','%s','%d','%d','%s','%s','%s','%s','%s','%s','%s') );

    if ( $result === false ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[Cookiebaas] Consent log insert mislukt: ' . $wpdb->last_error );
        }
        wp_send_json_error( array( 'msg' => 'Consent kon niet worden opgeslagen.' ) );
        return;
    }

    wp_send_json_success( array(
        'consent_id' => $consent_id,
        'analytics'  => $analytics,
        'marketing'  => $marketing,
        'method'     => $method_clean,
    ) );
}

