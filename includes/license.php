<?php
/**
 * Cookiebaas — Licentiebeheer
 *
 * Valideert de licentie bij de licentieserver (cookiebaas.nl).
 * Zonder geldige licentie verschijnt de banner niet.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   CONSTANTEN & OPTIES
   ================================================================ */
function cm_license_api_url() {
    $url = get_option( 'cm_license_api_url', 'https://cookiebaas.nl' );
    return rtrim( $url, '/' );
}

function cm_license_get() {
    return get_option( 'cm_license_data', array(
        'key'          => '',
        'status'       => '',       // active, expired, invalid, ''
        'expires_at'   => '',
        'max_sites'    => 0,
        'domain'       => '',
        'last_check'   => 0,        // unix timestamp
        'last_success' => 0,        // unix timestamp van laatste succesvolle check
    ) );
}

function cm_license_save( $data ) {
    update_option( 'cm_license_data', $data );
}

/**
 * Is de licentie geldig?
 * Geldig = status 'active', niet verlopen, domein gekoppeld.
 *
 * LET OP: dit stuurt NIET de compliance-kern aan. De cookiebanner, de
 * scriptblokkering en Consent Mode werken ALTIJD, ongeacht licentie — een
 * privacyproduct mag zijn bescherming nooit uitzetten bij een betaalprobleem.
 * De licentie gate uitsluitend premium-extra's; gebruik daarvoor de expliciete
 * cm_scan_requires_license()-achtige helpers, niet deze functie in de frontend.
 */
function cm_license_is_valid() {
    return cm_license_data_is_valid( cm_license_get() );
}

/** Geldigheid van opgeslagen licentiegegevens (ook voor oude/nieuwe waarde bij een wijziging). */
function cm_license_data_is_valid( $lic ) {
    $lic = is_array( $lic ) ? $lic : array();
    if ( empty( $lic['key'] ) || ! isset( $lic['status'] ) || $lic['status'] !== 'active' ) return false;
    if ( ! empty( $lic['expires_at'] ) && strtotime( $lic['expires_at'] ) < time() ) return false;
    return true;
}

/**
 * Mag de automatische scan draaien? Automatisch toevoegen en de e-mailmelding
 * zijn premium; zonder geldige licentie slaat de cron ze over. De handmatige
 * scan, de banner, de blokkering en het vastleggen van toestemmingen werken
 * altijd.
 */
function cm_scan_requires_license() {
    return ! cm_license_is_valid();
}

/*
 * De vermelding "Cookiebaas" in de banner hangt af van de licentie. Leeg de
 * paginacache alleen als de geldigheid echt verandert (niet bij elke
 * dagelijkse controle, die last_check bijwerkt).
 */
function cm_license_validity_changed( $old, $new ) {
    return cm_license_data_is_valid( $old ) !== cm_license_data_is_valid( $new );
}
function cm_license_purge_if_changed( $old, $new ) {
    if ( cm_license_validity_changed( $old, $new ) && function_exists( 'cm_purge_page_caches' ) ) cm_purge_page_caches();
}
add_action( 'update_option_cm_license_data', 'cm_license_purge_if_changed', 10, 2 );
add_action( 'add_option_cm_license_data', function ( $name, $value ) { cm_license_purge_if_changed( array(), $value ); }, 10, 2 );
add_action( 'delete_option', function ( $name ) {
    if ( $name === 'cm_license_data' ) cm_license_purge_if_changed( get_option( 'cm_license_data', array() ), array() );
} );

/* ================================================================
   API CALLS
   ================================================================ */
function cm_license_api_call( $endpoint, $params = array() ) {
    $url = cm_license_api_url() . '/wp-json/cookiebaas-license/v1/' . $endpoint;

    $response = wp_remote_post( $url, array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( $params ),
        'timeout' => 15,
    ) );

    if ( is_wp_error( $response ) ) {
        return array( 'success' => false, 'error' => $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! is_array( $body ) ) {
        return array( 'success' => false, 'error' => 'Ongeldig antwoord van licentieserver (HTTP ' . $code . ').' );
    }

    return $body;
}

function cm_license_get_domain() {
    $domain = strtolower( trim( parse_url( home_url(), PHP_URL_HOST ) ) );
    $domain = preg_replace( '/^www\./', '', $domain );
    return $domain;
}

/* ================================================================
   ACTIVEREN
   ================================================================ */
function cm_license_activate( $key ) {
    $domain = cm_license_get_domain();

    // Als er al een actieve licentie is met een andere sleutel: eerst deactiveren
    $existing = cm_license_get();
    if ( ! empty( $existing['key'] ) && $existing['key'] !== $key && $existing['status'] === 'active' ) {
        cm_license_api_call( 'deactivate', array(
            'license_key' => $existing['key'],
            'domain'      => $domain,
        ) );
        $existing['status']       = '';
        $existing['domain']       = '';
        $existing['last_success'] = 0;
        cm_license_save( $existing );
    }

    $result = cm_license_api_call( 'activate', array(
        'license_key' => $key,
        'domain'      => $domain,
    ) );

    $lic = cm_license_get();
    $lic['key']    = $key;
    $lic['domain'] = $domain;

    if ( ! empty( $result['success'] ) ) {
        $lic['status']       = 'active';
        $lic['expires_at']   = $result['expires_at'] ?? '';
        $lic['max_sites']    = $result['max_sites'] ?? 1;
        $lic['last_check']   = time();
        $lic['last_success'] = time();
        cm_license_save( $lic );
        return array( 'success' => true, 'message' => $result['message'] ?? 'Licentie geactiveerd.' );
    }

    $lic['status'] = 'invalid';
    $lic['last_check'] = time();
    cm_license_save( $lic );
    return array( 'success' => false, 'error' => $result['error'] ?? 'Activatie mislukt.' );
}

/* ================================================================
   DEACTIVEREN
   ================================================================ */
function cm_license_deactivate() {
    $lic = cm_license_get();
    if ( empty( $lic['key'] ) ) {
        return array( 'success' => false, 'error' => 'Geen licentiesleutel ingesteld.' );
    }

    $result = cm_license_api_call( 'deactivate', array(
        'license_key' => $lic['key'],
        'domain'      => cm_license_get_domain(),
    ) );

    // Altijd lokaal resetten na deactivatie
    $lic['status']       = '';
    $lic['domain']       = '';
    $lic['last_check']   = time();
    $lic['last_success'] = 0;
    cm_license_save( $lic );

    if ( ! empty( $result['success'] ) ) {
        return array( 'success' => true, 'message' => $result['message'] ?? 'Licentie gedeactiveerd.' );
    }

    return array( 'success' => true, 'remote' => false, 'message' => 'De licentie is op deze website gedeactiveerd, maar de licentieserver was niet bereikbaar. Deactiveer de website eventueel ook in uw account op cookiebaas.nl.' );
}

/* ================================================================
   STATUS CHECK (dagelijks via cron)
   ================================================================ */
function cm_license_check_status() {
    $lic = cm_license_get();
    if ( empty( $lic['key'] ) ) return array();

    $result = cm_license_api_call( 'status', array(
        'license_key' => $lic['key'],
        'domain'      => cm_license_get_domain(),
    ) );

    $lic['last_check'] = time();

    if ( isset( $result['valid'] ) ) {
        if ( $result['valid'] ) {
            $lic['status']       = 'active';
            $lic['expires_at']   = $result['expires_at'] ?? $lic['expires_at'];
            $lic['max_sites']    = $result['max_sites'] ?? $lic['max_sites'];
            $lic['last_success'] = time();
        } else {
            // Licentie niet meer geldig: verlopen, ingetrokken, of domein ontkoppeld
            $lic['status'] = $result['status'] ?? 'invalid';
        }
    }
    // Bij netwerkfouten: behoud huidige status (niet direct blokkeren)
    // Maar als laatste succesvolle check > 3 dagen geleden is, blokkeren
    if ( ! isset( $result['valid'] ) && $lic['last_success'] > 0 ) {
        if ( time() - $lic['last_success'] > 3 * DAY_IN_SECONDS ) {
            $lic['status'] = 'invalid';
        }
    }

    cm_license_save( $lic );
    return $result;
}

/* ================================================================
   CRON — dagelijkse hervalidatie
   ================================================================ */
add_action( 'cm_license_cron', 'cm_license_check_status' );
add_action( 'init', 'cm_license_schedule_cron' );
function cm_license_schedule_cron() {
    if ( ! wp_next_scheduled( 'cm_license_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'cm_license_cron' );
    }
}

