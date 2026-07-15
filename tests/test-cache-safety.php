<?php
/**
 * Cache-veiligheid — de belangrijkste test.
 *
 * Borgt de fix uit v1.7.7: de gerenderde HTML mag NIET afhangen van de
 * consent-cookie van de bezoeker die de pagina genereert. Paginacaches
 * (LiteSpeed, WP Rocket, Cloudflare) serveren één kopie aan iedereen — een
 * server-side ingebakken "granted" zou dan aan álle bezoekers worden gegeven.
 *
 * Zie ook de memory 'cookiebaas-cache-safety-rule'.
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

// Voorbeeldpagina met een third-party tracker, een vreemd GTM-snippet en een embed.
$PAGE =
      '<html><head>'
    . '<script src="https://static.hotjar.com/c/hotjar-123.js"></script>'
    . '<script>(function(w,d,s,l,i){w[l]=w[l]||[];})(window,document,"script","dataLayer","GTM-OTHER");</script>'
    . '<script src="https://connect.facebook.net/en_US/fbevents.js"></script>'
    . '</head><body><iframe src="https://www.youtube.com/embed/abc123" width="560" height="315"></iframe></body></html>';

/** Volledige plugin-output (head-injectie + gefilterde body) voor een cookie-status. */
function render_full( $cookie ) {
    global $PAGE;
    if ( $cookie === null ) unset( $_COOKIE['cc_cm_consent'] );
    else $_COOKIE['cc_cm_consent'] = urlencode( json_encode( $cookie ) );
    ob_start();
    cm_inject_google_consent_mode();
    $head = ob_get_clean();
    return $head . cm_filter_buffer( $PAGE );
}

function run_mode( $advanced ) {
    cm_test_set_settings( array(
        'ga4_measurement_id'           => '',
        'gtm_container_id'             => 'GTM-TEST123',
        'ua_tracking_id'               => '',
        'google_consent_mode_advanced' => $advanced ? 1 : 0,
        'google_load_default'          => 0,
        'google_url_passthrough'       => 0,
        'embed_blocker_enabled'        => 1,
        'block_analytics_patterns'     => '',
        'block_marketing_patterns'     => '',
    ) );

    $none     = render_full( null );
    $accepted = render_full( array( 'analytics' => true,  'marketing' => true,  'method' => 'accept-all' ) );
    $rejected = render_full( array( 'analytics' => false, 'marketing' => false, 'method' => 'reject-all' ) );
    $partial  = render_full( array( 'analytics' => true,  'marketing' => false, 'method' => 'custom' ) );

    $label = $advanced ? 'advanced' : 'basic';

    cm_test_group( "[$label] HTML is identiek voor elke bezoeker (cache-veilig)" );
    cm_assert( 'geen cookie == geaccepteerd', $none === $accepted );
    cm_assert( 'geen cookie == geweigerd',    $none === $rejected );
    cm_assert( 'geen cookie == gedeeltelijk', $none === $partial );

    cm_test_group( "[$label] Geen ingebakken consent-status" );
    cm_assert( 'consent-update gebruikt runtime-variabelen (a/k), geen ingebakken status',
        strpos( $none, "gtag('consent', 'update', {\n        'analytics_storage':  a," ) !== false );
    cm_assert( 'geen ingebakken cm_method (bijv. accept-all)', strpos( $accepted, 'accept-all' ) === false );
    cm_assert( 'consent DEFAULT staat wel in de HTML (denied)', strpos( $none, "'analytics_storage':  'denied'" ) !== false );
    cm_assert( 'client-side cookie-lezer aanwezig', strpos( $none, 'cc_cm_consent=([^;]*)' ) !== false );

    // Gedrag van de gedeelde blocker-interpreter op een extern Google-script.
    $cfg = cm_blocker_config();
    $google_blocked = cm_blocker_match( 'https://www.googletagmanager.com/gtag/js?id=G-X', '', $cfg );
    if ( $advanced ) {
        cm_test_group( '[advanced] Google-tags niet blokkeren (Consent Mode regelt ze)' );
        cm_assert( 'GTM-loader van de plugin wordt live geladen', strpos( $none, 'data-cm-allow="1"' ) !== false );
        cm_assert( 'GTM-snippet van ander plugin NIET geblokkeerd',
            strpos( $none, '<script type="text/plain" data-cm-type="analytics">(function(w,d,s,l,i)' ) === false );
        cm_assert( 'interpreter blokkeert een extern Google-script NIET', $google_blocked === false );
    } else {
        cm_test_group( '[basic] Google volledig blokkeren tot consent' );
        cm_assert( 'GTM-loader geblokkeerd (text/plain)',
            strpos( $none, '<script type="text/plain" data-cm-type="analytics">(function(w,d,s,l,i)' ) !== false );
        cm_assert( 'geen live GTM-loader zonder consent', strpos( $none, 'data-cm-allow="1"' ) === false );
        cm_assert( 'interpreter blokkeert een extern Google-script WEL', $google_blocked === 'analytics' );
    }

    cm_test_group( "[$label] Niet-Google trackers blijven altijd geblokkeerd" );
    cm_assert( 'Hotjar geblokkeerd', strpos( $none, 'data-cm-type="analytics"' ) !== false && strpos( $none, 'hotjar' ) !== false );
    cm_assert( 'Facebook Pixel geblokkeerd', strpos( $none, 'data-cm-type="marketing"' ) !== false );
    cm_assert( 'YouTube-embed vervangen door placeholder', strpos( $none, 'cm-embed-placeholder' ) !== false );
}

run_mode( true );
run_mode( false );

exit( cm_test_summary() );
