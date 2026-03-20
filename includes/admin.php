<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'cm_register_menu' );
function cm_register_menu() {
    add_menu_page(
        'Cookiebaas', 'Cookiebaas', 'manage_options',
        'cookiemelding', 'cm_render_admin_page',
        'dashicons-privacy', 81
    );
    add_submenu_page( 'cookiemelding', 'Instellingen — Cookiebaas',       'Instellingen',          'manage_options', 'cookiemelding',                   'cm_render_admin_page' );
    add_submenu_page( 'cookiemelding', 'Cookies & scan — Cookiebaas',     'Cookies & scan',        'manage_options', 'cookiemelding-cookies',            'cm_render_cookies_page' );
    add_submenu_page( 'cookiemelding', 'Privacyverklaring — Cookiebaas',  'Privacyverklaring',     'manage_options', 'cookiemelding-privacy',            'cm_render_privacy_standalone_page' );
    add_submenu_page( 'cookiemelding', 'Consent log — Cookiebaas',        'Consent log',           'manage_options', 'cookiemelding-log',                'cm_render_log_page' );
    add_submenu_page( 'cookiemelding', 'Beheer — Cookiebaas',             'Beheer',                'manage_options', 'cookiemelding-beheer',             'cm_render_beheer_page' );
}

add_action( 'admin_enqueue_scripts', 'cm_admin_assets' );
function cm_admin_assets( $hook ) {
    $our_hooks = array(
        'toplevel_page_cookiemelding',
        'toplevel_page_cookiemelding',
        'cookiemelding_page_cookiemelding-cookies',
        'cookiemelding_page_cookiemelding-tracking',
        'cookiemelding_page_cookiemelding-privacy',
        'cookiemelding_page_cookiemelding-log',
        'cookiemelding_page_cookiemelding-compliance',
        'cookiemelding_page_cookiemelding-exportimport',
        'cookiemelding_page_cookiemelding-help',
        'cookiemelding_page_cookiemelding-reset',
        'cookiemelding_page_cookiemelding-beheer',
        'cookiebaas_page_cookiemelding-cookies',
        'cookiebaas_page_cookiemelding-tracking',
        'cookiebaas_page_cookiemelding-privacy',
        'cookiebaas_page_cookiemelding-log',
        'cookiebaas_page_cookiemelding-compliance',
        'cookiebaas_page_cookiemelding-exportimport',
        'cookiebaas_page_cookiemelding-help',
        'cookiebaas_page_cookiemelding-reset',
        'cookiebaas_page_cookiemelding-beheer',
    );
    if ( ! in_array( $hook, $our_hooks, true ) ) return;
    wp_enqueue_style(  'cm-admin',         CM_PLUGIN_URL . 'assets/css/admin.css',    array(), CM_VERSION );
    wp_enqueue_style(  'cm-frontend-prev', CM_PLUGIN_URL . 'assets/css/frontend.css', array(), CM_VERSION );
    wp_enqueue_script( 'cm-admin',         CM_PLUGIN_URL . 'assets/js/admin.js',      array('jquery'), CM_VERSION, true );
    wp_localize_script( 'cm-admin', 'CM_DATA', array(
        'nonce'    => wp_create_nonce( 'cm_save_settings' ),
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'settings' => get_option( 'cm_settings', cm_default_settings() ),
        'defaults' => cm_default_settings(),
    ));
}

add_action( 'wp_ajax_cm_save_settings', 'cm_ajax_save_settings' );

add_action( 'wp_ajax_cm_reset_google', 'cm_ajax_reset_google' );
function cm_ajax_reset_google() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );
    $existing = (array) get_option( 'cm_settings', array() );
    $existing['ga4_measurement_id'] = '';
    $existing['gtm_container_id']   = '';
    $existing['ua_tracking_id']     = '';
    update_option( 'cm_settings', $existing );
    wp_send_json_success();
}


function cm_ajax_reset_settings() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );
    update_option( 'cm_settings', cm_default_settings() );
    wp_send_json_success();
}

add_action( 'wp_ajax_cm_reset_cookielist', 'cm_ajax_reset_cookielist' );
function cm_ajax_reset_cookielist() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );
    update_option( 'cm_cookie_list', array() );
    wp_send_json_success();
}

add_action( 'wp_ajax_cm_reset_privacy', 'cm_ajax_reset_privacy' );
function cm_ajax_reset_privacy() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );
    update_option( 'cm_privacy', cm_default_privacy() );
    wp_send_json_success();
}

add_action( 'wp_ajax_cm_bump_consent_version', 'cm_ajax_bump_consent_version' );
function cm_ajax_bump_consent_version() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

    $v = (int) get_option( 'cm_consent_version', 1 );
    $new_v = $v + 1;
    update_option( 'cm_consent_version', $new_v );

    // Sla changelog op
    $reason    = sanitize_text_field( isset($_POST['reason']) ? wp_unslash($_POST['reason']) : '' );
    $changelog = get_option( 'cm_consent_changelog', array() );
    $changelog[] = array(
        'date'    => date_i18n( get_option('date_format') . ' ' . get_option('time_format') ),
        'version' => $new_v,
        'reason'  => $reason,
    );
    // Max 50 entries bewaren
    if ( count($changelog) > 50 ) $changelog = array_slice($changelog, -50);
    update_option( 'cm_consent_changelog', $changelog );

    wp_send_json_success( array( 'version' => $new_v ) );
}

function cm_ajax_save_settings() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

    $defaults = cm_default_settings();

    // Begin met bestaande opgeslagen waarden zodat velden van andere pagina's nooit verloren gaan
    $existing = get_option( 'cm_settings', array() );
    $settings = is_array( $existing ) ? $existing : array();

    // Zorg dat alle defaultkeys als fallback aanwezig zijn
    foreach ( $defaults as $key => $default ) {
        if ( ! array_key_exists( $key, $settings ) ) {
            $settings[ $key ] = $default;
        }
    }

    // Verwerk alleen de velden die daadwerkelijk in deze POST zitten
    // Checkboxes komen als 0 mee als ze uitgevinkt zijn (JS stuurt altijd de waarde)
    // Velden van andere pagina's ontbreken in POST → bestaande waarde blijft intact
    foreach ( $defaults as $key => $default ) {
        if ( ! isset( $_POST[ $key ] ) ) continue;

        // Velden die HTML mogen bevatten (alle taalvarianten van body-teksten)
        $html_fields = array( 'txt_banner_body', 'txt_prefs_body', 'txt_banner_body_en', 'txt_prefs_body_en' );
        if ( in_array( $key, $html_fields, true ) ) {
            $settings[ $key ] = wp_kses( wp_unslash( $_POST[ $key ] ), array(
                'a' => array( 'href' => array(), 'target' => array() ),
                'strong' => array(),
                'em'     => array(),
            ));
        } else {
            $settings[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
        }
    }

    update_option( 'cm_settings', $settings );

    // Cron herplannen als retentie is gewijzigd
    cm_maybe_schedule_retention_cron();

    wp_send_json_success( array( 'message' => 'Opgeslagen.' ) );
}

/* ================================================================
   OPEN COOKIE DATABASE — IMPORT
================================================================ */
add_action( 'wp_ajax_cm_import_cookie_db', 'cm_ajax_import_cookie_db' );
function cm_ajax_import_cookie_db() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

    global $wpdb;
    $table = $wpdb->prefix . 'cm_cookie_db';

    // Download CSV van GitHub
    $csv_url  = 'https://raw.githubusercontent.com/jkwakman/Open-Cookie-Database/master/open-cookie-database.csv';
    $response = wp_remote_get( $csv_url, array(
        'timeout'   => 60,
        'sslverify' => true,
    ));

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'msg' => 'Download mislukt: ' . $response->get_error_message() ) );
        return;
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        wp_send_json_error( array( 'msg' => 'Lege response ontvangen.' ) );
        return;
    }

    // Zorg dat tabel bestaat
    cm_create_cookie_db_table();

    // Leeg de tabel en herlaad
    $wpdb->query( "TRUNCATE TABLE {$table}" );

    // CSV parsen
    $lines   = explode( "\n", str_replace( "\r\n", "\n", $body ) );
    $header  = null;
    $imported = 0;
    $skipped  = 0;

    // Categorie mapping
    $cat_map = array(
        'Functional'      => 'functional',
        'Analytics'       => 'analytics',
        'Marketing'       => 'marketing',
        'Personalization' => 'functional',
        'Security'        => 'functional',
    );

    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( empty( $line ) ) continue;

        // Eenvoudige CSV-parser die quoted velden ondersteunt
        $fields = cm_parse_csv_line( $line );
        if ( ! $fields || count( $fields ) < 9 ) { $skipped++; continue; }

        // Eerste rij = header
        if ( $header === null ) {
            $header = $fields;
            continue;
        }

        // Kolommen: ID,Platform,Category,Cookie/Data Key name,Domain,Description,Retention period,Data Controller,User Privacy & GDPR Rights Portals,Wildcard match
        $cookie_id   = sanitize_text_field( $fields[0] );
        $platform    = sanitize_text_field( $fields[1] );
        $category_raw= sanitize_text_field( $fields[2] );
        $cookie_name = sanitize_text_field( $fields[3] );
        $domain      = sanitize_text_field( $fields[4] );
        $description = sanitize_textarea_field( $fields[5] );
        $retention   = cm_translate_retention( sanitize_text_field( $fields[6] ) );
        $controller  = sanitize_text_field( $fields[7] );
        $privacy_url = esc_url_raw( $fields[8] );
        $wildcard    = isset( $fields[9] ) ? intval( $fields[9] ) : 0;

        if ( empty( $cookie_name ) ) { $skipped++; continue; }

        $category = isset( $cat_map[ $category_raw ] ) ? $cat_map[ $category_raw ] : 'functional';

        $wpdb->insert( $table, array(
            'cookie_id'   => $cookie_id,
            'platform'    => $platform,
            'category'    => $category,
            'cookie_name' => $cookie_name,
            'domain'      => $domain,
            'description' => $description,
            'retention'   => $retention,
            'controller'  => $controller,
            'privacy_url' => $privacy_url,
            'wildcard'    => $wildcard,
        ), array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%d') );

        if ( $wpdb->insert_id ) $imported++;
        else $skipped++;
    }

    // Sla datum op
    update_option( 'cm_cookie_db_updated', current_time('mysql') );
    update_option( 'cm_cookie_db_count', $imported );

    wp_send_json_success( array(
        'imported' => $imported,
        'skipped'  => $skipped,
        'msg'      => "Database bijgewerkt: {$imported} cookies geïmporteerd.",
    ));
}

/**
 * Eenvoudige CSV-regelparser die quoted velden met komma's ondersteunt.
 */
function cm_parse_csv_line( $line ) {
    $fields = array();
    $i      = 0;
    $len    = strlen( $line );
    $field  = '';

    while ( $i < $len ) {
        if ( $line[$i] === '"' ) {
            $i++; // sla openingsquote over
            while ( $i < $len ) {
                if ( $line[$i] === '"' && isset($line[$i+1]) && $line[$i+1] === '"' ) {
                    $field .= '"'; $i += 2;
                } elseif ( $line[$i] === '"' ) {
                    $i++; break;
                } else {
                    $field .= $line[$i++];
                }
            }
            // sla komma na sluitquote over
            if ( $i < $len && $line[$i] === ',' ) $i++;
        } else {
            $pos = strpos( $line, ',', $i );
            if ( $pos === false ) {
                $field .= substr( $line, $i );
                $i = $len;
            } else {
                $field .= substr( $line, $i, $pos - $i );
                $i = $pos + 1;
            }
        }
        $fields[] = $field;
        $field = '';
    }
    return $fields;
}

add_action( 'wp_ajax_cm_scan_urls', 'cm_ajax_scan_urls' );
/**
 * Stap 1: Geeft alle te scannen URLs terug.
 */
function cm_ajax_scan_urls() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $home = trailingslashit( home_url('/') );
    $urls = array( $home );
    $visited = array( $home => true );

    $all_pts = get_post_types( array( 'public' => true ), 'names' );
    unset( $all_pts['attachment'] );

    $all_content = get_posts( array(
        'post_type'      => array_values( $all_pts ),
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));
    foreach ( $all_content as $id ) {
        $url = trailingslashit( get_permalink($id) );
        if ( $url && ! isset($visited[$url]) ) {
            $visited[$url] = true;
            $urls[] = $url;
        }
    }

    wp_send_json_success( array( 'urls' => $urls, 'total' => count($urls) ) );
}

add_action( 'wp_ajax_cm_scan_batch', 'cm_ajax_scan_batch' );
/**
 * Stap 2: Scant een batch van URLs en geeft gevonden cookies terug.
 */
function cm_ajax_scan_batch() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    @set_time_limit( 120 );

    $urls = isset($_POST['urls']) ? (array) $_POST['urls'] : array();
    if ( empty($urls) ) wp_send_json_success( array( 'cookies' => array(), 'scanned' => 0 ) );

    // Sanitize URLs
    $urls = array_map( 'esc_url_raw', $urls );

    // Hergebruik de kennisbank en lookup-logica uit de hoofdscanner
    $db_count = (int) get_option('cm_cookie_db_count', 0);
    $use_db   = $db_count > 0;
    $fallback_cookies = cm_fallback_cookies();

    $lookup_fn = function( $cookie_name ) use ( $use_db, $fallback_cookies ) {
        $fallback_cat = null;
        if ( isset( $fallback_cookies[ $cookie_name ] ) ) {
            $fallback_cat = $fallback_cookies[ $cookie_name ][0];
        } else {
            foreach ( $fallback_cookies as $pat => $f ) {
                if ( substr($pat,-1) === '_' && strpos($cookie_name, $pat) === 0 ) {
                    $fallback_cat = $f[0];
                    break;
                }
            }
        }
        if ( $use_db ) {
            $row = cm_lookup_cookie( $cookie_name );
            if ( $row ) {
                return array(
                    'category'    => $fallback_cat ?: $row['category'],
                    'provider'    => $row['platform'] ?: $row['controller'],
                    'duration'    => $row['retention'],
                    'description' => $row['description'],
                    'privacy_url' => $row['privacy_url'],
                );
            }
        }
        if ( isset( $fallback_cookies[ $cookie_name ] ) ) {
            $f = $fallback_cookies[ $cookie_name ];
            return array( 'category' => $f[0], 'provider' => $f[1], 'duration' => $f[2], 'description' => $f[3], 'privacy_url' => '' );
        }
        foreach ( $fallback_cookies as $pat => $f ) {
            if ( substr($pat,-1) === '_' && strpos($cookie_name, $pat) === 0 ) {
                return array( 'category' => $f[0], 'provider' => $f[1], 'duration' => $f[2], 'description' => $f[3], 'privacy_url' => '' );
            }
        }
        return null;
    };

    $script_signatures = cm_script_signatures();
    $http_cookies   = array();
    $script_cookies = array();
    $pages_scanned  = 0;

    foreach ( $urls as $url ) {
        $response = wp_remote_get( $url, array(
            'timeout'    => 12,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (compatible; CookieScan/2.0)',
            'sslverify'  => true,
            'redirection'=> 5,
        ));
        if ( is_wp_error( $response ) && strpos( $response->get_error_message(), 'cURL error 60' ) !== false ) {
            $response = wp_remote_get( $url, array(
                'timeout' => 12, 'user-agent' => 'CookieScan/2.0', 'sslverify' => false, 'redirection' => 5,
            ));
        }
        if ( is_wp_error($response) ) continue;
        $pages_scanned++;

        $headers = wp_remote_retrieve_headers( $response );
        $body    = wp_remote_retrieve_body( $response );

        // Verwijder Cookiebaas eigen scripts, herstel geblokkeerde third-party scripts
        $body = preg_replace_callback(
            '/<script(?:\s[^>]*)?>[\s\S]*?<\/script>/i',
            function( $m ) {
                $block = $m[0];
                if ( stripos($block, 'cm_consent_update') !== false ) return '';
                if ( stripos($block, 'COOKIEMELDING PLUGIN') !== false ) return '';
                if ( preg_match('/id=["\']cm-blocker["\']/i', $block) ) return '';
                if ( preg_match('/gtag\s*\(\s*[\'"]consent[\'"]\s*,\s*[\'"]default[\'"]/i', $block)
                     && stripos($block, 'analytics_storage') !== false ) return '';
                if ( preg_match('/type=["\']text\/plain["\']/', $block)
                     && preg_match('/data-cm-type=["\']/', $block) ) {
                    $block = preg_replace('/\s*type=["\']text\/plain["\']/', '', $block);
                    $block = preg_replace('/\s*data-cm-type=["\'][^"\']*["\']/', '', $block);
                    return $block;
                }
                return $block;
            },
            $body
        );

        // Set-Cookie headers
        $raw_set = array();
        if ( isset($headers['set-cookie']) ) {
            $sc = $headers['set-cookie'];
            $raw_set = is_array($sc) ? $sc : array($sc);
        }
        foreach ( $raw_set as $cookie_str ) {
            $parts       = explode(';', $cookie_str);
            $name_val    = explode('=', trim($parts[0]), 2);
            $cookie_name = trim($name_val[0]);
            if ( ! $cookie_name || isset($http_cookies[$cookie_name]) ) continue;
            $duration = 'Sessie';
            foreach ( $parts as $part ) {
                $p = strtolower(trim($part));
                if ( strpos($p, 'max-age=') === 0 ) { $duration = cm_secs_to_human( intval(substr($p, 8)) ); break; }
                if ( strpos($p, 'expires=') === 0 ) { $ts = strtotime(trim(substr($part, 8))); if ($ts && $ts > time()) $duration = cm_secs_to_human($ts - time()); break; }
            }
            $info = $lookup_fn( $cookie_name );
            $svc = cm_service_for_cookie( $cookie_name );
            $http_cookies[$cookie_name] = array(
                'name'        => $cookie_name,
                'type'        => $info ? $info['category']    : 'unknown',
                'provider'    => $svc ? $svc['service'] : ( $info ? $info['provider'] : 'Onbekend' ),
                'duration'    => ($info && $duration === 'Sessie') ? $info['duration'] : $duration,
                'description' => $info ? $info['description'] : '',
                'privacy_url' => $info ? $info['privacy_url'] : '',
                'how'         => 'server',
            );
        }

        // Script-gebaseerde detectie
        $scripts_only = '';
        if ( preg_match_all( '/<script[^>]*>([\s\S]*?)<\/script>/i', $body, $sm ) ) {
            $scripts_only = implode( "\n", $sm[0] );
        }
        foreach ( $script_signatures as $pattern => $entries ) {
            if ( stripos($scripts_only, $pattern) === false ) continue;
            foreach ( $entries as $entry ) {
                $cname = $entry[0];
                $already = false;
                foreach ( array_keys($http_cookies) as $existing ) {
                    if ( $existing === $cname ) { $already = true; break; }
                    if ( substr($cname,-1) === '_' && strpos($existing, rtrim($cname,'_')) === 0 ) { $already = true; break; }
                }
                if ( $already || isset($script_cookies[$cname]) ) continue;
                $db_info = $lookup_fn( $cname );
                $script_cookies[$cname] = array(
                    'name'        => $cname,
                    'type'        => $db_info ? $db_info['category']    : $entry[1],
                    'provider'    => $db_info ? $db_info['provider']    : $entry[2],
                    'duration'    => $db_info ? $db_info['duration']    : $entry[3],
                    'description' => $db_info ? $db_info['description'] : $entry[2],
                    'privacy_url' => $db_info ? $db_info['privacy_url'] : '',
                    'how'         => 'script',
                );
            }
        }

        // Herken geblokkeerde embeds (placeholders)
        if ( preg_match_all( '/data-cm-embed-src=["\']([^"\']+)["\']/i', $body, $ph_matches ) ) {
            $embed_domains = cm_get_embed_domains();
            foreach ( $ph_matches[1] as $embed_src ) {
                $host = @parse_url( $embed_src, PHP_URL_HOST );
                if ( ! $host ) continue;
                $host = strtolower( preg_replace( '/^www\./i', '', $host ) );
                foreach ( $embed_domains as $domain => $info ) {
                    if ( $host === $domain || substr($host, -strlen('.'.$domain)) === '.'.$domain ) {
                        $svc_name = $info['service'];
                        foreach ( $fallback_cookies as $fc_name => $fc_data ) {
                            if ( $fc_data[1] !== $svc_name ) continue;
                            if ( isset($http_cookies[$fc_name]) || isset($script_cookies[$fc_name]) ) continue;
                            $db_info = $lookup_fn( $fc_name );
                            $script_cookies[$fc_name] = array(
                                'name'        => $fc_name,
                                'type'        => $db_info ? $db_info['category']    : $fc_data[0],
                                'provider'    => $svc_name,
                                'duration'    => $db_info ? $db_info['duration']     : $fc_data[2],
                                'description' => $db_info ? $db_info['description']  : $fc_data[3],
                                'privacy_url' => $db_info ? $db_info['privacy_url']  : '',
                                'how'         => 'embed',
                            );
                        }
                        break;
                    }
                }
            }
        }
    }

    // Merge
    $all = array_values($http_cookies);
    foreach ( $script_cookies as $entry ) {
        $already = false;
        foreach ( $all as $e ) {
            if ( $e['name'] === $entry['name'] ) { $already = true; break; }
            if ( substr($entry['name'],-1) === '_' && strpos($e['name'], $entry['name']) === 0 ) { $already = true; break; }
        }
        if ( ! $already ) $all[] = $entry;
    }

    wp_send_json_success( array(
        'cookies'       => $all,
        'scanned'       => $pages_scanned,
        'http_count'    => count($http_cookies),
        'script_count'  => count($script_cookies),
    ));
}

/**
 * Geeft de fallback cookie kennisbank terug (herbruikbaar).
 */
function cm_fallback_cookies() {
    static $cache = null;
    if ( $cache !== null ) return $cache;
    $cache = array(
        'cc_cm_consent'         => array('functional','Cookiemelding','12 maanden','Slaat uw cookievoorkeuren op.'),
        'PHPSESSID'             => array('functional','Deze website','Sessie','PHP sessiecookie.'),
        'wordpress_logged_in_'  => array('functional','WordPress','Sessie','WordPress login sessie.'),
        'wp-settings-'          => array('functional','WordPress','1 jaar','WordPress admin-instellingen.'),
        'woocommerce_cart_hash' => array('functional','WooCommerce','Sessie','Winkelwagen hash.'),
        'woocommerce_items_in_cart' => array('functional','WooCommerce','Sessie','Items in winkelwagen.'),
        'wp_woocommerce_session_' => array('functional','WooCommerce','2 dagen','WooCommerce sessie.'),
        '__cf_bm'               => array('functional','Cloudflare','30 min','Cloudflare bot-beheer cookie.'),
        '_cfuvid'               => array('functional','Cloudflare','Sessie','Cloudflare unieke bezoekersidentificatie.'),
        'XSRF-TOKEN'            => array('functional','Deze website','Sessie','Beveiligingstoken tegen cross-site request forgery.'),
        'laravel_session'       => array('functional','Laravel','Sessie','Laravel sessiecookie.'),
        'YSC'                        => array('marketing','YouTube','Sessie','Registreert een unieke ID om statistieken bij te houden over welke YouTube-video\'s zijn bekeken.'),
        'VISITOR_INFO1_LIVE'         => array('marketing','YouTube','6 maanden','Schat de bandbreedte in om de videokwaliteit op YouTube aan te passen.'),
        'yt-remote-device-id'        => array('marketing','YouTube','Onbepaald','YouTube apparaat-ID voor het afspelen van video\'s.'),
        'yt-remote-connected-devices'=> array('marketing','YouTube','Onbepaald','YouTube verbonden apparaten.'),
        '_ga'                   => array('analytics','Google Analytics','2 jaar','Unieke bezoeker-ID voor Google Analytics.'),
        '_ga_'                  => array('analytics','Google Analytics','2 jaar','Google Analytics 4 sessie-data.'),
        '_gid'                  => array('analytics','Google Analytics','24 uur','Korte-termijn bezoeker-ID voor Google Analytics.'),
        '_gat'                  => array('analytics','Google Analytics','1 min','Throttling van Google Analytics requests.'),
        '_gcl_au'               => array('marketing','Google Ads','3 maanden','Google Ads conversie-tracking.'),
        '_gcl_aw'               => array('marketing','Google Ads','3 maanden','Google Ads klik-conversie.'),
        'IDE'                   => array('marketing','Google DoubleClick','13 maanden','Google DoubleClick advertentie-tracking.'),
        'test_cookie'           => array('marketing','Google DoubleClick','Sessie','Test of de browser cookies ondersteunt.'),
        '_fbp'                  => array('marketing','Meta / Facebook','3 maanden','Facebook Pixel bezoeker-ID.'),
        '_fbc'                  => array('marketing','Meta / Facebook','2 jaar','Facebook klik-ID.'),
        'fr'                    => array('marketing','Meta / Facebook','3 maanden','Facebook advertentie-targeting.'),
        '_pin_unauth'           => array('marketing','Pinterest','1 jaar','Pinterest tracking cookie.'),
        'li_sugr'               => array('marketing','LinkedIn','3 maanden','LinkedIn Insight Tag.'),
        'bcookie'               => array('marketing','LinkedIn','1 jaar','LinkedIn browser-ID.'),
        'lidc'                  => array('marketing','LinkedIn','24 uur','LinkedIn datacenter routering.'),
        'UserMatchHistory'      => array('marketing','LinkedIn','30 dagen','LinkedIn Ads ID-synchronisatie.'),
        '_tt_enable_cookie'     => array('marketing','TikTok','13 maanden','TikTok tracking-pixel.'),
        '_ttp'                  => array('marketing','TikTok','13 maanden','TikTok Pixel bezoeker-ID.'),
    );
    return $cache;
}

/**
 * Geeft de script-signature patronen terug (herbruikbaar).
 */
function cm_script_signatures() {
    static $cache = null;
    if ( $cache !== null ) return $cache;
    $cache = array(
        'googletagmanager.com/gtm'          => array(
            array('_ga',      'analytics', 'Google Analytics (via GTM)',  '2 jaar'),
            array('_ga_',     'analytics', 'Google Analytics 4 (GTM)',    '2 jaar'),
            array('_gid',     'analytics', 'Google Analytics',            '24 uur'),
            array('_gcl_au',  'marketing', 'Google Ads conversie',        '3 maanden'),
        ),
        'GTM-'                              => array(
            array('_ga',      'analytics', 'Google Analytics (via GTM)',  '2 jaar'),
            array('_ga_',     'analytics', 'Google Analytics 4',          '2 jaar'),
            array('_gcl_au',  'marketing', 'Google Ads conversie',        '3 maanden'),
        ),
        'googletagmanager.com/gtag'         => array(
            array('_ga',      'analytics', 'Google Analytics 4',          '2 jaar'),
            array('_ga_',     'analytics', 'Google Analytics 4',          '2 jaar'),
            array('_gid',     'analytics', 'Google Analytics',            '24 uur'),
            array('_gcl_au',  'marketing', 'Google Ads conversie',        '3 maanden'),
        ),
        'google-analytics.com/analytics'   => array(
            array('_ga',      'analytics', 'Google Analytics',            '2 jaar'),
            array('_gid',     'analytics', 'Google Analytics',            '24 uur'),
        ),
        'google-analytics.com/ga.js'       => array(
            array('__utma',   'analytics', 'Google Analytics (UA)',        '2 jaar'),
            array('__utmb',   'analytics', 'Google Analytics (UA)',        '30 min'),
            array('__utmz',   'analytics', 'Google Analytics (UA)',        '6 maanden'),
        ),
        "'GA_MEASUREMENT_ID'"              => array(
            array('_ga',      'analytics', 'Google Analytics 4',          '2 jaar'),
            array('_ga_',     'analytics', 'Google Analytics 4',          '2 jaar'),
        ),
        'googleadservices.com'             => array(
            array('_gcl_aw',  'marketing', 'Google Ads conversie',        '3 maanden'),
            array('IDE',      'marketing', 'Google DoubleClick',          '13 maanden'),
        ),
        'doubleclick.net'                  => array(
            array('IDE',      'marketing', 'Google DoubleClick',          '13 maanden'),
            array('test_cookie','marketing','Google DoubleClick test',    'Sessie'),
        ),
        'google.com/pagead'                => array(
            array('IDE',      'marketing', 'Google Ads',                  '13 maanden'),
            array('_gcl_aw',  'marketing', 'Google Ads conversie',        '3 maanden'),
        ),
        'connect.facebook.net'             => array(
            array('_fbp',     'marketing', 'Meta / Facebook Pixel',       '3 maanden'),
            array('_fbc',     'marketing', 'Meta / Facebook klik-ID',     '2 jaar'),
            array('fr',       'marketing', 'Meta / Facebook advertenties','3 maanden'),
        ),
        'facebook.com/tr'                  => array(
            array('_fbp',     'marketing', 'Meta / Facebook Pixel',       '3 maanden'),
        ),
        'fbq('                             => array(
            array('_fbp',     'marketing', 'Meta / Facebook Pixel',       '3 maanden'),
            array('_fbc',     'marketing', 'Meta / Facebook klik-ID',     '2 jaar'),
        ),
        'snap.licdn.com'                   => array(
            array('li_sugr',  'marketing', 'LinkedIn Insight Tag',        '3 maanden'),
            array('bcookie',  'marketing', 'LinkedIn browser-ID',         '1 jaar'),
            array('UserMatchHistory','marketing','LinkedIn Ads ID-sync',  '30 dagen'),
        ),
        'linkedin.com/px'                  => array(
            array('li_sugr',  'marketing', 'LinkedIn Pixel',              '3 maanden'),
        ),
        'analytics.tiktok.com'             => array(
            array('_tt_enable_cookie','marketing','TikTok tracking-pixel','13 maanden'),
            array('_ttp',     'marketing', 'TikTok Pixel bezoeker-ID',    '13 maanden'),
        ),
        'assets.pinterest.com'             => array(
            array('_pin_unauth','marketing','Pinterest tracking',         '1 jaar'),
        ),
        'pintrk('                          => array(
            array('_pin_unauth','marketing','Pinterest Tag',              '1 jaar'),
        ),
        'youtube.com/embed'                => array(
            array('VISITOR_INFO1_LIVE', 'marketing', 'YouTube bandbreedte-schatting', '6 maanden'),
            array('YSC',               'marketing', 'YouTube sessie',              'Sessie'),
        ),
    );
    return $cache;
}

add_action( 'wp_ajax_cm_run_scan', 'cm_ajax_run_scan' );
function cm_ajax_run_scan() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    // Verlengt de PHP-timelimit voor de crawl (alle pagina's)
    @set_time_limit( 300 );
    $scan_start = microtime( true );

    /* ----------------------------------------------------------------
       KENNISBANK: cookie opzoeken via Open Cookie Database tabel.
       Fallback op WordPress/WooCommerce eigen cookies indien DB leeg.
    ---------------------------------------------------------------- */
    $db_count = (int) get_option('cm_cookie_db_count', 0);
    $use_db   = $db_count > 0;

    // Fallback kennisbank voor eigen/WordPress cookies (altijd aanwezig)
    $fallback_cookies = array(
        'PHPSESSID'             => array('functional', 'Deze website',           'Sessie',    'Sessie op de server om de bezoeker te herkennen.'),
        'wordpress_logged_in'   => array('functional', 'WordPress',              'Sessie',    'WordPress inlogstatus van de gebruiker.'),
        'wordpress_sec'         => array('functional', 'WordPress',              'Sessie',    'WordPress beveiligingscookie.'),
        'wordpress_test_cookie' => array('functional', 'WordPress',              'Sessie',    'Test of cookies werken in de browser.'),
        'wp-settings-'          => array('functional', 'WordPress',              '1 jaar',    'Slaat WordPress-gebruikersinstellingen op.'),
        'wp_lang'               => array('functional', 'WordPress',              'Sessie',    'Voorkeurstaal van de WordPress-gebruiker.'),
        'woocommerce_cart_hash' => array('functional', 'WooCommerce',            'Sessie',    'Bijhouden van de winkelwagen (hash).'),
        'woocommerce_items_in_cart' => array('functional', 'WooCommerce',        'Sessie',    'Bijhouden of er items in de winkelwagen liggen.'),
        'woocommerce_session_'  => array('functional', 'WooCommerce',            '2 dagen',   'Sessie-identifier voor WooCommerce winkelwagen.'),
        'wc_cart_created'       => array('functional', 'WooCommerce',            'Sessie',    'Tijdstip waarop de winkelwagen is aangemaakt.'),
        'cc_cm_consent'         => array('functional', 'Cookiemelding Plugin',   intval(cm_get('expiry_months')).' mnd', 'Sla de cookievoorkeur van de bezoeker op.'),
        'cf_clearance'          => array('functional', 'Cloudflare',             '1 jaar',    'Cloudflare verificatiecookie na beveiligingscontrole.'),
        '__cf_bm'               => array('functional', 'Cloudflare',             '30 min',    'Cloudflare bot-beheer cookie.'),
        '_cfuvid'               => array('functional', 'Cloudflare',             'Sessie',    'Cloudflare unieke bezoekersidentificatie.'),
        'XSRF-TOKEN'            => array('functional', 'Deze website',           'Sessie',    'Beveiligingstoken tegen cross-site request forgery.'),
        'laravel_session'       => array('functional', 'Laravel',                'Sessie',    'Laravel sessiecookie.'),
        // YouTube — altijd marketing, ook als Open Cookie DB anders categoriseert
        'YSC'                        => array('marketing', 'YouTube',            'Sessie',    'Registreert een unieke ID om statistieken bij te houden over welke YouTube-video\'s zijn bekeken.'),
        'VISITOR_INFO1_LIVE'         => array('marketing', 'YouTube',            '6 maanden', 'Schat de bandbreedte in om de videokwaliteit op YouTube aan te passen.'),
        'yt-remote-device-id'        => array('marketing', 'YouTube',            'Permanent', 'Slaat videovoorkeuren op voor ingesloten YouTube-video\'s.'),
        'yt-remote-connected-devices'=> array('marketing', 'YouTube',            'Permanent', 'Slaat videovoorkeuren op voor ingesloten YouTube-video\'s.'),
    );

    // Script-signatures voor bekende diensten (als fallback én aanvulling)
    // Elk patroon matcht op innerHTML van de pagina (scripts, data-attrs, GTM config, etc.)
    $script_signatures = array(

        // ── Google Tag Manager (laadt zelf GA/Ads/etc.) ───────────────
        'googletagmanager.com/gtm'          => array(
            array('_ga',      'analytics', 'Google Analytics (via GTM)',  '2 jaar'),
            array('_ga_',     'analytics', 'Google Analytics 4 (GTM)',    '2 jaar'),
            array('_gid',     'analytics', 'Google Analytics',            '24 uur'),
            array('_gcl_au',  'marketing', 'Google Ads conversie',        '3 maanden'),
        ),
        'GTM-'                              => array(
            array('_ga',      'analytics', 'Google Analytics (via GTM)',  '2 jaar'),
            array('_ga_',     'analytics', 'Google Analytics 4',          '2 jaar'),
            array('_gcl_au',  'marketing', 'Google Ads conversie',        '3 maanden'),
        ),

        // ── Google Analytics direct ───────────────────────────────────
        'googletagmanager.com/gtag'         => array(
            array('_ga',      'analytics', 'Google Analytics 4',          '2 jaar'),
            array('_ga_',     'analytics', 'Google Analytics 4',          '2 jaar'),
            array('_gid',     'analytics', 'Google Analytics',            '24 uur'),
            array('_gcl_au',  'marketing', 'Google Ads conversie',        '3 maanden'),
        ),
        'google-analytics.com/analytics'   => array(
            array('_ga',      'analytics', 'Google Analytics',            '2 jaar'),
            array('_gid',     'analytics', 'Google Analytics',            '24 uur'),
        ),
        'google-analytics.com/ga.js'       => array(
            array('__utma',   'analytics', 'Google Analytics (UA)',        '2 jaar'),
            array('__utmb',   'analytics', 'Google Analytics (UA)',        '30 min'),
            array('__utmz',   'analytics', 'Google Analytics (UA)',        '6 maanden'),
        ),
        "'GA_MEASUREMENT_ID'"              => array(
            array('_ga',      'analytics', 'Google Analytics 4',          '2 jaar'),
            array('_ga_',     'analytics', 'Google Analytics 4',          '2 jaar'),
        ),

        // ── Google Ads / DoubleClick ──────────────────────────────────
        'googleadservices.com'             => array(
            array('_gcl_aw',  'marketing', 'Google Ads conversie',        '3 maanden'),
            array('IDE',      'marketing', 'Google DoubleClick',          '13 maanden'),
        ),
        'doubleclick.net'                  => array(
            array('IDE',      'marketing', 'Google DoubleClick',          '13 maanden'),
            array('test_cookie','marketing','Google DoubleClick test',    'Sessie'),
        ),
        'google.com/pagead'                => array(
            array('IDE',      'marketing', 'Google Ads',                  '13 maanden'),
            array('_gcl_aw',  'marketing', 'Google Ads conversie',        '3 maanden'),
        ),

        // ── Meta / Facebook ───────────────────────────────────────────
        'connect.facebook.net'             => array(
            array('_fbp',     'marketing', 'Meta / Facebook Pixel',       '3 maanden'),
            array('_fbc',     'marketing', 'Meta / Facebook klik-ID',     '2 jaar'),
            array('fr',       'marketing', 'Meta / Facebook advertenties','3 maanden'),
        ),
        'facebook.com/tr'                  => array(
            array('_fbp',     'marketing', 'Meta / Facebook Pixel',       '3 maanden'),
        ),
        'fbq('                             => array(
            array('_fbp',     'marketing', 'Meta / Facebook Pixel',       '3 maanden'),
            array('_fbc',     'marketing', 'Meta / Facebook klik-ID',     '2 jaar'),
        ),

        // ── LinkedIn ──────────────────────────────────────────────────
        'snap.licdn.com'                   => array(
            array('bcookie',  'marketing', 'LinkedIn',                    '2 jaar'),
            array('lidc',     'marketing', 'LinkedIn datacenter',         '1 dag'),
            array('li_gc',    'marketing', 'LinkedIn toestemming',        '2 jaar'),
        ),
        'linkedin.com/li.lms'              => array(
            array('bcookie',  'marketing', 'LinkedIn',                    '2 jaar'),
            array('UserMatchHistory', 'marketing', 'LinkedIn remarketing','1 maand'),
        ),
        '_linkedin_partner_id'             => array(
            array('bcookie',  'marketing', 'LinkedIn Insight Tag',        '2 jaar'),
            array('UserMatchHistory', 'marketing', 'LinkedIn remarketing','1 maand'),
        ),

        // ── Hotjar ────────────────────────────────────────────────────
        'static.hotjar.com'                => array(
            array('_hjid',            'analytics', 'Hotjar gebruikers-ID',      '1 jaar'),
            array('_hjFirstSeen',     'analytics', 'Hotjar eerste bezoek',      'Sessie'),
            array('_hjAbsoluteSessionInProgress', 'analytics', 'Hotjar sessie','30 min'),
        ),
        'hotjar.com/api'                   => array(
            array('_hjid',    'analytics', 'Hotjar',                      '1 jaar'),
        ),
        'hjid:'                            => array(
            array('_hjid',    'analytics', 'Hotjar gebruikers-ID',        '1 jaar'),
        ),

        // ── Microsoft Clarity ─────────────────────────────────────────
        'clarity.ms'                       => array(
            array('_clck',    'analytics', 'Microsoft Clarity gebruiker', '1 jaar'),
            array('_clsk',    'analytics', 'Microsoft Clarity sessie',    '1 dag'),
            array('CLID',     'analytics', 'Microsoft Clarity ID',        '1 jaar'),
            array('MUID',     'marketing', 'Microsoft gebruikers-ID',     '13 maanden'),
        ),
        'clarityId:'                       => array(
            array('_clck',    'analytics', 'Microsoft Clarity',           '1 jaar'),
        ),

        // ── TikTok ────────────────────────────────────────────────────
        'analytics.tiktok.com'             => array(
            array('_ttp',     'marketing', 'TikTok tracking',             '13 maanden'),
            array('tt_webid', 'marketing', 'TikTok gebruikers-ID',        '1 jaar'),
        ),
        'tiktok.com/i18n/pixel'            => array(
            array('_ttp',     'marketing', 'TikTok Pixel',                '13 maanden'),
        ),
        'ttq.load('                        => array(
            array('_ttp',     'marketing', 'TikTok Pixel',                '13 maanden'),
            array('tt_webid', 'marketing', 'TikTok gebruikers-ID',        '1 jaar'),
        ),

        // ── Snapchat ──────────────────────────────────────────────────
        'sc-static.net/s/snap'             => array(
            array('sc_at',    'marketing', 'Snapchat Pixel',              '13 maanden'),
            array('_scid',    'marketing', 'Snapchat bezoeker-ID',        '13 maanden'),
        ),

        // ── Pinterest ─────────────────────────────────────────────────
        'ct.pinterest.com'                 => array(
            array('_pin_unauth', 'marketing', 'Pinterest anonieme bezoeker', '1 jaar'),
            array('_derived_epik', 'marketing', 'Pinterest conversie',    '1 jaar'),
        ),
        'pintrk('                          => array(
            array('_pin_unauth', 'marketing', 'Pinterest Pixel',          '1 jaar'),
        ),

        // ── Twitter / X ───────────────────────────────────────────────
        'static.ads-twitter.com'           => array(
            array('personalization_id', 'marketing', 'Twitter/X advertenties', '2 jaar'),
            array('guest_id',           'marketing', 'Twitter/X bezoeker-ID',  '2 jaar'),
        ),
        'twq('                             => array(
            array('personalization_id', 'marketing', 'Twitter/X Pixel', '2 jaar'),
        ),

        // ── HubSpot ───────────────────────────────────────────────────
        'js.hs-scripts.com'                => array(
            array('__hstc',   'marketing', 'HubSpot tracking',            '13 maanden'),
            array('hubspotutk','marketing','HubSpot bezoeker-ID',         '13 maanden'),
            array('__hssc',   'analytics', 'HubSpot sessie',              '30 min'),
        ),
        'hs-banner.com'                    => array(
            array('__hstc',   'marketing', 'HubSpot',                     '13 maanden'),
        ),

        // ── Intercom ──────────────────────────────────────────────────
        'widget.intercom.io'               => array(
            array('intercom-id-',     'functional', 'Intercom chat bezoeker', '9 maanden'),
            array('intercom-session-','functional', 'Intercom chat sessie',   '1 week'),
        ),

        // ── Vimeo ─────────────────────────────────────────────────────
        'player.vimeo.com'                 => array(
            array('vuid',     'analytics', 'Vimeo gebruikers-ID',         '2 jaar'),
        ),

        // ── YouTube ───────────────────────────────────────────────────
        'youtube.com/embed'                => array(
            array('VISITOR_INFO1_LIVE', 'marketing', 'YouTube bandbreedte-schatting', '6 maanden'),
            array('YSC',               'marketing', 'YouTube sessie',              'Sessie'),
        ),
        'youtube-nocookie.com'             => array(
            array('VISITOR_INFO1_LIVE', 'analytics', 'YouTube (privacy-modus)',  '6 maanden'),
        ),

        // ── Stripe ────────────────────────────────────────────────────
        'js.stripe.com'                    => array(
            array('__stripe_mid', 'functional', 'Stripe betaalsessie',    '1 jaar'),
            array('__stripe_sid', 'functional', 'Stripe betaalsessie',    '30 min'),
        ),

        // ── Cloudflare Turnstile / Rocket ─────────────────────────────
        'challenges.cloudflare.com'        => array(
            array('cf_clearance', 'functional', 'Cloudflare verificatie', '1 jaar'),
        ),

        // ── Cookiebot ─────────────────────────────────────────────────
        'cookiebot.com'                    => array(
            array('CookieConsent', 'functional', 'Cookiebot toestemming', '1 jaar'),
        ),
    );

    /**
     * Zoek cookie op: eerst Open Cookie DB, dan fallback kennisbank.
     * Geeft array terug: [category, provider, duration, description, privacy_url]
     */
    $lookup_fn = function( $cookie_name ) use ( $use_db, $fallback_cookies ) {
        // Bepaal of de fallback een categorie-override heeft voor deze cookie
        $fallback_cat = null;
        if ( isset( $fallback_cookies[ $cookie_name ] ) ) {
            $fallback_cat = $fallback_cookies[ $cookie_name ][0];
        } else {
            foreach ( $fallback_cookies as $pat => $f ) {
                if ( substr($pat,-1) === '_' && strpos($cookie_name, $pat) === 0 ) {
                    $fallback_cat = $f[0];
                    break;
                }
            }
        }

        // 1. Open Cookie Database
        if ( $use_db ) {
            $row = cm_lookup_cookie( $cookie_name );
            if ( $row ) {
                return array(
                    // Fallback-categorie wint van de database (bijv. YSC = marketing, niet functional)
                    'category'    => $fallback_cat ?: $row['category'],
                    'provider'    => $row['platform'] ?: $row['controller'],
                    'duration'    => $row['retention'],
                    'description' => $row['description'],
                    'privacy_url' => $row['privacy_url'],
                );
            }
        }
        // 2. Fallback: exacte match
        if ( isset( $fallback_cookies[ $cookie_name ] ) ) {
            $f = $fallback_cookies[ $cookie_name ];
            return array( 'category' => $f[0], 'provider' => $f[1], 'duration' => $f[2], 'description' => $f[3], 'privacy_url' => '' );
        }
        // 3. Fallback: prefix match (eindigend op _)
        foreach ( $fallback_cookies as $pat => $f ) {
            if ( substr($pat,-1) === '_' && strpos($cookie_name, $pat) === 0 ) {
                return array( 'category' => $f[0], 'provider' => $f[1], 'duration' => $f[2], 'description' => $f[3], 'privacy_url' => '' );
            }
        }
        return null;
    };

    /* ----------------------------------------------------------------
       STAP 1: Bouw lijst van te crawlen URLs via WordPress API
       Scant ALLE gepubliceerde content: pagina's, posts, custom post types.
    ---------------------------------------------------------------- */
    $home = trailingslashit( home_url('/') );
    $urls_to_scan = array( $home );
    $visited      = array( $home => true );

    // Alle publieke post types ophalen (page, post, portfolio, product, etc.)
    $all_pts = get_post_types( array( 'public' => true ), 'names' );
    // attachment overslaan — die hebben geen eigen pagina-template met scripts
    unset( $all_pts['attachment'] );

    $all_content = get_posts( array(
        'post_type'      => array_values( $all_pts ),
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));
    foreach ( $all_content as $id ) {
        $url = trailingslashit( get_permalink($id) );
        if ( $url && ! isset($visited[$url]) ) {
            $visited[$url]  = true;
            $urls_to_scan[] = $url;
        }
    }

    /* ----------------------------------------------------------------
       STAP 2: Crawl elke pagina
    ---------------------------------------------------------------- */
    $http_cookies  = array(); // naam => entry  (echte Set-Cookie headers)
    $script_cookies= array(); // naam => entry  (afgeleid uit scripts)
    $pages_scanned = 0;

    foreach ( $urls_to_scan as $url ) {
        $response = wp_remote_get( $url, array(
            'timeout'             => 12,
            'user-agent'          => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (compatible; CookieScan/2.0)',
            'sslverify'           => true,
            'redirection'         => 5,
        ));

        // Fallback: als SSL-verificatie faalt (lokale dev), probeer zonder
        if ( is_wp_error( $response ) && strpos( $response->get_error_message(), 'cURL error 60' ) !== false ) {
            $response = wp_remote_get( $url, array(
                'timeout'    => 12,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (compatible; CookieScan/2.0)',
                'sslverify'  => false,
                'redirection'=> 5,
            ));
        }

        if ( is_wp_error($response) ) continue;
        $pages_scanned++;

        $headers = wp_remote_retrieve_headers( $response );
        $body    = wp_remote_retrieve_body( $response );

        // --- Verwijder Cookiebaas plugin-eigen scripts uit de body vóór de scan ---
        // Belangrijk: we matchen per individueel script-blok om te voorkomen dat
        // regex over meerdere scripts heen matcht en te veel HTML verwijdert.
        $body = preg_replace_callback(
            '/<script(?:\s[^>]*)?>[\s\S]*?<\/script>/i',
            function( $m ) {
                $block = $m[0];
                // Cookiebaas eigen scripts herkennen en verwijderen
                if ( stripos($block, 'cm_consent_update') !== false ) return '';
                if ( stripos($block, 'COOKIEMELDING PLUGIN') !== false ) return '';
                if ( preg_match('/id=["\']cm-blocker["\']/i', $block) ) return '';
                // Consent Mode default script (door Cookiebaas geïnjecteerd)
                if ( preg_match('/gtag\s*\(\s*[\'"]consent[\'"]\s*,\s*[\'"]default[\'"]/i', $block)
                     && stripos($block, 'analytics_storage') !== false ) return '';
                // Door Cookiebaas geblokkeerde scripts van derden (type=text/plain data-cm-type):
                // NIET verwijderen maar herstellen naar normaal script zodat de scanner ze detecteert
                if ( preg_match('/type=["\']text\/plain["\']/', $block)
                     && preg_match('/data-cm-type=["\']/', $block) ) {
                    $block = preg_replace('/\s*type=["\']text\/plain["\']/', '', $block);
                    $block = preg_replace('/\s*data-cm-type=["\'][^"\']*["\']/', '', $block);
                    return $block;
                }
                return $block;
            },
            $body
        );

        // --- Herken geblokkeerde embeds (Cookiebaas placeholders) ---
        // De embed blocker vervangt iframes door placeholders. Haal de originele src terug uit data-cm-embed-src.
        if ( preg_match_all( '/data-cm-embed-src=["\']([^"\']+)["\']/i', $body, $ph_matches ) ) {
            foreach ( $ph_matches[1] as $embed_src ) {
                $host = @parse_url( $embed_src, PHP_URL_HOST );
                if ( ! $host ) continue;
                $host = strtolower( preg_replace( '/^www\./i', '', $host ) );
                $embed_domains_pre = cm_get_embed_domains();
                foreach ( $embed_domains_pre as $domain => $info ) {
                    if ( $host === $domain || substr($host, -strlen('.'.$domain)) === '.'.$domain ) {
                        $svc_name = $info['service'];
                        foreach ( $fallback_cookies as $fc_name => $fc_data ) {
                            if ( $fc_data[1] !== $svc_name ) continue;
                            if ( isset($http_cookies[$fc_name]) || isset($script_cookies[$fc_name]) ) continue;
                            $db_info = $lookup_fn( $fc_name );
                            $script_cookies[$fc_name] = array(
                                'name'        => $fc_name,
                                'type'        => $db_info ? $db_info['category']    : $fc_data[0],
                                'provider'    => $svc_name,
                                'duration'    => $db_info ? $db_info['duration']     : $fc_data[2],
                                'description' => $db_info ? $db_info['description']  : $fc_data[3],
                                'privacy_url' => $db_info ? $db_info['privacy_url']  : '',
                                'how'         => 'embed',
                            );
                        }
                        break;
                    }
                }
            }
        }

        // --- Set-Cookie headers ---
        $raw_set = array();
        if ( isset($headers['set-cookie']) ) {
            $sc = $headers['set-cookie'];
            $raw_set = is_array($sc) ? $sc : array($sc);
        }

        foreach ( $raw_set as $cookie_str ) {
            $parts       = explode(';', $cookie_str);
            $name_val    = explode('=', trim($parts[0]), 2);
            $cookie_name = trim($name_val[0]);
            if ( ! $cookie_name || isset($http_cookies[$cookie_name]) ) continue;

            // Looptijd bepalen
            $duration = 'Sessie';
            foreach ( $parts as $part ) {
                $p = strtolower(trim($part));
                if ( strpos($p, 'max-age=') === 0 ) {
                    $duration = cm_secs_to_human( intval(substr($p, 8)) );
                    break;
                }
                if ( strpos($p, 'expires=') === 0 ) {
                    $ts = strtotime( trim(substr($part, 8)) );
                    if ( $ts && $ts > time() ) {
                        $duration = cm_secs_to_human( $ts - time() );
                    }
                    break;
                }
            }

            // Herkennen via Open Cookie Database / fallback kennisbank
            $info = $lookup_fn( $cookie_name );
            $category    = $info ? $info['category']    : 'unknown';
            $provider    = $info ? $info['provider']    : 'Onbekend';
            $description = $info ? $info['description'] : '';
            $privacy_url = $info ? $info['privacy_url'] : '';
            if ( $info && $duration === 'Sessie' ) $duration = $info['duration'];

            // Normaliseer provider via centrale service-mapping
            $svc = cm_service_for_cookie( $cookie_name );
            if ( $svc ) $provider = $svc['service'];

            $http_cookies[$cookie_name] = array(
                'name'        => $cookie_name,
                'type'        => $category,
                'provider'    => $provider,
                'duration'    => $duration,
                'description' => $description,
                'privacy_url' => $privacy_url,
                'how'         => 'server',
            );
        }

        // --- Script-gebaseerde detectie ---
        // Extraheer alleen de inhoud van <script> tags om false positives te vermijden
        $scripts_only = '';
        if ( preg_match_all( '/<script[^>]*>([\s\S]*?)<\/script>/i', $body, $sm ) ) {
            $scripts_only = implode( "\n", $sm[0] );
        }

        foreach ( $script_signatures as $pattern => $entries ) {
            if ( stripos($scripts_only, $pattern) === false ) continue;
            foreach ( $entries as $entry ) {
                $cname = $entry[0];
                // Dedupliceer: check of er al een cookie is met dezelfde naam of waarvan deze naam een prefix is
                $already = false;
                foreach ( array_keys($http_cookies) as $existing ) {
                    if ( $existing === $cname ) { $already = true; break; }
                    if ( substr($cname,-1) === '_' && strpos($existing, rtrim($cname,'_')) === 0 ) { $already = true; break; }
                }
                if ( $already ) continue;
                if ( isset($script_cookies[$cname]) ) continue;
                // Probeer ook hier de database
                $db_info = $lookup_fn( $cname );
                // Forceer bekende cookies naar juiste categorie ongeacht DB
                $forced_categories = array('YSC'=>'marketing','VISITOR_INFO1_LIVE'=>'marketing','yt-remote-device-id'=>'marketing','yt-remote-connected-devices'=>'marketing');
                $forced_type = isset($forced_categories[$cname]) ? $forced_categories[$cname] : ($db_info ? $db_info['category'] : $entry[1]);
                $raw_provider = $db_info ? $db_info['provider'] : $entry[2];
                // Normaliseer provider via centrale service-mapping
                $svc_info = cm_service_for_cookie( $cname );
                $script_cookies[$cname] = array(
                    'name'        => $cname,
                    'type'        => $forced_type,
                    'provider'    => $svc_info ? $svc_info['service'] : $raw_provider,
                    'duration'    => $db_info ? $db_info['duration']    : $entry[3],
                    'description' => $db_info ? $db_info['description'] : '',
                    'privacy_url' => $db_info ? $db_info['privacy_url'] : '',
                    'how'         => 'script',
                );
            }
        }

        // --- Iframe/embed detectie ---
        // Zoek iframes in de HTML en koppel ze aan bekende embed-domeinen
        $embed_domains = cm_get_embed_domains();
        if ( preg_match_all( '/<iframe\s[^>]*src\s*=\s*["\']([^"\']+)["\']/i', $body, $iframe_matches ) ) {
            foreach ( $iframe_matches[1] as $iframe_src ) {
                $host = @parse_url( $iframe_src, PHP_URL_HOST );
                if ( ! $host ) continue;
                $host = strtolower( preg_replace( '/^www\./i', '', $host ) );
                // Match tegen bekende embed-domeinen
                foreach ( $embed_domains as $domain => $info ) {
                    if ( $host === $domain || substr($host, -strlen('.'.$domain)) === '.'.$domain ) {
                        $svc_name = $info['service'];
                        // Voeg bekende cookies toe voor deze dienst
                        foreach ( $fallback_cookies as $fc_name => $fc_data ) {
                            if ( $fc_data[1] !== $svc_name ) continue;
                            if ( isset($http_cookies[$fc_name]) || isset($script_cookies[$fc_name]) ) continue;
                            $db_info = $lookup_fn( $fc_name );
                            $script_cookies[$fc_name] = array(
                                'name'        => $fc_name,
                                'type'        => $db_info ? $db_info['category']    : $fc_data[0],
                                'provider'    => $svc_name,
                                'duration'    => $db_info ? $db_info['duration']     : $fc_data[2],
                                'description' => $db_info ? $db_info['description']  : $fc_data[3],
                                'privacy_url' => $db_info ? $db_info['privacy_url']  : '',
                                'how'         => 'embed',
                            );
                        }
                        break;
                    }
                }
            }
        }
    }

    /* ----------------------------------------------------------------
       STAP 3: Samenvoegen & sorteren
    ---------------------------------------------------------------- */
    $all = array_values($http_cookies) ;
    // Script-cookies toevoegen die niet al via header gevonden zijn
    foreach ( $script_cookies as $entry ) {
        $already = false;
        foreach ( $all as $e ) {
            // Exacte match of prefix (bv. _ga_ matcht _ga)
            if ( $e['name'] === $entry['name'] ) { $already = true; break; }
            if ( substr($entry['name'],-1) === '_' && strpos($e['name'], $entry['name']) === 0 ) { $already = true; break; }
        }
        if ( ! $already ) $all[] = $entry;
    }

    $order = array('functional'=>0,'analytics'=>1,'marketing'=>2,'unknown'=>3);
    usort($all, function($a,$b) use ($order) {
        $oa = isset($order[$a['type']]) ? $order[$a['type']] : 9;
        $ob = isset($order[$b['type']]) ? $order[$b['type']] : 9;
        return $oa !== $ob ? $oa - $ob : strcmp($a['name'], $b['name']);
    });

    $scan_secs = round( microtime( true ) - $scan_start, 1 );
    $scan_duration = $scan_secs < 60 ? $scan_secs . 's' : round($scan_secs / 60, 1) . ' min';

    wp_send_json_success( array(
        'cookies'        => $all,
        'pages_scanned'  => $pages_scanned,
        'pages_total'    => count($urls_to_scan),
        'http_count'     => count($http_cookies),
        'script_count'   => count($script_cookies),
        'scan_duration'  => $scan_duration,
    ));
}

function cm_secs_to_human( $secs ) {
    if ($secs <= 0)          return 'Sessie';
    if ($secs < 3600)        return round($secs/60) . ' min';
    if ($secs < 86400)       return round($secs/3600) . ' uur';
    if ($secs < 86400*7)     return round($secs/86400) . ' dagen';
    if ($secs < 86400*31)    return round($secs/(86400*7)) . ' weken';
    if ($secs < 86400*365)   return round($secs/(86400*30)) . ' maanden';
    return round($secs/(86400*365),1) . ' jaar';
}


/* ================================================================
   AJAX — EXPORT / IMPORT
================================================================ */

add_action( 'wp_ajax_cm_export_register', 'cm_ajax_export_register' );
function cm_ajax_export_register() {
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : ( isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '' );
    if ( ! wp_verify_nonce( $nonce, 'cm_save_settings' ) ) wp_die('Ongeldige nonce');
    if ( ! current_user_can('manage_options') ) wp_die('Geen toegang');

    $pv  = array_merge( cm_default_privacy(), (array) get_option('cm_privacy', array()) );
    $pvf = function($k) use ($pv) { return isset($pv[$k]) ? $pv[$k] : ''; };

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="verwerkingsregister-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM voor Excel

    // Header conform AVG art. 30
    fputcsv($out, array(
        'Verwerkingsregister — ' . $pvf('pv_bedrijfsnaam'),
        'Gegenereerd door Cookiebaas', 'Datum: ' . date('d-m-Y'), '', '', '', ''
    ));
    fputcsv($out, array('', '', '', '', '', '', ''));
    fputcsv($out, array(
        'Verwerkingsactiviteit', 'Categorie betrokkenen', 'Doel', 'Rechtsgrondslag',
        'Ontvangers / Verwerkers', 'Bewaartermijn', 'Internationale doorgifte'
    ));

    // Verwerkingen uit privacyverklaring
    $doeleinden = json_decode($pvf('pv_doeleinden'), true);
    $ontvangers_raw = json_decode($pvf('pv_ontvangers'), true);
    $ontvangers_str = '';
    if ( is_array($ontvangers_raw) ) {
        foreach ($ontvangers_raw as $o) {
            if (!empty($o['partij'])) $ontvangers_str .= $o['partij'] . ' (' . ($o['locatie'] ?? '') . '); ';
        }
    }
    $doorgifte = $pvf('pv_doorgifte') ?: 'Nee / Niet van toepassing';

    if ( is_array($doeleinden) ) {
        foreach ($doeleinden as $d) {
            fputcsv($out, array(
                $d['doel']       ?? '',
                $d['categorie']  ?? 'Websitebezoekers',
                $d['doel']       ?? '',
                $d['grondslag']  ?? '',
                $ontvangers_str ?: '—',
                '—',
                $doorgifte,
            ));
        }
    }

    // Cookiemelding consent log als aparte verwerkingsactiviteit
    $ret = intval(cm_get('log_retention_months'));
    fputcsv($out, array(
        'Cookietoestemming registratie',
        'Websitebezoekers',
        'Vastleggen en bewaren van toestemming voor cookies (AVG art. 7 verantwoordingsplicht)',
        'Wettelijke verplichting (AVG art. 7 lid 1)',
        'Cookiebaas plugin / ' . $pvf('pv_bedrijfsnaam'),
        $ret > 0 ? $ret . ' maanden' : 'Niet ingesteld',
        'Nee — opslag op eigen server',
    ));

    // Contactformulier
    fputcsv($out, array(
        'Contactformulier',
        'Contactpersonen / Klanten',
        'Beantwoorden van contactverzoeken',
        'Gerechtvaardigd belang / Toestemming',
        $pvf('pv_bedrijfsnaam'),
        $pvf('pv_bewaar_contact') ?: '—',
        'Nee',
    ));

    fputcsv($out, array('', '', '', '', '', '', ''));
    fputcsv($out, array('Verwerkingsverantwoordelijke', $pvf('pv_bedrijfsnaam'), $pvf('pv_straat'), $pvf('pv_postcode_plaats'), 'E-mail: ' . $pvf('pv_email'), '', ''));
    if (!empty($pv['pv_dpo_enabled']) && $pv['pv_dpo_enabled'] === '1') {
        fputcsv($out, array('Functionaris Gegevensbescherming (DPO)', $pvf('pv_dpo_naam'), $pvf('pv_dpo_email'), $pvf('pv_dpo_telefoon'), '', '', ''));
    }

    fclose($out);
    exit;
}


add_action( 'wp_ajax_cm_export_settings', 'cm_ajax_export_settings' );
function cm_ajax_export_settings() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

    $export = array(
        '_meta' => array(
            'plugin'    => 'cookiebaas',
            'version'   => CM_VERSION,
            'exported'  => current_time( 'c' ),
            'site'      => get_bloginfo('url'),
        ),
        'settings'    => get_option( 'cm_settings',     cm_default_settings() ),
        'cookie_list' => get_option( 'cm_cookie_list',  array() ),
        'privacy'     => get_option( 'cm_privacy',      cm_default_privacy() ),
    );

    wp_send_json_success( $export );
}

add_action( 'wp_ajax_cm_import_settings', 'cm_ajax_import_settings' );
function cm_ajax_import_settings() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

    $raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
    if ( empty( $raw ) ) {
        wp_send_json_error( array( 'msg' => 'Geen data ontvangen.' ) );
        return;
    }

    $data = json_decode( $raw, true );
    $valid_plugins = array( 'cookiebaas', 'cookiemelding' ); // cookiemelding = oude exports
    if ( ! $data || ! isset( $data['_meta']['plugin'] ) || ! in_array( $data['_meta']['plugin'], $valid_plugins, true ) ) {
        wp_send_json_error( array( 'msg' => 'Ongeldig bestand. Importeer alleen bestanden geëxporteerd door de Cookiebaas plugin.' ) );
        return;
    }

    $imported = array();

    if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
        // Merge met defaults zodat nieuwe keys altijd aanwezig zijn
        $merged = array_merge( cm_default_settings(), $data['settings'] );
        update_option( 'cm_settings', $merged );
        $imported[] = 'plugin-instellingen';
    }

    if ( isset( $data['cookie_list'] ) && is_array( $data['cookie_list'] ) ) {
        update_option( 'cm_cookie_list', $data['cookie_list'] );
        $imported[] = count( $data['cookie_list'] ) . ' cookies';
    }

    if ( isset( $data['privacy'] ) && is_array( $data['privacy'] ) ) {
        $merged_pv = array_merge( cm_default_privacy(), $data['privacy'] );
        update_option( 'cm_privacy', $merged_pv );
        $imported[] = 'privacyverklaring';
    }

    wp_send_json_success( array(
        'msg'      => 'Import geslaagd: ' . implode( ', ', $imported ) . '.',
        'version'  => $data['_meta']['version'] ?? '?',
        'exported' => $data['_meta']['exported'] ?? '?',
        'site'     => $data['_meta']['site'] ?? '?',
    ));
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

    $method_clean = in_array( $method, array('accept-all','reject-all','custom','pageload'), true ) ? $method : 'custom';

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

add_action( 'wp_ajax_cm_get_log', 'cm_ajax_get_log' );
function cm_ajax_get_log() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_die();

    global $wpdb;
    $table  = $wpdb->prefix . 'cm_consent_log';
    $page   = max(1, intval( isset($_POST['page']) ? $_POST['page'] : 1 ));
    $per    = 25;
    $offset = ($page - 1) * $per;
    $search = isset($_POST['search']) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

    // Zorg dat tabel bestaat (bestaande installaties zonder activatie)
    $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( ! $table_exists ) {
        cm_create_log_table();
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( ! $table_exists ) {
            wp_send_json_success( array(
                'rows' => array(), 'total' => 0, 'page' => 1, 'pages' => 1,
                'stats' => array('accept_all'=>0,'reject_all'=>0,'custom'=>0),
                'notice' => 'Tabel kon niet worden aangemaakt.',
            ) );
            return;
        }
    }

    if ( $search ) {
        $like   = '%' . $wpdb->esc_like( $search ) . '%';
        $rows   = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, consent_id, analytics, marketing, method, url, plugin_version, created_at FROM `{$table}` WHERE consent_id LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $like, $per, $offset
        ), ARRAY_A );
        $total  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE consent_id LIKE %s", $like
        ) );
    } else {
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, consent_id, analytics, marketing, method, url, plugin_version, created_at FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per, $offset
        ), ARRAY_A );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
    }

    $stats = $wpdb->get_row(
        "SELECT
            SUM(CASE WHEN method = 'accept-all' THEN 1 ELSE 0 END) as accept_all,
            SUM(CASE WHEN method = 'reject-all' THEN 1 ELSE 0 END) as reject_all,
            SUM(CASE WHEN method = 'custom'     THEN 1 ELSE 0 END) as custom
         FROM `{$table}`
         WHERE created_at >= NOW() - INTERVAL 30 DAY
           AND method != 'pageload'",
        ARRAY_A
    );

    wp_send_json_success( array(
        'rows'  => $rows ?: array(),
        'total' => $total,
        'page'  => $page,
        'pages' => max(1, ceil($total / $per)),
        'stats' => $stats ?: array('accept_all'=>0,'reject_all'=>0,'custom'=>0),
    ));
}

add_action( 'wp_ajax_cm_export_log_csv', 'cm_ajax_export_log_csv' );
function cm_ajax_export_log_csv() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_die();

    global $wpdb;
    $table   = $wpdb->prefix . 'cm_consent_log';
    $from    = isset($_GET['from']) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
    $to      = isset($_GET['to'])   ? sanitize_text_field( wp_unslash( $_GET['to'] ) )   : '';

    // Valideer datumformaat
    $from_dt = ( $from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ) ? $from . ' 00:00:00' : null;
    $to_dt   = ( $to   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) )   ? $to   . ' 23:59:59' : null;

    if ( $from_dt && $to_dt ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT consent_id, method, analytics, marketing, url, plugin_version, created_at FROM `{$table}` WHERE method != 'pageload' AND created_at BETWEEN %s AND %s ORDER BY created_at DESC",
            $from_dt, $to_dt
        ), ARRAY_A );
    } elseif ( $from_dt ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT consent_id, method, analytics, marketing, url, plugin_version, created_at FROM `{$table}` WHERE method != 'pageload' AND created_at >= %s ORDER BY created_at DESC",
            $from_dt
        ), ARRAY_A );
    } else {
        $rows = $wpdb->get_results(
            "SELECT consent_id, method, analytics, marketing, url, plugin_version, created_at FROM `{$table}` WHERE method != 'pageload' ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="consent-log-' . date('Y-m-d') . '.csv"' );

    $out = fopen('php://output', 'w');
    fputs( $out, "\xEF\xBB\xBF" );
    fputcsv( $out, array( 'Consent ID', 'Consent Status', 'Analytisch', 'Marketing', 'Pagina', 'Plugin versie', 'Datum/Tijd' ) );

    $status_map = array(
        'accept-all' => 'Geaccepteerd',
        'reject-all' => 'Geweigerd',
        'custom'     => 'Aangepast',
    );

    foreach ( $rows as $row ) {
        fputcsv( $out, array(
            $row['consent_id'] ?: '—',
            $status_map[ $row['method'] ] ?? $row['method'],
            $row['analytics'] ? 'Ja' : 'Nee',
            $row['marketing']  ? 'Ja' : 'Nee',
            $row['url'],
            $row['plugin_version'] ?? '',
            $row['created_at'],
        ));
    }
    fclose($out);
    exit;
}

add_action( 'wp_ajax_cm_get_consent_proof', 'cm_ajax_get_consent_proof' );
function cm_ajax_get_consent_proof() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_die();

    global $wpdb;
    $consent_id = sanitize_text_field( wp_unslash( isset($_POST['consent_id']) ? $_POST['consent_id'] : '' ) );
    if ( ! $consent_id ) { wp_send_json_error(); return; }

    $table = $wpdb->prefix . 'cm_consent_log';
    $row   = $wpdb->get_row( $wpdb->prepare(
        "SELECT consent_id, method, analytics, marketing, url, plugin_version, created_at FROM `{$table}` WHERE consent_id = %s LIMIT 1",
        $consent_id
    ), ARRAY_A );

    if ( ! $row ) { wp_send_json_error( array('msg'=>'Niet gevonden') ); return; }

    $status_map = array( 'accept-all'=>'Geaccepteerd', 'reject-all'=>'Geweigerd', 'custom'=>'Aangepast' );
    wp_send_json_success( array(
        'consent_id'     => $row['consent_id'],
        'status'         => $status_map[ $row['method'] ] ?? $row['method'],
        'analytics'      => $row['analytics'] ? 'Ja' : 'Nee',
        'marketing'      => $row['marketing']  ? 'Ja' : 'Nee',
        'url'            => $row['url'],
        'plugin_version' => $row['plugin_version'] ?? '',
        'created_at'     => $row['created_at'],
    ));
}

add_action( 'wp_ajax_cm_clear_log', 'cm_ajax_clear_log' );
function cm_ajax_clear_log() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_die();
    global $wpdb;
    $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}cm_consent_log" );
    wp_send_json_success();
}

add_action( 'wp_ajax_cm_delete_log_row', 'cm_ajax_delete_log_row' );
function cm_ajax_delete_log_row() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_die();
    global $wpdb;
    $consent_id = sanitize_text_field( wp_unslash( $_POST['consent_id'] ?? '' ) );
    if ( ! $consent_id ) wp_send_json_error( array('msg' => 'Geen consent_id') );
    $deleted = $wpdb->delete(
        $wpdb->prefix . 'cm_consent_log',
        array('consent_id' => $consent_id),
        array('%s')
    );
    if ( $deleted !== false ) {
        wp_send_json_success( array('deleted' => $deleted) );
    } else {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[Cookiebaas] Consent log delete mislukt: ' . $wpdb->last_error );
        }
        wp_send_json_error( array('msg' => 'Verwijderen mislukt.') );
    }
}

/* ================================================================
   AJAX — COOKIELIJST BEHEER
================================================================ */

add_action( 'wp_ajax_cm_save_cookie_list', 'cm_ajax_save_cookie_list' );
function cm_ajax_save_cookie_list() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    // Lees als JSON string (werkt ook bij lege array)
    $raw = array();
    if ( isset($_POST['cookies_json']) && $_POST['cookies_json'] !== '' ) {
        $decoded = json_decode( wp_unslash($_POST['cookies_json']), true );
        if ( is_array($decoded) ) $raw = $decoded;
    } elseif ( isset($_POST['cookies']) && is_array($_POST['cookies']) ) {
        $raw = $_POST['cookies']; // fallback voor oude aanroepen
    }

    $clean = array();
    foreach ( $raw as $ck ) {
        $name = sanitize_text_field( isset($ck['name']) ? $ck['name'] : '' );
        if ( ! $name ) continue;
        $cat = sanitize_text_field( isset($ck['category']) ? $ck['category'] : 'functional' );
        if ( ! in_array($cat, array('functional','analytics','marketing')) ) $cat = 'functional';
        // Normaliseer provider via centrale service-mapping
        $raw_provider = sanitize_text_field( isset($ck['provider']) ? $ck['provider'] : '' );
        $svc = cm_service_for_cookie( $name );
        $provider = $svc ? $svc['service'] : $raw_provider;
        $clean[] = array(
            'name'     => $name,
            'provider' => $provider,
            'purpose'  => sanitize_text_field( isset($ck['purpose'])   ? $ck['purpose']   : '' ),
            'duration' => sanitize_text_field( isset($ck['duration'])  ? $ck['duration']  : 'Sessie' ),
            'category' => $cat,
            'builtin'  => false,
        );
    }

    // Altijd opslaan — ook als $clean leeg is (gebruiker heeft alle cookies verwijderd)
    update_option( 'cm_cookie_list', $clean );
    wp_send_json_success( array( 'count' => count($clean) ) );
}

add_action( 'wp_ajax_cm_get_cookie_list', 'cm_ajax_get_cookie_list' );
function cm_ajax_get_cookie_list() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    wp_send_json_success( array(
        'builtin' => cm_default_cookies(),
        'managed' => get_option( 'cm_cookie_list', array() ),
    ));
}

/* ================================================================
   RENDER
================================================================ */
function cm_render_admin_page() {
    $s = array_merge( cm_default_settings(), (array) get_option( 'cm_settings', array() ) );
    ?>
    <div class="wrap" id="cm-admin-wrap">

        <div class="cm-page-header">
            <div>
                <h1 class="wp-heading-inline">Cookiebaas &mdash; Instellingen</h1>
                <span class="cm-version-tag">v<?php echo esc_html(CM_VERSION); ?> &mdash; door <a href="https://www.ruudvdheijden.nl/" target="_blank">Ruud van der Heijden</a></span>
            </div>
        </div>
        <?php cm_saved_toast(); ?>

        <div class="cm-layout">

            <!-- ======== SETTINGS ======== -->
            <div class="cm-settings-col">

                <nav class="nav-tab-wrapper cm-nav-tabs">
                    <a class="nav-tab nav-tab-active" data-tab="kleuren" href="#">Vormgeving</a>
                    <a class="nav-tab" data-tab="teksten" href="#">Teksten</a>
                    <a class="nav-tab" data-tab="layout" href="#">Layout</a>
                    <a class="nav-tab" data-tab="gedrag" href="#">Gedrag</a>
                    <a class="nav-tab" data-tab="google" href="#">Google</a>
                    <a class="nav-tab" data-tab="embeds" href="#">Embeds</a>
                </nav>

                <!-- ======== TAB KLEUREN ======== -->
                <div class="cm-tab-pane active" id="cm-pane-kleuren">

                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head" data-acc="kleuren-float">
                            <span class="cm-acc-icon">+</span> Zweefknop kleuren
                            <span class="cm-acc-sub">Kleuren van het icoontje en de tekstknop</span>
                        </h3>
                        <div class="cm-accordion-body" id="cm-acc-kleuren-float" style="display:none">
                        <p class="description" style="padding:10px 16px 0;margin:0">De zweefknop kan worden in- of uitgeschakeld en ingesteld op de <a href="#" onclick="jQuery('.cm-nav-tabs .nav-tab[data-tab=layout]').click();return false;">Layout-tab</a>. Hier stelt u alleen de kleuren in.</p>
                        <table class="form-table cm-form-table"><tbody>
                        <tr id="cm-float-icon-colors">
                            <td colspan="2"><h4 class="cm-btn-sub-head">Kleuren icoontje</h4></td>
                        </tr>
                        <tr class="cm-float-icon-row">
                            <th><label>Achtergrond</label></th>
                            <td><?php cm_color_field('color_float_icon_bg', $s); ?></td>
                        </tr>
                        <tr class="cm-float-icon-row">
                            <th><label>Icoontje kleur</label></th>
                            <td><?php cm_color_field('color_float_icon_color', $s); ?></td>
                        </tr>
                        <tr class="cm-float-icon-row">
                            <th><label>Achtergrond hover</label></th>
                            <td><?php cm_color_field('color_float_icon_hover_bg', $s); ?></td>
                        </tr>
                        <tr class="cm-float-icon-row">
                            <th><label>Icoontje kleur hover</label></th>
                            <td><?php cm_color_field('color_float_icon_hover_color', $s); ?></td>
                        </tr>
                        <tr>
                            <td colspan="2"><h4 class="cm-btn-sub-head">Kleuren tekstknop</h4></td>
                        </tr>
                        <tr>
                            <th><label>Achtergrond</label></th>
                            <td><?php cm_color_field('color_float_text_bg', $s); ?></td>
                        </tr>
                        <tr>
                            <th><label>Tekstkleur</label></th>
                            <td><?php cm_color_field('color_float_text_color', $s); ?></td>
                        </tr>
                        <tr>
                            <th><label>Randkleur</label></th>
                            <td><?php cm_color_field('color_float_text_border', $s); ?></td>
                        </tr>
                        <tr>
                            <th><label>Achtergrond hover</label></th>
                            <td><?php cm_color_field('color_float_text_hover_bg', $s); ?></td>
                        </tr>
                        <tr>
                            <th><label>Tekstkleur hover</label></th>
                            <td><?php cm_color_field('color_float_text_hover_color', $s); ?></td>
                        </tr>
                        </tbody></table>
                        </div>
                    </div>

                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head" data-acc="kleuren-popup">
                            <span class="cm-acc-icon">+</span> Popup
                            <span class="cm-acc-sub">Achtergrond, titels, tekst, radius, overlay</span>
                        </h3>
                        <div class="cm-accordion-body" id="cm-acc-kleuren-popup" style="display:none">
                        <table class="form-table cm-form-table"><tbody>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('color_popup_bg',$s); ?></td></tr>
                        <tr><th><label>Kleur titels</label></th><td><?php cm_color_field('color_title',$s); ?></td></tr>
                        <tr><th><label>Kleur bodytekst</label></th><td><?php cm_color_field('color_body',$s); ?></td></tr>
                        <tr><th><label>Kleur links</label></th><td><?php cm_color_field('color_link',$s); ?></td></tr>
                        <tr><th><label>Border radius popup</label></th><td><?php cm_radius_field('radius_popup',$s,0,60); ?></td></tr>
                        <tr><th><label>Overlay donkerte</label></th><td><?php cm_range_field('overlay_opacity',$s,0,90,'%'); ?><p class="description">Donkerte van de achtergrond achter de popup.</p></td></tr>
                        </tbody></table>
                        </div>
                    </div>

                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head" data-acc="kleuren-buttons">
                            <span class="cm-acc-icon">+</span> Buttons
                            <span class="cm-acc-sub">Akkoord, Weigeren, Voorkeuren, Alle cookies toestaan</span>
                        </h3>
                        <div class="cm-accordion-body" id="cm-acc-kleuren-buttons" style="display:none">
                        <table class="form-table cm-form-table"><tbody>
                        <tr><td colspan="2"><h4 class="cm-sub-head cm-btn-sub-head">Akkoord button</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('color_accept_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur</label></th><td><?php cm_color_field('color_accept_text',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover</label></th><td><?php cm_color_field('color_accept_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur hover</label></th><td><?php cm_color_field('color_accept_hover_text',$s); ?></td></tr>
                        <tr><th><label>Randkleur (optioneel)</label></th><td><?php cm_color_field_optional('color_accept_border',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-sub-head cm-btn-sub-head">Weigeren button</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('color_reject_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur</label></th><td><?php cm_color_field('color_reject_text',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover</label></th><td><?php cm_color_field('color_reject_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur hover</label></th><td><?php cm_color_field('color_reject_hover_text',$s); ?></td></tr>
                        <tr><th><label>Randkleur (optioneel)</label></th><td><?php cm_color_field_optional('color_reject_border',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-sub-head cm-btn-sub-head">Cookie voorkeuren button</h4></td></tr>
                        <tr><th><label>Randkleur</label></th><td><?php cm_color_field('color_prefs_border',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur</label></th><td><?php cm_color_field('color_prefs_text',$s); ?></td></tr>
                        <tr><th><label>Randkleur hover</label></th><td><?php cm_color_field('color_prefs_hover_border',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur hover</label></th><td><?php cm_color_field('color_prefs_hover_text',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-sub-head cm-btn-sub-head">Alle cookies toestaan <span style="font-weight:400;font-size:12px;opacity:.7">(in voorkeuren-venster)</span></h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('color_allowall_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur</label></th><td><?php cm_color_field('color_allowall_text',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover</label></th><td><?php cm_color_field('color_allowall_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur hover</label></th><td><?php cm_color_field('color_allowall_hover_text',$s); ?></td></tr>
                        <tr><th><label>Randkleur (optioneel)</label></th><td><?php cm_color_field_optional('color_allowall_border',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-sub-head">Border radius buttons</h4></td></tr>
                        <tr><th><label>Afronding</label></th><td><?php cm_radius_field('radius_btn',$s,0,60); ?><p class="description">Geldt voor alle buttons.</p></td></tr>
                        </tbody></table>
                        </div>
                    </div>

                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head" data-acc="kleuren-toggles">
                            <span class="cm-acc-icon">+</span> Sluit-knop, Toggles &amp; Cookielijst
                            <span class="cm-acc-sub">Kruisje, toggles, cookielijst-kleuren</span>
                        </h3>
                        <div class="cm-accordion-body" id="cm-acc-kleuren-toggles" style="display:none">
                        <table class="form-table cm-form-table"><tbody>
                        <tr><th><label>Achtergrond kruisje</label></th><td><?php cm_color_field('color_close_bg',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover kruisje</label></th><td><?php cm_color_field('color_close_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Kleur kruisje</label></th><td><?php cm_color_field('color_close_icon',$s); ?></td></tr>
                        <tr><th><label>Toggle kleur (aan)</label></th><td><?php cm_color_field('color_toggle_on',$s); ?></td></tr>
                        <tr><th><label>"Altijd actief" badge</label></th><td><?php cm_color_field('color_always_bg',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-sub-head cm-btn-sub-head">Expand-icoon (+)</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('color_expand_bg',$s); ?><p class="description">Achtergrond van het + icoon bij categorieën.</p></td></tr>
                        <tr><th><label>Icoon kleur</label></th><td><?php cm_color_field('color_expand_icon',$s); ?></td></tr>
                        <tr><th><label>Achtergrond (open)</label></th><td><?php cm_color_field('color_expand_open_bg',$s); ?><p class="description">Achtergrond wanneer de categorie is uitgeklapt.</p></td></tr>
                        <tr><th><label>Icoon kleur (open)</label></th><td><?php cm_color_field('color_expand_open_icon',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-sub-head cm-btn-sub-head">Cookielijst in popup</h4></td></tr>
                        <tr><th><label>Randkleur (universeel)</label></th><td><?php cm_color_field('color_cat_border',$s); ?><p class="description">Rand om categorieën, diensten en cookie-items.</p></td></tr>
                        <tr><th><label>Achtergrond dienst</label></th><td><?php cm_color_field('color_service_bg',$s); ?></td></tr>
                        <tr><th><label>Achtergrond cookie-rij</label></th><td><?php cm_color_field('color_cookie_item_bg',$s); ?></td></tr>
                        </tbody></table>
                        </div>
                    </div>

                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head" data-acc="kleuren-darkmode">
                            <span class="cm-acc-icon">+</span> Dark mode
                            <span class="cm-acc-sub">Kleuren bij prefers-color-scheme: dark</span>
                        </h3>
                        <div class="cm-accordion-body" id="cm-acc-kleuren-darkmode" style="display:none">
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label>Dark mode</label></th>
                            <td>
                                <label><input type="checkbox" name="dark_mode_enabled" value="1" <?php checked($s['dark_mode_enabled'],1); ?> id="cm-dm-toggle"> Aparte kleuren tonen in donkere modus</label>
                                <p class="description">Als de bezoeker dark mode heeft ingesteld op zijn apparaat worden onderstaande kleuren gebruikt.</p>
                            </td>
                        </tr>
                        </tbody></table>
                        <div id="cm-dm-fields" style="<?php echo $s['dark_mode_enabled'] ? '' : 'display:none'; ?>">
                        <table class="form-table cm-form-table"><tbody>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Popup</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('dm_popup_bg',$s); ?></td></tr>
                        <tr><th><label>Kleur titels</label></th><td><?php cm_color_field('dm_title',$s); ?></td></tr>
                        <tr><th><label>Kleur bodytekst</label></th><td><?php cm_color_field('dm_body',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Akkoord button</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('dm_accept_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur</label></th><td><?php cm_color_field('dm_accept_text',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover</label></th><td><?php cm_color_field('dm_accept_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur hover</label></th><td><?php cm_color_field('dm_accept_hover_text',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Weigeren button</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('dm_reject_bg',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover</label></th><td><?php cm_color_field('dm_reject_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur</label></th><td><?php cm_color_field('dm_reject_text',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur hover</label></th><td><?php cm_color_field('dm_reject_hover_text',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Cookie voorkeuren button</h4></td></tr>
                        <tr><th><label>Randkleur</label></th><td><?php cm_color_field('dm_prefs_border',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur</label></th><td><?php cm_color_field('dm_prefs_text',$s); ?></td></tr>
                        <tr><th><label>Randkleur hover</label></th><td><?php cm_color_field('dm_prefs_hover_border',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur hover</label></th><td><?php cm_color_field('dm_prefs_hover_text',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Alle cookies toestaan button</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('dm_allowall_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur</label></th><td><?php cm_color_field('dm_allowall_text',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover</label></th><td><?php cm_color_field('dm_allowall_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Tekstkleur hover</label></th><td><?php cm_color_field('dm_allowall_hover_text',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Sluit-knop &amp; Toggles</h4></td></tr>
                        <tr><th><label>Achtergrond kruisje</label></th><td><?php cm_color_field('dm_close_bg',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover kruisje</label></th><td><?php cm_color_field('dm_close_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Kleur kruisje</label></th><td><?php cm_color_field('dm_close_icon',$s); ?></td></tr>
                        <tr><th><label>Toggle kleur (aan)</label></th><td><?php cm_color_field('dm_toggle_on',$s); ?></td></tr>
                        <tr><th><label>"Altijd actief" badge</label></th><td><?php cm_color_field('dm_always_bg',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Categorieën (voorkeuren-venster)</h4></td></tr>
                        <tr><th><label>Randkleur categorieën</label></th><td><?php cm_color_field('dm_cat_border',$s); ?><p class="description">Rand om en tussen de cookie-categorieën.</p></td></tr>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Zweefknop icoontje</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('dm_float_icon_bg',$s); ?></td></tr>
                        <tr><th><label>Icoontje kleur</label></th><td><?php cm_color_field('dm_float_icon_color',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover</label></th><td><?php cm_color_field('dm_float_icon_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Icoontje kleur hover</label></th><td><?php cm_color_field('dm_float_icon_hover_color',$s); ?></td></tr>
                        </tbody></table>
                        </div><!-- /cm-dm-fields -->
                        </div>
                    </div>

                    <!-- Embed placeholder kleuren -->
                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head">
                            <span class="cm-acc-icon">+</span> Embed placeholder
                            <span class="cm-acc-sub">&mdash; kleuren van de geblokkeerde video/iframe weergave</span>
                        </h3>
                        <div class="cm-accordion-body" style="display:none">
                        <table class="form-table cm-form-table"><tbody>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('color_embed_bg',$s); ?></td></tr>
                        <tr><th><label>Titel kleur</label></th><td><?php cm_color_field('color_embed_title',$s); ?></td></tr>
                        <tr><th><label>Tekst kleur</label></th><td><?php cm_color_field('color_embed_body',$s); ?></td></tr>
                        <tr><td colspan="2"><h4 class="cm-btn-sub-head">Knop &ldquo;Inhoud laden&rdquo;</h4></td></tr>
                        <tr><th><label>Achtergrond</label></th><td><?php cm_color_field('color_embed_btn_bg',$s); ?></td></tr>
                        <tr><th><label>Tekst</label></th><td><?php cm_color_field('color_embed_btn_text',$s); ?></td></tr>
                        <tr><th><label>Achtergrond hover</label></th><td><?php cm_color_field('color_embed_btn_hover_bg',$s); ?></td></tr>
                        <tr><th><label>Tekst hover</label></th><td><?php cm_color_field('color_embed_btn_hover_text',$s); ?></td></tr>
                        </tbody></table>
                        </div>
                    </div>

                </div><!-- /pane-kleuren -->

                <!-- ======== TAB LAYOUT ======== -->
                <div class="cm-tab-pane" id="cm-pane-layout">

                    <div class="cm-group">
                        <h3 class="cm-group-title">Positie cookiebanner</h3>
                        <div style="padding:16px">
                            <p class="description" style="margin:0 0 16px;padding:0">Kies waar de cookiebanner verschijnt op het scherm. De positie is puur visueel &mdash; de functionaliteit blijft identiek.</p>

                            <?php $pos = $s['banner_position'] ?? 'bottom-center'; ?>
                            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;max-width:560px">

                                <!-- Optie 1: Onderaan gecentreerd (huidige standaard) -->
                                <label style="display:block;border:2px solid <?php echo $pos === 'bottom-center' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:12px;cursor:pointer;transition:border-color .15s;background:<?php echo $pos === 'bottom-center' ? '#f0f6fb' : '#fff'; ?>">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                                        <input type="radio" name="banner_position" value="bottom-center" <?php checked($pos, 'bottom-center'); ?> style="margin:0">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Onderaan gecentreerd</span>
                                    </div>
                                    <div style="background:#f0f0f1;border-radius:4px;height:80px;position:relative;overflow:hidden">
                                        <div style="position:absolute;bottom:6px;left:50%;transform:translateX(-50%);width:70%;height:20px;background:#2271b1;border-radius:3px;opacity:0.8"></div>
                                    </div>
                                    <div style="font-size:11px;color:#787c82;margin-top:6px">Breed onderaan het scherm (standaard)</div>
                                </label>

                                <!-- Optie 2: Midden van het scherm -->
                                <label style="display:block;border:2px solid <?php echo $pos === 'center' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:12px;cursor:pointer;transition:border-color .15s;background:<?php echo $pos === 'center' ? '#f0f6fb' : '#fff'; ?>">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                                        <input type="radio" name="banner_position" value="center" <?php checked($pos, 'center'); ?> style="margin:0">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Midden van het scherm</span>
                                    </div>
                                    <div style="background:#f0f0f1;border-radius:4px;height:80px;position:relative;overflow:hidden">
                                        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:55%;height:34px;background:#2271b1;border-radius:3px;opacity:0.8"></div>
                                    </div>
                                    <div style="font-size:11px;color:#787c82;margin-top:6px">Gecentreerd op het scherm, zelfde breedte als voorkeuren-venster</div>
                                </label>

                                <!-- Optie 3: Linksonder -->
                                <label style="display:block;border:2px solid <?php echo $pos === 'bottom-left' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:12px;cursor:pointer;transition:border-color .15s;background:<?php echo $pos === 'bottom-left' ? '#f0f6fb' : '#fff'; ?>">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                                        <input type="radio" name="banner_position" value="bottom-left" <?php checked($pos, 'bottom-left'); ?> style="margin:0">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Linksonder</span>
                                    </div>
                                    <div style="background:#f0f0f1;border-radius:4px;height:80px;position:relative;overflow:hidden">
                                        <div style="position:absolute;bottom:6px;left:8px;width:40%;height:34px;background:#2271b1;border-radius:3px;opacity:0.8"></div>
                                    </div>
                                    <div style="font-size:11px;color:#787c82;margin-top:6px">Compact venster linksonder</div>
                                </label>

                                <!-- Optie 4: Rechtsonder -->
                                <label style="display:block;border:2px solid <?php echo $pos === 'bottom-right' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:12px;cursor:pointer;transition:border-color .15s;background:<?php echo $pos === 'bottom-right' ? '#f0f6fb' : '#fff'; ?>">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                                        <input type="radio" name="banner_position" value="bottom-right" <?php checked($pos, 'bottom-right'); ?> style="margin:0">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Rechtsonder</span>
                                    </div>
                                    <div style="background:#f0f0f1;border-radius:4px;height:80px;position:relative;overflow:hidden">
                                        <div style="position:absolute;bottom:6px;right:8px;width:40%;height:34px;background:#2271b1;border-radius:3px;opacity:0.8"></div>
                                    </div>
                                    <div style="font-size:11px;color:#787c82;margin-top:6px">Compact venster rechtsonder</div>
                                </label>

                            </div>
                        </div>
                    </div>

                    <!-- Zweefknop layout -->
                    <div class="cm-group">
                        <h3 class="cm-group-title">Zweefknop</h3>
                        <div style="padding:16px">

                            <!-- Aan/uit -->
                            <div style="margin-bottom:16px">
                                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                                    <input type="checkbox" name="show_float_btn" value="1" <?php checked($s['show_float_btn'],1); ?> style="margin:0">
                                    <span style="font-weight:600;color:#1d2327">Zweefknop tonen</span>
                                </label>
                                <p class="description" style="margin:4px 0 0 26px;padding:0">Toont een knop op elke pagina waarmee bezoekers hun cookievoorkeuren kunnen wijzigen. AVG-vereiste: als u dit uitschakelt, plaats dan een cookielink in uw footer.</p>
                                <p class="description" style="margin:6px 0 0 26px;padding:0">
                                    <a href="#" id="cm-show-footer-links" style="color:#2271b1;text-decoration:none" onclick="document.getElementById('cm-footer-link-codes').style.display='block';this.style.display='none';return false;">Footer-link om de banner te openen &darr;</a>
                                </p>
                                <div id="cm-footer-link-codes" style="display:none;margin:6px 0 0 26px">
                                    <p class="description" style="margin:0 0 8px;padding:0">Footer-link om de banner te openen:<br><code style="display:inline-block;margin-top:4px;padding:4px 8px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;font-size:12px">&lt;a href=&quot;#&quot; onclick=&quot;Cookiebaas.showBanner();return false;&quot;&gt;Cookie-instellingen&lt;/a&gt;</code></p>
                                    <p class="description" style="margin:0;padding:0">Of direct het voorkeuren-venster openen:<br><code style="display:inline-block;margin-top:4px;padding:4px 8px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;font-size:12px">&lt;a href=&quot;#&quot; onclick=&quot;Cookiebaas.openPrefs();return false;&quot;&gt;Cookie-instellingen&lt;/a&gt;</code></p>
                                </div>
                            </div>

                            <!-- Stijl keuze -->
                            <p style="font-size:12px;font-weight:600;color:#1d2327;margin:0 0 10px">Stijl</p>
                            <?php $fstyle = $s['float_btn_style'] ?? 'icon'; ?>
                            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;max-width:400px;margin-bottom:20px">

                                <label style="display:block;border:2px solid <?php echo $fstyle === 'icon' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:12px;cursor:pointer;background:<?php echo $fstyle === 'icon' ? '#f0f6fb' : '#fff'; ?>">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                                        <input type="radio" name="float_btn_style" value="icon" <?php checked($fstyle, 'icon'); ?> style="margin:0" class="cm-float-style-radio">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Rond icoontje</span>
                                    </div>
                                    <div style="background:#f0f0f1;border-radius:4px;height:60px;position:relative;overflow:hidden">
                                        <div style="position:absolute;bottom:8px;left:8px;width:28px;height:28px;background:#2271b1;border-radius:50%;opacity:0.9"></div>
                                    </div>
                                </label>

                                <label style="display:block;border:2px solid <?php echo $fstyle === 'text' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:12px;cursor:pointer;background:<?php echo $fstyle === 'text' ? '#f0f6fb' : '#fff'; ?>">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                                        <input type="radio" name="float_btn_style" value="text" <?php checked($fstyle, 'text'); ?> style="margin:0" class="cm-float-style-radio">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Tekstknop</span>
                                    </div>
                                    <div style="background:#f0f0f1;border-radius:4px;height:60px;position:relative;overflow:hidden">
                                        <div style="position:absolute;bottom:8px;left:8px;width:80px;height:22px;background:#2271b1;border-radius:3px;opacity:0.9"></div>
                                    </div>
                                </label>

                            </div>

                            <!-- Icoontje opties (alleen zichtbaar bij stijl=icon) -->
                            <div id="cm-float-icon-options" style="<?php echo $fstyle !== 'icon' ? 'display:none;' : ''; ?>margin-bottom:20px">
                                <p style="font-size:12px;font-weight:600;color:#1d2327;margin:0 0 10px">Grootte icoontje</p>
                                <?php $fsize = $s['float_icon_size'] ?? 'normal'; ?>
                                <div style="display:flex;gap:12px;margin-bottom:16px">
                                    <label style="display:flex;align-items:center;gap:8px;border:2px solid <?php echo $fsize === 'normal' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:10px 16px;cursor:pointer;background:<?php echo $fsize === 'normal' ? '#f0f6fb' : '#fff'; ?>">
                                        <input type="radio" name="float_icon_size" value="normal" <?php checked($fsize, 'normal'); ?> style="margin:0" class="cm-float-size-radio">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Normaal</span>
                                        <span style="font-size:11px;color:#787c82">(52px)</span>
                                    </label>
                                    <label style="display:flex;align-items:center;gap:8px;border:2px solid <?php echo $fsize === 'small' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:10px 16px;cursor:pointer;background:<?php echo $fsize === 'small' ? '#f0f6fb' : '#fff'; ?>">
                                        <input type="radio" name="float_icon_size" value="small" <?php checked($fsize, 'small'); ?> style="margin:0" class="cm-float-size-radio">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Klein</span>
                                        <span style="font-size:11px;color:#787c82">(40px)</span>
                                    </label>
                                </div>

                                <p style="font-size:12px;font-weight:600;color:#1d2327;margin:0 0 10px">Icoontje</p>
                                <?php $custom_svg = $s['float_icon_custom_svg'] ?? ''; ?>
                                <div style="display:flex;gap:12px;margin-bottom:10px">
                                    <label style="display:flex;align-items:center;gap:8px;border:2px solid <?php echo empty($custom_svg) ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:10px 16px;cursor:pointer;background:<?php echo empty($custom_svg) ? '#f0f6fb' : '#fff'; ?>">
                                        <input type="radio" name="cm_icon_type" value="default" <?php checked(empty($custom_svg)); ?> style="margin:0" class="cm-icon-type-radio">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Standaard</span>
                                    </label>
                                    <label style="display:flex;align-items:center;gap:8px;border:2px solid <?php echo !empty($custom_svg) ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:10px 16px;cursor:pointer;background:<?php echo !empty($custom_svg) ? '#f0f6fb' : '#fff'; ?>">
                                        <input type="radio" name="cm_icon_type" value="custom" <?php checked(!empty($custom_svg)); ?> style="margin:0" class="cm-icon-type-radio">
                                        <span style="font-size:13px;font-weight:600;color:#1d2327">Eigen SVG</span>
                                    </label>
                                </div>
                                <div id="cm-custom-svg-area" style="<?php echo empty($custom_svg) ? 'display:none;' : ''; ?>margin-top:10px">
                                    <textarea name="float_icon_custom_svg" id="float_icon_custom_svg" rows="4" style="width:100%;max-width:500px;font-family:monospace;font-size:12px;border:1px solid #8c8f94;border-radius:4px;padding:8px;background:#fff;color:#2c3338" placeholder="Plak hier uw SVG code, bijv: &lt;svg viewBox=&quot;0 0 24 24&quot;&gt;...&lt;/svg&gt;"><?php echo esc_textarea($custom_svg); ?></textarea>
                                    <p class="description" style="margin:4px 0 0;padding:0">Plak de volledige <code>&lt;svg&gt;...&lt;/svg&gt;</code> code. Het icoontje wordt automatisch geschaald. De kleur wordt bepaald door de icoontje-kleur instelling op de Vormgeving-tab.</p>
                                </div>
                            </div>

                            <!-- Positie keuze -->
                            <p style="font-size:12px;font-weight:600;color:#1d2327;margin:0 0 10px">Positie</p>
                            <?php $fpos = $s['float_position'] ?? 'left'; ?>
                            <div style="display:flex;gap:12px;max-width:400px">

                                <label style="display:flex;align-items:center;gap:8px;border:2px solid <?php echo $fpos === 'left' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:10px 16px;cursor:pointer;background:<?php echo $fpos === 'left' ? '#f0f6fb' : '#fff'; ?>">
                                    <input type="radio" name="float_position" value="left" <?php checked($fpos, 'left'); ?> style="margin:0" class="cm-float-pos-radio">
                                    <span style="font-size:13px;font-weight:600;color:#1d2327">Linksonder</span>
                                </label>

                                <label style="display:flex;align-items:center;gap:8px;border:2px solid <?php echo $fpos === 'right' ? '#2271b1' : '#dcdcde'; ?>;border-radius:6px;padding:10px 16px;cursor:pointer;background:<?php echo $fpos === 'right' ? '#f0f6fb' : '#fff'; ?>">
                                    <input type="radio" name="float_position" value="right" <?php checked($fpos, 'right'); ?> style="margin:0" class="cm-float-pos-radio">
                                    <span style="font-size:13px;font-weight:600;color:#1d2327">Rechtsonder</span>
                                </label>

                            </div>
                            <p class="description" style="margin:8px 0 0;padding:0">De kleuren van de zweefknop kunt u instellen op de <a href="#" onclick="jQuery('.cm-nav-tabs .nav-tab[data-tab=kleuren]').click();return false;">Vormgeving-tab</a>.</p>

                        </div>
                    </div>

                </div><!-- /pane-layout -->

                <!-- ======== TAB TEKSTEN ======== -->
                <div class="cm-tab-pane" id="cm-pane-teksten">

                    <?php
                    $banner_lang = $s['banner_language'] ?? 'nl';
                    $langs = array( 'nl' => '🇳🇱 Nederlands', 'en' => '🇬🇧 English' );
                    ?>

                    <!-- Bannertaal instelling -->
                    <div class="cm-group">
                        <h3 class="cm-group-title">Bannertaal <span style="font-size:11px;font-weight:500;color:#fff;background:#f0b429;border-radius:3px;padding:2px 6px;vertical-align:middle;letter-spacing:.3px">Beta</span></h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label>Taal van de banner</label></th>
                            <td>
                                <label style="display:inline-flex;align-items:center;gap:8px;margin-right:20px">
                                    <input type="radio" name="banner_language" value="nl" <?php checked($banner_lang,'nl'); ?>>
                                    🇳🇱 Nederlands
                                </label>
                                <label style="display:inline-flex;align-items:center;gap:8px">
                                    <input type="radio" name="banner_language" value="en" <?php checked($banner_lang,'en'); ?>>
                                    🇬🇧 English <span style="font-size:11px;color:#9a5a00;font-weight:600">(Beta)</span>
                                </label>
                                <p class="description" style="margin-top:8px">
                                    Kies in welke taal de cookiebanner getoond wordt aan bezoekers. Onafhankelijk van de WordPress site-taal. Pas hieronder de teksten per taal aan.
                                </p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>

                    <!-- Taalwisselaar tabs — altijd zichtbaar, beide panes altijd in DOM -->
                    <div class="cm-group" style="background:#f6f7f7;border-color:#dcdcde">
                        <div style="display:flex;gap:0;padding:0">
                            <button type="button" class="cm-lang-tab" data-lang="nl"
                                style="padding:10px 20px;border:none;border-bottom:3px solid <?php echo $banner_lang==='nl' ? '#2271b1' : 'transparent'; ?>;background:transparent;font-weight:<?php echo $banner_lang==='nl' ? '600' : '400'; ?>;cursor:pointer;font-size:13px;color:#1d2327">
                                🇳🇱 Nederlands
                            </button>
                            <button type="button" class="cm-lang-tab" data-lang="en"
                                style="padding:10px 20px;border:none;border-bottom:3px solid <?php echo $banner_lang==='en' ? '#2271b1' : 'transparent'; ?>;background:transparent;font-weight:<?php echo $banner_lang==='en' ? '600' : '400'; ?>;cursor:pointer;font-size:13px;color:#1d2327">
                                🇬🇧 English
                            </button>
                        </div>
                    </div>

                    <?php foreach ( $langs as $lc => $label ) :
                        $sfx = $lc === 'nl' ? '' : '_' . $lc;
                        $is_en = $lc === 'en';
                    ?>
                    <div class="cm-lang-pane" data-lang="<?php echo esc_attr($lc); ?>" style="<?php echo ( $lc !== $banner_lang ) ? 'display:none' : ''; ?>">

                    <div class="cm-group">
                        <h3 class="cm-group-title">Hoofdbanner</h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr><th><label>Titel</label></th><td><input type="text" name="txt_banner_title<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_banner_title'.$sfx]); ?>"></td></tr>
                        <tr><th><label>Tekst</label></th><td><textarea name="txt_banner_body<?php echo $sfx; ?>" class="large-text cm-textarea"><?php echo esc_textarea($s['txt_banner_body'.$sfx]); ?></textarea><p class="description">Toegestane HTML: &lt;a href=""&gt;, &lt;strong&gt;, &lt;em&gt;</p></td></tr>
                        <tr><th><label>Label "Cookie voorkeuren"</label></th><td><input type="text" name="txt_btn_prefs<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_btn_prefs'.$sfx]); ?>"></td></tr>
                        <tr><th><label>Label "Weigeren"</label></th><td><input type="text" name="txt_btn_reject<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_btn_reject'.$sfx]); ?>"></td></tr>
                        <tr><th><label>Label "Akkoord"</label></th><td><input type="text" name="txt_btn_accept<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_btn_accept'.$sfx]); ?>"></td></tr>
                        </tbody></table>
                    </div>

                    <div class="cm-group">
                        <h3 class="cm-group-title">Voorkeuren venster</h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr><th><label>Titel</label></th><td><input type="text" name="txt_prefs_title<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_prefs_title'.$sfx]); ?>"></td></tr>
                        <tr><th><label>Tekst</label></th><td><textarea name="txt_prefs_body<?php echo $sfx; ?>" class="large-text cm-textarea"><?php echo esc_textarea($s['txt_prefs_body'.$sfx]); ?></textarea><p class="description">Toegestane HTML: &lt;a href=""&gt;, &lt;strong&gt;, &lt;em&gt;</p></td></tr>
                        <tr><th><label>Label "Alle toestaan"</label></th><td><input type="text" name="txt_btn_allowall<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_btn_allowall'.$sfx]); ?>"></td></tr>
                        <tr><th><label>Label "Alles afwijzen"</label></th><td><input type="text" name="txt_btn_rejectall<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_btn_rejectall'.$sfx]); ?>"></td></tr>
                        <tr><th><label>Label "Keuzes opslaan"</label></th><td><input type="text" name="txt_btn_save<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_btn_save'.$sfx]); ?>"></td></tr>
                        </tbody></table>
                    </div>

                    <?php foreach ( array(
                        1 => 'Categorie 1 &mdash; Functioneel',
                        2 => 'Categorie 2 &mdash; Analytisch',
                        3 => 'Categorie 3 &mdash; Marketing',
                    ) as $i => $catLabel ) : ?>
                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head" data-acc="teksten-cat<?php echo $i . $lc; ?>">
                            <span class="cm-acc-icon">+</span> <?php echo $catLabel; ?>
                            <span class="cm-acc-sub">Naam, omschrijving</span>
                        </h3>
                        <div class="cm-accordion-body" id="cm-acc-teksten-cat<?php echo $i . $lc; ?>" style="display:none">
                        <table class="form-table cm-form-table"><tbody>
                        <tr><th><label>Naam</label></th><td><input type="text" name="txt_cat<?php echo $i; ?>_name<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s["txt_cat{$i}_name{$sfx}"] ?? ''); ?>"></td></tr>
                        <tr><th><label>Korte omschrijving</label></th><td><input type="text" name="txt_cat<?php echo $i; ?>_short<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s["txt_cat{$i}_short{$sfx}"] ?? ''); ?>"></td></tr>
                        <tr><th><label>Uitgebreide beschrijving</label></th><td><textarea name="txt_cat<?php echo $i; ?>_long<?php echo $sfx; ?>" class="large-text cm-textarea"><?php echo esc_textarea($s["txt_cat{$i}_long{$sfx}"] ?? ''); ?></textarea></td></tr>
                        </tbody></table>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="cm-group">
                        <h3 class="cm-group-title">Zweefknop</h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr><th><label>Label</label></th><td><input type="text" name="txt_float_label<?php echo $sfx; ?>" class="regular-text" value="<?php echo esc_attr($s['txt_float_label'.$sfx]); ?>"></td></tr>
                        </tbody></table>
                    </div>

                    </div><!-- /cm-lang-pane -->
                    <?php endforeach; ?>

                </div><!-- /pane-teksten -->

                <!-- ======== TAB GEDRAG ======== -->
                <div class="cm-tab-pane" id="cm-pane-gedrag">

                    <div class="cm-group">
                        <h3 class="cm-group-title">Standaard voorkeuren</h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label>Analytische cookies</label></th>
                            <td>
                                <label><input type="checkbox" name="analytics_default" value="1" <?php checked($s['analytics_default'],1); ?>> Standaard aangevinkt in het voorkeuren-venster</label>
                                <p class="description">Marketing cookies staan altijd standaard uit (AVG-vereiste).</p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>

                    <div class="cm-group">
                        <h3 class="cm-group-title">Consent instellingen</h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label>Verlooptijd consent</label></th>
                            <td><?php cm_range_field('expiry_months',$s,1,24,' mnd'); ?><p class="description">Na deze periode wordt opnieuw om toestemming gevraagd. AVG-richtlijn: maximaal 12 maanden.</p></td>
                        </tr>
                        <tr>
                            <th><label>Log retentie</label></th>
                            <td>
                                <?php
                                $ret = intval( $s['log_retention_months'] ?? 0 );
                                $next = wp_next_scheduled('cm_log_retention_cron');
                                ?>
                                <select name="log_retention_months" id="cm-log-retention-select" style="min-width:220px">
                                    <option value="0"  <?php selected($ret,0);  ?>>Nooit automatisch opschonen</option>
                                    <option value="3"  <?php selected($ret,3);  ?>>Na 3 maanden</option>
                                    <option value="6"  <?php selected($ret,6);  ?>>Na 6 maanden</option>
                                    <option value="12" <?php selected($ret,12); ?>>Na 12 maanden</option>
                                    <option value="24" <?php selected($ret,24); ?>>Na 24 maanden</option>
                                    <option value="36" <?php selected($ret,36); ?>>Na 36 maanden (aanbevolen)</option>
                                </select>
                                <p class="description">
                                    Logs ouder dan de ingestelde periode worden dagelijks automatisch verwijderd.
                                    De AVG (artikel 5 lid 1e) verplicht dat persoonsgegevens niet langer worden bewaard dan noodzakelijk. 36 maanden biedt voldoende bewijskracht bij een klacht, zonder onnodig lang gegevens te bewaren.
                                    <?php if ( $ret > 0 && $next ) : ?>
                                    <br><span style="color:#00a32a">&#10003; Cron actief — volgende run: <?php echo esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $next ) ); ?></span>
                                    <?php elseif ( $ret > 0 ) : ?>
                                    <br><span style="color:#f0b429">&#9888; Cron wordt ingepland bij de volgende paginalading.</span>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Do Not Track</label></th>
                            <td>
                                <label><input type="checkbox" name="respect_dnt" value="1" <?php checked($s['respect_dnt'],1); ?>> Respecteer het Do Not Track (DNT) signaal van de browser</label>
                                <p class="description">Als een bezoeker DNT heeft ingeschakeld in de browser, worden analytics- en marketing-cookies automatisch geweigerd. De banner wordt nog wel getoond, maar de toggles staan uit en de consent wordt gelogd als &ldquo;dnt&rdquo;.</p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>

                    <div class="cm-group">
                        <h3 class="cm-group-title">Geo-targeting <span style="font-size:11px;font-weight:400;color:#787c82">— aan wie de banner wordt getoond</span></h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label>Zichtbaarheid banner</label></th>
                            <td>
                                <label style="display:block;margin-bottom:10px">
                                    <input type="radio" name="geo_enabled" value="0" <?php checked( (string)$s['geo_enabled'], '0' ); ?>>
                                    <strong>Altijd tonen</strong> — banner verschijnt voor alle bezoekers wereldwijd <span style="color:#787c82;font-size:12px">(standaard, veiligste keuze)</span>
                                </label>
                                <label style="display:block">
                                    <input type="radio" name="geo_enabled" value="1" <?php checked( (string)$s['geo_enabled'], '1' ); ?>>
                                    <strong>Alleen tonen in landen met privacywetgeving</strong> — EU/EEA, VK, Zwitserland, Brazilië, Canada, India, Thailand, Indonesië, Zuid-Afrika, Japan, Zuid-Korea, Australië, Nieuw-Zeeland, Singapore, Argentinië en Mexico
                                </label>
                            </td>
                        </tr>
                        <tr id="cm-geo-outside-row" style="<?php echo $s['geo_enabled'] ? '' : 'display:none'; ?>">
                            <th><label>Overige landen</label></th>
                            <td>
                                <label style="display:block;margin-bottom:6px">
                                    <input type="radio" name="geo_outside_eu" value="hide" <?php checked($s['geo_outside_eu'],'hide'); ?>>
                                    Geen banner — cookies worden niet geblokkeerd
                                </label>
                                <label style="display:block">
                                    <input type="radio" name="geo_outside_eu" value="accept" <?php checked($s['geo_outside_eu'],'accept'); ?>>
                                    Automatisch akkoord — alle cookies direct toegestaan
                                </label>
                                <p class="description" style="margin-top:8px">Als er geen land-header beschikbaar is (geen Cloudflare of CDN), wordt de banner altijd getoond.</p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>

                    <div class="cm-group">
                        <h3 class="cm-group-title">Pagina-uitzonderingen <span style="font-size:11px;font-weight:400;color:#787c82">— geen banner op deze pagina's</span></h3>
                        <table class="form-table cm-form-table"><tbody>

                        <tr>
                            <th><label>Ingebouwde uitzonderingen</label></th>
                            <td>
                                <label style="display:block;margin-bottom:8px">
                                    <input type="checkbox" name="exclude_login_page" value="1" <?php checked($s['exclude_login_page'],1); ?>>
                                    WordPress inlogpagina <code style="font-size:11px;margin-left:4px">wp-login.php</code>
                                </label>
                                <?php if ( class_exists('WooCommerce') ) : ?>
                                <label style="display:block">
                                    <input type="checkbox" name="exclude_woocommerce_checkout" value="1" <?php checked($s['exclude_woocommerce_checkout'],1); ?>>
                                    WooCommerce checkout, betaling &amp; bestellingsbevestiging
                                </label>
                                <?php else : ?>
                                <label style="display:block;opacity:.5">
                                    <input type="checkbox" disabled>
                                    WooCommerce checkout <span style="font-size:12px;color:#787c82">(WooCommerce niet actief)</span>
                                </label>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th><label for="cm-exclude-pages">Specifieke pagina's</label></th>
                            <td>
                                <?php
                                // Bouw pagina-selectie
                                $excluded_ids = array_filter( array_map( 'intval', explode( ',', $s['exclude_page_ids'] ) ) );
                                $all_pages    = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'number' => 200 ) );
                                ?>
                                <select id="cm-exclude-pages" name="exclude_page_ids_select[]" multiple
                                    style="width:100%;max-width:460px;min-height:120px;border:1px solid #8c8f94;border-radius:3px;padding:4px"
                                    onchange="cmSyncExcludeIds()">
                                    <?php foreach ( $all_pages as $p ) : ?>
                                    <option value="<?php echo esc_attr($p->ID); ?>" <?php echo in_array($p->ID,$excluded_ids,true) ? 'selected' : ''; ?>>
                                        <?php echo esc_html( $p->post_title ); ?> <span style="color:#999">(ID: <?php echo esc_html($p->ID); ?>)</span>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="exclude_page_ids" id="cm-exclude-page-ids-hidden"
                                    value="<?php echo esc_attr( $s['exclude_page_ids'] ); ?>">
                                <p class="description">Houd Ctrl/Cmd ingedrukt om meerdere pagina's te selecteren.</p>
                            </td>
                        </tr>

                        <tr>
                            <th><label>URL-patronen</label></th>
                            <td>
                                <input type="text" name="exclude_url_patterns" class="large-text"
                                    value="<?php echo esc_attr( $s['exclude_url_patterns'] ); ?>"
                                    placeholder="/bedankt, /privacyverklaring, /checkout">
                                <p class="description">Komma-gescheiden URL-stukken. De banner wordt verborgen op elke pagina waarvan de URL dit patroon bevat.</p>
                            </td>
                        </tr>

                        </tbody></table>
                    </div>

                    <div class="cm-group">
                        <h3 class="cm-group-title">REST API <span style="font-size:11px;font-weight:400;color:#787c82">— Consent verificatie voor externe integraties</span></h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label>API endpoint</label></th>
                            <td>
                                <code style="font-size:12px"><?php echo esc_html( rest_url('cookiebaas/v1/consent/{consent_id}') ); ?></code>
                                <p class="description">Gebruik dit endpoint om consent te verifiëren vanuit een CRM, e-mailplatform of andere externe dienst.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="api_key">API-sleutel</label></th>
                            <td>
                                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                    <input type="text" id="api_key" name="api_key"
                                        value="<?php echo esc_attr( cm_get('api_key') ); ?>"
                                        class="regular-text"
                                        style="font-family:monospace;font-size:12px"
                                        placeholder="Leeg = alleen toegankelijk voor ingelogde beheerders"
                                        autocomplete="off" readonly onfocus="this.removeAttribute('readonly')">
                                    <button type="button" class="button" id="cm-generate-api-key">Genereer nieuwe sleutel</button>
                                    <?php if ( cm_get('api_key') ) : ?>
                                    <button type="button" class="button" id="cm-revoke-api-key" style="color:#d63638;border-color:#d63638">Intrekken</button>
                                    <?php endif; ?>
                                </div>
                                <p class="description" style="margin-top:6px">
                                    Stuur de sleutel mee als HTTP-header: <code>X-Cookiebaas-Key: [uw sleutel]</code><br>
                                    Zonder sleutel is het endpoint alleen toegankelijk via een WordPress Application Password.
                                </p>
                                <?php if ( cm_get('api_key') ) : ?>
                                <div style="margin-top:10px;padding:10px 14px;background:#f0f6fc;border:1px solid #b3d1f0;border-radius:4px;font-size:12px">
                                    <strong>Voorbeeld aanroep (cURL):</strong><br>
                                    <code style="font-size:11px">curl -H "X-Cookiebaas-Key: <?php echo esc_attr( cm_get('api_key') ); ?>" \<br>
                                    &nbsp;&nbsp;"<?php echo esc_html( rest_url('cookiebaas/v1/consent/') ); ?>{consent_id}"</code>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Voorbeeld response</label></th>
                            <td>
                                <pre style="background:#f6f7f7;padding:10px 14px;border-radius:4px;font-size:11px;line-height:1.7;overflow-x:auto;border:1px solid #e0e0e0">{
  "consent_id": "a1b2c3d4-...",
  "status": "Geaccepteerd",
  "method": "accept-all",
  "analytics": true,
  "marketing": true,
  "config_hash": "a3f9d2b1c4e87f20",
  "timestamp": "2026-03-17 10:25:00",
  "verified": true
}</pre>
                                <p class="description">Statuswaarden: <code>Geaccepteerd</code>, <code>Geweigerd</code>, <code>Aangepast</code>, <code>Terugkerend bezoek</code>. HTTP 404 als Consent ID niet gevonden.</p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>

                </div><!-- /pane-gedrag -->

                <!-- ======== TAB GOOGLE ======== -->
                <div class="cm-tab-pane" id="cm-pane-google">

                    <div class="cm-group">
                        <h3 class="cm-group-title">Hoe werkt het?</h3>
                        <div style="padding:16px 20px;line-height:1.8;font-size:13px;color:#3c434a">
                            <p style="margin:0 0 10px">Cookiebaas beheert tracking scripts volledig zelf via <strong>Google Consent Mode v2</strong>. Zo werkt het:</p>
                            <ol style="margin:0 0 10px;padding-left:18px">
                                <li>Vul hieronder uw tracking ID's in en sla op.</li>
                                <li>Verwijder de bestaande snippets uit uw code of uit andere plugins indien deze al vermeld staan.</li>
                                <li>Cookiebaas laadt de scripts zelf in, geblokkeerd totdat de bezoeker toestemming geeft.</li>
                                <li>Na akkoord worden scripts direct geactiveerd zonder pagina-herlaad.</li>
                            </ol>
                            <p style="margin:0;color:#787c82">Scripts die u hier invult worden automatisch correct geblokkeerd en vrijgegeven &mdash; geen verdere configuratie nodig.</p>
                        </div>
                    </div>

                    <div class="cm-group">
                        <h3 class="cm-group-title">Google Analytics &amp; Tag Manager</h3>
                        <p class="description" style="margin:0;padding:8px 20px 4px">Gebruik <strong>&oacute;f</strong> GA4 <strong>&oacute;f</strong> GTM &mdash; niet beide. GTM heeft de voorkeur als u meerdere Google diensten gebruikt.</p>
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label for="ga4_measurement_id">GA4 Measurement ID</label></th>
                            <td>
                                <input type="text" id="ga4_measurement_id" name="ga4_measurement_id" value="<?php echo esc_attr( cm_get('ga4_measurement_id') ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                                <p class="description">Begint met <code>G-</code>. Te vinden in Google Analytics &rarr; Beheer &rarr; Gegevensstreams. Verwijder na het invullen de bestaande snippets uit uw code of uit andere plugins.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="gtm_container_id">GTM Container ID</label></th>
                            <td>
                                <input type="text" id="gtm_container_id" name="gtm_container_id" value="<?php echo esc_attr( cm_get('gtm_container_id') ); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
                                <p class="description">Begint met <code>GTM-</code>. Te vinden in Google Tag Manager &rarr; Workspace. Verwijder na het invullen de bestaande snippets uit uw code of uit andere plugins.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ua_tracking_id">Universal Analytics ID <span style="font-size:11px;font-weight:400;color:#787c82">(verouderd)</span></label></th>
                            <td>
                                <input type="text" id="ua_tracking_id" name="ua_tracking_id" value="<?php echo esc_attr( cm_get('ua_tracking_id') ); ?>" class="regular-text" placeholder="UA-XXXXXXXXX-X">
                                <p class="description">Begint met <code>UA-</code>. Google heeft Universal Analytics per 1 juli 2023 stopgezet. Gebruik bij voorkeur GA4. Deze optie is beschikbaar voor websites die nog UA-code draaien.</p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>

                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head">
                            <span class="cm-acc-icon">+</span>
                            Niet-Google tags via GTM
                            <span class="cm-acc-sub">&mdash; Meta Pixel, TikTok, LinkedIn &amp; andere</span>
                        </h3>
                        <div class="cm-accordion-body" style="display:none">
                        <div style="padding:16px 0 20px;font-size:13px;color:#3c434a;line-height:1.7">
                            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;padding:0 20px">
                                <div style="background:#f0faf4;border:1px solid #b7dfca;border-radius:5px;padding:13px 16px;display:flex;align-items:center;gap:16px">
                                    <div style="font-weight:600;color:#1a7a45;white-space:nowrap">&#10003;&nbsp; Automatisch geregeld</div>
                                    <div style="font-size:12px;color:#3c434a;line-height:1.5">
                                        <strong>Google tags</strong> &mdash; GA4, Google Ads, Floodlight &mdash; Consent Mode v2, volledig door de plugin beheerd.<br>
                                        <strong>Microsoft UET</strong> &mdash; Microsoft Advertising &mdash; de plugin pusht automatisch <code>uetq consent update</code> bij elke consentkeuze.
                                    </div>
                                </div>
                                <div style="background:#fef8f0;border:1px solid #f0d0a0;border-radius:5px;padding:13px 16px;display:flex;align-items:center;gap:16px">
                                    <div style="font-weight:600;color:#9a5a00;white-space:nowrap">&#9432;&nbsp; Eenmalig instellen in GTM</div>
                                    <div style="font-size:12px;color:#3c434a;line-height:1.5">
                                        <strong>Niet-Google tags via GTM</strong> &mdash; Meta Pixel, TikTok, LinkedIn, Hotjar e.a. &mdash; er bestaat geen universele standaard. Volg onderstaande 3 stappen eenmalig in GTM.
                                    </div>
                                </div>
                            </div>
                            <div style="padding:0 20px">
                            <p style="margin:0 0 4px;font-weight:600">Stap 1 &mdash; Maak twee variabelen aan in GTM</p>
                            <p style="margin:0 0 10px;color:#646970;font-size:12px">Variabele type: <strong>Data Layer Variable</strong></p>
                            <table style="border-collapse:collapse;width:100%;margin-bottom:20px;font-size:12px">
                                <thead><tr style="background:#f6f7f7">
                                    <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Variabele naam</th>
                                    <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Data Layer Variable Name</th>
                                    <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Gebruik voor</th>
                                </tr></thead>
                                <tbody>
                                    <tr><td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Analytics Consent</code></td><td style="padding:8px 12px;border:1px solid #dcdcde"><code>cm_analytics</code></td><td style="padding:8px 12px;border:1px solid #dcdcde">Hotjar, Matomo, Clarity e.a.</td></tr>
                                    <tr style="background:#f9f9f9"><td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Marketing Consent</code></td><td style="padding:8px 12px;border:1px solid #dcdcde"><code>cm_marketing</code></td><td style="padding:8px 12px;border:1px solid #dcdcde">Meta Pixel, TikTok, LinkedIn e.a.</td></tr>
                                </tbody>
                            </table>
                            <p style="margin:0 0 4px;font-weight:600">Stap 2 &mdash; Maak twee triggers aan in GTM</p>
                            <p style="margin:0 0 10px;color:#646970;font-size:12px">Trigger type: <strong>Custom Event</strong> &mdash; Event name: <code>cm_consent_update</code></p>
                            <table style="border-collapse:collapse;width:100%;margin-bottom:20px;font-size:12px">
                                <thead><tr style="background:#f6f7f7">
                                    <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Trigger naam</th>
                                    <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Conditie</th>
                                </tr></thead>
                                <tbody>
                                    <tr><td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Analytics toegestaan</code></td><td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Analytics Consent</code> &nbsp;equals&nbsp; <code>true</code></td></tr>
                                    <tr style="background:#f9f9f9"><td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Marketing toegestaan</code></td><td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Marketing Consent</code> &nbsp;equals&nbsp; <code>true</code></td></tr>
                                </tbody>
                            </table>
                            <p style="margin:0 0 4px;font-weight:600">Stap 3 &mdash; Koppel de trigger aan uw tag</p>
                            <p style="margin:0 0 16px;color:#3c434a">Open uw Meta Pixel-, TikTok- of LinkedIn-tag in GTM en stel als triggering condition in: <code>CM - Marketing toegestaan</code>. De tag vuurt dan alleen als de bezoeker marketing cookies heeft geaccepteerd.</p>
                            <p style="margin:0 0 16px;color:#3c434a"><strong>Microsoft UET (Bing Ads) buiten GTM?</strong> De plugin pusht automatisch <code>window.uetq.push('consent', 'update', ...)</code> &mdash; geen extra stappen nodig als de UET-tag direct op de site staat.</p>
                            </div>
                            <div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:12px 16px 12px 20px;font-size:12px;margin:0 20px 0">
                                <strong style="display:block;margin-bottom:6px">Voorbeeld dataLayer push &mdash; automatisch door de plugin:</strong>
                                <pre style="margin:0;font-size:11px;line-height:1.7;background:none;padding:0;border:none;overflow-x:auto">{
  event              : "cm_consent_update",
  cm_analytics       : true,
  cm_marketing       : false,
  cm_method          : "custom",
  analytics_storage  : "granted",
  ad_storage         : "denied",
  ad_user_data       : "denied",
  ad_personalization : "denied"
}</pre>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="cm-group cm-accordion">
                        <h3 class="cm-group-title cm-accordion-head">
                            <span class="cm-acc-icon">+</span>
                            Overige tracking scripts
                        </h3>
                        <div class="cm-accordion-body" style="display:none">
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label for="block_analytics_patterns">Extra analytics patronen</label></th>
                            <td>
                                <input type="text" id="block_analytics_patterns" name="block_analytics_patterns" value="<?php echo esc_attr( cm_get('block_analytics_patterns') ); ?>" class="regular-text" placeholder="optioneel — bijv. mijnanalytics.nl">
                                <p class="description">Komma-gescheiden URL-patronen voor analytics scripts die niet automatisch herkend worden.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="block_marketing_patterns">Extra marketing patronen</label></th>
                            <td>
                                <input type="text" id="block_marketing_patterns" name="block_marketing_patterns" value="<?php echo esc_attr( cm_get('block_marketing_patterns') ); ?>" class="regular-text" placeholder="optioneel — bijv. mijnretargeting.nl">
                                <p class="description">Komma-gescheiden URL-patronen voor marketing scripts die niet automatisch herkend worden.</p>
                            </td>
                        </tr>
                        </tbody></table>
                        </div>
                    </div>

                </div><!-- /pane-google -->

                <!-- ======== TAB EMBEDS ======== -->
                <div class="cm-tab-pane" id="cm-pane-embeds">

                    <div class="cm-group">
                        <h3 class="cm-group-title">Embed blocker</h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label for="embed_blocker_enabled">Embed blocker</label></th>
                            <td>
                                <label><input type="checkbox" name="embed_blocker_enabled" id="embed_blocker_enabled" value="1" <?php checked( cm_get('embed_blocker_enabled'), 1 ); ?>> Actief &mdash; blokkeer iframes (YouTube, Vimeo, etc.) tot consent</label>
                                <p class="description">Vervangt iframes van bekende diensten automatisch door een placeholder. Pas na toestemming (of klik op &ldquo;Inhoud laden&rdquo;) wordt het iframe geladen.</p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>

                    <div class="cm-group">
                        <h3 class="cm-group-title">Diensten blokkeren</h3>
                        <div style="padding:16px 20px;font-size:13px;color:#3c434a;line-height:1.8">
                            <p style="margin:0 0 10px">Vink aan welke diensten geblokkeerd moeten worden tot de bezoeker toestemming geeft. Uitgevinkte diensten worden niet tegengehouden.</p>
                            <div style="display:flex;gap:6px;margin-bottom:14px">
                                <button type="button" class="button button-small" id="cm-embed-check-all">Alles aanvinken</button>
                                <button type="button" class="button button-small" id="cm-embed-uncheck-all">Alles uitvinken</button>
                            </div>
                            <?php
                            $embed_services = array();
                            foreach ( cm_get_embed_domains() as $info ) {
                                if ( $info['category'] !== 'functional' && ! in_array( $info['service'], $embed_services, true ) ) {
                                    $embed_services[] = $info['service'];
                                }
                            }
                            // Huidige geblokkeerde diensten: leeg = alles blokkeren (standaard)
                            $blocked_raw = cm_get('embed_blocked_services');
                            $blocked_list = $blocked_raw ? array_map('trim', explode(',', $blocked_raw)) : array();
                            $all_blocked = empty($blocked_raw); // Leeg = alles blokkeren
                            ?>
                            <div style="display:flex;flex-wrap:wrap;gap:8px">
                                <?php foreach ( $embed_services as $svc ) :
                                    $slug = sanitize_title($svc);
                                    $checked = $all_blocked || in_array($svc, $blocked_list, true);
                                ?>
                                <label style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:<?php echo $checked ? '#f0faf4' : '#fafafa'; ?>;border:1px solid <?php echo $checked ? '#b7dfca' : '#dcdcde'; ?>;border-radius:4px;cursor:pointer;min-width:140px;transition:all .15s">
                                    <input type="checkbox" class="cm-embed-service-cb" value="<?php echo esc_attr($svc); ?>" <?php checked($checked); ?> style="margin:0">
                                    <span style="font-size:13px;font-weight:500"><?php echo esc_html($svc); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="embed_blocked_services" id="embed_blocked_services" value="<?php echo esc_attr($blocked_raw); ?>">
                            <p style="margin:10px 0 0;color:#787c82;font-size:12px">reCAPTCHA is bewust uitgezonderd (categorie functioneel) zodat formulieren blijven werken.</p>
                        </div>
                    </div>

                    <div class="cm-group">
                        <h3 class="cm-group-title">Placeholder teksten</h3>
                        <table class="form-table cm-form-table"><tbody>
                        <tr>
                            <th><label for="txt_embed_title">Titel</label></th>
                            <td><input type="text" name="txt_embed_title" id="txt_embed_title" value="<?php echo esc_attr( cm_get('txt_embed_title') ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="txt_embed_body">Tekst</label></th>
                            <td>
                                <textarea name="txt_embed_body" id="txt_embed_body" class="large-text cm-textarea" rows="3"><?php echo esc_textarea( cm_get('txt_embed_body') ); ?></textarea>
                                <p class="description">Gebruik <code>{service}</code> als placeholder voor de dienstnaam (bijv. YouTube).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="txt_embed_btn">Knoptekst</label></th>
                            <td><input type="text" name="txt_embed_btn" id="txt_embed_btn" value="<?php echo esc_attr( cm_get('txt_embed_btn') ); ?>" class="regular-text"></td>
                        </tr>
                        </tbody></table>
                    </div>

                </div><!-- /pane-embeds -->

                <div class="cm-save-footer">
                    <button type="button" class="button button-primary cm-save-btn">Instellingen opslaan</button>
                    <span class="cm-saved-msg">&#10003; Opgeslagen</span>
                </div>

            </div><!-- /cm-settings-col -->

            <!-- ======== PREVIEW ======== -->
            <div class="cm-preview-col">
                <div class="cm-preview-label">Live preview</div>

                <p class="cm-prev-sublabel">Banner</p>
                <div class="cm-prev-viewport" id="cm-prev-banner-vp">
                    <div class="cm-prev-scale-wrap" id="cm-prev-banner-wrap">
                        <!-- Fake site achtergrond -->
                        <div class="cm-prev-fake-site"></div>
                        <!-- Overlay — bovenop de site, onder de banner -->
                        <div class="cm-prev-fake-overlay" id="prev-overlay"></div>
                        <!-- Banner — echte frontend structuur -->
                        <div class="cm-prev-banner-anchor">
                            <div class="cm-box" id="prev-box">
                                <h2 class="cm-title" id="prev-title"></h2>
                                <div class="cm-text" id="prev-text"></div>
                                <div class="cm-footer">
                                    <div class="cm-footer-left">
                                        <button type="button" class="cm-btn cm-btn-ghost" id="prev-btn-prefs"></button>
                                    </div>
                                    <div class="cm-footer-right">
                                        <button type="button" class="cm-btn cm-btn-reject" id="prev-btn-reject"></button>
                                        <button type="button" class="cm-btn cm-btn-accept" id="prev-btn-accept"></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="cm-prev-sublabel" style="margin-top:20px;">Voorkeuren venster</p>
                <div class="cm-prev-viewport cm-prev-viewport--prefs" id="cm-prev-prefs-vp">
                    <div class="cm-prev-scale-wrap" id="cm-prev-prefs-wrap">
                        <div class="cm-prefs-box" id="prev-prefs-box">
                            <div class="cm-prefs-header" id="prev-prefs-header">
                                <button type="button" class="cm-prefs-close" id="prev-close-btn" aria-label="Sluiten">&#x2715;</button>
                                <h2 class="cm-prefs-title" id="prev-prefs-title"></h2>
                                <p class="cm-prefs-text" id="prev-prefs-text"></p>
                                <button type="button" class="cm-allow-all" id="prev-allowall"></button>
                            </div>
                            <div class="cm-prefs-body">
                                <div class="cm-section-label" id="prev-section-label"></div>
                                <div class="cm-categories">
                                    <!-- Functioneel -->
                                    <div class="cm-category" id="prev-cat-functional">
                                        <div class="cm-cat-header cm-prev-cat-toggle" data-prev-cat="prev-cat-functional">
                                            <span class="cm-expand-icon">+</span>
                                            <div class="cm-cat-info">
                                                <div class="cm-cat-name" id="prev-cat1-name"></div>
                                                <div class="cm-cat-desc" id="prev-cat1-short"></div>
                                            </div>
                                            <span class="cm-always-on" id="prev-always-badge"></span>
                                        </div>
                                        <div class="cm-cat-detail">
                                            <p id="prev-cat1-long"></p>
                                            <div class="cm-cookie-list">
                                                <div class="cm-cookie-item">
                                                    <div class="cm-cookie-name">cm_consent</div>
                                                    <div class="cm-cookie-meta">
                                                        <div class="cm-cookie-meta-row"><span id="prev-cookie-purpose"></span></div>
                                                        <div class="cm-cookie-meta-row"><span id="prev-cookie-duration"></span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Analytisch -->
                                    <div class="cm-category" id="prev-cat-analytics">
                                        <div class="cm-cat-header cm-prev-cat-toggle" data-prev-cat="prev-cat-analytics">
                                            <span class="cm-expand-icon">+</span>
                                            <div class="cm-cat-info">
                                                <div class="cm-cat-name" id="prev-cat2-name"></div>
                                                <div class="cm-cat-desc" id="prev-cat2-short"></div>
                                            </div>
                                            <label class="cm-toggle" onclick="event.stopPropagation()">
                                                <input type="checkbox" checked>
                                                <span class="cm-toggle-track" id="prev-tog2"></span>
                                            </label>
                                        </div>
                                        <div class="cm-cat-detail">
                                            <p id="prev-cat2-long"></p>
                                        </div>
                                    </div>
                                    <!-- Marketing -->
                                    <div class="cm-category" id="prev-cat-marketing">
                                        <div class="cm-cat-header cm-prev-cat-toggle" data-prev-cat="prev-cat-marketing">
                                            <span class="cm-expand-icon">+</span>
                                            <div class="cm-cat-info">
                                                <div class="cm-cat-name" id="prev-cat3-name"></div>
                                                <div class="cm-cat-desc" id="prev-cat3-short"></div>
                                            </div>
                                            <label class="cm-toggle" onclick="event.stopPropagation()">
                                                <input type="checkbox">
                                                <span class="cm-toggle-track" id="prev-tog3"></span>
                                            </label>
                                        </div>
                                        <div class="cm-cat-detail">
                                            <p id="prev-cat3-long"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="cm-prefs-footer">
                                <button type="button" class="cm-btn cm-btn-outline" id="prev-btn-rejectall"></button>
                                <button type="button" class="cm-btn cm-btn-accept" id="prev-btn-save"></button>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /cm-preview-col -->

        </div><!-- /cm-layout -->
    </div>
    <?php
}

/* ---- Helper: kleurpicker (altijd HEX, geen alpha) ---- */
function cm_color_field( $name, $settings ) {
    $val = isset($settings[$name]) && $settings[$name] !== '' ? esc_attr($settings[$name]) : '#000000';
    echo '<div class="cm-color-row">';
    echo '<input type="color" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="cm-color-picker" value="' . $val . '">';
    echo '<input type="text" class="cm-hex-input" data-for="' . esc_attr($name) . '" value="' . $val . '" maxlength="7" placeholder="#000000">';
    echo '</div>';
}

/* ---- Helper: optionele kleurpicker (mag leeg = geen rand) ---- */
function cm_color_field_optional( $name, $settings ) {
    $val = isset($settings[$name]) ? esc_attr($settings[$name]) : '';
    $has = $val !== '';
    echo '<div class="cm-color-row">';
    echo '<input type="color" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="cm-color-picker" value="' . ($has ? $val : '#cccccc') . '" ' . ($has ? '' : 'disabled') . '>';
    echo '<input type="text" class="cm-hex-input' . ($has ? '' : ' cm-hex-disabled') . '" data-for="' . esc_attr($name) . '" value="' . $val . '" maxlength="7" placeholder="Geen rand">';
    echo '<label class="cm-optional-toggle"><input type="checkbox" class="cm-enable-color" data-target="' . esc_attr($name) . '" ' . ($has ? 'checked' : '') . '> Inschakelen</label>';
    echo '</div>';
}

/* ---- Helper: radius (slider + cijferinvoer) ---- */
function cm_radius_field( $name, $settings, $min, $max ) {
    $val = isset($settings[$name]) ? intval($settings[$name]) : $min;
    echo '<div class="cm-range-row">';
    echo '<input type="range" class="cm-range" data-for-number="' . esc_attr($name) . '" min="' . $min . '" max="' . $max . '" value="' . $val . '">';
    echo '<input type="number" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="cm-number-input" min="' . $min . '" max="' . $max . '" value="' . $val . '">px';
    echo '</div>';
}

/* ---- Helper: range slider ---- */
function cm_range_field( $name, $settings, $min, $max, $unit ) {
    $val = isset($settings[$name]) ? intval($settings[$name]) : $min;
    echo '<div class="cm-range-row">';
    echo '<input type="range" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="cm-range" min="' . $min . '" max="' . $max . '" value="' . $val . '" data-unit="' . esc_attr($unit) . '">';
    echo '<span class="cm-range-val" id="val_' . esc_attr($name) . '">' . $val . $unit . '</span>';
    echo '</div>';
}


/* ================================================================
   HELPER — gedeelde paginakop
================================================================ */
function cm_page_header( $title ) {
    ?>
    <div class="cm-page-header">
        <div>
            <h1 class="wp-heading-inline">Cookiebaas &mdash; <?php echo esc_html( $title ); ?></h1>
            <span class="cm-version-tag">v<?php echo esc_html(CM_VERSION); ?> &mdash; door <a href="https://www.ruudvdheijden.nl/" target="_blank">Ruud van der Heijden</a></span>
        </div>
    </div>
    <?php cm_saved_toast(); ?>
    <?php
}

function cm_saved_toast() {
    // Eén keer per pagina renderen
    if ( ! empty( $GLOBALS['cm_toast_rendered'] ) ) return;
    $GLOBALS['cm_toast_rendered'] = true;
    ?>
    <div id="cm-notice-global" style="
        display:none;
        position:fixed;
        top:32px;
        left:50%;
        transform:translateX(-50%);
        z-index:99999;
        background:#00a32a;
        color:#fff;
        padding:10px 24px;
        border-radius:4px;
        font-size:13px;
        font-weight:500;
        box-shadow:0 2px 8px rgba(0,0,0,.18);
        pointer-events:none;
        white-space:nowrap;
    ">&#10003; Instellingen opgeslagen.</div>
    <?php
}

/* ================================================================
   PAGINA — PRIVACYVERKLARING
================================================================ */
function cm_render_privacy_standalone_page() {
    $pv  = get_option( 'cm_privacy', cm_default_privacy() );
    $pvg = function( $key ) use ( $pv ) {
        $d = cm_default_privacy();
        return isset( $pv[$key] ) ? $pv[$key] : ( isset($d[$key]) ? $d[$key] : '' );
    };
    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Privacyverklaring'); ?>

        <div id="cm-notice" class="notice notice-success is-dismissible" style="display:none;margin-top:12px">
            <p>Privacyverklaring opgeslagen.</p>
        </div>

        <div class="cm-layout">
            <div class="cm-settings-col">

                <div class="cm-group">
                    <div class="cm-group-title" style="display:flex;align-items:center;justify-content:space-between">
                        <span>Shortcode</span>
                    </div>
                    <div style="padding:14px 20px">
                        <p style="margin:0 0 6px">Plaats de shortcode op uw privacypagina (<a href="/privacyverklaring">/privacyverklaring</a>):</p>
                        <code style="font-size:15px;background:#f0f0f1;padding:6px 12px;border-radius:4px;display:inline-block">[cookiebaas_privacy]</code>
                        <p class="description" style="margin-top:8px">Velden die leeg zijn worden niet getoond in de verklaring.</p>
                    </div>
                </div>

                <!-- Bedrijfsgegevens -->
                <div class="cm-group">
                    <h3 class="cm-group-title">Bedrijfsgegevens</h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr><th><label>Bedrijfsnaam</label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_bedrijfsnaam" value="<?php echo esc_attr($pvg('pv_bedrijfsnaam')); ?>"></td></tr>
                    <tr><th><label>Straat + huisnummer</label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_straat" value="<?php echo esc_attr($pvg('pv_straat')); ?>"></td></tr>
                    <tr><th><label>Postcode + plaats + land</label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_postcode_plaats" value="<?php echo esc_attr($pvg('pv_postcode_plaats')); ?>" placeholder="1234 AB Amsterdam, Nederland"></td></tr>
                    <tr><th><label>KVK-nummer <span style="font-weight:400">(optioneel)</span></label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_kvk" value="<?php echo esc_attr($pvg('pv_kvk')); ?>"></td></tr>
                    <tr><th><label>E-mailadres</label></th><td><input type="email" class="regular-text cm-pv-field" name="pv_email" value="<?php echo esc_attr($pvg('pv_email')); ?>"></td></tr>
                    <tr><th><label>Versienummer</label></th><td><input type="text" style="width:80px" class="cm-pv-field" name="pv_versie" value="<?php echo esc_attr($pvg('pv_versie')); ?>" placeholder="1.0"></td></tr>
                    <tr><th><label>Datum bijgewerkt</label></th><td><input type="text" style="width:160px" class="cm-pv-field" name="pv_datum" value="<?php echo esc_attr($pvg('pv_datum')); ?>" placeholder="1 januari 2025"></td></tr>
                    </tbody></table>
                </div>

                <!-- Functionaris Gegevensbescherming (DPO) -->
                <div class="cm-group">
                    <h3 class="cm-group-title">Functionaris Gegevensbescherming <span style="font-size:11px;font-weight:400;color:#787c82">(DPO) — optioneel</span></h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr>
                        <th><label>DPO-sectie tonen</label></th>
                        <td>
                            <label>
                                <input type="checkbox" class="cm-pv-cb" name="pv_dpo_enabled" value="1" <?php checked($pvg('pv_dpo_enabled'),'1'); ?> id="cm-dpo-toggle">
                                Ja, wij hebben een aangestelde Functionaris Gegevensbescherming
                            </label>
                            <p class="description">Verplicht voor overheidsorganisaties en organisaties die op grote schaal bijzondere persoonsgegevens verwerken (AVG art. 37). Voor de meeste MKB-organisaties optioneel.</p>
                        </td>
                    </tr>
                    </tbody></table>
                    <div id="cm-dpo-fields" style="<?php echo $pvg('pv_dpo_enabled') === '1' ? '' : 'display:none'; ?>">
                        <table class="form-table cm-form-table"><tbody>
                        <tr><th><label>Naam DPO</label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_dpo_naam" value="<?php echo esc_attr($pvg('pv_dpo_naam')); ?>" placeholder="Voornaam Achternaam"></td></tr>
                        <tr><th><label>E-mailadres DPO</label></th><td><input type="email" class="regular-text cm-pv-field" name="pv_dpo_email" value="<?php echo esc_attr($pvg('pv_dpo_email')); ?>" placeholder="dpo@uwbedrijf.nl"></td></tr>
                        <tr><th><label>Telefoon DPO <span style="font-weight:400">(optioneel)</span></label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_dpo_telefoon" value="<?php echo esc_attr($pvg('pv_dpo_telefoon')); ?>"></td></tr>
                        </tbody></table>
                    </div>
                    <script>
                    document.getElementById('cm-dpo-toggle').addEventListener('change', function() {
                        document.getElementById('cm-dpo-fields').style.display = this.checked ? '' : 'none';
                    });
                    </script>
                </div>

                <!-- 1. Inleiding -->
                <div class="cm-group">
                    <h3 class="cm-group-title">1. Inleiding</h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr>
                        <th><label>Naam in inleiding</label></th>
                        <td>
                            <input type="text" class="regular-text cm-pv-field" name="pv_inleiding_naam" value="<?php echo esc_attr($pvg('pv_inleiding_naam')); ?>" placeholder="Laat leeg om bedrijfsnaam te gebruiken">
                            <p class="description">Verschijnt als: <em>&ldquo;<strong>[naam]</strong> respecteert uw privacy&hellip;&rdquo;</em></p>
                        </td>
                    </tr>
                    </tbody></table>
                </div>

                <!-- 2.1 Contactformulier -->
                <div class="cm-group">
                    <h3 class="cm-group-title">2.1 Contactformulier — verzamelde velden</h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr>
                        <th>Velden</th>
                        <td>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_voornaam"   value="1" <?php checked($pvg('pv_cf_voornaam'),'1'); ?>> Voornaam</label>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_achternaam" value="1" <?php checked($pvg('pv_cf_achternaam'),'1'); ?>> Achternaam</label>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_email"      value="1" <?php checked($pvg('pv_cf_email'),'1'); ?>> E-mailadres</label>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_website"    value="1" <?php checked($pvg('pv_cf_website'),'1'); ?>> Website (optioneel)</label>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_telefoon"   value="1" <?php checked($pvg('pv_cf_telefoon'),'1'); ?>> Telefoonnummer (optioneel)</label>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_bericht"    value="1" <?php checked($pvg('pv_cf_bericht'),'1'); ?>> Uw bericht</label>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_bedrijf"    value="1" <?php checked($pvg('pv_cf_bedrijf'),'1'); ?>> Bedrijfsnaam (optioneel)</label>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_adres"      value="1" <?php checked($pvg('pv_cf_adres'),'1'); ?>> Adresgegevens (optioneel)</label>
                            <label style="display:block;margin-bottom:6px"><input type="checkbox" class="cm-pv-cb" name="pv_cf_privacy"    value="1" <?php checked($pvg('pv_cf_privacy'),'1'); ?>> Acceptatie privacyverklaring</label>
                            <p class="description" style="margin-top:10px">Vink alles uit om sectie 2.1 volledig te verbergen.</p>
                        </td></tr>
                        <tr>
                            <th><label>Eigen velden</label></th>
                            <td>
                                <textarea class="large-text cm-pv-field" name="pv_cf_extra" rows="3" placeholder="Één veld per regel, bijv:&#10;Factuurnummer&#10;Projectnaam"><?php echo esc_textarea($pvg('pv_cf_extra')); ?></textarea>
                                <p class="description">Optioneel: voeg eigen veldnamen toe, één per regel. Deze worden vermeld in de privacyverklaring.</p>
                        </td>
                    </tr>
                    </tbody></table>
                </div>

                <!-- 3. Doeleinden -->
                <div class="cm-group">
                    <h3 class="cm-group-title">3. Doeleinden en grondslagen</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:13px" id="cm-pv-doeleinden-tbl">
                        <thead><tr style="background:#f6f7f7">
                            <th style="padding:8px 12px;text-align:left;border:1px solid #dcdcde;width:40%">Doel</th>
                            <th style="padding:8px 12px;text-align:left;border:1px solid #dcdcde;width:30%">Grondslag</th>
                            <th style="padding:8px 12px;text-align:left;border:1px solid #dcdcde;width:22%">Bewaartermijn</th>
                            <th style="border:1px solid #dcdcde;width:8%"></th>
                        </tr></thead>
                        <tbody id="cm-pv-doeleinden-body">
                        <?php $doeleinden = json_decode($pvg('pv_doeleinden'), true) ?: array(); foreach ($doeleinden as $rij) : ?>
                        <tr>
                            <td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Doel" value="<?php echo esc_attr($rij['doel']??''); ?>"></td>
                            <td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Grondslag" value="<?php echo esc_attr($rij['grondslag']??''); ?>"></td>
                            <td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Termijn" value="<?php echo esc_attr($rij['termijn']??''); ?>"></td>
                            <td style="border:1px solid #dcdcde;padding:4px 6px;text-align:center"><button type="button" class="button button-small cm-pv-del-row" style="color:#b32d2e">&#x2715;</button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="cm-group-add-btn"><button type="button" class="button button-secondary" id="cm-pv-add-doel">+ Rij toevoegen</button></div>
                </div>

                <!-- 4. Cookies -->
                <div class="cm-group">
                    <h3 class="cm-group-title">4. Cookies</h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr>
                        <th>Google Tag Manager</th>
                        <td><label><input type="checkbox" class="cm-pv-cb" name="pv_gtm" value="1" <?php checked($pvg('pv_gtm'),'1'); ?>> Vermeld dat de website Google Tag Manager gebruikt</label></td>
                    </tr>
                    <tr>
                        <th>Opt-out links</th>
                        <td>
                            <table style="width:100%;border-collapse:collapse;font-size:13px">
                                <thead><tr style="background:#f6f7f7">
                                    <th style="padding:8px 12px;text-align:left;border:1px solid #dcdcde;width:28%">Naam</th>
                                    <th style="padding:8px 12px;text-align:left;border:1px solid #dcdcde">URL</th>
                                    <th style="border:1px solid #dcdcde;width:8%"></th>
                                </tr></thead>
                                <tbody id="cm-pv-optout-body">
                                <?php $optout = json_decode($pvg('pv_optout_links'), true) ?: array(); foreach ($optout as $link) : ?>
                                <tr>
                                    <td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Naam" value="<?php echo esc_attr($link['naam']??''); ?>"></td>
                                    <td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="https://..." value="<?php echo esc_attr($link['url']??''); ?>"></td>
                                    <td style="border:1px solid #dcdcde;padding:4px 6px;text-align:center"><button type="button" class="button button-small cm-pv-del-row" style="color:#b32d2e">&#x2715;</button></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="padding:8px 0 0"><button type="button" class="button button-secondary cm-pv-add-optout">+ Link toevoegen</button></div>
                        </td>
                    </tr>
                    </tbody></table>
                </div>

                <!-- 5. Ontvangers -->
                <div class="cm-group">
                    <h3 class="cm-group-title">5. Ontvangers van persoonsgegevens</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead><tr style="background:#f6f7f7">
                            <th style="padding:8px 12px;text-align:left;border:1px solid #dcdcde;width:28%">Partij</th>
                            <th style="padding:8px 12px;text-align:left;border:1px solid #dcdcde;width:44%">Doel</th>
                            <th style="padding:8px 12px;text-align:left;border:1px solid #dcdcde;width:20%">Locatie</th>
                            <th style="border:1px solid #dcdcde;width:8%"></th>
                        </tr></thead>
                        <tbody id="cm-pv-ontvangers-body">
                        <?php $ontvangers = json_decode($pvg('pv_ontvangers'), true) ?: array(); foreach ($ontvangers as $rij) : ?>
                        <tr>
                            <td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Partij" value="<?php echo esc_attr($rij['partij']??''); ?>"></td>
                            <td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Doel" value="<?php echo esc_attr($rij['doel']??''); ?>"></td>
                            <td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="NL / VS* / VK" value="<?php echo esc_attr($rij['locatie']??''); ?>"></td>
                            <td style="border:1px solid #dcdcde;padding:4px 6px;text-align:center"><button type="button" class="button button-small cm-pv-del-row" style="color:#b32d2e">&#x2715;</button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="cm-group-add-btn"><button type="button" class="button button-secondary" id="cm-pv-add-ontvanger">+ Partij toevoegen</button></div>
                </div>

                <!-- 6. Internationale doorgifte -->
                <div class="cm-group">
                    <h3 class="cm-group-title">6. Internationale doorgifte <span style="font-weight:400;font-size:12px;color:#787c82">(leeg = standaardtekst)</span></h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr><th><label>Aangepaste tekst</label></th><td><textarea class="large-text cm-pv-field" name="pv_doorgifte" rows="4"><?php echo esc_textarea($pvg('pv_doorgifte')); ?></textarea></td></tr>
                    </tbody></table>
                </div>

                <!-- 7. Bewaartermijnen -->
                <div class="cm-group">
                    <h3 class="cm-group-title">7. Bewaartermijnen</h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr><th><label>Contactformulier</label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_bewaar_contact" value="<?php echo esc_attr($pvg('pv_bewaar_contact')); ?>" placeholder="3 jaar na laatste contact"></td></tr>
                    <tr><th><label>Serverlogbestanden</label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_bewaar_logs" value="<?php echo esc_attr($pvg('pv_bewaar_logs')); ?>" placeholder="maximaal 6 maanden"></td></tr>
                    </tbody></table>
                </div>

                <!-- 8. Rechten -->
                <div class="cm-group">
                    <h3 class="cm-group-title">8. Uw rechten</h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr><th><label>Contact e-mail</label></th><td><input type="email" class="regular-text cm-pv-field" name="pv_rechten_email" value="<?php echo esc_attr($pvg('pv_rechten_email')); ?>" placeholder="Laat leeg om algemeen e-mailadres te gebruiken"></td></tr>
                    <tr><th><label>Reactietermijn</label></th><td><input type="text" class="regular-text cm-pv-field" name="pv_rechten_termijn" value="<?php echo esc_attr($pvg('pv_rechten_termijn')); ?>" placeholder="één maand"></td></tr>
                    </tbody></table>
                </div>

                <!-- 10. Klachten -->
                <div class="cm-group">
                    <h3 class="cm-group-title">10. Klachten</h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr><th>Autoriteit Persoonsgegevens</th><td><label><input type="checkbox" class="cm-pv-cb" name="pv_ap_tonen" value="1" <?php checked($pvg('pv_ap_tonen'),'1'); ?>> Toon adres van de Autoriteit Persoonsgegevens</label></td></tr>
                    </tbody></table>
                </div>

                <!-- 11. Wijzigingen -->
                <div class="cm-group">
                    <h3 class="cm-group-title">11. Wijzigingen <span style="font-weight:400;font-size:12px;color:#787c82">(optioneel)</span></h3>
                    <table class="form-table cm-form-table"><tbody>
                    <tr><th><label>Aanvullende tekst</label></th><td><textarea class="large-text cm-pv-field" name="pv_wijzigingen_extra" rows="3"><?php echo esc_textarea($pvg('pv_wijzigingen_extra')); ?></textarea></td></tr>
                    </tbody></table>
                </div>

                <div class="cm-save-footer">
                    <button type="button" class="button button-primary" id="cm-pv-save-btn">Privacyverklaring opslaan</button>
                    <span class="cm-saved-msg" id="cm-pv-notice">&#10003; Opgeslagen</span>
                </div>

            </div><!-- /cm-settings-col (geen preview op deze pagina) -->
        </div><!-- /cm-layout -->
    </div>
    <?php
}

/* ================================================================
   PAGINA — CONSENT LOG
================================================================ */
function cm_render_log_page() {
    $ret_months = (int) cm_get('log_retention_months');
    $next_cron  = wp_next_scheduled('cm_log_retention_cron');
    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Consent log'); ?>
        <div style="max-width:1100px;margin-top:20px">

            <?php if ( $ret_months > 0 ) : ?>
            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px;color:#2c3338">
                <strong>Logs worden automatisch verwijderd na <?php echo esc_html($ret_months); ?> maanden.</strong> Dagelijkse controle om 12:00u.
                <a href="<?php echo esc_url( admin_url('admin.php?page=cookiemelding') ); ?>#tab=gedrag" style="margin-left:8px">Instelling aanpassen</a>
            </div>
            <?php endif; ?>

            <!-- Stat-kaarten met iconen -->
            <div id="cm-log-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
                <div style="flex:1;min-width:140px;background:#fff;border:1px solid #dcdcde;padding:16px;display:flex;align-items:center;gap:14px">
                    <div style="width:44px;height:44px;border-radius:50%;background:#d1f0da;display:flex;align-items:center;justify-content:center;font-size:18px;color:#00561b;font-weight:700;flex-shrink:0">&#10003;</div>
                    <div>
                        <div id="stat-accept" style="font-size:24px;font-weight:700;color:#00a32a;line-height:1">&mdash;</div>
                        <div style="font-size:11px;color:#787c82;margin-top:2px">Akkoord</div>
                    </div>
                </div>
                <div style="flex:1;min-width:140px;background:#fff;border:1px solid #dcdcde;padding:16px;display:flex;align-items:center;gap:14px">
                    <div style="width:44px;height:44px;border-radius:50%;background:#fcebeb;display:flex;align-items:center;justify-content:center;font-size:18px;color:#d63638;font-weight:700;flex-shrink:0">&#10005;</div>
                    <div>
                        <div id="stat-reject" style="font-size:24px;font-weight:700;color:#d63638;line-height:1">&mdash;</div>
                        <div style="font-size:11px;color:#787c82;margin-top:2px">Geweigerd</div>
                    </div>
                </div>
                <div style="flex:1;min-width:140px;background:#fff;border:1px solid #dcdcde;padding:16px;display:flex;align-items:center;gap:14px">
                    <div style="width:44px;height:44px;border-radius:50%;background:#dce9f8;display:flex;align-items:center;justify-content:center;font-size:16px;color:#0a4480;font-weight:700;flex-shrink:0">&#9881;</div>
                    <div>
                        <div id="stat-custom" style="font-size:24px;font-weight:700;color:#2271b1;line-height:1">&mdash;</div>
                        <div style="font-size:11px;color:#787c82;margin-top:2px">Aangepast</div>
                    </div>
                </div>
            </div>

            <!-- Log tabel -->
            <div class="cm-group" style="margin-top:0">
                <div style="padding:12px 16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;border-bottom:1px solid #f0f0f1">
                    <!-- Zoekbalk -->
                    <div style="position:relative;flex:1 1 220px;max-width:300px">
                        <input type="text" id="cm-log-search" placeholder="Zoek op Consent ID&hellip;"
                            style="width:100%;padding:6px 30px 6px 10px;border:1px solid #8c8f94;border-radius:3px;font-size:12px;box-sizing:border-box;background:#fff;color:#2c3338">
                        <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);color:#787c82;pointer-events:none;font-size:13px">&#x1F50D;</span>
                    </div>
                    <!-- Filter pillen -->
                    <div style="display:flex;gap:4px" id="cm-log-filters">
                        <button type="button" class="cm-log-filter active" data-filter="all" style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:500;cursor:pointer;border:1px solid #2271b1;background:#2271b1;color:#fff">Alles</button>
                        <button type="button" class="cm-log-filter" data-filter="accept-all" style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:500;cursor:pointer;border:1px solid #00a32a;background:#fff;color:#00a32a">Akkoord</button>
                        <button type="button" class="cm-log-filter" data-filter="reject-all" style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:500;cursor:pointer;border:1px solid #d63638;background:#fff;color:#d63638">Geweigerd</button>
                        <button type="button" class="cm-log-filter" data-filter="custom" style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:500;cursor:pointer;border:1px solid #2271b1;background:#fff;color:#2271b1">Aangepast</button>
                    </div>
                    <span style="flex:1"></span>
                    <a id="cm-log-export-btn" href="#" class="button button-small" style="font-size:12px">&#8595; CSV</a>
                    <button type="button" class="button button-small" id="cm-log-clear-btn" style="color:#d63638;border-color:#d63638;font-size:12px">Leegmaken</button>
                </div>
                <div id="cm-log-loading" style="padding:20px;text-align:center;color:#787c82;display:none">Laden...</div>
                <div class="cm-table-scroll">
                <table class="widefat" id="cm-log-table" style="border:none;border-collapse:collapse">
                    <thead><tr style="background:#f6f7f7">
                        <th style="width:4px;padding:0"></th>
                        <th style="width:210px;padding:8px 10px;font-size:12px;font-weight:600;color:#1d2327">Consent ID</th>
                        <th style="width:110px;padding:8px 10px;font-size:12px;font-weight:600;color:#1d2327">Status</th>
                        <th style="padding:8px 10px;font-size:12px;font-weight:600;color:#1d2327">Details</th>
                        <th style="width:160px;padding:8px 10px;font-size:12px;font-weight:600;color:#1d2327">Datum</th>
                        <th style="width:110px;padding:8px 10px;font-size:12px;font-weight:600;color:#1d2327">Acties</th>
                    </tr></thead>
                    <tbody id="cm-log-rows">
                        <tr><td colspan="6" style="color:#787c82;padding:20px;text-align:center;font-size:13px">Log wordt geladen&hellip;</td></tr>
                    </tbody>
                </table>
                </div>
                <div id="cm-log-pagination" style="padding:10px 16px;display:flex;gap:8px;align-items:center;font-size:12px;border-top:1px solid #f0f0f1"></div>
            </div>

            <!-- Popup: Bewijs van consent -->
            <div id="cm-proof-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center">
                <div style="background:#fff;border-radius:4px;padding:28px 32px;max-width:520px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18);position:relative">
                    <h2 style="margin:0 0 20px;font-size:18px;font-weight:600;color:#1d2327">Bewijs van consent</h2>
                    <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:24px">
                        <tr><td style="padding:6px 0;color:#787c82;width:140px;vertical-align:top"><strong>Consent ID:</strong></td><td id="cm-proof-id" style="padding:6px 0;font-family:monospace;word-break:break-all"></td></tr>
                        <tr><td style="padding:6px 0;color:#787c82;vertical-align:top"><strong>Consent datum:</strong></td><td id="cm-proof-date" style="padding:6px 0"></td></tr>
                        <tr><td style="padding:6px 0;color:#787c82;vertical-align:top"><strong>Status:</strong></td><td id="cm-proof-status" style="padding:6px 0"></td></tr>
                        <tr><td style="padding:6px 0;color:#787c82;vertical-align:top"><strong>Analytisch:</strong></td><td id="cm-proof-analytics" style="padding:6px 0"></td></tr>
                        <tr><td style="padding:6px 0;color:#787c82;vertical-align:top"><strong>Marketing:</strong></td><td id="cm-proof-marketing" style="padding:6px 0"></td></tr>
                        <tr><td style="padding:6px 0;color:#787c82;vertical-align:top"><strong>Plugin versie:</strong></td><td id="cm-proof-version" style="padding:6px 0"></td></tr>
                    </table>
                    <div style="display:flex;justify-content:flex-end;gap:10px">
                        <button type="button" id="cm-proof-cancel" class="button button-secondary" style="padding:6px 20px">Annuleren</button>
                        <button type="button" id="cm-proof-pdf" class="button button-primary" style="background:#2271b1;border-color:#2271b1;padding:6px 20px">PDF genereren</button>
                    </div>
                </div>
            </div>

            <!-- Popup: CSV export datumkeuze -->
            <div id="cm-export-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center">
                <div style="background:#fff;border-radius:4px;padding:28px 32px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18)">
                    <h2 style="margin:0 0 20px;font-size:18px;font-weight:600;color:#1d2327">Exporteer als CSV</h2>
                    <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:24px">
                        <tr>
                            <td style="padding:8px 0;color:#787c82;width:160px">Datumbereik</td>
                            <td style="padding:8px 0">
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    <input type="date" id="cm-export-from" style="padding:5px 8px;border:1px solid #8c8f94;border-radius:3px;font-size:13px;background:#fff;color:#2c3338">
                                    <span style="color:#787c82">&mdash;</span>
                                    <input type="date" id="cm-export-to" style="padding:5px 8px;border:1px solid #8c8f94;border-radius:3px;font-size:13px;background:#fff;color:#2c3338">
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div style="display:flex;justify-content:flex-end;gap:10px">
                        <button type="button" id="cm-export-cancel" class="button button-secondary" style="padding:6px 20px">Annuleren</button>
                        <button type="button" id="cm-export-confirm" class="button button-primary" style="background:#2271b1;border-color:#2271b1;padding:6px 20px">Exporteer als CSV</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php
}

/* ================================================================
   PAGINA — COMPLIANCE CHECK
================================================================ */
function cm_render_compliance_page() {
    $s   = array_merge( cm_default_settings(), (array) get_option( 'cm_settings', array() ) );
    $pv  = array_merge( cm_default_privacy(),  (array) get_option( 'cm_privacy',  array() ) );
    $cookies = cm_get_cookie_list();

    // Helper: instelling-link — gebruikt hash voor directe tab-activering
    $link = function( $page, $tab, $label ) {
        $url = admin_url( 'admin.php?page=' . $page );
        if ( $tab ) $url .= '#tab=' . $tab;
        return '<a href="' . esc_url($url) . '">' . esc_html($label) . ' →</a>';
    };

    // ── Checks definiëren ────────────────────────────────────────────────────
    // Elke check: array( 'title', 'desc', 'status' => 'ok'|'warn'|'fail', 'fix' => html-link )
    $checks = array();

    // 1. Weigeren-knop visuele prominentie
    $reject_bg = trim( $s['color_reject_bg'] ?? '' );
    $accept_bg = trim( $s['color_accept_bg'] ?? '#111111' );

    // Bereken relatieve helderheid via hex → luminance vergelijking
    $luminance = function( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen($hex) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if ( strlen($hex) !== 6 ) return 0.5; // onbekend → neutraal
        $r = hexdec(substr($hex,0,2)) / 255;
        $g = hexdec(substr($hex,2,2)) / 255;
        $b = hexdec(substr($hex,4,2)) / 255;
        return 0.299 * $r + 0.587 * $g + 0.114 * $b; // perceived brightness
    };

    $reject_lum = $luminance( $reject_bg ?: '#f5f2ee' );
    $accept_lum = $luminance( $accept_bg ?: '#111111' );
    // Weigeren is prominent genoeg als helderheid niet meer dan 0.4 verschilt van akkoord
    // OF als de weigeren-knop zelf donker genoeg is (< 0.5)
    $prominence_ok = ( $reject_lum < 0.5 ) || ( abs($reject_lum - $accept_lum) < 0.45 );

    $checks[] = array(
        'cat'    => 'Visuele prominentie',
        'title'  => 'Weigeren-knop even prominent als akkoord',
        'desc'   => 'EDPB 2023: beide knoppen moeten dezelfde visuele grootte en stijl hebben. Een lichtgrijze weigeren-knop naast een donkere akkoord-knop voldoet hier niet aan.',
        'status' => $prominence_ok ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'kleuren', 'Stel achtergrond weigeren in'),
        'detail' => ! $prominence_ok ? 'De weigeren-knop (' . ($reject_bg ?: '#f5f2ee') . ') is aanzienlijk lichter dan de akkoord-knop (' . $accept_bg . '). Geef de weigeren-knop een donkerdere achtergrond voor gelijke prominentie.' : '',
    );

    // 2. Geen pre-aangevinkte analytics
    $checks[] = array(
        'cat'    => 'Visuele prominentie',
        'title'  => 'Analytics standaard uitgeschakeld',
        'desc'   => 'AVG art. 7: toestemming moet actief gegeven worden, niet vooraf aangevinkt.',
        'status' => empty( $s['analytics_default'] ) ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding', 'gedrag', 'Gedrag instellingen'),
    );

    // 3. Privacyverklaring link in bannertekst
    $banner_body = $s['txt_banner_body'] ?? '';
    $has_pv_link = strpos( $banner_body, 'href' ) !== false || strpos( $banner_body, 'privac' ) !== false;
    $checks[] = array(
        'cat'    => 'Informatieplicht',
        'title'  => 'Link naar privacyverklaring in bannertekst',
        'desc'   => 'AVG art. 13: bezoekers moeten eenvoudig toegang hebben tot de privacyverklaring.',
        'status' => $has_pv_link ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'teksten', 'Bannertekst aanpassen'),
    );

    // 4. Alle cookies hebben doel en looptijd
    $managed = array_filter( $cookies, function($c) { return empty($c['builtin']); } );
    $incomplete = array_filter( $managed, function($c) {
        return empty($c['purpose']) || empty($c['duration']);
    });
    $checks[] = array(
        'cat'    => 'Informatieplicht',
        'title'  => 'Alle cookies hebben doel en looptijd',
        'desc'   => 'AVG art. 13: per cookie moet het doel en de bewaartermijn vermeld zijn.',
        'status' => count($managed) === 0 ? 'warn' : ( count($incomplete) === 0 ? 'ok' : 'warn' ),
        'fix'    => $link('cookiemelding-cookies', '', 'Cookielijst aanvullen'),
        'detail' => count($managed) === 0 ? 'Geen cookies in de lijst — voeg cookies toe via de Cookielijst.' :
                    ( count($incomplete) > 0 ? count($incomplete) . ' cookie(s) missen doel of looptijd.' : '' ),
    );

    // 5. Categorieën hebben een beschrijving
    $cats_ok = ! empty( $s['txt_cat1_long'] ) && ! empty( $s['txt_cat2_long'] ) && ! empty( $s['txt_cat3_long'] );
    $checks[] = array(
        'cat'    => 'Informatieplicht',
        'title'  => 'Alle cookiecategorieën hebben een beschrijving',
        'desc'   => 'Bezoekers moeten begrijpen waarvoor elke categorie gebruikt wordt.',
        'status' => $cats_ok ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'teksten', 'Teksten aanpassen'),
    );

    // 6. Script blocking actief
    $block_a  = trim( $s['block_analytics_patterns'] ?? '' );
    $block_m  = trim( $s['block_marketing_patterns'] ?? '' );
    $ga4_id   = trim( $s['ga4_measurement_id'] ?? '' );
    $gtm_id   = trim( $s['gtm_container_id'] ?? '' );
    $ua_id    = trim( $s['ua_tracking_id'] ?? '' );
    // GA4/GTM/UA self-loader via plugin telt ook als blocking (Consent Mode v2 default=denied)
    $has_self_loader  = ( $ga4_id && preg_match('/^G-[A-Z0-9]+$/i', $ga4_id) )
                     || ( $gtm_id && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id) )
                     || ( $ua_id  && preg_match('/^UA-[0-9]+-[0-9]+$/i', $ua_id) );
    $blocking_ok = $block_a || $block_m || $has_self_loader;
    $checks[] = array(
        'cat'    => 'Technische vereisten',
        'title'  => 'Scripts worden geblokkeerd vóór consent',
        'desc'   => 'Scripts mogen pas laden nadat de bezoeker toestemming heeft gegeven.',
        'status' => $blocking_ok ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'google', 'Google integratie instellen'),
        'detail' => $blocking_ok
            ? ( $has_self_loader && ! $block_a && ! $block_m
                ? 'GA4/GTM wordt geblokkeerd via de ingebouwde Consent Mode v2 self-loader (default: denied).'
                : '' )
            : 'Vul een GA4/GTM ID in, of stel Script blocking patronen in bij Instellingen → Gedrag.',
    );

    // 7. Intrekkingsmogelijkheid (zweefknop)
    $checks[] = array(
        'cat'    => 'Technische vereisten',
        'title'  => 'Bezoeker kan consent altijd intrekken (zweefknop)',
        'desc'   => 'AVG art. 7 lid 3: intrekken van toestemming moet even eenvoudig zijn als geven.',
        'status' => ! empty( $s['show_float_btn'] ) ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding', 'layout', 'Zweefknop inschakelen'),
    );

    // 8. Consent verlooptijd ≤ 12 maanden
    $expiry = intval( $s['expiry_months'] ?? 12 );
    $checks[] = array(
        'cat'    => 'Technische vereisten',
        'title'  => 'Consent verlooptijd maximaal 12 maanden',
        'desc'   => 'AP-richtlijn: toestemming mag maximaal 12 maanden geldig zijn.',
        'status' => $expiry <= 12 ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'gedrag', 'Verlooptijd aanpassen'),
        'detail' => $expiry > 12 ? 'Huidige instelling: ' . $expiry . ' maanden.' : '',
    );

    // 9. Consent logging ingeschakeld
    $checks[] = array(
        'cat'    => 'Consent logging',
        'title'  => 'Consent logging ingeschakeld',
        'desc'   => 'AVG art. 5 lid 2: aantoonbaarheid vereist dat toestemming wordt geregistreerd.',
        'status' => ! empty( $s['consent_logging_enabled'] ) ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding', 'gedrag', 'Logging inschakelen'),
    );

    // 10. Log retentie ingesteld
    $retention = intval( $s['log_retention_months'] ?? 0 );
    $checks[] = array(
        'cat'    => 'Consent logging',
        'title'  => 'Automatische log-retentie ingesteld',
        'desc'   => 'AVG art. 5 lid 1e: gegevens mogen niet langer bewaard worden dan nodig (aanbevolen: 36 mnd).',
        'status' => $retention > 0 && $retention <= 36 ? 'ok' : ( $retention === 0 ? 'warn' : 'warn' ),
        'fix'    => $link('cookiemelding', 'gedrag', 'Retentie instellen'),
        'detail' => $retention === 0 ? 'Logs worden nooit automatisch verwijderd.' :
                    ( $retention > 36 ? 'Overweeg een kortere bewaarperiode dan ' . $retention . ' maanden.' : '' ),
    );

    // 11. Privacyverklaring: bedrijfsnaam ingevuld
    $checks[] = array(
        'cat'    => 'Privacyverklaring',
        'title'  => 'Bedrijfsnaam ingevuld',
        'desc'   => 'AVG art. 13: de verwerkingsverantwoordelijke moet duidelijk worden vermeld.',
        'status' => ! empty( $pv['pv_bedrijfsnaam'] ) ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding-privacy', '', 'Privacyverklaring aanvullen'),
    );

    // 12. Privacyverklaring: e-mailadres ingevuld
    $checks[] = array(
        'cat'    => 'Privacyverklaring',
        'title'  => 'Contactgegevens ingevuld',
        'desc'   => 'AVG art. 13: contactgegevens van de verwerkingsverantwoordelijke zijn verplicht.',
        'status' => ! empty( $pv['pv_email'] ) ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding-privacy', '', 'Privacyverklaring aanvullen'),
    );

    // 13. Privacyverklaring: datum bijgewerkt
    $checks[] = array(
        'cat'    => 'Privacyverklaring',
        'title'  => 'Datum bijgewerkt ingevuld',
        'desc'   => 'Bezoekers moeten kunnen zien wanneer de privacyverklaring voor het laatst is bijgewerkt.',
        'status' => ! empty( $pv['pv_datum'] ) ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding-privacy', '', 'Datum invullen'),
    );

    // ── Score berekenen ──────────────────────────────────────────────────────
    $total  = count( $checks );
    $ok     = count( array_filter( $checks, fn($c) => $c['status'] === 'ok' ) );
    $warns  = count( array_filter( $checks, fn($c) => $c['status'] === 'warn' ) );
    $fails  = count( array_filter( $checks, fn($c) => $c['status'] === 'fail' ) );
    $pct    = round( $ok / $total * 100 );

    $score_color = $fails > 0 ? '#b32d2e' : ( $warns > 2 ? '#996800' : '#00a32a' );
    $score_label = $fails > 0 ? 'Kritieke punten aanwezig' : ( $warns > 0 ? 'Bijna compliant' : 'Volledig compliant' );

    // ── Groepeer per categorie ────────────────────────────────────────────────
    $cats_grouped = array();
    foreach ( $checks as $check ) {
        $cats_grouped[ $check['cat'] ][] = $check;
    }

    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Compliance check'); ?>
        <div style="max-width:860px;margin-top:20px">

            <!-- Scorebalk -->
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <div style="flex-shrink:0;text-align:center">
                    <div style="font-size:42px;font-weight:700;line-height:1;color:<?php echo esc_attr($score_color); ?>"><?php echo esc_html($pct); ?>%</div>
                    <div style="font-size:12px;color:#787c82;margin-top:4px"><?php echo esc_html($ok . '/' . $total); ?> checks</div>
                </div>
                <div style="flex:1;min-width:200px">
                    <div style="font-size:16px;font-weight:600;color:<?php echo esc_attr($score_color); ?>;margin-bottom:6px"><?php echo esc_html($score_label); ?></div>
                    <div style="background:#f0f0f1;border-radius:4px;height:10px;overflow:hidden">
                        <div style="background:<?php echo esc_attr($score_color); ?>;height:100%;width:<?php echo esc_html($pct); ?>%;border-radius:4px;transition:width .4s"></div>
                    </div>
                    <div style="display:flex;gap:16px;margin-top:8px;font-size:12px">
                        <span style="color:#00a32a">✓ <?php echo esc_html($ok); ?> geslaagd</span>
                        <?php if ( $warns ) : ?><span style="color:#996800">⚠ <?php echo esc_html($warns); ?> aandachtspunt<?php echo $warns > 1 ? 'en' : ''; ?></span><?php endif; ?>
                        <?php if ( $fails ) : ?><span style="color:#b32d2e">✗ <?php echo esc_html($fails); ?> kritiek</span><?php endif; ?>
                    </div>
                </div>
                <div style="flex-shrink:0">
                    <button type="button" class="button" onclick="window.location.reload()">↻ Opnieuw controleren</button>
                </div>
            </div>

            <!-- Checks per categorie -->
            <?php foreach ( $cats_grouped as $cat_name => $cat_checks ) : ?>
            <div class="cm-group" style="margin-bottom:16px">
                <h3 class="cm-group-title"><?php echo esc_html($cat_name); ?></h3>
                <table style="width:100%;border-collapse:collapse">
                    <?php foreach ( $cat_checks as $check ) :
                        $icon  = $check['status'] === 'ok'   ? '✓' : ( $check['status'] === 'fail' ? '✗' : '⚠' );
                        $color = $check['status'] === 'ok'   ? '#00a32a' : ( $check['status'] === 'fail' ? '#b32d2e' : '#996800' );
                        $bg    = $check['status'] === 'ok'   ? '#f0faf0' : ( $check['status'] === 'fail' ? '#fcf0f1' : '#fef8ee' );
                    ?>
                    <tr style="border-bottom:1px solid #f0f0f1">
                        <td style="width:36px;padding:12px 8px 12px 16px;vertical-align:top">
                            <span style="display:inline-flex;width:24px;height:24px;border-radius:50%;background:<?php echo esc_attr($bg); ?>;color:<?php echo esc_attr($color); ?>;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0"><?php echo esc_html($icon); ?></span>
                        </td>
                        <td style="padding:12px 8px;vertical-align:top">
                            <div style="font-weight:600;font-size:13px;color:#1d2327;margin-bottom:2px"><?php echo esc_html($check['title']); ?></div>
                            <div style="font-size:12px;color:#646970;line-height:1.5"><?php echo esc_html($check['desc']); ?></div>
                            <?php if ( ! empty($check['detail']) ) : ?>
                            <div style="font-size:12px;color:<?php echo esc_attr($color); ?>;margin-top:4px;font-weight:500"><?php echo esc_html($check['detail']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px 16px 12px 8px;vertical-align:middle;text-align:right;white-space:nowrap;font-size:12px">
                            <?php if ( $check['status'] !== 'ok' ) echo $check['fix']; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endforeach; ?>

            <p style="font-size:12px;color:#787c82;margin-top:8px">
                Deze check is gebaseerd op de EDPB-richtlijnen, de AP-handhavingscriteria en AVG artikelen 5, 7 en 13.
                Een geslaagde check geeft geen juridische garantie — raadpleeg een juridisch adviseur voor zekerheid.
            </p>

        </div>
    </div>
    <?php
}


/* ================================================================
   PAGINA — EXPORT / IMPORT
================================================================ */
function cm_render_exportimport_page() {
    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Export / Import'); ?>
        <div style="max-width:720px;margin-top:20px">

            <div class="cm-group">
                <h3 class="cm-group-title">Export</h3>
                <div style="padding:16px 20px">
                    <p style="margin:0 0 6px">Download alle instellingen als een JSON-bestand. Gebruik dit als backup of om instellingen over te zetten naar een andere website.</p>
                    <p style="margin:0 0 14px"><strong>Wat wordt geëxporteerd:</strong> plugin-instellingen, cookielijst, privacyverklaring. De consent-log en de Open Cookie Database worden niet meegenomen.</p>
                    <button type="button" class="button button-primary" id="cm-export-btn">&#8659;&nbsp; Instellingen exporteren (.json)</button>
                    <span id="cm-export-status" style="margin-left:12px;font-size:13px"></span>
                </div>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Verwerkingsregister <span style="font-size:11px;font-weight:400;color:#787c82">— AVG artikel 30</span></h3>
                <div style="padding:16px 20px">
                    <p style="margin:0 0 10px">Genereer een verwerkingsregister op basis van uw privacyverklaring-instellingen. Verplicht voor organisaties met ≥250 medewerkers en voor kleinere organisaties die op grote schaal bijzondere persoonsgegevens verwerken.</p>
                    <p style="margin:0 0 14px;color:#787c82;font-size:13px">Het register bevat: verwerkingsdoeleinden en grondslagen, categorieën van betrokkenen, ontvangers/verwerkers, bewaartermijnen en internationale doorgiftes.</p>
                    <button type="button" class="button button-primary" id="cm-export-register-btn">&#8659;&nbsp; Verwerkingsregister downloaden (.csv)</button>
                    <span id="cm-export-register-status" style="margin-left:12px;font-size:13px"></span>
                </div>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Import</h3>
                <div style="padding:16px 20px">
                    <p style="margin:0 0 14px">Importeer een eerder geëxporteerd JSON-bestand. <strong>Let op:</strong> alle huidige instellingen worden overschreven.</p>
                    <label id="cm-import-dropzone" for="cm-import-file" style="display:block;border:2px dashed #c3c4c7;border-radius:6px;padding:40px 24px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:12px">
                        <span style="font-size:36px;display:block;margin-bottom:10px;line-height:1">&#8679;</span>
                        <span id="cm-import-dropzone-label" style="color:#646970;font-size:14px">Sleep een .json bestand hierheen of <strong>klik om te bladeren</strong></span>
                        <input type="file" id="cm-import-file" accept=".json,application/json" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none">
                    </label>
                    <div id="cm-import-preview" style="display:none;margin-bottom:14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:14px 16px;font-size:13px">
                        <strong>Bestand geladen:</strong> <span id="cm-import-filename"></span><br>
                        <span id="cm-import-summary" style="color:#646970;margin-top:4px;display:block"></span>
                    </div>
                    <button type="button" class="button button-primary" id="cm-import-btn" disabled>&#8593;&nbsp; Importeren &amp; overschrijven</button>
                    <span id="cm-import-status" style="margin-left:12px;font-size:13px"></span>
                    <p class="description" style="margin-top:10px">Alleen bestanden geëxporteerd door de Cookiemelding plugin worden geaccepteerd.</p>
                </div>
            </div>

        </div>
    </div>
    <?php
}

/* ================================================================
   PAGINA — COOKIELIJST
================================================================ */
function cm_render_cookies_page() {
    $db_count   = (int) get_option('cm_cookie_db_count', 0);
    $db_updated = get_option('cm_cookie_db_updated', '');
    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Cookies & scan'); ?>
        <div style="max-width:1100px;margin-top:20px">

            <div class="cm-group">
                <h3 class="cm-group-title">Open Cookie Database</h3>
                <table class="form-table cm-form-table"><tbody>
                <tr>
                    <th><label>Cookiedatabase</label></th>
                    <td>
                        <?php
                        if ( $db_count > 0 ) {
                            echo '<p style="margin:0 0 8px"><span style="color:#00a32a;font-weight:600">✓ ' . number_format($db_count) . ' cookies geladen</span>';
                            if ( $db_updated ) echo ' &mdash; bijgewerkt op ' . esc_html( date_i18n('j F Y H:i', strtotime($db_updated)) );
                            echo '</p>';
                        } else {
                            echo '<p style="margin:0 0 8px;color:#646970">Database nog niet geladen. Klik op "Database laden" om de Open Cookie Database te importeren.</p>';
                        }
                        ?>
                        <button type="button" class="button button-secondary" id="cm-import-db-btn">
                            <?php echo $db_count > 0 ? 'Database bijwerken' : 'Database laden'; ?>
                        </button>
                        <span id="cm-import-db-status" style="margin-left:10px;font-size:13px"></span>
                        <p class="description">Importeert de <a href="https://github.com/jkwakman/Open-Cookie-Database" target="_blank">Open Cookie Database</a> (Apache 2.0 licentie, ~2.200+ cookies). Wordt gebruikt bij de scan om cookies te herkennen en te omschrijven.</p>
                    </td>
                </tr>
                </tbody></table>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Cookie scan</h3>
                <div style="padding:14px 20px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <button type="button" class="button button-secondary" id="cm-scan-btn">Cookie scan starten</button>
                    <span style="color:#646970;font-size:13px">Crawlt alle pagina's en detecteert cookies via HTTP-headers en script-detectie.</span>
                </div>
                <div id="cm-scan-result" style="display:none;border-top:1px solid #f0f0f1;padding:16px 20px"></div>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Automatische scan</h3>
                <?php
                $scan_mode     = cm_get('auto_scan_mode') ?: 'off';
                $scan_interval = cm_get('auto_scan_interval') ?: '30';
                $scan_email    = cm_get('auto_scan_email') ?: '';
                $next_scan     = get_option('cm_auto_scan_next', '');
                $last_scan     = get_option('cm_auto_scan_last', '');
                ?>
                <table class="form-table cm-form-table"><tbody>
                <tr>
                    <th><label>Scan modus</label></th>
                    <td>
                        <fieldset style="display:flex;flex-direction:column;gap:10px;padding-top:2px">
                            <label style="display:flex;align-items:flex-start;gap:8px">
                                <input type="radio" name="auto_scan_mode" value="off" <?php checked($scan_mode,'off'); ?> style="margin-top:3px;flex-shrink:0">
                                <span>
                                    <strong>Handmatig</strong> — scan alleen via de knop hierboven
                                </span>
                            </label>
                            <label style="display:flex;align-items:flex-start;gap:8px">
                                <input type="radio" name="auto_scan_mode" value="auto" <?php checked($scan_mode,'auto'); ?> style="margin-top:3px;flex-shrink:0">
                                <span>
                                    <strong>Automatisch toevoegen</strong> — nieuw gevonden cookies worden direct toegevoegd aan de cookielijst
                                </span>
                            </label>
                            <label style="display:flex;align-items:flex-start;gap:8px">
                                <input type="radio" name="auto_scan_mode" value="notify" <?php checked($scan_mode,'notify'); ?> style="margin-top:3px;flex-shrink:0">
                                <span>
                                    <strong>Melding per e-mail</strong> — ontvang een melding als er nieuwe cookies gevonden worden ten opzichte van de huidige lijst
                                </span>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr id="cm-scan-interval-row" <?php echo $scan_mode === 'off' ? 'style="display:none"' : ''; ?>>
                    <th><label for="auto_scan_interval">Scanfrequentie</label></th>
                    <td>
                        <select name="auto_scan_interval" id="auto_scan_interval" style="min-width:200px">
                            <option value="10"  <?php selected($scan_interval,'10');  ?>>Elke 10 dagen</option>
                            <option value="30"  <?php selected($scan_interval,'30');  ?>>Elke maand (30 dagen)</option>
                            <option value="180" <?php selected($scan_interval,'180'); ?>>Elk half jaar (180 dagen)</option>
                        </select>

                        <?php if ( $last_scan ) : ?>
                        <p class="description" style="margin-top:8px">
                            Laatste scan: <strong><?php echo esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_scan) ) ); ?></strong>
                        </p>
                        <?php endif; ?>

                    </td>
                </tr>
                <tr id="cm-scan-email-row" <?php echo $scan_mode === 'notify' ? '' : 'style="display:none"'; ?>>
                    <th><label for="auto_scan_email">E-mailadres voor melding</label></th>
                    <td>
                        <input type="email" id="auto_scan_email" name="auto_scan_email"
                            class="regular-text"
                            value="<?php echo esc_attr($scan_email); ?>"
                            placeholder="<?php echo esc_attr( get_option('admin_email') ); ?>">
                        <p class="description">Laat leeg om het WordPress admin e-mailadres te gebruiken (<code><?php echo esc_html( get_option('admin_email') ); ?></code>).</p>
                    </td>
                </tr>
                <?php if ( $scan_mode !== 'off' ) : ?>
                <tr id="cm-scan-countdown-row" <?php echo ( $scan_mode === 'off' ) ? 'style="display:none"' : ''; ?>>
                    <th style="vertical-align:middle"><label>Volgende scan</label></th>
                    <td>
                        <?php if ( $next_scan ) :
                            $next_ts = strtotime($next_scan); ?>
                        <div id="cm-scan-countdown-wrap" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                            <div style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:#f0f6fc;border:1px solid #b3d1f0;border-radius:4px">
                                <div>
                                    <div style="font-size:10px;color:#2271b1;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">Ingepland op</div>
                                    <div id="cm-scan-next-date" style="font-size:12px;color:#1d2327"><?php echo esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $next_ts ) ); ?></div>
                                </div>
                                <div style="width:1px;height:32px;background:#c5d9f0;flex-shrink:0"></div>
                                <div>
                                    <div style="font-size:10px;color:#2271b1;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">Nog</div>
                                    <div id="cm-scan-countdown" style="font-size:14px;font-weight:700;color:#1d2327;font-variant-numeric:tabular-nums;white-space:nowrap">&mdash;</div>
                                </div>
                            </div>
                            <button type="button" id="cm-scan-reset-btn" class="button" style="color:#d63638;border-color:#d63638">&#x21BA; Reset timer</button>
                        </div>
                        <script>
                        jQuery(document).ready(function($) {
                            // Start countdown zodra admin.js geladen is
                            if (window.cmStartCountdown) {
                                window.cmStartCountdown(<?php echo (int) $next_ts; ?>);
                            } else {
                                // Fallback: wacht tot cmStartCountdown beschikbaar is
                                var checkInterval = setInterval(function() {
                                    if (window.cmStartCountdown) {
                                        clearInterval(checkInterval);
                                        window.cmStartCountdown(<?php echo (int) $next_ts; ?>);
                                    }
                                }, 100);
                            }
                        });
                        </script>
                        <?php else : ?>
                        <p class="description" style="margin:0">Sla de instellingen op om de eerste scan in te plannen.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody></table>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Vaste (ingebouwde) cookies</h3>
                <p class="description" style="padding:12px 20px 14px">Deze cookies worden altijd getoond in het voorkeuren-venster. Ze zijn niet te verwijderen omdat ze noodzakelijk zijn voor de werking van de plugin zelf.</p>
                <div class="cm-table-scroll" style="margin-bottom:20px">
                <table class="widefat cm-cookie-table" id="cm-builtin-table">
                    <thead><tr>
                        <th>Cookie</th><th>Provider</th><th>Doel</th><th>Looptijd</th><th>Categorie</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( cm_default_cookies() as $ck ) : ?>
                    <tr>
                        <td><code><?php echo esc_html($ck['name']); ?></code></td>
                        <td><?php echo esc_html($ck['provider']); ?></td>
                        <td><?php echo esc_html($ck['purpose']); ?></td>
                        <td><?php echo esc_html($ck['duration']); ?></td>
                        <td><span class="cm-cat-badge cm-cat-functional">Functioneel</span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Cookielijst beheren</h3>
                <div style="padding:14px 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;border-bottom:1px solid #f0f0f1">
                    <button type="button" class="button button-primary" id="cm-cookie-add-btn">+ Cookie toevoegen</button>
                    <button type="button" class="button button-secondary" id="cm-cookie-import-btn">&#x1F4CB; Plakken vanuit F12</button>
                    <button type="button" class="button" id="cm-cookie-clear-btn" style="margin-left:auto;color:#d63638;border-color:#d63638">&#x1F5D1; Lijst leegmaken</button>
                </div>
                <div id="cm-import-panel" style="display:none;padding:14px 20px">
                    <div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:16px">
                        <p style="margin:0 0 10px;font-weight:600">Cookies plakken vanuit F12 → Applicatie → Cookies</p>
                        <p class="description" style="margin-bottom:10px">Kopieer de volledige cookie-tabel uit de browser developer tools en plak hem hieronder.</p>
                        <textarea id="cm-import-textarea" rows="8" style="width:100%;font-family:monospace;font-size:12px;border:1px solid #c3c4c7;border-radius:3px;padding:8px" placeholder="Plak hier de cookie-tekst vanuit F12..."></textarea>
                        <div style="margin-top:10px;display:flex;gap:10px">
                            <button type="button" class="button button-primary" id="cm-import-parse-btn">Verwerk &amp; importeer</button>
                            <button type="button" class="button" id="cm-import-cancel-btn">Annuleren</button>
                        </div>
                    </div>
                </div>
                <div class="cm-table-scroll">
                <table class="widefat cm-cookie-table" id="cm-managed-table">
                    <thead><tr>
                        <th style="width:160px">Cookie naam</th>
                        <th>Provider</th>
                        <th>Doel</th>
                        <th style="width:100px">Looptijd</th>
                        <th style="width:120px">Categorie</th>
                        <th style="width:40px"></th>
                    </tr></thead>
                    <tbody id="cm-cookie-rows"></tbody>
                </table>
                </div>
                <p class="description" style="padding:10px 20px 14px">
                    De cookielijst wordt getoond in het voorkeuren-venster op uw website.
                </p>
            </div>

            <div class="cm-save-footer">
                <button type="button" class="button button-primary cm-cookie-save-footer-btn">Cookielijst opslaan</button>
                <span class="cm-saved-msg" id="cm-cookie-save-msg">&#10003; Opgeslagen</span>
            </div>

        </div>
    </div>
    <?php
}

/* ================================================================
   PAGINA — HELP
================================================================ */
/* ================================================================
   PAGINA — TRACKING SCRIPTS
================================================================ */
function cm_render_tracking_page() {
    $s = array_merge( cm_default_settings(), (array) get_option( 'cm_settings', array() ) );
    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Google integratie'); ?>
        <div style="max-width:800px;margin-top:20px">

            <div class="cm-group">
                <h3 class="cm-group-title">Hoe werkt het?</h3>
                <div style="padding:16px 20px;line-height:1.8;font-size:13px;color:#3c434a">
                    <p style="margin:0 0 10px">Cookiebaas beheert tracking scripts volledig zelf via <strong>Google Consent Mode v2</strong>. Zo werkt het:</p>
                    <ol style="margin:0 0 10px;padding-left:18px">
                        <li>Vul hieronder uw tracking ID's in en sla op.</li>
                        <li>Verwijder de bestaande snippets uit uw code of uit andere plugins indien deze al vermeld staan.</li>
                        <li>Cookiebaas laadt de scripts zelf in, geblokkeerd totdat de bezoeker toestemming geeft.</li>
                        <li>Na akkoord worden scripts direct geactiveerd zonder pagina-herlaad.</li>
                    </ol>
                    <p style="margin:0;color:#787c82">Scripts die u hier invult worden automatisch correct geblokkeerd en vrijgegeven — geen verdere configuratie nodig.</p>
                </div>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Google Analytics &amp; Tag Manager</h3>
                <p class="description" style="margin:0;padding:8px 20px 4px">Gebruik <strong>óf</strong> GA4 <strong>óf</strong> GTM — niet beide. GTM heeft de voorkeur als u meerdere Google diensten gebruikt.</p>
                <table class="form-table cm-form-table"><tbody>
                <tr>
                    <th><label for="ga4_measurement_id">GA4 Measurement ID</label></th>
                    <td>
                        <input type="text" id="ga4_measurement_id" name="ga4_measurement_id" value="<?php echo esc_attr( cm_get('ga4_measurement_id') ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                        <p class="description">Begint met <code>G-</code>. Te vinden in Google Analytics → Beheer → Gegevensstreams. Verwijder na het invullen de bestaande snippets uit uw code of uit andere plugins indien deze al vermeld staan.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gtm_container_id">GTM Container ID</label></th>
                    <td>
                        <input type="text" id="gtm_container_id" name="gtm_container_id" value="<?php echo esc_attr( cm_get('gtm_container_id') ); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
                        <p class="description">Begint met <code>GTM-</code>. Te vinden in Google Tag Manager → Workspace. Verwijder na het invullen de bestaande snippets uit uw code of uit andere plugins indien deze al vermeld staan.</p>
                    </td>
                </tr>
                </tbody></table>
            </div>

            <div class="cm-group cm-accordion">
                <h3 class="cm-group-title cm-accordion-head">
                    <span class="cm-acc-icon">+</span>
                    Niet-Google tags via GTM
                    <span class="cm-acc-sub">— Meta Pixel, TikTok, LinkedIn &amp; andere</span>
                </h3>
                <div class="cm-accordion-body" style="display:none">
                <div style="padding:16px 0 20px;font-size:13px;color:#3c434a;line-height:1.7">

                    <?php
                    // Twee gekleurde info-blokken: automatisch vs handmatig
                    ?>
                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;padding:0 20px">
                        <div style="background:#f0faf4;border:1px solid #b7dfca;border-radius:5px;padding:13px 16px;display:flex;align-items:center;gap:16px">
                            <div style="font-weight:600;color:#1a7a45;white-space:nowrap">&#10003;&nbsp; Automatisch geregeld</div>
                            <div style="font-size:12px;color:#3c434a;line-height:1.5">
                                <strong>Google tags</strong> — GA4, Google Ads, Floodlight &mdash; Consent Mode v2, volledig door de plugin beheerd.<br>
                                <strong>Microsoft UET</strong> — Microsoft Advertising &mdash; de plugin pusht automatisch <code>uetq consent update</code> bij elke consentkeuze.
                            </div>
                        </div>
                        <div style="background:#fef8f0;border:1px solid #f0d0a0;border-radius:5px;padding:13px 16px;display:flex;align-items:center;gap:16px">
                            <div style="font-weight:600;color:#9a5a00;white-space:nowrap">&#9432;&nbsp; Eenmalig instellen in GTM</div>
                            <div style="font-size:12px;color:#3c434a;line-height:1.5">
                                <strong>Niet-Google tags via GTM</strong> — Meta Pixel, TikTok, LinkedIn, Hotjar e.a. &mdash; er bestaat geen universele standaard. Volg onderstaande 3 stappen eenmalig in GTM.
                            </div>
                        </div>
                    </div>

                    <div style="padding:0 20px">
                    <p style="margin:0 0 4px;font-weight:600">Stap 1 &mdash; Maak twee variabelen aan in GTM</p>
                    <p style="margin:0 0 10px;color:#646970;font-size:12px">Variabele type: <strong>Data Layer Variable</strong></p>
                    <table style="border-collapse:collapse;width:100%;margin-bottom:20px;font-size:12px">
                        <thead>
                            <tr style="background:#f6f7f7">
                                <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Variabele naam</th>
                                <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Data Layer Variable Name</th>
                                <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Gebruik voor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Analytics Consent</code></td>
                                <td style="padding:8px 12px;border:1px solid #dcdcde"><code>cm_analytics</code></td>
                                <td style="padding:8px 12px;border:1px solid #dcdcde">Hotjar, Matomo, Clarity e.a.</td>
                            </tr>
                            <tr style="background:#f9f9f9">
                                <td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Marketing Consent</code></td>
                                <td style="padding:8px 12px;border:1px solid #dcdcde"><code>cm_marketing</code></td>
                                <td style="padding:8px 12px;border:1px solid #dcdcde">Meta Pixel, TikTok, LinkedIn e.a.</td>
                            </tr>
                        </tbody>
                    </table>

                    <p style="margin:0 0 4px;font-weight:600">Stap 2 &mdash; Maak twee triggers aan in GTM</p>
                    <p style="margin:0 0 10px;color:#646970;font-size:12px">Trigger type: <strong>Custom Event</strong> &mdash; Event name: <code>cm_consent_update</code></p>
                    <table style="border-collapse:collapse;width:100%;margin-bottom:20px;font-size:12px">
                        <thead>
                            <tr style="background:#f6f7f7">
                                <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Trigger naam</th>
                                <th style="padding:8px 12px;border:1px solid #dcdcde;text-align:left;font-weight:600">Conditie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Analytics toegestaan</code></td>
                                <td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Analytics Consent</code> &nbsp;equals&nbsp; <code>true</code></td>
                            </tr>
                            <tr style="background:#f9f9f9">
                                <td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Marketing toegestaan</code></td>
                                <td style="padding:8px 12px;border:1px solid #dcdcde"><code>CM - Marketing Consent</code> &nbsp;equals&nbsp; <code>true</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <p style="margin:0 0 4px;font-weight:600">Stap 3 &mdash; Koppel de trigger aan uw tag</p>
                    <p style="margin:0 0 16px;color:#3c434a">
                        Open uw Meta Pixel-, TikTok- of LinkedIn-tag in GTM en stel als triggering condition in: <code>CM - Marketing toegestaan</code>. De tag vuurt dan alleen als de bezoeker marketing cookies heeft geaccepteerd &mdash; ook bij terugkerende bezoekers zonder dat de banner opnieuw verschijnt.
                    </p>
                    <p style="margin:0 0 16px;color:#3c434a">
                        <strong>Microsoft UET (Bing Ads) buiten GTM?</strong> De plugin pusht automatisch <code>window.uetq.push('consent', 'update', ...)</code> &mdash; geen extra stappen nodig als de UET-tag direct op de site staat.
                    </p>

                    </div><!-- /padded steps -->
                    <div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:12px 16px 12px 20px;font-size:12px;margin:0 20px 0">
                        <strong style="display:block;margin-bottom:6px">Voorbeeld dataLayer push &mdash; automatisch door de plugin bij elke paginaweergave:</strong>
                        <pre style="margin:0;font-size:11px;line-height:1.7;background:none;padding:0;border:none;overflow-x:auto">{
  event              : "cm_consent_update",
  cm_analytics       : true,      <span style="color:#787c82">// false als geweigerd</span>
  cm_marketing       : false,     <span style="color:#787c82">// true als marketing geaccepteerd</span>
  cm_method          : "custom",  <span style="color:#787c82">// accept-all | reject-all | custom | returning</span>
  analytics_storage  : "granted",
  ad_storage         : "denied",
  ad_user_data       : "denied",
  ad_personalization : "denied"
}</pre>
                    </div>

                </div>
                </div><!-- /cm-accordion-body -->
            </div>

            <div class="cm-group cm-accordion">
                <h3 class="cm-group-title cm-accordion-head">
                    <span class="cm-acc-icon">+</span>
                    Overige tracking scripts
                </h3>
                <div class="cm-accordion-body" style="display:none">
                <table class="form-table cm-form-table"><tbody>
                <tr>
                    <th><label for="block_analytics_patterns">Extra analytics patronen</label></th>
                    <td>
                        <input type="text" id="block_analytics_patterns" name="block_analytics_patterns" value="<?php echo esc_attr( cm_get('block_analytics_patterns') ); ?>" class="regular-text" placeholder="optioneel — bijv. mijnanalytics.nl">
                        <p class="description">Komma-gescheiden URL-patronen voor analytics scripts die niet automatisch herkend worden. Automatisch herkend: Google Analytics, GTM, Hotjar, Clarity, Mixpanel, Matomo e.a.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="block_marketing_patterns">Extra marketing patronen</label></th>
                    <td>
                        <input type="text" id="block_marketing_patterns" name="block_marketing_patterns" value="<?php echo esc_attr( cm_get('block_marketing_patterns') ); ?>" class="regular-text" placeholder="optioneel — bijv. mijnretargeting.nl">
                        <p class="description">Komma-gescheiden URL-patronen voor marketing scripts die niet automatisch herkend worden. Automatisch herkend: Facebook Pixel, Google Ads, TikTok, LinkedIn, Pinterest, Bing Ads e.a.</p>
                    </td>
                </tr>
                </tbody></table>
                <p class="description" style="padding:8px 20px 16px">Scripts van andere aanbieders worden automatisch herkend via de ingebouwde kennisbank (Facebook Pixel, TikTok, Hotjar, e.a.). Staan uw scripts er niet tussen? Voeg dan hierboven extra URL-patronen toe.</p>
                </div><!-- /cm-accordion-body -->
            </div>

            <div class="cm-save-footer">
                <button type="button" class="button button-primary cm-save-btn">Opslaan</button>
                <span class="cm-saved-msg">&#10003; Opgeslagen</span>
            </div>

        </div>
    </div>
    <?php
}

function cm_render_help_page() {
    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Info'); ?>
        <div style="max-width:760px;margin-top:20px">

            <div class="cm-group">
                <h3 class="cm-group-title">Plugin informatie</h3>
                <table class="form-table cm-form-table"><tbody>
                <tr><th>Versie</th><td><?php echo esc_html(CM_VERSION); ?></td></tr>
                <tr><th>Gemaakt door</th><td><a href="https://www.ruudvdheijden.nl/" target="_blank"><strong>Ruud van der Heijden</strong></a></td></tr>
                <tr><th>Cookie naam</th><td><code>cc_cm_consent</code></td></tr>
                <tr><th>AVG-compliant</th><td>Ja &mdash; opt-in, geen pre-aangevinkte marketing cookies, gelijke button-prominentie</td></tr>
                </tbody></table>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Snel aan de slag</h3>
                <div style="padding:16px 20px;line-height:1.8">
                    <ol style="margin:0;padding-left:18px">
                        <li>Ga naar <strong>Google integratie</strong> en vul uw GA4 Measurement ID of GTM Container ID in. Verwijder daarna de bestaande snippets uit uw code of uit andere plugins indien deze al vermeld staan.</li>
                        <li>Ga naar <strong>Instellingen</strong> en stel kleuren, teksten en gedrag in naar uw huisstijl.</li>
                        <li>Ga naar <strong>Cookielijst</strong> → laad de Open Cookie Database en voer een cookie scan uit op uw website.</li>
                        <li>Voeg gevonden cookies toe aan de cookielijst via de <em>+ Lijst</em> knop in de scanresultaten.</li>
                        <li>Ga naar <strong>Privacyverklaring</strong> en vul bedrijfsgegevens, doeleinden en ontvangers in.</li>
                        <li>Maak een pagina aan op <code>/privacyverklaring</code> en plaats de shortcode <code>[cookiebaas_privacy]</code>.</li>
                        <li>Test de plugin: verwijder het <code>cc_cm_consent</code> cookie in uw browser en herlaad de pagina. Controleer in F12 → Application → Cookies dat GA cookies pas verschijnen ná akkoord.</li>
                        <li>Gebruik <strong>Export / Import</strong> om periodiek een backup te maken van alle instellingen.</li>
                    </ol>
                </div>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Shortcodes</h3>
                <table class="form-table cm-form-table"><tbody>
                <tr><th>Volledige privacyverklaring</th><td><code>[cookiebaas_privacy]</code></td></tr>
                <tr><th>Alleen cookieparagraaf (hoofdstuk 4)</th><td><code>[cookiebaas_cookies]</code></td></tr>
                </tbody></table>
            </div>

            <div class="cm-group" style="border-color:#b32d2e">
                <h3 class="cm-group-title" style="background:#fef7f7;border-color:#f5c6c7;color:#b32d2e">Disclaimer</h3>
                <div style="padding:16px 20px;line-height:1.8;font-size:13px;color:#444">
                    <p style="margin:0 0 10px">De Cookiebaas plugin wordt aangeboden <strong>zoals die is</strong>, zonder enige garantie of toezegging omtrent volledigheid, juistheid, geschiktheid voor een bepaald doel of AVG-conformiteit voor uw specifieke situatie.</p>
                    <p style="margin:0 0 10px">Ruud van der Heijden is niet aansprakelijk voor directe, indirecte, incidentele of gevolgschade die voortvloeit uit het gebruik van, of het niet kunnen gebruiken van, deze plugin — waaronder maar niet beperkt tot: onjuiste weergave of werking van cookies, gemiste of verkeerde registratie van toestemmingen, of sancties opgelegd door toezichthouders.</p>
                    <p style="margin:0 0 10px">Het correct configureren van de cookiemelding, het bijhouden van de cookielijst en het voldoen aan de AVG/GDPR-vereisten valt onder de verantwoordelijkheid van de websitebeheerder. Bij twijfel over uw juridische verplichtingen is het raadzaam een juridisch adviseur of privacyspecialist te raadplegen.</p>
                    <p style="margin:0">Door gebruik te maken van deze plugin aanvaardt u bovenstaande voorwaarden.</p>
                </div>
            </div>

            <div class="cm-group">
                <h3 class="cm-group-title">Contact &amp; ondersteuning</h3>
                <div style="padding:16px 20px;line-height:1.8">
                    <p style="margin:0">Voor vragen en ondersteuning kunt u contact opnemen via <a href="https://www.ruudvdheijden.nl/" target="_blank">ruudvdheijden.nl</a>.</p>
                </div>
            </div>

        </div>
    </div>
    <?php
}

/* ================================================================
   PAGINA — RESET
================================================================ */
function cm_render_reset_page() {
    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Reset'); ?>
        <div style="max-width:640px;margin-top:20px">

            <!-- Google integratie reset — eigen blok -->
            <div class="cm-group" style="border-color:#d63638">
                <h3 class="cm-group-title" style="background:#fef7f7;border-color:#f5c6c7;color:#d63638">&#9888; Google integratie wissen</h3>
                <div style="padding:20px">
                    <p style="margin:0 0 10px">Wist de ingevulde <strong>GA4 Measurement ID</strong> en <strong>GTM Container ID</strong>. De overige instellingen blijven bewaard.</p>
                    <p style="margin:0 0 14px;color:#787c82;font-size:13px">Gebruik dit als u de Google-koppeling wilt verwijderen zonder de rest van de plugin te resetten.</p>
                    <button type="button" class="button" id="cm-reset-google" style="color:#d63638;border-color:#d63638;font-weight:600">Google IDs wissen</button>
                    <span id="cm-reset-google-status" style="margin-left:12px;font-size:13px"></span>
                </div>
            </div>

            <!-- Selectief resetten -->
            <div class="cm-group" style="border-color:#d63638">
                <h3 class="cm-group-title" style="background:#fef7f7;border-color:#f5c6c7;color:#d63638">&#9888; Selectief resetten</h3>
                <div style="padding:20px">
                    <p style="margin:0 0 14px">Kies wat u wilt resetten. U krijgt eerst een samenvatting te zien vóór er iets wordt gewist.</p>
                    <p style="margin:0 0 14px;color:#787c82;font-size:13px">Tip: maak eerst een export via <a href="<?php echo esc_url(admin_url('admin.php?page=cookiemelding-beheer#tab=exportimport')); ?>">Export / Import</a>.</p>

                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                            <input type="checkbox" name="cm_reset_item" value="settings" id="cr-settings" style="margin-top:2px">
                            <span>
                                <strong>Instellingen</strong>
                                <span style="display:block;font-size:12px;color:#646970;margin-top:2px">Alle kleur-, tekst- en gedrag-instellingen terug naar standaard. Cookielijst en privacyverklaring blijven bewaard.</span>
                            </span>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                            <input type="checkbox" name="cm_reset_item" value="cookielist" id="cr-cookielist" style="margin-top:2px">
                            <span>
                                <strong>Cookielijst</strong>
                                <span style="display:block;font-size:12px;color:#646970;margin-top:2px">Alle handmatig toegevoegde cookies worden verwijderd. Ingebouwde cookies blijven aanwezig.</span>
                            </span>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                            <input type="checkbox" name="cm_reset_item" value="privacy" id="cr-privacy" style="margin-top:2px">
                            <span>
                                <strong>Privacyverklaring</strong>
                                <span style="display:block;font-size:12px;color:#646970;margin-top:2px">Alle ingevulde gegevens in de privacyverklaring worden gewist en teruggezet naar standaardwaarden.</span>
                            </span>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                            <input type="checkbox" name="cm_reset_item" value="log" id="cr-log" style="margin-top:2px">
                            <span>
                                <strong>Consent log</strong>
                                <span style="display:block;font-size:12px;color:#646970;margin-top:2px">Alle opgeslagen toestemmingsregistraties worden verwijderd. Bezoekers hoeven niet opnieuw toestemming te geven.</span>
                            </span>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                            <input type="checkbox" name="cm_reset_item" value="consent" id="cr-consent" style="margin-top:2px">
                            <span>
                                <strong>Consent data</strong>
                                <span style="display:block;font-size:12px;color:#646970;margin-top:2px">Verhoogt de consent-versie zodat <strong>alle bezoekers de banner opnieuw krijgen te zien</strong>.</span>
                            </span>
                        </label>
                    </div>

                    <button type="button" class="button" id="cm-reset-selective-preview" style="color:#d63638;border-color:#d63638;font-weight:600" disabled>Bekijk samenvatting &amp; reset →</button>
                    <span id="cm-reset-selective-none" style="margin-left:12px;font-size:12px;color:#646970;display:none">Selecteer minimaal één onderdeel.</span>

                    <!-- Samenvatting -->
                    <div id="cm-reset-summary" style="display:none;margin-top:16px;border:1px solid #d63638;border-radius:4px;background:#fff8f8;padding:16px">
                        <strong style="font-size:13px;color:#d63638">U staat op het punt te resetten:</strong>
                        <ul id="cm-reset-summary-list" style="margin:10px 0 14px 18px;font-size:13px;line-height:1.8"></ul>
                        <p style="margin:0 0 12px;font-size:12px;color:#646970"><strong>Dit kan niet ongedaan worden gemaakt.</strong> Maak eerst een backup via <a href="<?php echo esc_url(admin_url('admin.php?page=cookiemelding-beheer#tab=exportimport')); ?>">Export / Import</a> als u dat nog niet gedaan heeft.</p>
                        <button type="button" class="button" id="cm-reset-selective-confirm" style="background:#d63638;color:#fff;border-color:#d63638;font-weight:700">Bevestig reset</button>
                        <button type="button" class="button" id="cm-reset-selective-cancel" style="margin-left:8px">Annuleren</button>
                        <span id="cm-reset-selective-status" style="margin-left:12px;font-size:13px"></span>
                    </div>
                </div>
            </div>

            <!-- Consent data wissen (apart vanwege changelog) -->
            <div class="cm-group" style="border-color:#d63638">
                <h3 class="cm-group-title" style="background:#fef7f7;border-color:#f5c6c7;color:#d63638">&#9888; Consent data wissen</h3>
                <div style="padding:20px">
                    <p style="margin:0 0 10px">Wist het cookie <code>cc_cm_consent</code> bij alle bezoekers. <strong>Alle bezoekers krijgen de cookiemelding opnieuw te zien</strong> bij hun volgende bezoek.</p>
                    <p style="margin:0 0 12px;color:#787c82;font-size:13px">Gebruik dit na een wijziging in uw cookiegebruik waarvoor u opnieuw toestemming nodig heeft.</p>
                    <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px">Reden (optioneel — voor uw eigen administratie):</label>
                    <input type="text" id="cm-bump-reason" class="regular-text" placeholder="bijv. Nieuwe marketing cookies toegevoegd — Meta Pixel" style="margin-bottom:12px;display:block">
                    <button type="button" class="button" id="cm-reset-consent" style="color:#d63638;border-color:#d63638;font-weight:600">Consent data wissen</button>
                    <span id="cm-reset-consent-status" style="margin-left:12px;font-size:13px"></span>
                    <?php
                    $changelog = get_option('cm_consent_changelog', array());
                    if ( ! empty($changelog) ) : ?>
                    <div style="margin-top:16px">
                        <strong style="font-size:13px">Changelog:</strong>
                        <table style="width:100%;margin-top:8px;font-size:12px;border-collapse:collapse">
                            <thead><tr style="background:#f6f7f7">
                                <th style="padding:6px 10px;text-align:left;border:1px solid #e0e0e0">Datum</th>
                                <th style="padding:6px 10px;text-align:left;border:1px solid #e0e0e0">Versie</th>
                                <th style="padding:6px 10px;text-align:left;border:1px solid #e0e0e0">Reden</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ( array_reverse($changelog) as $entry ) : ?>
                            <tr>
                                <td style="padding:6px 10px;border:1px solid #e0e0e0"><?php echo esc_html($entry['date']); ?></td>
                                <td style="padding:6px 10px;border:1px solid #e0e0e0">v<?php echo esc_html($entry['version']); ?></td>
                                <td style="padding:6px 10px;border:1px solid #e0e0e0"><?php echo esc_html($entry['reason'] ?: '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alles resetten -->
            <div class="cm-group" style="border-color:#8a0000;background:#fff5f5">
                <h3 class="cm-group-title" style="background:#fde8e8;border-color:#f5b8b8;color:#8a0000;font-size:1rem">&#9888;&#9888; Alles resetten</h3>
                <div style="padding:20px">
                    <p style="margin:0 0 10px">Reset <strong>alles</strong> in één keer: instellingen, cookielijst, privacyverklaring, consent log én consent data. <strong>Dit kan niet ongedaan worden gemaakt.</strong></p>
                    <p style="margin:0 0 16px;color:#787c82;font-size:13px">Tip: maak eerst een export via <a href="<?php echo esc_url(admin_url('admin.php?page=cookiemelding-beheer#tab=exportimport')); ?>">Export / Import</a>.</p>
                    <button type="button" class="button" id="cm-reset-all" style="background:#8a0000;color:#fff;border-color:#8a0000;font-weight:700;padding:8px 20px;font-size:14px">&#x26A0; Alles resetten</button>
                    <span id="cm-reset-all-status" style="margin-left:12px;font-size:13px"></span>
                </div>
            </div>

        </div>
    </div>
    <?php
}

/* ================================================================
   PAGINA — BEHEER (samenvoeging van Compliance + Export/Import + Reset + Info)
================================================================ */
function cm_render_beheer_page() {
    ?>
    <div class="wrap" id="cm-admin-wrap">
        <?php cm_page_header('Beheer'); ?>
        <div style="max-width:1100px;margin-top:20px">

            <nav class="nav-tab-wrapper cm-nav-tabs">
                <a class="nav-tab nav-tab-active" data-tab="compliance" href="#">Compliance</a>
                <a class="nav-tab" data-tab="exportimport" href="#">Export / Import</a>
                <a class="nav-tab" data-tab="reset" href="#">Reset</a>
                <a class="nav-tab" data-tab="info" href="#">Info</a>
            </nav>

            <!-- ======== TAB COMPLIANCE ======== -->
            <div class="cm-tab-pane active" id="cm-pane-compliance">
                <?php cm_render_compliance_content(); ?>
            </div>

            <!-- ======== TAB EXPORT / IMPORT ======== -->
            <div class="cm-tab-pane" id="cm-pane-exportimport">
                <?php cm_render_exportimport_content(); ?>
            </div>

            <!-- ======== TAB RESET ======== -->
            <div class="cm-tab-pane" id="cm-pane-reset">
                <?php cm_render_reset_content(); ?>
            </div>

            <!-- ======== TAB INFO ======== -->
            <div class="cm-tab-pane" id="cm-pane-info">
                <?php cm_render_help_content(); ?>
            </div>

        </div>
    </div>
    <?php
}

/* ---- Beheer sub-renderers: herbruikbare content blokken ---- */

function cm_render_compliance_content() {
    $s   = array_merge( cm_default_settings(), (array) get_option( 'cm_settings', array() ) );
    $pv  = array_merge( cm_default_privacy(),  (array) get_option( 'cm_privacy',  array() ) );
    $cookies = cm_get_cookie_list();
    $link = function( $page, $tab, $label ) {
        $url = admin_url( 'admin.php?page=' . $page );
        if ( $tab ) $url .= '#tab=' . $tab;
        return '<a href="' . esc_url($url) . '">' . esc_html($label) . ' &rarr;</a>';
    };

    // ── Helpers ──
    $reject_bg = trim( $s['color_reject_bg'] ?? '' );
    $accept_bg = trim( $s['color_accept_bg'] ?? '#111111' );
    $luminance = function( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen($hex) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if ( strlen($hex) !== 6 ) return 0.5;
        $r = hexdec(substr($hex,0,2)) / 255; $g = hexdec(substr($hex,2,2)) / 255; $b = hexdec(substr($hex,4,2)) / 255;
        return 0.299 * $r + 0.587 * $g + 0.114 * $b;
    };
    $reject_lum = $luminance( $reject_bg ?: '#f5f2ee' );
    $accept_lum = $luminance( $accept_bg ?: '#111111' );
    $prominence_ok = ( $reject_lum < 0.5 ) || ( abs($reject_lum - $accept_lum) < 0.45 );
    $banner_body = $s['txt_banner_body'] ?? '';
    $has_pv_link = strpos( $banner_body, 'href' ) !== false || strpos( $banner_body, 'privac' ) !== false;
    $ga4_id = trim( $s['ga4_measurement_id'] ?? '' ); $gtm_id = trim( $s['gtm_container_id'] ?? '' ); $ua_id = trim( $s['ua_tracking_id'] ?? '' );
    $has_self_loader = ( $ga4_id && preg_match('/^G-[A-Z0-9]+$/i', $ga4_id) ) || ( $gtm_id && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id) ) || ( $ua_id && preg_match('/^UA-[0-9]+-[0-9]+$/i', $ua_id) );
    $block_a = trim( $s['block_analytics_patterns'] ?? '' ); $block_m = trim( $s['block_marketing_patterns'] ?? '' );
    $blocking_ok = $block_a || $block_m || $has_self_loader;
    $has_float = ! empty( $s['show_float_btn'] );
    $expiry = intval( $s['expiry_months'] ?? 12 );
    $managed = array_filter( $cookies, function($c) { return empty($c['builtin']); } );
    $incomplete = array_filter( $managed, function($c) { return empty($c['purpose']) || empty($c['duration']); });
    $cats_ok = ! empty( $s['txt_cat1_long'] ) && ! empty( $s['txt_cat2_long'] ) && ! empty( $s['txt_cat3_long'] );
    $retention = intval( $s['log_retention_months'] ?? 0 );

    // ════════════════════════════════════════════════════════════
    // DEEL 1: Instelbare checks — beïnvloed door plugin-instellingen
    // ════════════════════════════════════════════════════════════
    $checks = array();

    // ── AP 9 vuistregels ──
    $checks[] = array(
        'cat'    => 'AP 9 vuistregels cookiebanners',
        'title'  => '1. Weigeren even makkelijk als accepteren',
        'desc'   => 'De banner heeft zowel een &ldquo;Akkoord&rdquo; als een &ldquo;Weigeren&rdquo; knop op dezelfde laag, met gelijke visuele prominentie. Geen extra klikken nodig om te weigeren.',
        'ref'    => 'AP vuistregel 1 &middot; AVG art. 7 lid 3 &middot; EDPB 05/2020',
        'status' => $prominence_ok ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'kleuren', 'Knopkleuren aanpassen'),
        'detail' => ! $prominence_ok ? 'De weigeren-knop is aanzienlijk lichter dan de akkoord-knop.' : '',
    );
    $checks[] = array(
        'cat'    => 'AP 9 vuistregels cookiebanners',
        'title'  => '2. Geen vooraf aangevinkte opties',
        'desc'   => 'Analytics- en marketing-toggles staan standaard uit in het voorkeuren-venster. Consent vereist een actieve handeling (opt-in).',
        'ref'    => 'AP vuistregel 2 &middot; HvJEU Planet49 (C-673/17)',
        'status' => empty( $s['analytics_default'] ) ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding', 'gedrag', 'Gedrag instellingen'),
    );
    $checks[] = array(
        'cat'    => 'AP 9 vuistregels cookiebanners',
        'title'  => '4. Duidelijke informatie over doel cookies',
        'desc'   => 'Het voorkeuren-venster toont per categorie een beschrijving, en per dienst/cookie het doel, de looptijd en de provider.',
        'ref'    => 'AP vuistregel 4 &middot; AVG art. 13 &middot; Tw art. 11.7a lid 1',
        'status' => $cats_ok ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'teksten', 'Teksten aanpassen'),
        'detail' => ! $cats_ok ? 'Niet alle cookiecategorie&euml;n hebben een beschrijving.' : '',
    );
    $checks[] = array(
        'cat'    => 'AP 9 vuistregels cookiebanners',
        'title'  => '7. Intrekken consent even makkelijk als geven',
        'desc'   => 'Zweefknop op elke pagina om cookievoorkeuren te wijzigen. Banner opent opnieuw met dezelfde knoppen. Cookies worden actief verwijderd bij intrekking.',
        'ref'    => 'AP vuistregel 7 &middot; AVG art. 7 lid 3 &middot; AP Normuitleg 2024',
        'status' => $has_float ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding', 'layout', 'Zweefknop inschakelen'),
        'detail' => ! $has_float ? 'Zweefknop is uitgeschakeld &mdash; bezoekers kunnen consent niet makkelijk intrekken.' : '',
    );
    $checks[] = array(
        'cat'    => 'AP 9 vuistregels cookiebanners',
        'title'  => '8. Geen misleidend ontwerp (dark patterns)',
        'desc'   => 'Knoppen hebben gelijke grootte en stijl. Geen kleurverschil dat akkoord bevoordeelt. Geen verwarrende teksten of dubbele ontkenningen.',
        'ref'    => 'AP vuistregel 8 &middot; EDPB Guidelines 3/2022',
        'status' => $prominence_ok ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'kleuren', 'Knopkleuren aanpassen'),
    );
    $checks[] = array(
        'cat'    => 'AP 9 vuistregels cookiebanners',
        'title'  => '9. Link naar privacyverklaring',
        'desc'   => 'Bannertekst bevat een link naar de privacyverklaring zodat bezoekers zich kunnen informeren v&oacute;&oacute;r het geven van consent.',
        'ref'    => 'AP vuistregel 9 &middot; AVG art. 13/14',
        'status' => $has_pv_link ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'teksten', 'Bannertekst aanpassen'),
    );

    // ── Technische vereisten ──
    $checks[] = array(
        'cat'    => 'Technische vereisten',
        'title'  => 'Script blocking v&oacute;&oacute;r consent',
        'desc'   => 'Drielaags blokkade: PHP output buffer, JS MutationObserver, Google Consent Mode v2. Scripts laden pas na toestemming.',
        'ref'    => 'Tw art. 11.7a &middot; ePrivacy-richtlijn',
        'status' => $blocking_ok ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'google', 'Google integratie instellen'),
        'detail' => ! $blocking_ok ? 'Geen GA4/GTM ID of blokkeerpatronen geconfigureerd.' : '',
    );
    $checks[] = array(
        'cat'    => 'Technische vereisten',
        'title'  => 'Iframe/embed blocking v&oacute;&oacute;r consent',
        'desc'   => 'YouTube, Vimeo, Google Maps, Spotify, TikTok en meer worden automatisch geblokkeerd en vervangen door een placeholder.',
        'ref'    => 'Tw art. 11.7a &middot; AP-standpunt embedded content',
        'status' => cm_get('embed_blocker_enabled') ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'embeds', 'Embed blocker inschakelen'),
    );
    $checks[] = array(
        'cat'    => 'Technische vereisten',
        'title'  => 'Google Consent Mode v2',
        'desc'   => 'Automatische integratie met GA4 en GTM. Default: denied. Update naar granted na consent.',
        'ref'    => 'Google EU User Consent Policy &middot; Digital Markets Act',
        'status' => $has_self_loader ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'google', 'GA4 of GTM ID invullen'),
        'detail' => ! $has_self_loader ? 'Vul een GA4 of GTM ID in voor automatische Consent Mode v2.' : '',
    );
    $checks[] = array(
        'cat'    => 'Technische vereisten',
        'title'  => 'Consent verlooptijd &le; 12 maanden',
        'desc'   => 'De consent-cookie verloopt na de ingestelde periode. De AP adviseert maximaal 12 maanden.',
        'ref'    => 'AP-handhavingscriteria &middot; EDPB aanbeveling',
        'status' => $expiry <= 12 ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'gedrag', 'Verlooptijd aanpassen'),
        'detail' => $expiry > 12 ? 'Huidige instelling: ' . $expiry . ' maanden.' : 'Huidige instelling: ' . $expiry . ' maanden.',
    );

    // ── Verantwoordingsplicht ──
    $checks[] = array(
        'cat'    => 'Verantwoordingsplicht (AVG art. 5 lid 2)',
        'title'  => 'Automatische log-retentie ingesteld',
        'desc'   => 'Consent logs worden automatisch opgeschoond na de ingestelde periode. Dagelijkse controle om 12:00u.',
        'ref'    => 'AVG art. 5 lid 1e (opslagbeperking)',
        'status' => $retention > 0 && $retention <= 36 ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding', 'gedrag', 'Retentie instellen'),
        'detail' => $retention === 0 ? 'Logs worden nooit automatisch verwijderd.' : ( $retention > 36 ? 'Overweeg een kortere bewaarperiode.' : '' ),
    );
    $checks[] = array(
        'cat'    => 'Verantwoordingsplicht (AVG art. 5 lid 2)',
        'title'  => 'Alle cookies hebben doel en looptijd',
        'desc'   => 'Per cookie moet het doel en de bewaartermijn vermeld zijn in het voorkeuren-venster.',
        'ref'    => 'AVG art. 13 &middot; Tw art. 11.7a lid 1',
        'status' => count($managed) === 0 ? 'warn' : ( count($incomplete) === 0 ? 'ok' : 'warn' ),
        'fix'    => $link('cookiemelding-cookies', '', 'Cookielijst aanvullen'),
        'detail' => count($managed) === 0 ? 'Geen cookies in de lijst.' : ( count($incomplete) > 0 ? count($incomplete) . ' cookie(s) missen doel of looptijd.' : '' ),
    );

    // ── Privacyverklaring ──
    $checks[] = array(
        'cat'    => 'Privacyverklaring',
        'title'  => 'Bedrijfsnaam ingevuld',
        'desc'   => 'De verwerkingsverantwoordelijke moet duidelijk worden vermeld in de privacyverklaring.',
        'ref'    => 'AVG art. 13 lid 1a',
        'status' => ! empty( $pv['pv_bedrijfsnaam'] ) ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding-privacy', '', 'Privacyverklaring aanvullen'),
    );
    $checks[] = array(
        'cat'    => 'Privacyverklaring',
        'title'  => 'Contactgegevens ingevuld',
        'desc'   => 'Contactgegevens van de verwerkingsverantwoordelijke zijn verplicht zodat betrokkenen hun rechten kunnen uitoefenen.',
        'ref'    => 'AVG art. 13 lid 1a',
        'status' => ! empty( $pv['pv_email'] ) ? 'ok' : 'fail',
        'fix'    => $link('cookiemelding-privacy', '', 'Privacyverklaring aanvullen'),
    );
    $checks[] = array(
        'cat'    => 'Privacyverklaring',
        'title'  => 'Datum bijgewerkt ingevuld',
        'desc'   => 'Bezoekers moeten kunnen zien wanneer de privacyverklaring voor het laatst is bijgewerkt.',
        'ref'    => 'AVG art. 13 &middot; transparantiebeginsel',
        'status' => ! empty( $pv['pv_datum'] ) ? 'ok' : 'warn',
        'fix'    => $link('cookiemelding-privacy', '', 'Datum invullen'),
    );

    // ════════════════════════════════════════════════════════════
    // DEEL 2: Ingebouwde kenmerken — altijd actief, ter informatie
    // ════════════════════════════════════════════════════════════
    $info = array();
    $info[] = array(
        'cat'   => 'AP 9 vuistregels cookiebanners',
        'title' => '3. Toestemming v&oacute;&oacute;r plaatsen cookies',
        'desc'  => 'Scripts en iframes worden geblokkeerd via de output buffer, MutationObserver en Consent Mode v2 (default: denied). Geen tracking cookies v&oacute;&oacute;r consent.',
        'ref'   => 'AP vuistregel 3 &middot; Tw art. 11.7a &middot; ePrivacy art. 5(3)',
    );
    $info[] = array(
        'cat'   => 'AP 9 vuistregels cookiebanners',
        'title' => '5. Granulaire keuze per categorie',
        'desc'  => 'Bezoekers kiezen per categorie (functioneel/analytics/marketing) en zelfs per dienst apart. Dit gaat verder dan de minimumeis.',
        'ref'   => 'AP vuistregel 5 &middot; AVG art. 4(11) &ldquo;specifiek&rdquo;',
    );
    $info[] = array(
        'cat'   => 'AP 9 vuistregels cookiebanners',
        'title' => '6. Geen cookiewall',
        'desc'  => 'De website blijft volledig bruikbaar na weigering. Er is geen blokkade van content of functionaliteit.',
        'ref'   => 'AP vuistregel 6 &middot; AVG overweging 42',
    );
    $info[] = array(
        'cat'   => 'Technische vereisten',
        'title' => 'Cookie verwijdering bij intrekking',
        'desc'  => 'Bij het uitvinken van een categorie of dienst worden de bijbehorende cookies actief verwijderd via JavaScript (alle domein/pad-combinaties).',
        'ref'   => 'AVG art. 17 &middot; EDPB 05/2020 par. 121',
    );
    $info[] = array(
        'cat'   => 'Verantwoordingsplicht (AVG art. 5 lid 2)',
        'title' => 'Consent logging met bewijs',
        'desc'  => 'Elke consent-keuze wordt gelogd met: uniek Consent ID, methode, analytics/marketing status, geanonimiseerd IP (SHA-256 + salt), geanonimiseerde user agent, URL, config hash, en plugin-versie. PDF-bewijs genereerbaar per record.',
        'ref'   => 'AVG art. 7 lid 1 &middot; AVG art. 5 lid 2',
    );
    $info[] = array(
        'cat'   => 'Verantwoordingsplicht (AVG art. 5 lid 2)',
        'title' => 'IP-anonimisering in consent log',
        'desc'  => 'IP-adressen worden gehashed met SHA-256 + WordPress salt. Nooit als plain tekst opgeslagen. User agent gereduceerd tot browser-familie + apparaattype.',
        'ref'   => 'AVG art. 5 lid 1c (dataminimalisatie) &middot; AVG overweging 26',
    );
    $info[] = array(
        'cat'   => 'Verantwoordingsplicht (AVG art. 5 lid 2)',
        'title' => 'Verwerkingsregister exporteerbaar',
        'desc'  => 'CSV-export conform AVG art. 30 met verwerkingsactiviteiten, categorie&euml;n betrokkenen, grondslagen, ontvangers, bewaartermijnen en internationale doorgifte.',
        'ref'   => 'AVG art. 30',
    );
    $info[] = array(
        'cat'   => 'Privacyverklaring',
        'title' => 'Ge&iuml;ntegreerde privacyverklaring-generator',
        'desc'  => 'Uitgebreide generator met: bedrijfsgegevens, DPO, doeleinden + grondslagen, ontvangers, bewaartermijnen, rechten van betrokkenen, internationale doorgifte, klachtrecht AP. Shortcode voor frontend.',
        'ref'   => 'AVG art. 13/14',
    );

    // ── Score berekening (alleen deel 1) ──
    $total = count( $checks );
    $ok    = count( array_filter( $checks, function($c) { return $c['status'] === 'ok'; } ) );
    $warns = count( array_filter( $checks, function($c) { return $c['status'] === 'warn'; } ) );
    $fails = count( array_filter( $checks, function($c) { return $c['status'] === 'fail'; } ) );
    $pct   = $total > 0 ? round( $ok / $total * 100 ) : 0;
    $score_color = $fails > 0 ? '#b32d2e' : ( $warns > 2 ? '#996800' : '#00a32a' );
    $score_label = $fails > 0 ? 'Kritieke punten aanwezig' : ( $warns > 0 ? 'Bijna compliant &mdash; ' . $warns . ' aandachtspunt' . ( $warns > 1 ? 'en' : '' ) : 'Volledig compliant' );

    // Groepeer per categorie
    $cats1 = array(); foreach ( $checks as $c ) { $cats1[ $c['cat'] ][] = $c; }
    $cats2 = array(); foreach ( $info   as $c ) { $cats2[ $c['cat'] ][] = $c; }
    ?>
    <!-- Scorebalk -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;gap:24px;flex-wrap:wrap">
        <div style="flex-shrink:0;text-align:center">
            <div style="font-size:42px;font-weight:700;line-height:1;color:<?php echo esc_attr($score_color); ?>"><?php echo esc_html($pct); ?>%</div>
            <div style="font-size:12px;color:#787c82;margin-top:4px"><?php echo esc_html($ok . '/' . $total); ?> checks</div>
        </div>
        <div style="flex:1;min-width:200px">
            <div style="font-size:16px;font-weight:600;color:<?php echo esc_attr($score_color); ?>;margin-bottom:6px"><?php echo $score_label; ?></div>
            <div style="background:#f0f0f1;border-radius:4px;height:10px;overflow:hidden">
                <div style="background:<?php echo esc_attr($score_color); ?>;height:100%;width:<?php echo esc_html($pct); ?>%;border-radius:4px;transition:width .4s"></div>
            </div>
            <div style="display:flex;gap:16px;margin-top:8px;font-size:12px">
                <span style="color:#00a32a">&#10003; <?php echo esc_html($ok); ?> geslaagd</span>
                <?php if ( $warns ) : ?><span style="color:#996800">&#9888; <?php echo esc_html($warns); ?> aandachtspunt<?php echo $warns > 1 ? 'en' : ''; ?></span><?php endif; ?>
                <?php if ( $fails ) : ?><span style="color:#b32d2e">&#10007; <?php echo esc_html($fails); ?> kritiek</span><?php endif; ?>
            </div>
        </div>
        <div style="flex-shrink:0">
            <button type="button" class="button" onclick="window.location.reload()">&#8635; Opnieuw controleren</button>
        </div>
    </div>

    <!-- DEEL 1: Instelbare checks -->
    <h3 style="font-size:14px;font-weight:600;color:#1d2327;margin:24px 0 12px;padding:0">Uw instellingen</h3>
    <p style="font-size:12px;color:#646970;margin:0 0 16px">Deze checks zijn gebaseerd op uw huidige plugin-instellingen. U kunt de score verbeteren door de aanbevolen aanpassingen door te voeren.</p>

    <?php foreach ( $cats1 as $cat_name => $cat_checks ) : ?>
    <div class="cm-group" style="margin-bottom:16px">
        <h3 class="cm-group-title"><?php echo $cat_name; ?></h3>
        <table style="width:100%;border-collapse:collapse">
            <?php foreach ( $cat_checks as $check ) :
                $icon  = $check['status'] === 'ok' ? '&#10003;' : ( $check['status'] === 'fail' ? '&#10007;' : '&#9888;' );
                $color = $check['status'] === 'ok' ? '#00a32a' : ( $check['status'] === 'fail' ? '#b32d2e' : '#996800' );
                $bg    = $check['status'] === 'ok' ? '#f0faf0' : ( $check['status'] === 'fail' ? '#fcf0f1' : '#fef8ee' );
            ?>
            <tr style="border-bottom:1px solid #f0f0f1">
                <td style="width:36px;padding:12px 8px 12px 16px;vertical-align:top">
                    <span style="display:inline-flex;width:24px;height:24px;border-radius:50%;background:<?php echo esc_attr($bg); ?>;color:<?php echo esc_attr($color); ?>;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0"><?php echo $icon; ?></span>
                </td>
                <td style="padding:12px 8px;vertical-align:top">
                    <div style="font-weight:600;font-size:13px;color:#1d2327;margin-bottom:2px"><?php echo $check['title']; ?></div>
                    <div style="font-size:12px;color:#646970;line-height:1.5"><?php echo $check['desc']; ?></div>
                    <?php if ( ! empty($check['ref']) ) : ?>
                    <div style="font-size:11px;color:#a7aaad;margin-top:3px"><?php echo $check['ref']; ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty($check['detail']) ) : ?>
                    <div style="font-size:12px;color:<?php echo esc_attr($color); ?>;margin-top:4px;font-weight:500"><?php echo $check['detail']; ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:12px 16px 12px 8px;vertical-align:middle;text-align:right;white-space:nowrap;font-size:12px">
                    <?php if ( $check['status'] !== 'ok' ) echo $check['fix']; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>

    <!-- DEEL 2: Ingebouwde kenmerken -->
    <h3 style="font-size:14px;font-weight:600;color:#1d2327;margin:32px 0 12px;padding:0">Ingebouwd in Cookiebaas</h3>
    <p style="font-size:12px;color:#646970;margin:0 0 16px">Deze kenmerken zijn standaard actief en vereisen geen configuratie. Ze worden niet meegenomen in de score.</p>

    <?php foreach ( $cats2 as $cat_name => $cat_checks ) : ?>
    <div class="cm-group" style="margin-bottom:16px">
        <h3 class="cm-group-title"><?php echo $cat_name; ?></h3>
        <table style="width:100%;border-collapse:collapse">
            <?php foreach ( $cat_checks as $item ) : ?>
            <tr style="border-bottom:1px solid #f0f0f1">
                <td style="width:36px;padding:12px 8px 12px 16px;vertical-align:top">
                    <span style="display:inline-flex;width:24px;height:24px;border-radius:50%;background:#e6f1fb;color:#2271b1;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">&#8505;</span>
                </td>
                <td style="padding:12px 8px;vertical-align:top">
                    <div style="font-weight:600;font-size:13px;color:#1d2327;margin-bottom:2px"><?php echo $item['title']; ?></div>
                    <div style="font-size:12px;color:#646970;line-height:1.5"><?php echo $item['desc']; ?></div>
                    <?php if ( ! empty($item['ref']) ) : ?>
                    <div style="font-size:11px;color:#a7aaad;margin-top:3px"><?php echo $item['ref']; ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>

    <p style="font-size:12px;color:#787c82;margin-top:16px;padding:10px 16px;background:#f6f7f7;border-left:3px solid #c3c4c7;border-radius:2px">
        <strong>Disclaimer:</strong> Deze compliance-check is gebaseerd op de AP 9 vuistregels, EDPB-richtlijnen en AVG artikelen 5, 7 en 13.
        Een geslaagde check geeft g&eacute;&eacute;n juridische garantie. Raadpleeg altijd een juridisch adviseur.
        <a href="<?php echo esc_url( admin_url('admin.php?page=cookiemelding-beheer#tab=info') ); ?>" onclick="var t=jQuery('.cm-nav-tabs .nav-tab[data-tab=info]');if(t.length){t.click();window.scrollTo(0,0);return false;}">Lees de volledige disclaimer &rarr;</a>
    </p>
    <?php
}

function cm_render_exportimport_content() {
    ?>
    <div class="cm-group">
        <h3 class="cm-group-title">Export</h3>
        <div style="padding:16px 20px">
            <p style="margin:0 0 6px">Download alle instellingen als een JSON-bestand. Gebruik dit als backup of om instellingen over te zetten naar een andere website.</p>
            <p style="margin:0 0 14px"><strong>Wat wordt ge&euml;xporteerd:</strong> plugin-instellingen, cookielijst, privacyverklaring. De consent-log en de Open Cookie Database worden niet meegenomen.</p>
            <button type="button" class="button button-primary" id="cm-export-btn">&#8659;&nbsp; Instellingen exporteren (.json)</button>
            <span id="cm-export-status" style="margin-left:12px;font-size:13px"></span>
        </div>
    </div>
    <div class="cm-group">
        <h3 class="cm-group-title">Verwerkingsregister <span style="font-size:11px;font-weight:400;color:#787c82">&mdash; AVG artikel 30</span></h3>
        <div style="padding:16px 20px">
            <p style="margin:0 0 10px">Genereer een verwerkingsregister op basis van uw privacyverklaring-instellingen.</p>
            <button type="button" class="button button-primary" id="cm-export-register-btn">&#8659;&nbsp; Verwerkingsregister downloaden (.csv)</button>
            <span id="cm-export-register-status" style="margin-left:12px;font-size:13px"></span>
        </div>
    </div>
    <div class="cm-group">
        <h3 class="cm-group-title">Import</h3>
        <div style="padding:16px 20px">
            <p style="margin:0 0 14px">Importeer een eerder ge&euml;xporteerd JSON-bestand. <strong>Let op:</strong> alle huidige instellingen worden overschreven.</p>
            <label id="cm-import-dropzone" for="cm-import-file" style="display:block;border:2px dashed #c3c4c7;border-radius:6px;padding:40px 24px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:12px">
                <span style="font-size:36px;display:block;margin-bottom:10px;line-height:1">&#8679;</span>
                <span id="cm-import-dropzone-label" style="color:#646970;font-size:14px">Sleep een .json bestand hierheen of <strong>klik om te bladeren</strong></span>
                <input type="file" id="cm-import-file" accept=".json,application/json" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none">
            </label>
            <div id="cm-import-preview" style="display:none;margin-bottom:14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:14px 16px;font-size:13px">
                <strong>Bestand geladen:</strong> <span id="cm-import-filename"></span><br>
                <span id="cm-import-summary" style="color:#646970;margin-top:4px;display:block"></span>
            </div>
            <button type="button" class="button button-primary" id="cm-import-btn" disabled>&#8593;&nbsp; Importeren &amp; overschrijven</button>
            <span id="cm-import-status" style="margin-left:12px;font-size:13px"></span>
        </div>
    </div>
    <?php
}

function cm_render_reset_content() {
    ?>
    <div class="cm-group" style="border-color:#d63638">
        <h3 class="cm-group-title" style="background:#fef7f7;border-color:#f5c6c7;color:#d63638">&#9888; Google integratie wissen</h3>
        <div style="padding:20px">
            <p style="margin:0 0 10px">Wist de ingevulde <strong>GA4 Measurement ID</strong> en <strong>GTM Container ID</strong>.</p>
            <button type="button" class="button" id="cm-reset-google" style="color:#d63638;border-color:#d63638;font-weight:600">Google IDs wissen</button>
            <span id="cm-reset-google-status" style="margin-left:12px;font-size:13px"></span>
        </div>
    </div>
    <div class="cm-group" style="border-color:#d63638">
        <h3 class="cm-group-title" style="background:#fef7f7;border-color:#f5c6c7;color:#d63638">&#9888; Selectief resetten</h3>
        <div style="padding:20px">
            <p style="margin:0 0 14px">Kies wat u wilt resetten. U krijgt eerst een samenvatting te zien v&oacute;&oacute;r er iets wordt gewist.</p>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
                <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                    <input type="checkbox" name="cm_reset_item" value="settings" id="cr-settings" style="margin-top:2px">
                    <span><strong>Instellingen</strong><span style="display:block;font-size:12px;color:#646970;margin-top:2px">Alle kleur-, tekst- en gedrag-instellingen terug naar standaard.</span></span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                    <input type="checkbox" name="cm_reset_item" value="cookielist" id="cr-cookielist" style="margin-top:2px">
                    <span><strong>Cookielijst</strong><span style="display:block;font-size:12px;color:#646970;margin-top:2px">Alle handmatig toegevoegde cookies worden verwijderd.</span></span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                    <input type="checkbox" name="cm_reset_item" value="privacy" id="cr-privacy" style="margin-top:2px">
                    <span><strong>Privacyverklaring</strong><span style="display:block;font-size:12px;color:#646970;margin-top:2px">Alle privacyverklaring-gegevens worden gewist.</span></span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                    <input type="checkbox" name="cm_reset_item" value="log" id="cr-log" style="margin-top:2px">
                    <span><strong>Consent log</strong><span style="display:block;font-size:12px;color:#646970;margin-top:2px">Alle consent-registraties worden permanent verwijderd.</span></span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;cursor:pointer">
                    <input type="checkbox" name="cm_reset_item" value="consent_data" id="cr-consent" style="margin-top:2px">
                    <span><strong>Consent data wissen</strong><span style="display:block;font-size:12px;color:#646970;margin-top:2px">Verhoogt de consent-versie zodat alle bezoekers opnieuw de banner zien.</span></span>
                </label>
            </div>
            <button type="button" class="button" id="cm-reset-selected" style="color:#d63638;border-color:#d63638;font-weight:600">Geselecteerde items resetten</button>
            <span id="cm-reset-selected-status" style="margin-left:12px;font-size:13px"></span>
        </div>
    </div>
    <div class="cm-group" style="border-color:#8a0000;background:#fff5f5">
        <h3 class="cm-group-title" style="background:#fde8e8;border-color:#f5b8b8;color:#8a0000">&#9888;&#9888; Alles resetten</h3>
        <div style="padding:20px">
            <p style="margin:0 0 10px">Reset <strong>alles</strong> in &eacute;&eacute;n keer. <strong>Dit kan niet ongedaan worden gemaakt.</strong></p>
            <button type="button" class="button" id="cm-reset-all" style="background:#8a0000;color:#fff;border-color:#8a0000;font-weight:700">&#x26A0; Alles resetten</button>
            <span id="cm-reset-all-status" style="margin-left:12px;font-size:13px"></span>
        </div>
    </div>
    <?php
}

function cm_render_help_content() {
    ?>
    <div class="cm-group">
        <h3 class="cm-group-title">Plugin informatie</h3>
        <table class="form-table cm-form-table"><tbody>
        <tr><th>Versie</th><td><?php echo esc_html(CM_VERSION); ?></td></tr>
        <tr><th>Gemaakt door</th><td><a href="https://www.ruudvdheijden.nl/" target="_blank"><strong>Ruud van der Heijden</strong></a></td></tr>
        <tr><th>Cookie naam</th><td><code>cc_cm_consent</code></td></tr>
        <tr><th>AVG-compliant</th><td>Ja &mdash; opt-in, geen pre-aangevinkte marketing cookies</td></tr>
        </tbody></table>
    </div>
    <div class="cm-group">
        <h3 class="cm-group-title">Snel aan de slag</h3>
        <div style="padding:16px 20px;line-height:1.8">
            <ol style="margin:0;padding-left:18px">
                <li>Ga naar <strong>Instellingen &rarr; Google</strong> en vul uw GA4 of GTM ID in.</li>
                <li>Pas kleuren, teksten en gedrag aan naar uw huisstijl.</li>
                <li>Ga naar <strong>Cookies &amp; scan</strong> &rarr; laad de Open Cookie Database en voer een scan uit.</li>
                <li>Vul de <strong>Privacyverklaring</strong> in en plaats de shortcode <code>[cookiebaas_privacy]</code>.</li>
                <li>Test: verwijder het <code>cc_cm_consent</code> cookie en controleer in F12 dat cookies pas verschijnen na akkoord.</li>
            </ol>
        </div>
    </div>
    <div class="cm-group">
        <h3 class="cm-group-title">Shortcodes</h3>
        <table class="form-table cm-form-table"><tbody>
        <tr><th>Volledige privacyverklaring</th><td><code>[cookiebaas_privacy]</code></td></tr>
        <tr><th>Alleen cookieparagraaf</th><td><code>[cookiebaas_cookies]</code></td></tr>
        </tbody></table>
    </div>
    <div class="cm-group" style="border-color:#b32d2e">
        <h3 class="cm-group-title" style="background:#fef7f7;border-color:#f5c6c7;color:#b32d2e">Disclaimer &amp; aansprakelijkheid</h3>
        <div style="padding:16px 20px;line-height:1.9;font-size:13px;color:#3c434a">
            <p style="margin:0 0 12px"><strong>1. Geen juridisch advies</strong><br>
            De Cookiebaas plugin is een technisch hulpmiddel en biedt geen juridisch advies. De plugin vervangt op geen enkele wijze de noodzaak om een gekwalificeerde juridisch adviseur te raadplegen over uw specifieke situatie met betrekking tot de AVG/GDPR, de ePrivacy-richtlijn, de Telecommunicatiewet of andere toepasselijke wet- en regelgeving. Het gebruik van deze plugin garandeert niet dat uw website voldoet aan geldende privacywetgeving.</p>

            <p style="margin:0 0 12px"><strong>2. &ldquo;Zoals beschikbaar&rdquo;</strong><br>
            De Cookiebaas plugin wordt aangeboden <strong>&ldquo;as is&rdquo;</strong> en <strong>&ldquo;as available&rdquo;</strong>, zonder enige garantie van welke aard dan ook, uitdrukkelijk noch stilzwijgend. Dit omvat, maar is niet beperkt tot, garanties van verkoopbaarheid, geschiktheid voor een bepaald doel, niet-inbreuk, juistheid, volledigheid, of ononderbroken en foutloze werking.</p>

            <p style="margin:0 0 12px"><strong>3. Beperking van aansprakelijkheid</strong><br>
            Ruud van der Heijden en eventuele bijdragers zijn in geen geval aansprakelijk voor enige directe, indirecte, incidentele, speciale, gevolg- of voorbeeldschade (inclusief maar niet beperkt tot boetes van toezichthouders, verlies van gegevens, gederfde winst, bedrijfsonderbreking of reputatieschade) die voortvloeit uit of verband houdt met het gebruik of het onvermogen tot gebruik van deze plugin, zelfs indien op de hoogte gesteld van de mogelijkheid van dergelijke schade.</p>

            <p style="margin:0 0 12px"><strong>4. Verantwoordelijkheid van de gebruiker</strong><br>
            De website-eigenaar blijft te allen tijde zelf verantwoordelijk voor de naleving van privacywetgeving. Dit omvat onder meer: het correct configureren van de plugin, het actueel houden van de cookielijst en privacyverklaring, het testen of cookies daadwerkelijk geblokkeerd worden v&oacute;&oacute;r consent, het inschakelen van een juridisch adviseur bij twijfel, en het periodiek controleren van de compliance check.</p>

            <p style="margin:0 0 12px"><strong>5. Geen garantie op compliance</strong><br>
            Hoewel de Cookiebaas plugin is ontworpen met de AVG, EDPB-richtlijnen en AP-handhavingscriteria als uitgangspunt, kan de ontwikkelaar niet garanderen dat de plugin in alle situaties en jurisdicties volledige compliance biedt. Wet- en regelgeving verandert regelmatig en de interpretatie ervan kan per toezichthouder en per rechtsgebied verschillen.</p>

            <p style="margin:0 0 12px"><strong>6. Diensten van derden</strong><br>
            De plugin interageert met diensten van derden (Google Analytics, Google Tag Manager, YouTube, Vimeo, Meta/Facebook, etc.). De ontwikkelaar heeft geen controle over en is niet verantwoordelijk voor het gedrag, de cookiepraktijken of het privacybeleid van deze diensten. Het is de verantwoordelijkheid van de website-eigenaar om te controleren of het gebruik van deze diensten in overeenstemming is met de toepasselijke wetgeving.</p>

            <p style="margin:0 0 12px"><strong>7. Updates en ondersteuning</strong><br>
            Er is geen verplichting tot het leveren van updates, bugfixes, beveiligingspatches of ondersteuning. Eventuele updates worden naar eigen inzicht van de ontwikkelaar beschikbaar gesteld.</p>

            <p style="margin:0"><strong>8. Aanvaarding</strong><br>
            Door deze plugin te installeren, te activeren en/of te gebruiken, verklaart u dat u deze disclaimer en de daarin vervatte beperkingen van aansprakelijkheid hebt gelezen, begrepen en aanvaard. Indien u niet akkoord gaat met deze voorwaarden, dient u de plugin onmiddellijk te deactiveren en te verwijderen.</p>
        </div>
    </div>
    <div class="cm-group">
        <h3 class="cm-group-title">Contact &amp; ondersteuning</h3>
        <div style="padding:16px 20px;line-height:1.8">
            <p style="margin:0">Voor vragen: <a href="https://www.ruudvdheijden.nl/" target="_blank">ruudvdheijden.nl</a>.</p>
        </div>
    </div>
    <?php
}
