<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   PAGINA COOKIES — Cookielijst · Scannen
   De cookielijst (cm_cookie_list) heeft een eigen settings-groep; de
   scan-instellingen horen bij cm_settings.
================================================================ */

function cm_tabs_cookies() {
    return array(
        'lijst' => array( 'label' => 'Cookielijst', 'render' => 'cm_render_cookie_list_tab' ),
    );
}

/** Kolommen van de cookielijst-editor (sleutels = opgeslagen formaat). */
function cm_cookie_list_columns() {
    return array(
        'name'     => array( 'label' => 'Cookie', 'placeholder' => 'Naam' ),
        'provider' => array( 'label' => 'Provider', 'placeholder' => 'Bijvoorbeeld Google Analytics' ),
        'purpose'  => array( 'label' => 'Doel', 'placeholder' => 'Waarvoor dient deze cookie?' ),
        'duration' => array( 'label' => 'Looptijd', 'placeholder' => 'Sessie' ),
        'category' => array( 'label' => 'Categorie', 'type' => 'select', 'options' => array(
            'functional' => 'Functioneel',
            'analytics'  => 'Analytisch',
            'marketing'  => 'Marketing',
        ) ),
    );
}

function cm_render_cookie_list_tab() {
    $rows = get_option( 'cm_cookie_list', array() );
    echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" class="cm-form">';
    settings_fields( 'cookiebaas_cookies' );
    echo '<h2>Uw cookies</h2>';
    echo '<p>Deze lijst verschijnt in het voorkeurenvenster van de banner en in de cookietabel van de privacyverklaring.</p>';
    cm_admin_render_rows( 'cm_cookie_list', cm_cookie_list_columns(), is_array( $rows ) ? array_values( $rows ) : array(), 'Cookie toevoegen' );
    submit_button( 'Cookielijst opslaan' );
    echo '</form>';
    cm_render_cookie_list_tools();

    echo '<h2>Ingebouwde cookies</h2>';
    echo '<p>Deze cookies zet Cookiebaas zelf. Ze staan altijd in het voorkeurenvenster en zijn niet te verwijderen.</p>';
    echo '<table class="widefat striped"><thead><tr><th scope="col">Cookie</th><th scope="col">Provider</th><th scope="col">Doel</th><th scope="col">Looptijd</th></tr></thead><tbody>';
    foreach ( cm_default_cookies() as $ck ) {
        echo '<tr><td><code>' . esc_html( $ck['name'] ) . '</code></td><td>' . esc_html( $ck['provider'] ) . '</td><td>' . esc_html( $ck['purpose'] ) . '</td><td>' . esc_html( $ck['duration'] ) . '</td></tr>';
    }
    echo '</tbody></table>';
}

/**
 * Kennis over een cookie uit de bestaande kennisbank van de scanner
 * (cm_fallback_cookies): eerst exacte naam, dan prefixpatroon (eindigt op _ of -).
 * Geeft array( categorie, provider, looptijd, omschrijving ) of null.
 */
function cm_cookie_fallback_info( $name ) {
    $known = cm_fallback_cookies();
    if ( isset( $known[ $name ] ) ) return $known[ $name ];
    foreach ( $known as $pattern => $info ) {
        if ( cm_cookie_prefix_match( $name, $pattern ) ) return $info;
    }
    return null;
}

/** Verloopdatum uit F12 → leesbare looptijd; '' als sessie, verlopen of onleesbaar. */
function cm_f12_duration( $expires, $now ) {
    if ( ! preg_match( '/\d{4}/', (string) $expires ) ) return '';
    $ts = strtotime( (string) $expires );
    if ( ! $ts || $ts <= $now ) return '';
    $days = (int) round( ( $ts - $now ) / DAY_IN_SECONDS );
    if ( $days >= 365 ) return (int) round( $days / 365 ) . ' jaar';
    if ( $days >= 30 ) {
        $m = (int) round( $days / 30 );
        return $m . ( $m === 1 ? ' maand' : ' maanden' );
    }
    return $days . ( $days === 1 ? ' dag' : ' dagen' );
}

/**
 * Cookietabel geplakt uit de ontwikkelaarstools (F12 › Applicatie › Cookies):
 * tab-gescheiden, naam in kolom 1, verloopdatum in kolom 5. Kopregel, lege
 * regels en namen die al bestaan (hoofdletterongevoelig) worden overgeslagen.
 */
function cm_parse_f12_cookies( $raw, array $existing_names, $now = null ) {
    $now  = $now === null ? time() : (int) $now;
    $seen = array();
    foreach ( $existing_names as $n ) $seen[ strtolower( (string) $n ) ] = true;
    $rows = array();
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
        $line = trim( $line );
        if ( strlen( $line ) < 2 ) continue;
        $parts = explode( "\t", $line );
        $name  = count( $parts ) >= 2 ? trim( $parts[0] ) : (string) preg_split( '/\s+/', $line )[0];
        if ( $name === '' || strlen( $name ) > 128 ) continue;
        if ( $name[0] === '#' || preg_match( '/^(name|naam|cookie|cookienaam)$/i', $name ) ) continue; // kopregel
        $lower = strtolower( $name );
        if ( isset( $seen[ $lower ] ) ) continue;
        $seen[ $lower ] = true;
        $info     = cm_cookie_fallback_info( $name );
        $duration = cm_f12_duration( isset( $parts[4] ) ? $parts[4] : '', $now );
        $rows[]   = array(
            'name'     => $name,
            'provider' => $info ? $info[1] : '',
            'purpose'  => $info ? $info[3] : '',
            'duration' => $duration !== '' ? $duration : ( $info ? $info[2] : 'Sessie' ),
            'category' => $info ? $info[0] : 'functional',
        );
    }
    return $rows;
}

/** CSV-rijen van de cookielijst (voor de download en de oude AJAX-export). */
function cm_cookie_list_csv_rows( array $cookies ) {
    $labels = array( 'functional' => 'Functioneel', 'analytics' => 'Analytisch', 'marketing' => 'Marketing' );
    $bases  = array( 'functional' => 'Strikt noodzakelijk / Gerechtvaardigd belang', 'analytics' => 'Toestemming', 'marketing' => 'Toestemming' );
    $rows   = array( array( 'Cookie naam', 'Aanbieder', 'Categorie', 'Grondslag', 'Doel', 'Looptijd', 'Domein', 'Wildcard' ) );
    foreach ( $cookies as $ck ) {
        $cat    = isset( $ck['category'] ) ? $ck['category'] : 'functional';
        $rows[] = array(
            isset( $ck['name'] ) ? (string) $ck['name'] : '',
            isset( $ck['provider'] ) ? (string) $ck['provider'] : '',
            isset( $labels[ $cat ] ) ? $labels[ $cat ] : $cat,
            isset( $bases[ $cat ] ) ? $bases[ $cat ] : '',
            isset( $ck['purpose'] ) ? (string) $ck['purpose'] : '',
            isset( $ck['duration'] ) ? (string) $ck['duration'] : '',
            isset( $ck['domain'] ) ? (string) $ck['domain'] : '',
            ! empty( $ck['wildcard'] ) ? 'Ja' : 'Nee',
        );
    }
    return $rows;
}

/** Plakken vanuit F12, exporteren en leegmaken — eigen formulieren, buiten het lijstformulier. */
function cm_render_cookie_list_tools() {
    echo '<details class="cm-details postbox"><summary>Plakken vanuit de browser (F12)</summary><div class="cm-details-body">';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="cm_import_f12">';
    wp_nonce_field( 'cm_import_f12' );
    echo '<p><label for="cm-f12">Open de ontwikkelaarstools (F12) › Applicatie › Cookies, kopieer de tabel en plak die hier. Cookies die al in de lijst staan worden overgeslagen.</label></p>';
    echo '<p><textarea id="cm-f12" name="cm_f12" rows="8" class="large-text code"></textarea></p>';
    echo '<p><button type="submit" class="button">Toevoegen aan de lijst</button></p>';
    echo '</form></div></details>';
    echo '<p><a class="button" href="' . esc_url( cm_admin_action_url( 'export_cookies' ) ) . '">Exporteren als CSV</a> ';
    echo cm_admin_action_form( 'clear_cookie_list', 'Lijst leegmaken', array(), 'De cookielijst leegmaken? De ingebouwde cookies blijven staan.', 'button button-link-delete' );
    echo '</p>';
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'import_f12', function () {
        $raw   = isset( $_POST['cm_f12'] ) ? wp_unslash( $_POST['cm_f12'] ) : '';
        $list  = get_option( 'cm_cookie_list', array() );
        $list  = is_array( $list ) ? $list : array();
        $known = array_merge( array_column( $list, 'name' ), array_column( cm_default_cookies(), 'name' ) );
        $new   = cm_parse_f12_cookies( is_string( $raw ) ? $raw : '', $known );
        if ( ! $new ) return 'f12-none';
        update_option( 'cm_cookie_list', cm_sanitize_cookie_list( array_merge( $list, $new ) ) );
        return 'f12-imported';
    } );
    cm_admin_register_action( 'export_cookies', function () {
        cm_admin_send_csv( 'cookielijst-' . gmdate( 'Y-m-d' ) . '.csv', cm_cookie_list_csv_rows( cm_get_cookie_list() ) );
    } );
    cm_admin_register_action( 'clear_cookie_list', function () {
        update_option( 'cm_cookie_list', array() );
        return 'cookie-list-cleared';
    } );
}
