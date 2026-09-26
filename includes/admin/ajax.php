<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   AJAX — alleen voor wat lang duurt of live moet zijn (spec §5.3):
   de scan in batches, de cookiedatabase en een scanresultaat toevoegen.
   Eigen nonce per actie.
================================================================ */

/** Stop met een JSON-fout als de gebruiker geen rechten of een ongeldige nonce heeft. */
function cm_admin_verify_ajax( $action ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'msg' => 'Geen toegang.' ), 403 );
    }
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( wp_verify_nonce( $nonce, 'cm_' . $action ) ) return;
    // ponytail: de oude admin (tot plan 3) stuurt nog de gedeelde nonce; weg met de oude admin
    if ( wp_verify_nonce( $nonce, 'cm_save_settings' ) ) return;
    wp_send_json_error( array( 'msg' => 'De sessie is verlopen. Herlaad de pagina en probeer het opnieuw.' ), 403 );
}

/**
 * Scanresultaat (cm_scan_batch) → regel van de cookielijst, of null als de
 * naam ontbreekt of geen tekst is (bijv. een array) — anders levert
 * (string)$ck['name'] de letterlijke rij "Array" op.
 */
function cm_scan_result_to_row( array $ck ) {
    if ( ! isset( $ck['name'] ) || ! is_scalar( $ck['name'] ) || (string) $ck['name'] === '' ) return null;
    $type = isset( $ck['type'] ) ? (string) $ck['type'] : '';
    return array(
        'name'     => (string) $ck['name'],
        'provider' => isset( $ck['provider'] ) ? (string) $ck['provider'] : '',
        'purpose'  => isset( $ck['description'] ) ? (string) $ck['description'] : '',
        'duration' => isset( $ck['duration'] ) && (string) $ck['duration'] !== '' ? (string) $ck['duration'] : 'Sessie',
        'category' => in_array( $type, array( 'functional', 'analytics', 'marketing' ), true ) ? $type : 'functional',
    );
}

/** Voeg nieuwe regels toe; namen die al bestaan blijven ongemoeid. */
function cm_merge_cookie_list( array $existing, array $new ) {
    $names = array();
    foreach ( $existing as $ck ) {
        if ( isset( $ck['name'] ) ) $names[ (string) $ck['name'] ] = true;
    }
    $added = array();
    foreach ( $new as $ck ) {
        $n = isset( $ck['name'] ) ? (string) $ck['name'] : '';
        if ( $n === '' || isset( $names[ $n ] ) ) continue;
        $names[ $n ] = true;
        $existing[]  = $ck;
        $added[]     = $n;
    }
    return array( 'list' => $existing, 'added' => $added );
}

/**
 * Scanresultaten aan de cookielijst toevoegen. Het samenvoegen gebeurt hier,
 * op de opgeslagen lijst — de browser stuurt nooit een hele lijst terug, dus
 * er kan niets overschreven worden.
 */
add_action( 'wp_ajax_cm_scan_add', 'cm_ajax_scan_add' );
function cm_ajax_scan_add() {
    cm_admin_verify_ajax( 'scan_add' );
    if ( isset( $_POST['cookies'] ) && ! is_string( $_POST['cookies'] ) ) {
        wp_send_json_error( array( 'msg' => 'Ongeldige cookiegegevens ontvangen.' ) );
    }
    $raw = isset( $_POST['cookies'] ) ? json_decode( wp_unslash( $_POST['cookies'] ), true ) : null;
    if ( ! is_array( $raw ) ) wp_send_json_error( array( 'msg' => 'Er zijn geen cookies ontvangen.' ) );

    $builtin = array_column( cm_default_cookies(), 'name' );
    $rows    = array();
    foreach ( cm_sanitize_cookie_list( array_map( 'cm_scan_result_to_row', array_filter( $raw, 'is_array' ) ) ) as $row ) {
        if ( ! in_array( $row['name'], $builtin, true ) ) $rows[] = $row;
    }
    $current = get_option( 'cm_cookie_list', array() );
    $merged  = cm_merge_cookie_list( is_array( $current ) ? $current : array(), $rows );
    if ( $merged['added'] ) update_option( 'cm_cookie_list', cm_sanitize_cookie_list( $merged['list'] ) );
    wp_send_json_success( array( 'added' => $merged['added'] ) );
}
