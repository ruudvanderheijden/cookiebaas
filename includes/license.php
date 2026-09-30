<?php
/**
 * Cookiebaas — Licentiebeheer
 *
 * Valideert de licentie bij de licentieserver (cookiebaas.nl).
 * De banner en de blokkering werken altijd; de licentie ontgrendelt alleen
 * premiumfuncties in de admin en haalt de vermelding "Cookiebaas" weg.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   CONSTANTEN & OPTIES
   ================================================================ */
/**
 * Vast adres van de licentieserver (altijd https). Een oude optie uit 2.x die
 * elke beheerder kon zetten, wordt niet meer gebruikt; voor een eigen
 * testserver kan CM_LICENSE_API_URL in wp-config.php gezet worden.
 */
function cm_license_api_url() {
    $url = defined( 'CM_LICENSE_API_URL' ) ? (string) CM_LICENSE_API_URL : 'https://cookiebaas.nl';
    return rtrim( $url, '/' );
}

/** Tekst uit een antwoord van de licentieserver, of de terugvaltekst (een array of object mag nooit doorlopen). */
function cm_license_str( $v, $fallback = '' ) {
    return is_scalar( $v ) && (string) $v !== '' ? (string) $v : (string) $fallback;
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
 * De licentie gate uitsluitend premium-extra's in de admin (en de vermelding
 * "Cookiebaas" via cm_credit_link()); nooit de banner of de blokkering.
 */
function cm_license_is_valid() {
    return cm_license_data_is_valid( cm_license_get() );
}

/** Geldigheid van opgeslagen licentiegegevens (ook voor oude/nieuwe waarde bij een wijziging). */
function cm_license_data_is_valid( $lic ) {
    $lic = is_array( $lic ) ? $lic : array();
    if ( empty( $lic['key'] ) || ! isset( $lic['status'] ) || $lic['status'] !== 'active' ) return false;
    if ( ! empty( $lic['expires_at'] ) && ( ! is_string( $lic['expires_at'] ) || strtotime( $lic['expires_at'] ) < time() ) ) return false;
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
 * paginacache alleen als de geldigheid echt verandert: vergeleken met de
 * laatst geziene geldigheid, want een licentie kan op zijn vervaldatum al
 * ongeldig zijn vóórdat de dagelijkse controle hem als 'expired' opslaat.
 */
function cm_license_sync_validity( $lic ) {
    $now  = cm_license_data_is_valid( $lic ) ? '1' : '0';
    $seen = get_option( 'cm_license_valid_seen', '' );
    if ( $seen === $now ) return;
    update_option( 'cm_license_valid_seen', $now, false );
    // Eerste keer (bijv. net geüpdatet): de update-migratie leegt de cache al
    if ( $seen !== '' && function_exists( 'cm_purge_page_caches' ) ) cm_purge_page_caches();
    // Weer geldig: de automatische scan opnieuw inplannen (als die aan staat en er
    // geen cron meer loopt, bijv. doordat hij in 2.x zonder licentie stopte)
    if ( $now === '1' && function_exists( 'cm_maybe_schedule_auto_scan_cron' ) ) cm_maybe_schedule_auto_scan_cron();
}
add_action( 'update_option_cm_license_data', function ( $old, $new ) { cm_license_sync_validity( $new ); }, 10, 2 );
add_action( 'add_option_cm_license_data', function ( $name, $value ) { cm_license_sync_validity( $value ); }, 10, 2 );
add_action( 'delete_option', function ( $name ) {
    if ( $name === 'cm_license_data' ) cm_license_sync_validity( array() );
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

    // 'network': de server gaf geen (bruikbaar) antwoord. Dat zegt niets over de sleutel.
    if ( is_wp_error( $response ) ) {
        return array( 'success' => false, 'network' => true, 'error' => cm_license_network_message( $response->get_error_message() ) );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! is_array( $body ) ) {
        return array( 'success' => false, 'network' => true, 'error' => cm_license_network_message( 'HTTP ' . $code . ', geen geldig antwoord' ) );
    }

    return $body;
}

/** Uitleg bij een mislukte verbinding: het ligt aan de verbinding, niet aan de sleutel. */
function cm_license_network_message( $detail ) {
    return 'De licentieserver (cookiebaas.nl) gaf vanaf deze website geen antwoord. Dat ligt aan de verbinding tussen de hosting van deze website en de licentieserver, niet aan uw sleutel. Probeer het later opnieuw; blijft het zo, vraag uw hoster of de server verbinding mag maken met cookiebaas.nl. Technische melding: ' . $detail;
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

    $existing = cm_license_get();

    $result = cm_license_api_call( 'activate', array(
        'license_key' => $key,
        'domain'      => $domain,
    ) );

    // Geen antwoord van de server: niets opslaan. De sleutel is daarmee niet ongeldig,
    // en een licentie die al actief was blijft zoals hij was.
    if ( ! empty( $result['network'] ) ) {
        return array( 'success' => false, 'error' => cm_license_str( $result['error'] ?? '', 'Activatie mislukt.' ) );
    }

    $lic = cm_license_get();
    $lic['key']    = $key;
    $lic['domain'] = $domain;

    if ( ! empty( $result['success'] ) ) {
        // Stond er een andere actieve sleutel: die pas na een geslaagde activatie bij de server afmelden,
        // zodat een mislukte poging de bestaande licentie niet weghaalt
        if ( ! empty( $existing['key'] ) && $existing['key'] !== $key && isset( $existing['status'] ) && $existing['status'] === 'active' ) {
            cm_license_api_call( 'deactivate', array( 'license_key' => $existing['key'], 'domain' => $domain ) );
        }
        $lic['status']       = 'active';
        $lic['expires_at']   = cm_license_str( $result['expires_at'] ?? '' );
        $lic['max_sites']    = (int) ( is_scalar( $result['max_sites'] ?? null ) ? $result['max_sites'] : 1 );
        $lic['last_check']   = time();
        $lic['last_success'] = time();
        cm_license_save( $lic );
        return array( 'success' => true, 'message' => cm_license_str( $result['message'] ?? '', 'Licentie geactiveerd.' ) );
    }

    $lic['status'] = 'invalid';
    $lic['last_check'] = time();
    cm_license_save( $lic );
    return array( 'success' => false, 'error' => cm_license_str( $result['error'] ?? '', 'Activatie mislukt.' ) );
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

    // Deactiveren haalt de licentie altijd van deze website af, ook de sleutel
    delete_option( 'cm_license_data' );

    if ( ! empty( $result['network'] ) ) {
        return array( 'success' => true, 'remote' => false, 'message' => 'De licentie is van deze website verwijderd, maar de licentieserver was niet bereikbaar. Dit domein staat daar dus nog als geactiveerd; verwijder het ook in uw account op cookiebaas.nl, anders telt het nog mee.' );
    }
    return array( 'success' => true, 'message' => 'De licentie is gedeactiveerd en van deze website verwijderd.' );
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
            $lic['expires_at']   = cm_license_str( $result['expires_at'] ?? '', $lic['expires_at'] );
            $lic['max_sites']    = (int) ( is_scalar( $result['max_sites'] ?? null ) ? $result['max_sites'] : $lic['max_sites'] );
            $lic['last_success'] = time();
        } else {
            // Licentie niet meer geldig: verlopen, ingetrokken, of domein ontkoppeld
            $status        = sanitize_key( cm_license_str( $result['status'] ?? '', 'invalid' ) );
            $lic['status'] = $status === 'active' || $status === '' ? 'invalid' : $status;
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

