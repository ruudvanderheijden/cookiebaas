<?php
/**
 * Donker thema: 0 voor afronding en overlay blijft 0 (was 6px / 18px / 75% door `?:`).
 * Run: php tests/test-dark-zero.php
 */
require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

function dark_css( array $extra ) {
    cm_test_set_settings( array_merge( cm_default_settings(), array( 'color_theme' => 'dark' ), $extra ) );
    ob_start(); cm_output_inline_css(); $css = ob_get_clean();
    $start = strpos( $css, '<style id="cm-vars-dark">' );
    return $start === false ? '' : substr( $css, $start, strpos( $css, '</style>', $start ) - $start );
}

cm_test_group( 'Donker thema: waarde 0 blijft 0' );
$css = dark_css( array( 'dm_radius_btn' => '0', 'dm_radius_popup' => '0', 'dm_overlay_opacity' => '0' ) );
cm_assert( 'knopafronding 0px', strpos( $css, '--cm-btn-radius:0px;' ) !== false );
cm_assert( 'popupafronding 0px', strpos( $css, '--cm-popup-radius:0px;' ) !== false );
cm_assert( 'overlay 0', strpos( $css, '--cm-overlay-alpha:0;' ) !== false );

cm_test_group( 'Donker thema: andere waarden ongewijzigd' );
$css = dark_css( array( 'dm_radius_btn' => '12', 'dm_radius_popup' => '4', 'dm_overlay_opacity' => '40' ) );
cm_assert( 'knopafronding 12px', strpos( $css, '--cm-btn-radius:12px;' ) !== false );
cm_assert( 'popupafronding 4px', strpos( $css, '--cm-popup-radius:4px;' ) !== false );
cm_assert( 'overlay 0.4', strpos( $css, '--cm-overlay-alpha:0.4;' ) !== false );

exit( cm_test_summary() );
