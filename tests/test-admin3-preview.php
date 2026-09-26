<?php
/**
 * Nieuwe admin (3.0) — live preview.
 *
 * Borgt:
 *   - cm_banner_markup() levert exact de banner uit cm_render_frontend()
 *   - de CSS-variabelen van de preview zijn gelijk aan wat de frontend echt
 *     uitvoert (licht én donker) — één waarheid, geen afwijkende JS-fallbacks
 *   - het preview-iframe draait nooit scripts (Review Focus 5)
 */

function is_singular() { return false; }
function get_pages() { return array(); }
function wp_kses_post( $s ) { return (string) $s; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $s ) ); }
function date_i18n( $f, $t = null ) { return 'date'; }
function current_user_can() { return false; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function cm_render_switch() {} // hoort bij page-banner.php; deze test controleert alleen iframe en template

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';
require CM_PLUGIN_ROOT . '/includes/admin/preview.php';

cm_test_group( 'Banner-markup' );
ob_start(); $cats = cm_banner_markup(); $markup = ob_get_clean();
$GLOBALS['cm_rendered'] = false;
ob_start(); cm_render_frontend(); $full = ob_get_clean();
cm_assert( 'markup bevat banner, voorkeurenvenster en overlay', strpos( $markup, 'id="cm-banner"' ) !== false && strpos( $markup, 'id="cm-prefs"' ) !== false && strpos( $markup, 'id="cm-overlay"' ) !== false );
cm_assert( 'markup bevat geen script', stripos( $markup, '<script' ) === false );
cm_assert( 'frontend = markup + script', strpos( $full, $markup ) === 0 && stripos( $full, '<script', strlen( $markup ) ) !== false );
cm_assert( 'categorieën worden teruggegeven', isset( $cats['analytics'], $cats['marketing'], $cats['functional'] ) );

/** Effectieve CSS-variabelen uit de frontend-output (laatste declaratie wint). */
function frontend_vars() {
    ob_start(); cm_output_inline_css(); $css = ob_get_clean();
    $css = substr( $css, 0, strpos( $css, '<style id="cm-frontend-css">' ) ?: strlen( $css ) );
    preg_match_all( '/(--cm-[a-z0-9-]+):([^;]*);/', $css, $m, PREG_SET_ORDER );
    $vars = array();
    foreach ( $m as $d ) $vars[ $d[1] ] = $d[2];
    return $vars;
}

// Donker met 0: de frontend maakt daar via `?:` 75 / 18px / 6px van; de preview moet hetzelfde tonen.
$scenarios = array(
    'light'           => array( 'light', array() ),
    'dark'            => array( 'dark', array() ),
    'dark, waarden 0' => array( 'dark', array( 'dm_radius_btn' => '0', 'dm_radius_popup' => '0', 'dm_overlay_opacity' => '0' ) ),
);
foreach ( $scenarios as $label => list( $theme, $extra ) ) {
    cm_test_group( "CSS-variabelen preview == frontend ($label)" );
    cm_test_set_settings( array_merge( cm_default_settings(), array( 'color_theme' => $theme, 'color_accept_border' => '', 'dm_reject_border' => '#445566' ), $extra ) );
    $front = frontend_vars();
    $prev  = cm_preview_vars( cm_get_settings(), $theme );
    $diff  = array();
    foreach ( $front as $var => $val ) if ( ! array_key_exists( $var, $prev ) || (string) $prev[ $var ] !== (string) $val ) $diff[] = "$var (frontend $val, preview " . ( isset( $prev[ $var ] ) ? $prev[ $var ] : '—' ) . ')';
    cm_assert( 'elke variabele gelijk' . ( $diff ? ': ' . implode( '; ', $diff ) : '' ), ! $diff );
    cm_assert( 'geen extra variabelen in de preview', ! array_diff_key( $prev, $front ) );
}

cm_test_group( 'Iframe draait geen scripts' );
ob_start(); cm_admin_render_preview( 'vormgeving' ); $html = ob_get_clean();
cm_assert( 'sandbox zonder allow-scripts', preg_match( '/<iframe[^>]*sandbox="allow-same-origin"/', $html ) === 1 && strpos( $html, 'allow-scripts' ) === false );
cm_assert( 'banner-markup staat in een template', strpos( $html, '<template id="cm-preview-markup">' ) !== false );

exit( cm_test_summary() );
