<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   COOKIESCAN — AJAX voor de handmatige scan en de cookiedatabase, en de
   kennisbank die de scan gebruikt. In 3.0 ongewijzigd verhuisd uit de
   oude includes/admin.php.
================================================================ */
/* ================================================================
   OPEN COOKIE DATABASE — IMPORT
================================================================ */
add_action( 'wp_ajax_cm_import_cookie_db', 'cm_ajax_import_cookie_db' );
function cm_ajax_import_cookie_db() {
    cm_admin_verify_ajax( 'import_cookie_db' );

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
    if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        wp_send_json_error( array( 'msg' => 'Download mislukt (HTTP ' . (int) wp_remote_retrieve_response_code( $response ) . '). De bestaande database is niet gewijzigd.' ) );
        return;
    }

    // Eerst alles controleren en inlezen; pas bij een geldig bestand de tabel vervangen
    // (een foutpagina of half bestand zou de database anders leegmaken)
    $parsed = cm_cookie_db_parse( wp_remote_retrieve_body( $response ) );
    if ( count( $parsed['rows'] ) < 500 ) {
        wp_send_json_error( array( 'msg' => 'Het gedownloade bestand lijkt niet op de Open Cookie Database. De bestaande database is niet gewijzigd.' ) );
        return;
    }

    // Zorg dat tabel bestaat
    cm_create_cookie_db_table();

    // Leeg de tabel en herlaad
    $wpdb->query( "TRUNCATE TABLE {$table}" );

    $imported = 0;
    $skipped  = $parsed['skipped'];
    foreach ( $parsed['rows'] as $row ) {
        $wpdb->insert( $table, $row, array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%d') );
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
 * CSV van de Open Cookie Database → rijen voor {prefix}cm_cookie_db. De eerste
 * rij moet de verwachte kop zijn (ID, Platform, Category, …); anders geen rijen.
 * @return array( 'rows' => array, 'skipped' => int )
 */
function cm_cookie_db_parse( $body ) {
    $rows    = array();
    $skipped = 0;
    $header  = null;
    $cat_map = array(
        'Functional'      => 'functional',
        'Analytics'       => 'analytics',
        'Marketing'       => 'marketing',
        'Personalization' => 'functional',
        'Security'        => 'functional',
    );
    foreach ( explode( "\n", str_replace( "\r\n", "\n", (string) $body ) ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        $fields = cm_parse_csv_line( $line );
        if ( ! $fields || count( $fields ) < 9 ) { $skipped++; continue; }
        if ( $header === null ) {
            $header = $fields;
            if ( strcasecmp( trim( $header[0] ), 'ID' ) !== 0 || stripos( $header[3], 'name' ) === false ) return array( 'rows' => array(), 'skipped' => $skipped );
            continue;
        }
        // Kolommen: ID,Platform,Category,Cookie/Data Key name,Domain,Description,Retention period,Data Controller,User Privacy & GDPR Rights Portals,Wildcard match
        $cookie_name = sanitize_text_field( $fields[3] );
        if ( $cookie_name === '' ) { $skipped++; continue; }
        $category_raw = sanitize_text_field( $fields[2] );
        $rows[] = array(
            'cookie_id'   => sanitize_text_field( $fields[0] ),
            'platform'    => sanitize_text_field( $fields[1] ),
            'category'    => isset( $cat_map[ $category_raw ] ) ? $cat_map[ $category_raw ] : 'functional',
            'cookie_name' => $cookie_name,
            'domain'      => sanitize_text_field( $fields[4] ),
            'description' => sanitize_textarea_field( $fields[5] ),
            'retention'   => cm_translate_retention( sanitize_text_field( $fields[6] ) ),
            'controller'  => sanitize_text_field( $fields[7] ),
            'privacy_url' => esc_url_raw( $fields[8] ),
            'wildcard'    => isset( $fields[9] ) ? intval( $fields[9] ) : 0,
        );
    }
    return array( 'rows' => $rows, 'skipped' => $skipped );
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
    cm_admin_verify_ajax( 'scan' );

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
/**
 * Alleen http(s)-adressen van deze website (zelfde host als home_url). Zonder
 * deze controle liet de scan de server elke opgegeven URL ophalen, ook interne
 * adressen (SSRF).
 */
function cm_scan_filter_urls( array $urls ) {
    // Sites met een domein per taal of domeinmapping kunnen extra hosts toestaan (in code, niet via de admin)
    $hosts = array_map( 'strtolower', (array) apply_filters( 'cm_scan_allowed_hosts', array( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) );
    $out   = array();
    foreach ( $urls as $u ) {
        if ( ! is_string( $u ) ) continue;
        $u = esc_url_raw( $u, array( 'http', 'https' ) );
        if ( $u !== '' && in_array( strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) ), $hosts, true ) ) $out[] = $u;
    }
    return array_values( array_unique( $out ) );
}

function cm_ajax_scan_batch() {
    cm_admin_verify_ajax( 'scan' );
    @set_time_limit( 120 );

    $urls = cm_scan_filter_urls( isset( $_POST['urls'] ) ? (array) wp_unslash( $_POST['urls'] ) : array() );
    if ( empty($urls) ) wp_send_json_success( array( 'cookies' => array(), 'scanned' => 0 ) );

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
                if ( cm_cookie_prefix_match( $cookie_name, $pat ) ) {
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
            if ( cm_cookie_prefix_match( $cookie_name, $pat ) ) {
                return array( 'category' => $f[0], 'provider' => $f[1], 'duration' => $f[2], 'description' => $f[3], 'privacy_url' => '' );
            }
        }
        return null;
    };

    $script_signatures = cm_script_signatures();
    $http_cookies   = array();
    $script_cookies = array();
    $pages_scanned  = 0;
    $litespeed_seen = false;

    foreach ( $urls as $url ) {
        // wp_safe_remote_get controleert ook elke doorverwijzing (geen interne adressen)
        $response = wp_safe_remote_get( $url, array(
            'timeout'    => 12,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (compatible; CookieScan/2.0)',
            'sslverify'  => true,
            'redirection'=> 5,
        ));
        if ( is_wp_error( $response ) && strpos( $response->get_error_message(), 'cURL error 60' ) !== false ) {
            // Eigen site met een zelfondertekend certificaat (bijv. lokaal): alleen de eigen host, zie cm_scan_filter_urls
            $response = wp_safe_remote_get( $url, array(
                'timeout' => 12, 'user-agent' => 'CookieScan/2.0', 'sslverify' => false, 'redirection' => 5,
            ));
        }
        if ( is_wp_error($response) ) continue;
        $pages_scanned++;

        $headers = wp_remote_retrieve_headers( $response );
        $body    = wp_remote_retrieve_body( $response );
        if ( isset( $headers['x-litespeed-cache'] ) ) $litespeed_seen = true;

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

    // Omgevingscookies: aantoonbaar aanwezig op deze installatie maar
    // onzichtbaar voor de anonieme crawl (zie cm_server_env_cookies)
    foreach ( cm_server_env_cookies( $litespeed_seen ) as $env_name => $env ) {
        if ( isset($http_cookies[$env_name]) || isset($script_cookies[$env_name]) ) continue;
        $http_cookies[$env_name] = array(
            'name'        => $env_name,
            'type'        => $env[0],
            'provider'    => $env[1],
            'duration'    => $env[2],
            'description' => $env[3],
            'privacy_url' => '',
            'how'         => 'server',
        );
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
 * Prefix-match voor cookiepatronen: sleutels die eindigen op _ of -
 * zijn prefixes (bijv. wordpress_logged_in_ matcht wordpress_logged_in_abc123,
 * wp-settings- matcht wp-settings-3).
 */
function cm_cookie_prefix_match( $cookie_name, $pattern ) {
    $last = substr( $pattern, -1 );
    return ( $last === '_' || $last === '-' ) && strpos( $cookie_name, $pattern ) === 0;
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
        'wordpress_sec_'        => array('functional','WordPress','Sessie','WordPress beveiligingscookie voor ingelogde gebruikers.'),
        'wordpress_test_cookie' => array('functional','WordPress','Sessie','Test of cookies werken in de browser. Wordt gezet op de inlogpagina.'),
        'wp-settings-'          => array('functional','WordPress','1 jaar','WordPress admin-instellingen.'),
        'wp-postpass_'          => array('functional','WordPress','10 dagen','Onthoudt het wachtwoord voor wachtwoordbeveiligde berichten.'),
        'wp_lang'               => array('functional','WordPress','Sessie','Voorkeurstaal op de inlogpagina.'),
        'comment_author_'       => array('functional','WordPress','1 jaar','Onthoudt naam, e-mailadres en website van bezoekers die een reactie achterlaten.'),
        'sbjs_'                 => array('analytics','WooCommerce','Sessie/1 jaar','WooCommerce Order Attribution (sourcebuster): registreert de herkomst van de bezoeker (bron, campagne) voor bestellingstoeschrijving.'),
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
        // Google-cookies op het google.com-domein die meekomen met ingesloten
        // YouTube-content — provider 'YouTube' zodat de embed-detectie ze meeneemt
        'NID'                   => array('marketing','YouTube','6 maanden','Google-cookie (google.com-domein) met voorkeuren en advertentie-instellingen; wordt geplaatst zodra ingesloten Google-content laadt.'),
        '__Secure-ENID'         => array('marketing','YouTube','13 maanden','Beveiligde Google-cookie (google.com-domein) die voorkeuren en eerdere keuzes van de bezoeker onthoudt; komt mee met ingesloten Google-content.'),
        '__Secure-BUCKET'       => array('marketing','YouTube','Onbepaald','Google-cookie (google.com-domein) gerelateerd aan het afspelen en streamen van ingesloten video\'s.'),
    );
    return $cache;
}

/**
 * Cookies die de scan niet via Set-Cookie kan zien maar die op deze
 * installatie aantoonbaar voorkomen (omgevingsdetectie). De anonieme crawl
 * ziet ze nooit omdat ze alleen gezet worden bij inloggen, reageren,
 * winkelwagen-acties of specifieke cache-varianten.
 */
function cm_server_env_cookies( $litespeed_seen = false ) {
    $cookies = array(
        // Elke WordPress-site: inlogpagina + ingelogde gebruikers
        'wordpress_test_cookie' => array('functional','WordPress','Sessie','Controleert of cookies werken in de browser van de bezoeker. Wordt gezet op de inlogpagina.'),
        'wordpress_logged_in_'  => array('functional','WordPress','Sessie','Houdt de inlogstatus bij van ingelogde gebruikers.'),
        'wordpress_sec_'        => array('functional','WordPress','Sessie','Beveiligingscookie voor ingelogde gebruikers.'),
    );

    if ( $litespeed_seen || defined('LSCWP_V') || class_exists('\LiteSpeed\Core') ) {
        $cookies['_lscache_vary'] = array('functional','LiteSpeed Cache','Sessie','Bepaalt welke cache-variant LiteSpeed toont (bijv. ingelogd of uitgelogd). Bevat geen persoonsgegevens.');
    }

    // Reacties open → cookies voor terugkerende reageerders
    if ( function_exists('get_default_comment_status') && get_default_comment_status() === 'open' ) {
        $cookies['comment_author_'] = array('functional','WordPress','1 jaar','Onthoudt naam, e-mailadres en website van bezoekers die een reactie achterlaten.');
    }

    // Wachtwoordbeveiligde berichten aanwezig
    global $wpdb;
    if ( isset( $wpdb->posts ) && $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_password <> '' AND post_status = 'publish' LIMIT 1" ) ) {
        $cookies['wp-postpass_'] = array('functional','WordPress','10 dagen','Onthoudt het wachtwoord voor wachtwoordbeveiligde berichten.');
    }

    // WooCommerce: winkelwagen-cookies + Order Attribution (sbjs_*)
    if ( class_exists('WooCommerce') ) {
        $cookies['woocommerce_cart_hash']     = array('functional','WooCommerce','Sessie','Bijhouden van de winkelwagen (hash).');
        $cookies['woocommerce_items_in_cart'] = array('functional','WooCommerce','Sessie','Bijhouden of er items in de winkelwagen liggen.');
        $cookies['wp_woocommerce_session_']   = array('functional','WooCommerce','2 dagen','Sessie-identifier voor WooCommerce winkelwagen.');
        // Order Attribution staat sinds WooCommerce 8.5 standaard aan
        if ( get_option('woocommerce_feature_order_attribution_enabled', 'yes') !== 'no' ) {
            $attr_desc = 'WooCommerce Order Attribution (sourcebuster): registreert de herkomst van de bezoeker (bron, campagne) voor bestellingstoeschrijving.';
            foreach ( array('sbjs_current' => '6 maanden', 'sbjs_current_add' => '6 maanden', 'sbjs_first' => '6 maanden', 'sbjs_first_add' => '6 maanden', 'sbjs_udata' => '6 maanden', 'sbjs_session' => '30 min', 'sbjs_migrations' => '6 maanden') as $sbjs_name => $sbjs_dur ) {
                $cookies[ $sbjs_name ] = array('analytics','WooCommerce', $sbjs_dur, $attr_desc);
            }
        }
    }

    return $cookies;
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
            array('NID',               'marketing', 'Google voorkeuren/advertenties (google.com)', '6 maanden'),
            array('__Secure-ENID',     'marketing', 'Google voorkeuren, beveiligd (google.com)',   '13 maanden'),
            array('__Secure-BUCKET',   'marketing', 'Google video-streaming (google.com)',         'Onbepaald'),
        ),
    );
    return $cache;
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

