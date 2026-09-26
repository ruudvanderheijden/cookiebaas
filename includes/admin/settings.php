<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   OPSLAAN — Settings API + type-bewuste sanitizing + cache-purge.
   Gedeeld door de nieuwe admin (options.php) en de oude admin (AJAX),
   tot plan 3 de oude admin verwijdert.
================================================================ */

add_action( 'admin_init', 'cm_admin_register_settings' );
function cm_admin_register_settings() {
    register_setting( 'cookiebaas_settings', 'cm_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'cm_settings_sanitize_callback',
        'show_in_rest'      => false,
    ) );
}

/**
 * Sanitize-callback van register_setting. WordPress roept die bij ELKE
 * update_option('cm_settings') aan (ook migraties, resets, en twee keer bij
 * de eerste opslag), dus: idempotent, en null (option ontbrak in de POST
 * van options.php) laat alles staan.
 */
function cm_settings_sanitize_callback( $input ) {
    $existing = get_option( 'cm_settings', array() );
    $existing = is_array( $existing ) ? $existing : array();
    if ( ! is_array( $input ) ) return $existing;
    return cm_sanitize_settings( $input, $existing );
}

/** Hex zoals klanten het plakken → '#rrggbb' (lowercase), of null als het geen kleur is. */
function cm_normalize_hex( $v ) {
    $v = trim( (string) $v );
    if ( preg_match( '/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/', $v ) ) $v = '#' . $v;
    if ( preg_match( '/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $v, $m ) ) {
        $v = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    }
    return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? strtolower( $v ) : null;
}

/** Meld een ongeldige waarde; de vorige waarde blijft staan. */
function cm_sanitize_reject( array $f, $current ) {
    if ( function_exists( 'add_settings_error' ) ) {
        add_settings_error( 'cm_settings', 'cm-invalid-' . $f['key'], sprintf( 'Ongeldige waarde bij "%s". De vorige waarde is behouden.', $f['label'] ) );
    }
    return (string) $current;
}

/** Sanitize één waarde volgens zijn veld-definitie. Geeft altijd een string terug. */
function cm_sanitize_field_value( array $f, $raw, $current ) {
    if ( isset( $f['sanitize'] ) && is_callable( $f['sanitize'] ) ) {
        return (string) call_user_func( $f['sanitize'], $raw, $current, $f );
    }
    switch ( $f['type'] ) {
        case 'checkbox':
            return (string) $raw === '1' ? '1' : '0';
        case 'number':
            if ( ! is_numeric( $raw ) ) return cm_sanitize_reject( $f, $current );
            $n = (int) round( (float) $raw );
            if ( isset( $f['min'] ) ) $n = max( (int) $f['min'], $n );
            if ( isset( $f['max'] ) ) $n = min( (int) $f['max'], $n );
            return (string) $n;
        case 'radio':
        case 'select':
            return array_key_exists( (string) $raw, cm_admin_field_options( $f ) ) ? (string) $raw : cm_sanitize_reject( $f, $current );
        case 'color':
        case 'color_optional':
            if ( $f['type'] === 'color_optional' && trim( (string) $raw ) === '' ) return '';
            $hex = cm_normalize_hex( $raw );
            return $hex !== null ? $hex : cm_sanitize_reject( $f, $current );
        case 'textarea':
            return sanitize_textarea_field( is_scalar( $raw ) ? (string) $raw : '' );
        case 'html':
            return wp_kses( is_scalar( $raw ) ? (string) $raw : '', isset( $f['allowed'] ) ? $f['allowed'] : cm_html_allowed() );
        case 'code':
            return is_string( $raw ) ? $raw : ''; // frontend sanitized bij het renderen (SVG-whitelist)
        case 'media':
            return esc_url_raw( is_scalar( $raw ) ? (string) $raw : '' );
        case 'multiselect':
        case 'checkboxes':
            return is_array( $raw ) ? implode( ',', array_map( 'sanitize_text_field', cm_csv_list( $raw ) ) ) : sanitize_text_field( $raw );
        default:
            return sanitize_text_field( $raw );
    }
}

/**
 * Sanitize instellingen tegen de defaults (die dienen als whitelist).
 * Gedeeld door opslaan (nieuw en oud) en import.
 *
 * @param array      $input    Ongeslashte invoer (POST of geïmporteerde JSON).
 * @param array      $existing Basis: velden die niet in $input zitten blijven hieruit staan.
 * @param array|null $index    Veld-definities (sleutel → veld); null = het register.
 */
function cm_sanitize_settings( array $input, array $existing, $index = null ) {
    $defaults = cm_default_settings();
    if ( $index === null ) $index = function_exists( 'cm_admin_field_index' ) ? cm_admin_field_index( 'cm_settings' ) : array();

    // Zorg dat alle defaultkeys als fallback aanwezig zijn
    $settings = $existing;
    foreach ( $defaults as $key => $default ) {
        if ( ! array_key_exists( $key, $settings ) ) $settings[ $key ] = $default;
    }

    // Verwerk alleen de velden die in de invoer zitten; de rest blijft intact
    $html_fields = array( 'txt_banner_body', 'txt_prefs_body', 'txt_banner_body_en', 'txt_prefs_body_en' );
    foreach ( $defaults as $key => $default ) {
        if ( ! isset( $input[ $key ] ) ) continue;
        if ( isset( $index[ $key ] ) ) {
            $settings[ $key ] = cm_sanitize_field_value( $index[ $key ], $input[ $key ], $settings[ $key ] );
        } elseif ( in_array( $key, $html_fields, true ) ) {
            $settings[ $key ] = wp_kses( (string) $input[ $key ], cm_html_allowed() );
        } elseif ( $key === 'float_icon_custom_svg' ) {
            // Ongefilterd bewaren — frontend.php sanitized met een strikte tag/attribuut-whitelist bij het renderen
            $settings[ $key ] = is_string( $input[ $key ] ) ? $input[ $key ] : '';
        } elseif ( $key === 'float_icon_image_url' ) {
            $settings[ $key ] = esc_url_raw( (string) $input[ $key ] );
        } else {
            $settings[ $key ] = sanitize_text_field( $input[ $key ] );
        }
    }

    // Icoontype is alleen UI: wis pas bij opslaan wat niet gekozen is
    if ( isset( $input['cm_icon_type'] ) ) {
        $type = (string) $input['cm_icon_type'];
        if ( $type !== 'custom' ) $settings['float_icon_custom_svg'] = '';
        if ( $type !== 'image' )  $settings['float_icon_image_url']  = '';
    }

    // Als google_load_default aanstaat, moet analytics_default ook aanstaan
    // (als string, zoals alle gesanitizede waarden — anders is de sanitizer niet idempotent)
    if ( ! empty( $settings['google_load_default'] ) ) {
        $settings['analytics_default'] = '1';
    }

    return $settings;
}

/**
 * Sanitize een cookielijst (opslaan, import en automatische scan).
 * Verplaatst uit includes/admin.php, ongewijzigd.
 */
function cm_sanitize_cookie_list( array $raw ) {
    $clean = array();
    foreach ( $raw as $ck ) {
        if ( ! is_array( $ck ) ) continue;
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
    return $clean;
}

/* ---- Paginacache legen op één plek: elke inhoudswijziging, uit elke bron ---- */
function cm_purge_page_caches_on_change() {
    if ( function_exists( 'cm_purge_page_caches' ) ) cm_purge_page_caches();
}
foreach ( array( 'cm_settings', 'cm_cookie_list', 'cm_privacy', 'cm_consent_version' ) as $cm_purge_option ) {
    add_action( 'add_option_' . $cm_purge_option,    'cm_purge_page_caches_on_change' );
    add_action( 'update_option_' . $cm_purge_option, 'cm_purge_page_caches_on_change' );
}
unset( $cm_purge_option );
