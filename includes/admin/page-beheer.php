<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   BEHEER — licentie, backup, geavanceerd (REST API), reset en info.
   Alle acties via admin-post.php; geen AJAX.
================================================================ */

function cm_tabs_beheer() {
    return array(
        'licentie' => array( 'label' => 'Licentie', 'render' => 'cm_render_beheer_licentie' ),
        'backup'   => array( 'label' => 'Backup', 'render' => 'cm_render_beheer_backup' ),
        'reset'    => array( 'label' => 'Reset', 'render' => 'cm_render_beheer_reset' ),
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

/** Inhoud van de backup: alles wat de admin instelt, zonder API-sleutel en licentie. */
function cm_backup_payload() {
    $settings = get_option( 'cm_settings', cm_default_settings() );
    $settings = is_array( $settings ) ? $settings : array();
    unset( $settings['api_key'] );
    return array(
        '_meta'       => array(
            'plugin'   => 'cookiebaas',
            'version'  => CM_VERSION,
            'exported' => current_time( 'c' ),
            'site'     => get_bloginfo( 'url' ),
        ),
        'settings'    => $settings,
        'cookie_list' => get_option( 'cm_cookie_list', array() ),
        'privacy'     => get_option( 'cm_privacy', cm_default_privacy() ),
    );
}

/**
 * Backup terugzetten. Een ongeldig bestand schrijft niets. Alles gaat door
 * dezelfde sanitizers als opslaan; ongeldige waarden worden de standaard.
 * De huidige API-sleutel blijft staan: die zit niet in een backup.
 */
function cm_import_backup( $raw ) {
    $data = is_string( $raw ) && $raw !== '' ? json_decode( $raw, true ) : null;
    $meta = is_array( $data ) && isset( $data['_meta'] ) && is_array( $data['_meta'] ) ? $data['_meta'] : array();
    if ( ! isset( $meta['plugin'] ) || ! in_array( $meta['plugin'], array( 'cookiebaas', 'cookiemelding' ), true ) ) {
        return array( 'ok' => false, 'message' => 'Dit is geen backup van Cookiebaas. Er is niets gewijzigd.' );
    }
    $has = function ( $k ) use ( $data ) { return isset( $data[ $k ] ) && is_array( $data[ $k ] ); };
    if ( ! $has( 'settings' ) && ! $has( 'cookie_list' ) && ! $has( 'privacy' ) ) {
        return array( 'ok' => false, 'message' => 'De backup bevat geen instellingen, cookielijst of privacyverklaring. Er is niets gewijzigd.' );
    }

    $errors = count( get_settings_errors() );
    $done   = array();
    if ( $has( 'settings' ) ) {
        $base = array_merge( cm_default_settings(), array( 'api_key' => (string) cm_get( 'api_key' ) ) );
        update_option( 'cm_settings', cm_sanitize_settings( $data['settings'], $base ) );
        $done[] = 'instellingen';
    }
    if ( $has( 'cookie_list' ) ) {
        $list = cm_sanitize_cookie_list( $data['cookie_list'] );
        update_option( 'cm_cookie_list', $list );
        $done[] = count( $list ) === 1 ? '1 cookie' : count( $list ) . ' cookies';
    }
    if ( $has( 'privacy' ) ) {
        update_option( 'cm_privacy', cm_sanitize_privacy( array_merge( cm_default_privacy(), $data['privacy'] ) ) );
        $done[] = 'privacyverklaring';
    }
    $message = 'Teruggezet: ' . implode( ', ', $done ) . '.';
    if ( count( get_settings_errors() ) > $errors ) {
        $message .= ' Sommige waarden in de backup waren ongeldig; daar staat nu de standaardwaarde.';
    }
    return array( 'ok' => true, 'message' => $message );
}

function cm_license_reset_local() {
    delete_option( 'cm_license_data' );
    delete_option( 'cm_license_api_url' );
}

/** Zet alles terug. Geeft de onderdelen terug die mislukten (leeg = alles gelukt). */
function cm_reset_everything() {
    $failed = array();
    update_option( 'cm_settings', cm_default_settings() );
    update_option( 'cm_cookie_list', array() );
    update_option( 'cm_privacy', cm_default_privacy() );
    if ( ! cm_log_clear() ) $failed[] = 'consent log';
    cm_bump_consent_version( 'Alles gereset' );
    cm_license_reset_local();
    return $failed;
}

function cm_render_beheer_backup() {
    echo '<h2>Backup maken</h2>';
    echo '<p>Download de instellingen, de cookielijst en de privacyverklaring als één JSON-bestand: als backup, of om over te zetten naar een andere website. De consent log, de cookiedatabase, de licentie en de API-sleutel gaan niet mee.</p>';
    echo '<p><a class="button button-primary" href="' . esc_url( cm_admin_action_url( 'export_backup' ) ) . '">Backup downloaden (.json)</a></p>';

    echo '<h2>Backup terugzetten</h2>';
    echo '<p>Zet een eerder gemaakte backup terug. Ongeldige waarden worden de standaardwaarde; een bestand dat geen backup van Cookiebaas is, wijzigt niets.</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" class="cm-action-form" data-cm-confirm="' . esc_attr( 'De huidige instellingen, cookielijst en privacyverklaring worden overschreven door de backup. Doorgaan?' ) . '">';
    echo '<input type="hidden" name="action" value="cm_import_backup">';
    wp_nonce_field( 'cm_import_backup' );
    echo '<p><label for="cm-backup-file">Backupbestand (.json)</label><br><input type="file" id="cm-backup-file" name="cm_backup" accept=".json,application/json" required></p>';
    echo '<button type="submit" class="button">Terugzetten en overschrijven</button>';
    echo '</form>';
}

function cm_render_beheer_reset() {
    echo '<p>Losse onderdelen zet u op hun eigen plek terug: de kleuren onder Banner › Vormgeving, de cookielijst onder Cookies, de privacyverklaring op de pagina Privacyverklaring, en de consent log onder Consent log › Bewaren en opnieuw vragen.</p>';

    echo '<h2>Licentie lokaal wissen</h2>';
    echo '<p>Wist de licentiegegevens op deze website, zonder de licentieserver te benaderen. Gebruik dit als deactiveren niet lukt. De cookiebanner en de scriptblokkering blijven werken; de cookiescan pauzeert.</p>';
    echo '<div>' . cm_admin_action_form( 'license_reset', 'Licentie lokaal wissen', array(), 'De licentiegegevens op deze website wissen?' ) . '</div>';

    echo '<h2>Alles resetten</h2>';
    echo '<p>Zet alles in één keer terug. De instellingen (ook de API-sleutel), de cookielijst en de privacyverklaring gaan naar de standaard, de consent log wordt leeggemaakt, elke bezoeker ziet de banner opnieuw en de licentie wordt lokaal gewist. Dit kan niet ongedaan worden gemaakt.</p>';
    echo '<div>' . cm_admin_action_form( 'reset_all', 'Alles resetten', array(), 'Alles resetten? Instellingen, cookielijst, privacyverklaring, consent log en licentie worden gewist. Dit kan niet ongedaan worden gemaakt.', 'button button-link-delete' ) . '</div>';
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
    cm_admin_register_action( 'export_backup', function () {
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cookiebaas-backup-' . wp_date( 'Y-m-d' ) . '.json"' );
        echo wp_json_encode( cm_backup_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    } );
    cm_admin_register_action( 'import_backup', function () {
        $f   = isset( $_FILES['cm_backup'] ) && is_array( $_FILES['cm_backup'] ) ? $_FILES['cm_backup'] : array();
        $ok  = isset( $f['error'], $f['size'], $f['tmp_name'] ) && (int) $f['error'] === UPLOAD_ERR_OK
            && (int) $f['size'] <= MB_IN_BYTES && is_uploaded_file( $f['tmp_name'] );
        if ( ! $ok ) {
            cm_admin_flash( 'error', 'Kies een backupbestand (.json, maximaal 1 MB). Er is niets gewijzigd.' );
            return '';
        }
        $r = cm_import_backup( (string) file_get_contents( $f['tmp_name'] ) );
        cm_admin_flash( $r['ok'] ? ( strpos( $r['message'], 'ongeldig' ) !== false ? 'warning' : 'success' ) : 'error', $r['message'] );
        return '';
    } );
    cm_admin_register_action( 'reset_all', function () {
        $failed = cm_reset_everything();
        if ( ! $failed ) return 'reset-all-done';
        cm_admin_flash( 'error', 'Niet gelukt: ' . implode( ', ', $failed ) . '. De rest is wel teruggezet.' );
        return '';
    } );
    cm_admin_register_action( 'license_reset', function () {
        cm_license_reset_local();
        return 'license-cleared';
    } );
}
