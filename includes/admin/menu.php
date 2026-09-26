<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   NIEUWE ADMIN (3.0) — menu en paginaframe
   Draait tijdens de bouw naast het oude menu (cookiemelding). Beide
   schrijven naar dezelfde options; plan 3 verwijdert het oude menu.
================================================================ */

/** Menupagina's van de nieuwe admin: slug → titel. Plan 2 en 3 vullen aan. */
function cm_admin_pages() {
    return array(
        'cookiebaas'            => 'Overzicht',
        'cookiebaas-banner'     => 'Banner',
        'cookiebaas-blokkering' => 'Blokkering',
        'cookiebaas-cookies'    => 'Cookies',
    );
}

add_action( 'admin_menu', 'cm_admin3_register_menu' );
function cm_admin3_register_menu() {
    $GLOBALS['cm_admin_hooks'] = array();
    $GLOBALS['cm_admin_hooks'][] = add_menu_page(
        'Cookiebaas', 'Cookiebaas 3', 'manage_options', 'cookiebaas',
        'cm_admin_render_page', 'dashicons-privacy', 82
    );
    foreach ( cm_admin_pages() as $slug => $title ) {
        $GLOBALS['cm_admin_hooks'][] = add_submenu_page(
            'cookiebaas', $title . ' — Cookiebaas', $title, 'manage_options', $slug, 'cm_admin_render_page'
        );
    }
}

/** De gevraagde tab als die bestaat, anders de eerste tab van de pagina. */
function cm_admin_current_tab( array $tabs, $requested ) {
    if ( $requested !== '' && isset( $tabs[ $requested ] ) ) return $requested;
    $keys = array_keys( $tabs );
    return $keys ? $keys[0] : '';
}

/** Tabs van één pagina uit het register (Taak 2). */
function cm_admin_page_tabs( $page ) {
    $all = function_exists( 'cm_admin_tabs' ) ? cm_admin_tabs() : array();
    if ( isset( $all[ $page ] ) && $all[ $page ] ) return $all[ $page ];
    return array( 'overzicht' => cm_tabs_overzicht()['overzicht'] );
}

/** Rendert elke pagina van de nieuwe admin: wrap, titel, meldingen, tabs, inhoud. */
function cm_admin_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'cookiebaas';
    $pages = cm_admin_pages();
    $tabs  = cm_admin_page_tabs( $page );
    $tab   = cm_admin_current_tab( $tabs, isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '' );
    $def   = $tabs[ $tab ];

    echo '<div class="wrap cm-admin">';
    echo '<h1 class="wp-heading-inline">' . esc_html( isset( $pages[ $page ] ) ? $pages[ $page ] : 'Cookiebaas' ) . '</h1>';
    if ( ! empty( $def['title_actions'] ) ) call_user_func( $def['title_actions'] );
    echo '<hr class="wp-header-end">';
    settings_errors();
    if ( function_exists( 'cm_admin_render_notices' ) ) cm_admin_render_notices();

    if ( count( $tabs ) > 1 ) {
        echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="Onderdelen van ' . esc_attr( isset( $pages[ $page ] ) ? $pages[ $page ] : 'Cookiebaas' ) . '">';
        foreach ( $tabs as $slug => $t ) {
            $url = add_query_arg( array( 'page' => $page, 'tab' => $slug ), admin_url( 'admin.php' ) );
            $on  = $slug === $tab;
            echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $on ? ' nav-tab-active' : '' ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $t['label'] ) . '</a>';
        }
        echo '</nav>';
    }

    if ( ! empty( $def['render'] ) ) {
        call_user_func( $def['render'] );
    } else {
        cm_admin_render_form_tab( $page, $tab, $def );
    }
    echo '</div>';
}

/** Een tab met velden: formulier naar options.php, optioneel met preview-kolom. */
function cm_admin_render_form_tab( $page, $tab, array $def ) {
    $preview = ! empty( $def['preview'] ) && function_exists( 'cm_admin_render_preview' );
    if ( $preview ) echo '<div class="cm-cols">';
    echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" class="cm-form">';
    settings_fields( isset( $def['group'] ) ? $def['group'] : 'cookiebaas_settings' );
    $values = ! empty( $def['values'] ) ? call_user_func( $def['values'] ) : cm_get_settings();
    if ( function_exists( 'cm_admin_render_sections' ) ) {
        cm_admin_render_sections( isset( $def['sections'] ) ? $def['sections'] : array(), $values );
    }
    submit_button( 'Wijzigingen opslaan' );
    echo '</form>';
    if ( $preview ) cm_admin_render_preview( $tab );
    if ( ! empty( $def['after_form'] ) ) call_user_func( $def['after_form'] );
    if ( $preview ) echo '</div>';
}

add_action( 'admin_enqueue_scripts', 'cm_admin3_assets' );
function cm_admin3_assets( $hook ) {
    if ( empty( $GLOBALS['cm_admin_hooks'] ) || ! in_array( $hook, $GLOBALS['cm_admin_hooks'], true ) ) return;
    wp_enqueue_style( 'cm-admin-layout', CM_PLUGIN_URL . 'assets/css/admin-layout.css', array(), CM_VERSION );
    wp_enqueue_script( 'cm-admin-common', CM_PLUGIN_URL . 'assets/js/admin-common.js', array(), CM_VERSION, true );
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( $page === 'cookiebaas-banner' ) {
        wp_enqueue_media();
        if ( function_exists( 'cm_admin_preview_assets' ) ) cm_admin_preview_assets();
    }
    if ( $page === 'cookiebaas-cookies' ) {
        wp_enqueue_script( 'cm-admin-cookies', CM_PLUGIN_URL . 'assets/js/admin-cookies.js', array(), CM_VERSION, true );
        wp_localize_script( 'cm-admin-cookies', 'CM_COOKIES', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonces'  => array(
                'scan'     => wp_create_nonce( 'cm_scan' ),
                'scanAdd'  => wp_create_nonce( 'cm_scan_add' ),
                'importDb' => wp_create_nonce( 'cm_import_cookie_db' ),
            ),
        ) );
    }
}
