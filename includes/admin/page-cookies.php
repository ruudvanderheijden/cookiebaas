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

    echo '<h2>Ingebouwde cookies</h2>';
    echo '<p>Deze cookies zet Cookiebaas zelf. Ze staan altijd in het voorkeurenvenster en zijn niet te verwijderen.</p>';
    echo '<table class="widefat striped"><thead><tr><th scope="col">Cookie</th><th scope="col">Provider</th><th scope="col">Doel</th><th scope="col">Looptijd</th></tr></thead><tbody>';
    foreach ( cm_default_cookies() as $ck ) {
        echo '<tr><td><code>' . esc_html( $ck['name'] ) . '</code></td><td>' . esc_html( $ck['provider'] ) . '</td><td>' . esc_html( $ck['purpose'] ) . '</td><td>' . esc_html( $ck['duration'] ) . '</td></tr>';
    }
    echo '</tbody></table>';
}
