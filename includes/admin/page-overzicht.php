<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* Tijdelijke Overzicht-pagina — plan 3 vervangt die door het echte overzicht. */
function cm_tabs_overzicht() {
    return array(
        'overzicht' => array( 'label' => 'Overzicht', 'render' => 'cm_admin_render_overzicht' ),
    );
}

function cm_admin_render_overzicht() {
    echo '<div class="notice notice-info inline"><p>Deze nieuwe admin is in ontwikkeling. Wat hier nog ontbreekt, staat voorlopig in het oude menu <a href="' . esc_url( admin_url( 'admin.php?page=cookiemelding' ) ) . '">Cookiebaas</a>.</p></div>';
    echo '<ul class="ul-disc">';
    foreach ( cm_admin_pages() as $slug => $title ) {
        if ( $slug === 'cookiebaas' ) continue;
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $title ) . '</a></li>';
    }
    echo '</ul>';
}
