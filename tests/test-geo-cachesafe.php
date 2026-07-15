<?php
/**
 * Geo-targeting cache-veilig (v2.3.0).
 *
 * Vroeger besliste cm_render_frontend server-side op basis van het IP-land of
 * de banner gerenderd werd (en bakte bij 'accept' zelfs een auto-consent-script
 * in de HTML). Onder een paginacache kreeg iedereen de kopie van de eerste
 * bezoeker → zelfde klasse als het v1.7.7-privacylek.
 *
 * De banner rendert nu altijd; de geo-keuze valt client-side via een
 * ongecachete lookup. Deze test bewijst dat de HTML niet meer van het land
 * afhangt en dat de landdetectie zelf correct is.
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

if ( ! function_exists( 'plugins_url' ) )   { function plugins_url( $p = '', $f = '' ) { return 'https://example.test/wp-content/plugins/cookiebaas' . $p; } }
if ( ! function_exists( 'get_permalink' ) ) { function get_permalink( $id = 0 ) { return 'https://example.test/pagina'; } }

/** Rendert de banner met een gegeven land-header. */
function render_with_country( $country ) {
    if ( $country === null ) unset( $_SERVER['HTTP_CF_IPCOUNTRY'] );
    else $_SERVER['HTTP_CF_IPCOUNTRY'] = $country;
    unset( $GLOBALS['cm_rendered'] );
    ob_start();
    cm_render_frontend();
    return ob_get_clean();
}

function run_mode( $outside ) {
    cm_test_set_settings( array(
        'geo_enabled'      => '1',
        'geo_outside_eu'   => $outside,
        'show_float_btn'   => 0,
    ) );

    $nl   = render_with_country( 'NL' );   // gereguleerd land
    $us   = render_with_country( 'US' );   // buiten regulering
    $none = render_with_country( null );   // geen header

    cm_test_group( "[geo aan, outside=$outside] HTML is landonafhankelijk (cache-veilig)" );
    cm_assert( 'NL-bezoeker == US-bezoeker (byte-voor-byte)', $nl === $us );
    cm_assert( 'NL-bezoeker == zonder land-header', $nl === $none );
    cm_assert( 'geen ingebakken geo-auto consent-cookie in de HTML', strpos( $nl, 'method:"geo-auto"' ) === false && strpos( $nl, "method\\':\\'geo-auto" ) === false );
    cm_assert( 'banner wordt wél gerenderd (altijd aanwezig)', strpos( $nl, 'id="cm-banner"' ) !== false );
    cm_assert( 'geo-config staat in de JS (GEO_ENABLED)', strpos( $nl, 'var GEO_ENABLED       = true' ) !== false );
    cm_assert( "GEO_OUTSIDE = '$outside' in de JS", strpos( $nl, "var GEO_OUTSIDE       = '$outside'" ) !== false );
    cm_assert( 'client-side geoDecide() aanwezig', strpos( $nl, 'function geoDecide()' ) !== false );
    cm_assert( 'geo-beslissing via ongecachete admin-ajax (cm_geo_check)', strpos( $nl, 'cm_geo_check' ) !== false );
}

run_mode( 'hide' );
run_mode( 'accept' );

// --- Landdetectie zelf -----------------------------------------------------
cm_test_group( 'Landdetectie (cm_requires_consent_banner)' );
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'NL';  cm_assert( 'NL vereist consent', cm_requires_consent_banner() === true );
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'DE';  cm_assert( 'DE vereist consent', cm_requires_consent_banner() === true );
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'GB';  cm_assert( 'GB (UK GDPR) vereist consent', cm_requires_consent_banner() === true );
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';  cm_assert( 'US vereist geen consent', cm_requires_consent_banner() === false );
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'CN';  cm_assert( 'CN vereist geen consent', cm_requires_consent_banner() === false );
unset( $_SERVER['HTTP_CF_IPCOUNTRY'] );
cm_assert( 'geen land-header → fail-safe: consent vereist (banner tonen)', cm_requires_consent_banner() === true );

// --- Geo uit: geen endpoint-afhankelijkheid --------------------------------
cm_test_set_settings( array( 'geo_enabled' => '0', 'show_float_btn' => 0 ) );
unset( $GLOBALS['cm_rendered'] );
ob_start(); cm_render_frontend(); $off = ob_get_clean();
cm_test_group( 'Geo uit (standaard)' );
cm_assert( 'GEO_ENABLED = false in de JS', strpos( $off, 'var GEO_ENABLED       = false' ) !== false );
cm_assert( 'banner wordt gerenderd', strpos( $off, 'id="cm-banner"' ) !== false );

exit( cm_test_summary() );
