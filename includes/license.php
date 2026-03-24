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
 */
function cm_license_is_valid() {
    $lic = cm_license_get();
    if ( empty( $lic['key'] ) || $lic['status'] !== 'active' ) return false;
    if ( ! empty( $lic['expires_at'] ) && strtotime( $lic['expires_at'] ) < time() ) return false;
    return true;
}

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

    return array( 'success' => true, 'message' => 'Licentie lokaal gedeactiveerd.' );
}

/* ================================================================
   STATUS CHECK (dagelijks via cron)
   ================================================================ */
function cm_license_check_status() {
    $lic = cm_license_get();
    if ( empty( $lic['key'] ) ) return;

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

/* ================================================================
   AJAX HANDLERS
   ================================================================ */
add_action( 'wp_ajax_cm_license_activate', 'cm_ajax_license_activate' );
function cm_ajax_license_activate() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
    if ( ! $key ) wp_send_json_error( array( 'msg' => 'Vul een licentiesleutel in.' ) );

    $result = cm_license_activate( $key );
    if ( $result['success'] ) {
        wp_send_json_success( array( 'msg' => $result['message'] ) );
    } else {
        wp_send_json_error( array( 'msg' => $result['error'] ) );
    }
}

add_action( 'wp_ajax_cm_license_deactivate', 'cm_ajax_license_deactivate' );
function cm_ajax_license_deactivate() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $result = cm_license_deactivate();
    if ( $result['success'] ) {
        wp_send_json_success( array( 'msg' => $result['message'] ) );
    } else {
        wp_send_json_error( array( 'msg' => $result['error'] ) );
    }
}

add_action( 'wp_ajax_cm_license_check', 'cm_ajax_license_check' );
function cm_ajax_license_check() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    cm_license_check_status();
    $lic = cm_license_get();
    wp_send_json_success( array(
        'msg'    => 'Status gecontroleerd.',
        'status' => $lic['status'],
    ) );
}

/* ================================================================
   ADMIN NOTICE — waarschuwing bij ongeldige licentie
   ================================================================ */
add_action( 'admin_notices', 'cm_license_admin_notice' );
function cm_license_admin_notice() {
    $lic = cm_license_get();
    if ( empty( $lic['key'] ) ) {
        echo '<div class="notice notice-warning"><p><strong>Cookiebaas:</strong> Geen licentie geactiveerd. De cookiebanner wordt niet getoond. <a href="' . admin_url('admin.php?page=cookiemelding-beheer#tab=licentie') . '" onclick="var t=jQuery(\'.cm-nav-tabs .nav-tab[data-tab=licentie]\');if(t.length){t.click();window.scrollTo(0,0);return false;}">Licentie activeren &rarr;</a></p></div>';
        return;
    }
    if ( ! cm_license_is_valid() ) {
        $reason = $lic['status'] === 'expired' ? 'verlopen' : 'ongeldig';
        echo '<div class="notice notice-error"><p><strong>Cookiebaas:</strong> Uw licentie is ' . esc_html($reason) . '. De cookiebanner wordt niet getoond. <a href="' . admin_url('admin.php?page=cookiemelding-beheer#tab=licentie') . '" onclick="var t=jQuery(\'.cm-nav-tabs .nav-tab[data-tab=licentie]\');if(t.length){t.click();window.scrollTo(0,0);return false;}">Licentie beheren &rarr;</a></p></div>';
    }
}
