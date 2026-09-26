<?php
/**
 * Nieuwe admin (3.0) — paginaframe en broncheck.
 *
 * Borgt: de tab uit de URL wordt tegen een whitelist gecontroleerd, en de
 * nieuwe admin-code bevat geen inline styles, emoji of hardcoded hex-kleuren
 * (spec §4: alles WordPress-native).
 */

function settings_fields( $group ) { echo '<!--group:' . $group . '-->'; }
function submit_button( $text = '' ) { echo '<!--submit:' . $text . '-->'; }
function wp_kses_post( $s ) { return (string) $s; }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';

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
    glob( CM_PLUGIN_ROOT . '/assets/js/admin-cookies.js' ) ?: array(),
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

cm_test_group( 'Formulier-tab: groep en waarden per tab' );
ob_start();
cm_admin_render_form_tab( 'p', 't', array(
    'group'    => 'cookiebaas_privacy',
    'values'   => function () { return array( 'pv_x' => 'uit-values' ); },
    'sections' => array( array( 'fields' => array( cm_field( 'pv_x', 'text', 'X', array( 'option' => 'cm_privacy' ) ) ) ) ),
) );
$h = ob_get_clean();
cm_assert( 'eigen settings-groep', strpos( $h, '<!--group:cookiebaas_privacy-->' ) !== false );
cm_assert( 'waarden uit de tab-callback', strpos( $h, 'value="uit-values"' ) !== false );
ob_start(); cm_admin_render_form_tab( 'p', 't', array( 'sections' => array() ) ); $d = ob_get_clean();
cm_assert( 'standaard: cookiebaas_settings', strpos( $d, '<!--group:cookiebaas_settings-->' ) !== false );

cm_test_group( 'Scan-JS bestaat en gebruikt geen innerHTML met scandata' );
$js = (string) @file_get_contents( CM_PLUGIN_ROOT . '/assets/js/admin-cookies.js' );
cm_assert( 'admin-cookies.js bestaat', $js !== '' );
cm_assert( 'geen innerHTML (scandata alleen via textContent)', strpos( $js, 'innerHTML' ) === false );

exit( cm_test_summary() );
