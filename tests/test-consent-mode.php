<?php
/**
 * Google Consent Mode v2 — de <head>-injectie van cm_inject_google_consent_mode().
 *
 * Borgt het gedrag dat we in v1.7.0–v1.7.9 hebben uitgevochten:
 *   - advanced mode laadt de Google-tag altijd (cookieloze pings toegestaan)
 *   - de consent-status wordt client-side uit de cookie gelezen (cache-veilig)
 *   - url_passthrough is optioneel en standaard uit
 *   - kritieke scripts zijn beschermd tegen "Delay JS" van cache-plugins
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

/** Rendert de Consent Mode <head>-output voor gegeven instellingen + cookie. */
function render_consent( array $overrides = array(), $cookie = null ) {
    $settings = array_merge( array(
        'ga4_measurement_id'           => 'G-TESTID123',
        'gtm_container_id'             => 'GTM-TEST123',
        'ua_tracking_id'               => '',
        'google_consent_mode_advanced' => 1,
        'google_load_default'          => 0,
        'google_url_passthrough'       => 0,
    ), $overrides );
    cm_test_set_settings( $settings );

    if ( $cookie === null ) unset( $_COOKIE['cc_cm_consent'] );
    else $_COOKIE['cc_cm_consent'] = urlencode( json_encode( $cookie ) );

    ob_start();
    cm_inject_google_consent_mode();
    return ob_get_clean();
}

function default_analytics( $out ) {
    return preg_match( "/consent', 'default', \{\s*'analytics_storage':\s*'(\w+)'/", $out, $m ) ? $m[1] : null;
}
function wait_ms( $out ) {
    return preg_match( "/'wait_for_update':\s*(\d+)/", $out, $m ) ? (int) $m[1] : null;
}
function loads_gtm_live( $out ) {
    return strpos( $out, "gtm.js?id='+i" ) !== false
        && strpos( $out, 'data-cm-allow="1" data-no-defer="1" nowprocket>(function(w,d,s,l,i)' ) !== false;
}
function loads_ga4_live( $out ) {
    return strpos( $out, '<script async data-cm-allow="1" data-no-defer="1" nowprocket src="https://www.googletagmanager.com/gtag/js' ) !== false;
}

// --- Geen keuze, advanced (standaard) -------------------------------------
cm_test_group( 'Geen keuze — Consent Mode advanced' );
$out = render_consent();
cm_assert( 'consent default analytics_storage = denied', default_analytics( $out ) === 'denied' );
cm_assert( 'GTM wordt live geladen (tag geplaatst)', loads_gtm_live( $out ) );
cm_assert( 'GA4 wordt live geladen (tag geplaatst)', loads_ga4_live( $out ) );
cm_assert( 'wait_for_update = 500 (cookieloze pings mogen door)', wait_ms( $out ) === 500 );
cm_assert( 'consent-script beschermd tegen JS-delay (data-no-defer + nowprocket)',
    strpos( $out, '<script data-no-defer="1" nowprocket>' . "\n" . 'window.dataLayer' ) !== false );
cm_assert( 'url_passthrough standaard uit (geen _gl= linkvervuiling)', strpos( $out, 'url_passthrough' ) === false );
cm_assert( 'consent-update pas ná client-side cookie-check (if (!c) return;)',
    strpos( $out, 'if (!c) return;' ) !== false && strpos( $out, "'cm_method':          c.method" ) !== false );

// --- url_passthrough optie aan ---------------------------------------------
cm_test_group( 'URL passthrough aangezet' );
$out = render_consent( array( 'google_url_passthrough' => 1 ) );
cm_assert( 'url_passthrough aanwezig als instelling aanstaat', strpos( $out, "gtag('set', 'url_passthrough', true);" ) !== false );
cm_assert( 'ads_data_redaction blijft altijd aan', strpos( $out, "gtag('set', 'ads_data_redaction', true);" ) !== false );

// --- google_load_default (bewust direct laden) -----------------------------
cm_test_group( 'Google cookies direct laden (google_load_default)' );
$out = render_consent( array( 'google_load_default' => 1 ) );
cm_assert( 'consent default analytics_storage = granted', default_analytics( $out ) === 'granted' );
cm_assert( 'GTM wordt live geladen', loads_gtm_live( $out ) );
cm_assert( 'wait_for_update blijft 500 (tags mogen direct vuren)', wait_ms( $out ) === 500 );

// --- Weigering (client-side afgehandeld, HTML identiek) --------------------
cm_test_group( 'Weigering — client-side afgehandeld' );
$out = render_consent( array(), array( 'analytics' => false, 'marketing' => false, 'method' => 'reject-all' ) );
cm_assert( 'update leest de status uit de browser-cookie',
    strpos( $out, "var a = c.analytics ? 'granted' : 'denied';" ) !== false );
cm_assert( 'update wordt gestuurd vóór gtag.js laadt (zelfde inline script)',
    strpos( $out, "gtag('consent', 'update', {\n        'analytics_storage':  a," ) !== false );
cm_assert( 'GTM blijft geladen (advanced: cookieloze pings)', loads_gtm_live( $out ) );

// --- Akkoord (client-side; event + UET) ------------------------------------
cm_test_group( 'Akkoord — dataLayer-event en UET' );
$out = render_consent( array(), array( 'analytics' => true, 'marketing' => true, 'method' => 'accept-all' ) );
cm_assert( 'GTM wordt live geladen', loads_gtm_live( $out ) );
cm_assert( 'GA4 wordt live geladen', loads_ga4_live( $out ) );
cm_assert( 'dataLayer-event cm_consent_update aanwezig', strpos( $out, "'event':              'cm_consent_update'" ) !== false );
cm_assert( 'cm_analytics als boolean uit de cookie', strpos( $out, "'cm_analytics':       !!c.analytics," ) !== false );
cm_assert( 'Microsoft UET consent update aanwezig', strpos( $out, "window.uetq.push('consent', 'update', { 'ad_storage': k });" ) !== false );

exit( cm_test_summary() );
