<?php
/**
 * Nieuwe admin (3.0) — paginaframe en broncheck.
 *
 * Borgt: de tab uit de URL wordt tegen een whitelist gecontroleerd, en de
 * nieuwe admin-code bevat geen inline styles, emoji of hardcoded hex-kleuren
 * (spec §4: alles WordPress-native).
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';

cm_test_group( 'Tab uit de URL' );
$tabs = array( 'vormgeving' => array( 'label' => 'Vormgeving' ), 'teksten' => array( 'label' => 'Teksten' ) );
cm_assert( 'bekende tab blijft', cm_admin_current_tab( $tabs, 'teksten' ) === 'teksten' );
cm_assert( 'onbekende tab valt terug op de eerste', cm_admin_current_tab( $tabs, 'bestaat-niet' ) === 'vormgeving' );
cm_assert( 'lege tab valt terug op de eerste', cm_admin_current_tab( $tabs, '' ) === 'vormgeving' );

cm_test_group( 'Menu' );
$pages = cm_admin_pages();
cm_assert( 'Overzicht is de eerste pagina (slug cookiebaas)', array_key_first( $pages ) === 'cookiebaas' );

cm_test_group( 'Broncheck nieuwe admin: geen style=, emoji of hex-kleuren' );
$files = array_merge(
    glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ),
    glob( CM_PLUGIN_ROOT . '/assets/js/admin-common.js' ) ?: array(),
    glob( CM_PLUGIN_ROOT . '/assets/js/admin-preview.js' ) ?: array(),
    glob( CM_PLUGIN_ROOT . '/assets/css/admin-layout.css' ) ?: array()
);
foreach ( $files as $file ) {
    $src = file_get_contents( $file );
    $rel = str_replace( CM_PLUGIN_ROOT . '/', '', $file );
    cm_assert( "$rel: geen style=\"",         strpos( $src, 'style="' ) === false );
    cm_assert( "$rel: geen emoji",            ! preg_match( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src ) );
    cm_assert( "$rel: geen hex-kleur #rrggbb", ! preg_match( '/#[0-9a-fA-F]{6}\b/', $src ) );
    cm_assert( "$rel: geen &#-entities",      ! preg_match( '/&#x?[0-9a-fA-F]+;/', $src ) );
}

exit( cm_test_summary() );
