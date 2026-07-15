<?php
/**
 * Fail-open licentie omgedraaid (v2.2.0).
 *
 * De compliance-kern (banner, styling, Consent Mode, scriptblokkering) MOET
 * altijd werken, ook bij een verlopen/ontbrekende licentie — anders gaat een
 * site op de vervaldag stilzwijgend zonder toestemming trackeren. Alleen de
 * cookiescan is premium.
 *
 * Deze test laadt de échte includes/license.php (CM_TEST_REAL_LICENSE) zodat de
 * werkelijke licentielogica meegetest wordt.
 */

define( 'CM_TEST_REAL_LICENSE', true );
require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/license.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

// Wat extra stubs die alleen de volledige banner-render nodig heeft.
if ( ! function_exists( 'plugins_url' ) )      { function plugins_url( $p = '', $f = '' ) { return 'https://example.test/wp-content/plugins/cookiebaas' . $p; } }
if ( ! function_exists( 'wp_kses_post' ) )     { function wp_kses_post( $s ) { return $s; } }
if ( ! function_exists( 'wp_parse_url' ) )     { function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); } }
if ( ! function_exists( 'get_permalink' ) )    { function get_permalink( $id = 0 ) { return 'https://example.test/pagina'; } }

/** Zet de licentie in een gegeven staat via de echte optie-store. */
function set_license( $state ) {
    if ( $state === 'active' ) {
        update_option( 'cm_license_data', array( 'key' => 'CB-TEST', 'status' => 'active', 'expires_at' => gmdate( 'Y-m-d', time() + 86400 * 30 ) ) );
    } elseif ( $state === 'expired' ) {
        update_option( 'cm_license_data', array( 'key' => 'CB-TEST', 'status' => 'expired', 'expires_at' => gmdate( 'Y-m-d', time() - 86400 ) ) );
    } else { // none
        update_option( 'cm_license_data', array( 'key' => '', 'status' => '' ) );
    }
}

cm_test_set_settings( array(
    'ga4_measurement_id'           => 'G-TESTID123',
    'gtm_container_id'             => 'GTM-TEST123',
    'google_consent_mode_advanced' => 1,
    'embed_blocker_enabled'        => 1,
    'block_analytics_patterns'     => '',
    'block_marketing_patterns'     => '',
) );

$tracker_html = '<html><head><script src="https://static.hotjar.com/c/hotjar-1.js"></script></head><body></body></html>';

// --- Licentielogica zelf --------------------------------------------------
cm_test_group( 'Licentielogica (echte license.php)' );
set_license( 'expired' );
cm_assert( 'verlopen licentie → cm_license_is_valid() = false', cm_license_is_valid() === false );
cm_assert( 'verlopen licentie → cookiescan geblokkeerd', cm_scan_requires_license() === true );
set_license( 'none' );
cm_assert( 'geen licentie → cookiescan geblokkeerd', cm_scan_requires_license() === true );
set_license( 'active' );
cm_assert( 'actieve licentie → geldig', cm_license_is_valid() === true );
cm_assert( 'actieve licentie → cookiescan toegestaan', cm_scan_requires_license() === false );

// --- Compliance-kern werkt ONGEACHT licentie ------------------------------
foreach ( array( 'expired', 'none', 'active' ) as $state ) {
    set_license( $state );
    cm_test_group( "Compliance-kern werkt bij licentie: $state" );

    ob_start(); cm_output_inline_css();          $css   = ob_get_clean();
    ob_start(); cm_inject_google_consent_mode();  $cm    = ob_get_clean();
    ob_start(); cm_output_script_blocker();        $jsbl  = ob_get_clean();
    $filtered = cm_filter_buffer( $tracker_html );

    cm_assert( 'inline CSS wordt uitgevoerd', strpos( $css, '--cm-' ) !== false );
    cm_assert( 'Consent Mode defaults op denied worden geladen', strpos( $cm, "'analytics_storage':  'denied'" ) !== false );
    cm_assert( 'runtime-blocker (cm-blocker) wordt gerenderd', strpos( $jsbl, 'id="cm-blocker"' ) !== false );
    cm_assert( 'server-side blocker blokkeert Hotjar', strpos( $filtered, 'data-cm-type="analytics"' ) !== false );
}

// --- Banner-HTML verschijnt ook zonder licentie ---------------------------
set_license( 'none' );
unset( $GLOBALS['cm_rendered'] );
cm_test_group( 'Banner-HTML zonder licentie' );
ob_start(); cm_render_frontend(); $banner = ob_get_clean();
cm_assert( 'banner-container wordt gerenderd zonder licentie', strpos( $banner, 'cm-box' ) !== false || strpos( $banner, 'id="cm-banner"' ) !== false );

exit( cm_test_summary() );
