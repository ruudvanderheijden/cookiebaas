<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   FRONTEND-AJAX — consent loggen en de geo-check. Via admin-ajax, dus
   nooit in de paginacache. In 3.0 ongewijzigd verhuisd uit de oude
   includes/admin.php.
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

add_action( 'wp_ajax_nopriv_cm_log_consent', 'cm_ajax_log_consent' );
add_action( 'wp_ajax_cm_log_consent',        'cm_ajax_log_consent' );
function cm_ajax_log_consent() {
    global $wpdb;
    $table = $wpdb->prefix . 'cm_consent_log';

    // Anti-spam: alleen loggen als het verzoek van een echte bezoeker komt
    // die de banner heeft gezien (pageload-methode mag altijd)
    $method_raw = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
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

    $raw_a      = isset($_POST['analytics'])  ? strval($_POST['analytics'])  : '0';
    $raw_m      = isset($_POST['marketing'])  ? strval($_POST['marketing'])  : '0';
    $analytics  = ( $raw_a === '1' || $raw_a === 'true' ) ? 1 : 0;
    $marketing  = ( $raw_m === '1' || $raw_m === 'true' ) ? 1 : 0;
    $method     = sanitize_text_field( isset($_POST['method'])     ? $_POST['method']     : '' );
    $session_id = sanitize_text_field( isset($_POST['session_id']) ? $_POST['session_id'] : '' );
    $url        = esc_url_raw( isset($_POST['url'])                ? $_POST['url']        : '' );
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

    // IP anonimiseren (AVG)
    $ip_raw  = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : ( isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '' );
    $ip_raw  = trim(explode(',', $ip_raw)[0]);
    $ip_hash = hash('sha256', $ip_raw . wp_salt('auth'));

    // Rate limit per IP-hash: max 20 logs per 10 min. De sessie-limiet hieronder
    // is te omzeilen (session_id komt uit de request body); deze limiet niet.
    // Geen nonce-check: page caching serveert verouderde nonces waardoor
    // legitieme consents niet meer gelogd zouden worden.
    $rl_key   = 'cm_rl_' . substr( $ip_hash, 0, 32 );
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

    $method_clean = in_array( $method, array('accept-all','reject-all','custom','pageload','embed-accept'), true ) ? $method : 'custom';

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
        'url'            => substr( $url, 0, 500 ),
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
        'id'         => $wpdb->insert_id,
        'consent_id' => $consent_id,
        'analytics'  => $analytics,
        'marketing'  => $marketing,
        'method'     => $method_clean,
    ) );
}

