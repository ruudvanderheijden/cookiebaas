<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   BEHEER — licentie, backup, geavanceerd (REST API), reset en info.
   Alle acties via admin-post.php; geen AJAX.
================================================================ */

function cm_tabs_beheer() {
    return array(
        'licentie' => array( 'label' => 'Licentie', 'render' => 'cm_render_beheer_licentie' ),
    );
}

/** Licentiestatus: array( notice-type, woord, uitleg ), voor Beheer en Overzicht. */
function cm_license_summary( array $lic, $valid ) {
    if ( $valid ) return array( 'success', 'Actief', 'De cookiescan en de automatische scan zijn beschikbaar.' );
    if ( empty( $lic['key'] ) ) return array( 'warning', 'Geen licentie', 'De cookiebanner en de scriptblokkering werken gewoon; alleen de cookiescan is gepauzeerd.' );
    $word = isset( $lic['status'] ) && $lic['status'] === 'expired' ? 'Verlopen' : 'Ongeldig';
    return array( 'warning', $word, 'De cookiebanner en de scriptblokkering blijven werken; de cookiescan is gepauzeerd tot u de licentie verlengt of een geldige sleutel activeert.' );
}

/** Antwoord van de licentieserver → melding. Nooit "gelukt" zonder success. */
function cm_license_flash( array $result ) {
    if ( ! empty( $result['success'] ) ) {
        cm_admin_flash( 'success', ! empty( $result['message'] ) ? $result['message'] : 'Gelukt.' );
    } else {
        cm_admin_flash( 'error', ! empty( $result['error'] ) ? $result['error'] : 'De licentieserver gaf geen bruikbaar antwoord. Probeer het later opnieuw.' );
    }
}

/** Activeren: een lege sleutel gaat niet naar de server. */
function cm_license_activate_request( $key ) {
    $key = trim( (string) $key );
    return $key === '' ? array( 'success' => false, 'error' => 'Vul een licentiesleutel in.' ) : cm_license_activate( $key );
}

function cm_render_beheer_licentie() {
    $lic = cm_license_get();
    list( $type, $word, $text ) = cm_license_summary( $lic, cm_license_is_valid() );
    echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p><strong>' . esc_html( $word ) . '.</strong> ' . esc_html( $text ) . '</p></div>';

    if ( ! empty( $lic['key'] ) ) {
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Licentiesleutel</th><td><code>' . esc_html( $lic['key'] ) . '</code></td></tr>';
        echo '<tr><th scope="row">Status</th><td>' . esc_html( $word );
        if ( ! empty( $lic['expires_at'] ) ) echo esc_html( ' — verloopt op ' . date_i18n( 'j F Y', strtotime( $lic['expires_at'] ) ) );
        echo '</td></tr>';
        echo '<tr><th scope="row">Domein</th><td><code>' . esc_html( ! empty( $lic['domain'] ) ? $lic['domain'] : cm_license_get_domain() ) . '</code></td></tr>';
        echo '<tr><th scope="row">Laatste controle</th><td>' . esc_html( ! empty( $lic['last_check'] ) ? date_i18n( 'j F Y, H:i', $lic['last_check'] ) : 'Nog niet gecontroleerd' ) . '</td></tr>';
        echo '</tbody></table>';
        echo '<div>' . cm_admin_action_form( 'license_check', 'Status controleren' ) . ' '
           . cm_admin_action_form( 'license_deactivate', 'Deactiveren', array(), 'De licentie op deze website deactiveren? De cookiescan pauzeert tot u opnieuw activeert.', 'button button-link-delete' ) . '</div>';
    }

    echo '<h2>' . esc_html( ! empty( $lic['key'] ) ? 'Andere sleutel activeren' : 'Licentie activeren' ) . '</h2>';
    $field = '<p><label for="cm-license-key">Licentiesleutel</label><br>'
           . '<input type="text" id="cm-license-key" name="license_key" class="regular-text code" placeholder="CB-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off" required></p>';
    echo '<div>' . cm_admin_action_form( 'license_activate', 'Activeren', array(), '', 'button button-primary', $field ) . '</div>';
}

/** Waarschuwing bij een ontbrekende of ongeldige licentie: alleen op de schermen van Cookiebaas. */
add_action( 'admin_notices', 'cm_admin_license_notice' );
function cm_admin_license_notice() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ! cm_admin_is_own_screen( $screen->id ) || cm_license_is_valid() ) return;
    // Op de tab Licentie zelf staat de status al
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
    if ( $page === 'cookiebaas-beheer' && in_array( $tab, array( '', 'licentie' ), true ) ) return;
    $lic  = cm_license_get();
    if ( empty( $lic['key'] ) ) {
        $text = 'Geen licentie geactiveerd. De cookiebanner en de scriptblokkering werken gewoon door; alleen de cookiescan is gepauzeerd.';
        $link = 'Licentie activeren';
    } else {
        $text = 'Uw licentie is ' . ( isset( $lic['status'] ) && $lic['status'] === 'expired' ? 'verlopen' : 'ongeldig' ) . '. De cookiebanner en de scriptblokkering blijven werken; alleen de cookiescan is gepauzeerd tot u de licentie verlengt.';
        $link = 'Licentie beheren';
    }
    echo '<div class="notice notice-warning"><p><strong>Cookiebaas:</strong> ' . esc_html( $text ) . ' <a href="' . esc_url( cm_admin_page_url( 'cookiebaas-beheer', 'licentie' ) ) . '">' . esc_html( $link ) . '</a></p></div>';
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'license_activate', function () {
        cm_license_flash( cm_license_activate_request( isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '' ) );
        return '';
    } );
    cm_admin_register_action( 'license_check', function () {
        cm_license_check_status();
        list( , $word ) = cm_license_summary( cm_license_get(), cm_license_is_valid() );
        cm_admin_flash( 'info', 'Status gecontroleerd: ' . $word . '.' );
        return '';
    } );
    cm_admin_register_action( 'license_deactivate', function () {
        cm_license_flash( cm_license_deactivate() );
        return '';
    } );
}
