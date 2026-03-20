<?php
/**
 * Plugin Name: Cookiebaas
 * Plugin URI:  https://www.ruudvdheijden.nl/
 * Description: Cookiemelding plugin volgens AVG/GDPR-conformiteit met Google Consent Mode (v2) integratie en privacyverklaring generator.
 * Version:     1.4.1
 * Author:      Ruud van der Heijden
 * Author URI:  https://www.ruudvdheijden.nl/
 * License:     GPL-2.0+
 * Text Domain: cookiemelding
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CM_VERSION',     '1.4.1' );
define( 'CM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once CM_PLUGIN_DIR . 'includes/defaults.php';
require_once CM_PLUGIN_DIR . 'includes/admin.php';
require_once CM_PLUGIN_DIR . 'includes/frontend.php';
require_once CM_PLUGIN_DIR . 'includes/privacy.php';
require_once CM_PLUGIN_DIR . 'includes/updater.php';

// GitHub Updater — controleer op nieuwe versies via GitHub Releases
// Pas de gebruikersnaam en repository-naam hieronder aan.
if ( is_admin() ) {
    new CM_GitHub_Updater(
        __FILE__,
        'ruudvanderheijden',   // ← jouw GitHub gebruikersnaam
        'cookiebaas'           // ← jouw GitHub repository naam
    );
}

register_activation_hook( __FILE__, 'cm_activate' );
function cm_activate() {
    if ( ! get_option( 'cm_settings' ) ) {
        update_option( 'cm_settings', cm_default_settings() );
    }
    cm_create_log_table();
    cm_create_cookie_db_table();
}

function cm_create_log_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'cm_consent_log';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        consent_id      VARCHAR(36)  NOT NULL DEFAULT '',
        session_id      VARCHAR(64)  NOT NULL,
        analytics       TINYINT(1)   NOT NULL DEFAULT 0,
        marketing       TINYINT(1)   NOT NULL DEFAULT 0,
        method          VARCHAR(20)  NOT NULL DEFAULT '',
        ip_hash         VARCHAR(64)  NOT NULL DEFAULT '',
        user_agent      VARCHAR(60)  NOT NULL DEFAULT '',
        url             VARCHAR(500) NOT NULL DEFAULT '',
        config_hash     VARCHAR(16)  NOT NULL DEFAULT '',
        plugin_version  VARCHAR(16)  NOT NULL DEFAULT '',
        created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY consent_id (consent_id),
        KEY session_id (session_id),
        KEY created_at (created_at)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Upgrade bestaande tabellen — voeg ontbrekende kolommen toe
    $cols = $wpdb->get_col( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
        DB_NAME, $table
    ) );
    if ( ! in_array( 'consent_id', $cols ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `consent_id` VARCHAR(36) NOT NULL DEFAULT '' AFTER `id`, ADD KEY `consent_id` (`consent_id`)" );
    }
    if ( ! in_array( 'config_hash', $cols ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `config_hash` VARCHAR(16) NOT NULL DEFAULT '' AFTER `url`" );
    }
    if ( ! in_array( 'plugin_version', $cols ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `plugin_version` VARCHAR(16) NOT NULL DEFAULT '' AFTER `config_hash`" );
    }
}

function cm_create_cookie_db_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'cm_cookie_db';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        cookie_id   VARCHAR(64)  NOT NULL DEFAULT '',
        platform    VARCHAR(120) NOT NULL DEFAULT '',
        category    VARCHAR(30)  NOT NULL DEFAULT 'functional',
        cookie_name VARCHAR(120) NOT NULL DEFAULT '',
        domain      VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT         NOT NULL,
        retention   VARCHAR(80)  NOT NULL DEFAULT '',
        controller  VARCHAR(120) NOT NULL DEFAULT '',
        privacy_url VARCHAR(500) NOT NULL DEFAULT '',
        wildcard    TINYINT(1)   NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY cookie_name (cookie_name),
        KEY category (category)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// Maak tabellen ook aan bij update (voor bestaande installaties)
// en merge nieuwe defaults in zonder bestaande instellingen te overschrijven
add_action( 'plugins_loaded', function() {
    cm_create_log_table();
    cm_create_cookie_db_table();

    // Versie-upgrade: nieuwe instellingen toevoegen zonder bestaande te overschrijven
    $stored_version = get_option( 'cm_version', '0' );
    if ( version_compare( $stored_version, CM_VERSION, '<' ) ) {
        $existing = get_option( 'cm_settings', array() );
        if ( is_array( $existing ) && ! empty( $existing ) ) {
            $merged = array_merge( cm_default_settings(), $existing );

            // v1.2.2: reject-kleuren forceren naar nieuwe EDPB-conforme defaults
            // (gelijk aan akkoord-knop) als ze nog de oude lichtgrijze waarden hebben
            if ( version_compare( $stored_version, '1.2.2', '<' ) ) {
                $old_reject_defaults = array( '#f5f2ee', '#e8e4de', '#d5d0c8', '' );
                if ( in_array( $merged['color_reject_bg']        ?? '', $old_reject_defaults, true ) ) $merged['color_reject_bg']        = '#111111';
                if ( in_array( $merged['color_reject_hover_bg']  ?? '', $old_reject_defaults, true ) ) $merged['color_reject_hover_bg']  = '#0091ff';
                if ( in_array( $merged['color_reject_text']      ?? '', array('#333333','#555555'), true ) ) $merged['color_reject_text']  = '#ffffff';
                if ( in_array( $merged['color_reject_hover_text']?? '', array('#111111',''), true ) )   $merged['color_reject_hover_text']= '#ffffff';
                if ( in_array( $merged['color_reject_border']    ?? '', $old_reject_defaults, true ) ) $merged['color_reject_border']    = '#111111';
                // Dark mode reject — oud: donkergrijs, nieuw: gelijk aan akkoord
                if ( in_array( $merged['dm_reject_bg']           ?? '', array('#2a2a2a',''), true ) ) $merged['dm_reject_bg']           = '#f2f2f2';
                if ( in_array( $merged['dm_reject_hover_bg']     ?? '', array('#3a3a3a',''), true ) ) $merged['dm_reject_hover_bg']     = '#0091ff';
                if ( in_array( $merged['dm_reject_text']         ?? '', array('#c0c0c0',''), true ) ) $merged['dm_reject_text']         = '#111111';
                if ( in_array( $merged['dm_reject_hover_text']   ?? '', array('#f2f2f2',''), true ) ) $merged['dm_reject_hover_text']   = '#ffffff';
            }

            // v1.2.3: countdown timer fix en statistieken padding update
            // Geen database migraties nodig - alleen JS/CSS verbeteringen

            // v1.2.4: Embed Blocker — blokkeert iframes (YouTube, Vimeo, etc.) tot consent
            // Nieuwe instelling embed_blocker_enabled + placeholder teksten worden via merge toegevoegd

            // v1.3.0: Admin redesign — 9 pagina's samengevoegd naar 5, WordPress-native kleuren
            // Herplan cron naar 12:00u lokale tijd
            $old_ts = wp_next_scheduled('cm_log_retention_cron');
            if ( $old_ts ) {
                wp_unschedule_event( $old_ts, 'cm_log_retention_cron' );
            }
            // Embed placeholder titel bijwerken als die nog de oude standaard is
            if ( isset($merged['txt_embed_title']) && $merged['txt_embed_title'] === 'Externe inhoud geblokkeerd' ) {
                $merged['txt_embed_title'] = 'Accepteer de cookies om de video te bekijken';
            }

            // v1.4.0: Banner positie-keuze (layout tab)
            // Nieuwe instelling banner_position wordt via defaults merge automatisch toegevoegd

            update_option( 'cm_settings', $merged );
        }
        update_option( 'cm_version', CM_VERSION );
    }

    // Cron plannen als log-retentie actief is (altijd om 12:00)
    cm_maybe_schedule_retention_cron();
});

/* ================================================================
   LOG RETENTIE — automatisch opschonen via WordPress cron
================================================================ */

function cm_maybe_schedule_retention_cron() {
    $months = (int) cm_get('log_retention_months');
    if ( $months > 0 ) {
        $existing = wp_next_scheduled('cm_log_retention_cron');
        if ( ! $existing ) {
            // Plan om 12:00 lokale tijd (UTC offset meegerekend)
            $noon_local = strtotime( 'today 12:00', current_time('timestamp') );
            // Als 12:00 vandaag al geweest is, plan morgen 12:00
            if ( $noon_local <= time() ) {
                $noon_local = strtotime( 'tomorrow 12:00', current_time('timestamp') );
            }
            // Converteer lokale tijd naar UTC voor wp_schedule_event
            $utc_offset = (int) get_option('gmt_offset') * HOUR_IN_SECONDS;
            $noon_utc = $noon_local - $utc_offset;
            wp_schedule_event( $noon_utc, 'daily', 'cm_log_retention_cron' );
        }
    } else {
        // Retentie uitgeschakeld — cron verwijderen als die er nog staat
        $timestamp = wp_next_scheduled('cm_log_retention_cron');
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'cm_log_retention_cron' );
        }
    }
}

add_action( 'cm_log_retention_cron', 'cm_run_log_retention' );
function cm_run_log_retention() {
    $months = (int) cm_get('log_retention_months');
    if ( $months <= 0 ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'cm_consent_log';

    // Verwijder logs ouder dan ingesteld aantal maanden
    $deleted = $wpdb->query( $wpdb->prepare(
        "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d MONTH)",
        $months
    ) );

    // Log de opschoning in de WordPress debug log
    if ( defined('WP_DEBUG') && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf( '[Cookiebaas] Log retentie uitgevoerd: %d rijen verwijderd (ouder dan %d maanden).', (int) $deleted, $months ) );
    }
}

// Cron verwijderen bij deactivatie
register_deactivation_hook( __FILE__, function() {
    $timestamp = wp_next_scheduled('cm_log_retention_cron');
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'cm_log_retention_cron' );
    }
    // Ook auto-scan cron verwijderen
    $ts2 = wp_next_scheduled('cm_auto_scan_cron');
    if ( $ts2 ) wp_unschedule_event( $ts2, 'cm_auto_scan_cron' );
});

/* ================================================================
   AUTOMATISCHE COOKIE SCAN — cron + scheduling
================================================================ */

/**
 * Plan de auto-scan cron op basis van de ingestelde interval.
 * Wordt aangeroepen na het opslaan van de scan-instellingen.
 */
function cm_maybe_schedule_auto_scan_cron() {
    $mode     = cm_get('auto_scan_mode');
    $interval = max( 1, (int) cm_get('auto_scan_interval') );
    $hook     = 'cm_auto_scan_cron';

    if ( $mode === 'off' ) {
        // Uitschakelen — bestaande cron verwijderen
        $ts = wp_next_scheduled( $hook );
        if ( $ts ) wp_unschedule_event( $ts, $hook );
        delete_option( 'cm_auto_scan_next' );
        return;
    }

    // Check of er al een cron gepland staat
    $existing_ts = wp_next_scheduled( $hook );
    
    // Als er nog geen cron is, plan dan nieuwe (eerste keer activeren)
    if ( ! $existing_ts ) {
        $next_ts = time() + ( $interval * DAY_IN_SECONDS );
        wp_schedule_single_event( $next_ts, $hook );
        update_option( 'cm_auto_scan_next', gmdate( 'Y-m-d H:i:s', $next_ts ) );
        return;
    }
    
    // Er is al een cron gepland - synchroniseer de option met de bestaande cron tijd
    update_option( 'cm_auto_scan_next', gmdate( 'Y-m-d H:i:s', $existing_ts ) );
}

// Force reset van de scan timer (voor reset button)
function cm_force_reset_auto_scan_cron() {
    $mode     = cm_get('auto_scan_mode');
    $interval = max( 1, (int) cm_get('auto_scan_interval') );
    $hook     = 'cm_auto_scan_cron';

    if ( $mode === 'off' ) {
        return;
    }

    // Verwijder bestaande cron en plan opnieuw
    $ts = wp_next_scheduled( $hook );
    if ( $ts ) wp_unschedule_event( $ts, $hook );

    // Plan nieuwe cron vanaf nu
    wp_schedule_single_event( time() + ( $interval * DAY_IN_SECONDS ), $hook );
    update_option( 'cm_auto_scan_next', gmdate( 'Y-m-d H:i:s', time() + ( $interval * DAY_IN_SECONDS ) ) );
}

add_action( 'cm_auto_scan_cron', 'cm_run_auto_scan' );
function cm_run_auto_scan() {
    $mode  = cm_get('auto_scan_mode');
    if ( $mode === 'off' ) return;

    update_option( 'cm_auto_scan_last', gmdate('Y-m-d H:i:s') );

    // Voer de scan uit via de bestaande scan-functie
    // We simuleren een AJAX-achtige call door de scan-code direct aan te roepen
    $scan_result = cm_perform_background_scan();
    if ( ! $scan_result ) return;

    $new_cookies     = $scan_result['new_cookies'];     // cookies niet in huidige lijst
    $existing_list   = $scan_result['existing_list'];

    if ( empty( $new_cookies ) ) {
        // Niets nieuws — opnieuw inplannen en klaar
        cm_maybe_schedule_auto_scan_cron();
        return;
    }

    if ( $mode === 'auto' ) {
        // Automatisch toevoegen aan de cookielijst
        $managed = get_option( 'cm_cookie_list', array() );
        if ( ! is_array($managed) ) $managed = array();
        foreach ( $new_cookies as $ck ) {
            $managed[] = $ck;
        }
        update_option( 'cm_cookie_list', $managed );
        update_option( 'cm_auto_scan_last_added', count($new_cookies) );

    } elseif ( $mode === 'notify' ) {
        // Melding per e-mail sturen
        $email   = cm_get('auto_scan_email') ?: get_option('admin_email');
        $site    = get_bloginfo('name') ?: get_bloginfo('url');
        $subject = sprintf( '[%s] Nieuwe cookies gevonden — cookielijst bijwerken', $site );

        $rows = '';
        foreach ( $new_cookies as $ck ) {
            $rows .= sprintf(
                "  • %s (%s) — %s\n",
                $ck['name'],
                $ck['category'],
                $ck['provider'] ?? 'onbekend'
            );
        }

        $body = sprintf(
            "Hallo,\n\nTijdens de automatische cookie scan van %s zijn %d nieuwe cookies gevonden die nog niet in uw cookielijst staan:\n\n%s\n\nU kunt de cookielijst bijwerken via:\n%s\n\nMet vriendelijke groet,\nCookiebaas Plugin",
            $site,
            count($new_cookies),
            $rows,
            admin_url('admin.php?page=cookiemelding-cookies')
        );

        wp_mail( $email, $subject, $body );
        update_option( 'cm_auto_scan_last_found', count($new_cookies) );
    }

    // Opnieuw inplannen voor volgende run
    cm_maybe_schedule_auto_scan_cron();
}

/**
 * Voert een achtergrond cookie scan uit.
 * Haalt de homepage op en detecteert cookies via script-signatures.
 * Geeft array terug met nieuwe_cookies en bestaande lijst.
 */
function cm_perform_background_scan() {
    $url      = home_url('/');
    $response = wp_remote_get( $url, array(
        'timeout'    => 30,
        'user-agent' => 'Mozilla/5.0 (compatible; CookiebaasBot/1.0)',
        'sslverify'  => false,
    ));

    if ( is_wp_error($response) ) return false;

    $body = wp_remote_retrieve_body($response);
    if ( empty($body) ) return false;

    // Hergebruik de script_signatures uit de bestaande scan-functie
    // door de body te laten verwerken via een vereenvoudigde detectie
    $existing_list = get_option( 'cm_cookie_list', array() );
    if ( ! is_array($existing_list) ) $existing_list = array();

    // Bouw een set van bestaande cookienamen voor snelle vergelijking
    $existing_names = array();
    foreach ( $existing_list as $ck ) {
        if ( ! empty($ck['name']) ) $existing_names[ $ck['name'] ] = true;
    }
    // Voeg ook ingebouwde cookies toe
    foreach ( cm_default_cookies() as $ck ) {
        $existing_names[ $ck['name'] ] = true;
    }

    // Detecteer via wp_remote_get de Set-Cookie headers
    $headers     = wp_remote_retrieve_headers($response);
    $set_cookies = array();
    if ( isset($headers['set-cookie']) ) {
        $raw = is_array($headers['set-cookie']) ? $headers['set-cookie'] : array($headers['set-cookie']);
        foreach ( $raw as $cookie_line ) {
            $name = trim( explode('=', $cookie_line)[0] );
            if ( $name ) $set_cookies[] = $name;
        }
    }

    $new_cookies = array();
    foreach ( $set_cookies as $name ) {
        if ( isset($existing_names[$name]) ) continue;
        // Zoek info op via cm_lookup_cookie
        $info = cm_lookup_cookie($name);
        $new_cookies[] = array(
            'name'     => $name,
            'provider' => $info ? ( $info['service'] ?? 'Onbekend' ) : 'Onbekend',
            'purpose'  => $info ? ( $info['description'] ?? '' ) : '',
            'duration' => $info ? ( $info['retention'] ?? '' ) : '',
            'category' => $info ? cm_map_category( $info['category'] ?? 'Functional' ) : 'functional',
        );
        $existing_names[$name] = true;
    }

    return array(
        'new_cookies'   => $new_cookies,
        'existing_list' => $existing_list,
    );
}

// AJAX handler om scan-instellingen op te slaan vanaf de cookielijst-pagina
add_action( 'wp_ajax_cm_save_scan_settings', 'cm_ajax_save_scan_settings' );
function cm_ajax_save_scan_settings() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_die();

    $settings = get_option( 'cm_settings', array() );
    $mode     = sanitize_text_field( $_POST['auto_scan_mode'] ?? 'off' );
    $interval = in_array( (string)($_POST['auto_scan_interval'] ?? '30'), array('10','30','180') )
                ? (string)$_POST['auto_scan_interval'] : '30';
    $email    = sanitize_email( $_POST['auto_scan_email'] ?? '' );

    $settings['auto_scan_mode']     = $mode;
    $settings['auto_scan_interval'] = $interval;
    $settings['auto_scan_email']    = $email;
    update_option( 'cm_settings', $settings );

    // Cron herplannen (alleen als er nog geen timer loopt)
    cm_maybe_schedule_auto_scan_cron();

    $next = get_option('cm_auto_scan_next','');
    $next_ts = $next ? strtotime($next) : 0;
    wp_send_json_success( array(
        'message'        => 'Opgeslagen.',
        'next'           => $next ? date_i18n( get_option('date_format'), $next_ts ) : '',
        'next_ts'        => $next_ts,
        'next_formatted' => $next ? date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $next_ts ) : '',
    ));
}

// AJAX handler voor reset timer button - forceert timer reset
add_action( 'wp_ajax_cm_reset_scan_timer', 'cm_ajax_reset_scan_timer' );
function cm_ajax_reset_scan_timer() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_die();

    $settings = get_option( 'cm_settings', array() );
    $mode     = sanitize_text_field( $_POST['auto_scan_mode'] ?? 'off' );
    $interval = in_array( (string)($_POST['auto_scan_interval'] ?? '30'), array('10','30','180') )
                ? (string)$_POST['auto_scan_interval'] : '30';
    $email    = sanitize_email( $_POST['auto_scan_email'] ?? '' );

    $settings['auto_scan_mode']     = $mode;
    $settings['auto_scan_interval'] = $interval;
    $settings['auto_scan_email']    = $email;
    update_option( 'cm_settings', $settings );

    // Force reset van de timer
    cm_force_reset_auto_scan_cron();

    $next = get_option('cm_auto_scan_next','');
    $next_ts = $next ? strtotime($next) : 0;
    wp_send_json_success( array(
        'message'        => 'Timer gereset.',
        'next'           => $next ? date_i18n( get_option('date_format'), $next_ts ) : '',
        'next_ts'        => $next_ts,
        'next_formatted' => $next ? date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $next_ts ) : '',
    ));
}


/* ================================================================
   REST API — Consent verificatie
   GET  /wp-json/cookiebaas/v1/consent/{consent_id}
   POST /wp-json/cookiebaas/v1/consent  (body: {"consent_id":"..."})

   Authenticatie: WordPress Application Password of API-sleutel
   via header: X-Cookiebaas-Key: {sleutel uit instellingen}

   Retourneert consent-status zonder persoonsgegevens (geen IP, geen UA).
================================================================ */
add_action( 'rest_api_init', function() {

    // GET endpoint — consent opvragen op ID
    register_rest_route( 'cookiebaas/v1', '/consent/(?P<consent_id>[a-f0-9\-]{36})', array(
        'methods'             => 'GET',
        'callback'            => 'cm_rest_get_consent',
        'permission_callback' => 'cm_rest_check_auth',
        'args'                => array(
            'consent_id' => array(
                'required'          => true,
                'validate_callback' => function( $v ) {
                    return (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $v );
                },
            ),
        ),
    ) );

    // POST endpoint — batch opvragen
    register_rest_route( 'cookiebaas/v1', '/consent', array(
        'methods'             => 'POST',
        'callback'            => 'cm_rest_get_consent',
        'permission_callback' => 'cm_rest_check_auth',
    ) );

    // GET endpoint — plugin status / health check (geen auth vereist)
    register_rest_route( 'cookiebaas/v1', '/status', array(
        'methods'             => 'GET',
        'callback'            => function() {
            return rest_ensure_response( array(
                'plugin'  => 'Cookiebaas',
                'version' => CM_VERSION,
                'status'  => 'ok',
            ) );
        },
        'permission_callback' => '__return_true',
    ) );
} );

function cm_rest_check_auth( $request ) {
    // Optie 1: ingelogde gebruiker met manage_options
    if ( current_user_can( 'manage_options' ) ) return true;

    // Optie 2: X-Cookiebaas-Key header
    $api_key     = cm_get( 'api_key' );
    $header_key  = $request->get_header( 'X-Cookiebaas-Key' );
    if ( $api_key && $header_key && hash_equals( $api_key, $header_key ) ) return true;

    // Optie 3: WordPress Application Password (wordt automatisch afgehandeld door WP)
    // als de request al geauthenticeerd is via Basic Auth
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) return true;

    return new WP_Error( 'rest_forbidden', 'Authenticatie vereist. Gebruik X-Cookiebaas-Key header of WordPress Application Password.', array( 'status' => 401 ) );
}

function cm_rest_get_consent( $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'cm_consent_log';

    // Consent ID uit URL-param of POST-body
    $consent_id = $request->get_param( 'consent_id' );
    if ( ! $consent_id && $request->get_method() === 'POST' ) {
        $body       = $request->get_json_params();
        $consent_id = isset( $body['consent_id'] ) ? sanitize_text_field( $body['consent_id'] ) : '';
    }

    if ( ! $consent_id ) {
        return new WP_Error( 'missing_param', 'consent_id is verplicht.', array( 'status' => 400 ) );
    }

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT consent_id, analytics, marketing, method, config_hash, created_at FROM `{$table}` WHERE consent_id = %s LIMIT 1",
        $consent_id
    ), ARRAY_A );

    if ( ! $row ) {
        return new WP_Error( 'not_found', 'Geen consent-record gevonden voor dit ID.', array( 'status' => 404 ) );
    }

    $status_map = array(
        'accept-all' => 'Geaccepteerd',
        'reject-all' => 'Geweigerd',
        'custom'     => 'Aangepast',
        'pageload'   => 'Terugkerend bezoek',
    );

    return rest_ensure_response( array(
        'consent_id'   => $row['consent_id'],
        'status'       => $status_map[ $row['method'] ] ?? $row['method'],
        'method'       => $row['method'],
        'analytics'    => (bool) $row['analytics'],
        'marketing'    => (bool) $row['marketing'],
        'config_hash'  => $row['config_hash'],
        'timestamp'    => $row['created_at'],
        'verified'     => true,
    ) );
}


/* ================================================================
   Integreert met Gereedschappen → Privacy in WordPress admin.
================================================================ */

// Registreer als personal data exporter
add_filter( 'wp_privacy_personal_data_exporters', function( $exporters ) {
    $exporters['cookiebaas'] = array(
        'exporter_friendly_name' => 'Cookiebaas — Consent log',
        'callback'               => 'cm_privacy_exporter',
    );
    return $exporters;
});

function cm_privacy_exporter( $email_address, $page = 1 ) {
    // Consent logs zijn gekoppeld aan IP-hash, niet aan e-mail.
    // We zoeken op ip_hash van het huidige verzoek als benadering.
    // Omdat IP-hashes niet aan e-mail te koppelen zijn, retourneren we
    // een algemene melding over het beleid en de bewaartermijn.
    $export_items = array();
    $export_items[] = array(
        'group_id'          => 'cookiebaas_consent',
        'group_label'       => 'Cookiebaas — Toestemmingsregistratie',
        'group_description' => 'Geanonimiseerde registraties van cookietoestemming. IP-adressen worden als SHA-256 hash opgeslagen en zijn niet herleidbaar naar een persoon.',
        'item_id'           => 'cookiebaas_policy',
        'data'              => array(
            array(
                'name'  => 'Opgeslagen gegevens',
                'value' => 'Consent ID, geanonimiseerde browser-familie, gehashed IP-adres, toestemmingskeuze, tijdstip, pagina-URL.',
            ),
            array(
                'name'  => 'Bewaartermijn',
                'value' => intval( cm_get('log_retention_months') ) > 0
                    ? intval( cm_get('log_retention_months') ) . ' maanden, daarna automatisch verwijderd.'
                    : 'Geen automatische verwijdering ingesteld.',
            ),
            array(
                'name'  => 'Herleidbaarheid',
                'value' => 'IP-adressen zijn gehashed met een unieke site-sleutel en kunnen niet worden teruggeleid naar een persoon of e-mailadres.',
            ),
        ),
    );
    return array(
        'data' => $export_items,
        'done' => true,
    );
}

// Registreer als personal data eraser
add_filter( 'wp_privacy_personal_data_erasers', function( $erasers ) {
    $erasers['cookiebaas'] = array(
        'eraser_friendly_name' => 'Cookiebaas — Consent log',
        'callback'             => 'cm_privacy_eraser',
    );
    return $erasers;
});

function cm_privacy_eraser( $email_address, $page = 1 ) {
    // Consent logs bevatten geen e-mailadressen en zijn niet koppelbaar aan een specifiek e-mailadres.
    // We kunnen niet op e-mail wissen. Retourneer een bericht dat dit uitlegt.
    return array(
        'items_removed'  => false,
        'items_retained' => true,
        'messages'       => array(
            'Cookiebaas slaat geen e-mailadressen op in de consent log. '
            . 'Logs worden opgeslagen met een geanonimiseerd IP-hash en zijn niet koppelbaar aan een e-mailadres. '
            . 'Wilt u alle logs verwijderen? Ga naar Cookiebaas → Reset → Log leegmaken.',
        ),
        'done'           => true,
    );
}

