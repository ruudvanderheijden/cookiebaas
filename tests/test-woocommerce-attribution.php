<?php
/**
 * WooCommerce Order Attribution (sbjs_*-cookies) alleen met marketingtoestemming (3.2.3).
 *
 * Borgt: vóór order-attribution.js staat een script dat allowTracking in de
 * browser uitzet zonder marketingtoestemming (niet in de browserscan); na een
 * keuze zet sendConsentSignals() de tracking aan of uit; bij intrekken van
 * marketing ruimt Cookiebaas sbjs_* op; de migratie zet bestaande
 * WooCommerce-rijen op marketing en sessie. Is node aanwezig, dan draait het
 * script echt tegen nagebootste cookies.
 */

$GLOBALS['cm_test_enqueued'] = array();
$GLOBALS['cm_test_inline']   = array();
function wp_script_is( $h, $list = 'enqueued' ) { return in_array( $h, $GLOBALS['cm_test_enqueued'], true ); }
function wp_add_inline_script( $h, $js, $pos = 'after' ) { $GLOBALS['cm_test_inline'][] = array( $h, $js, $pos ); return true; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';
require CM_PLUGIN_ROOT . '/includes/browser-scan.php';

cm_test_group( 'Script vóór order-attribution.js' );
cm_wc_order_attribution_consent();
cm_assert( 'zonder WooCommerce-script niets', ! $GLOBALS['cm_test_inline'] );
$GLOBALS['cm_test_enqueued'] = array( 'wc-order-attribution' );
cm_wc_order_attribution_consent();
$inl = $GLOBALS['cm_test_inline'][0] ?? array( '', '', '' );
cm_assert( 'met WooCommerce: inline script ervoor', $inl[0] === 'wc-order-attribution' && $inl[2] === 'before' );
cm_assert( 'leest de toestemming in de browser (cache-veilig)', strpos( $inl[1], 'cc_cm_consent' ) !== false && strpos( $inl[1], 'c.marketing' ) !== false );
cm_assert( 'gekoppeld aan wp_footer vóór de footerscripts', has_action( 'wp_footer' ) );
$GLOBALS['cm_test_inline'] = array();
$GLOBALS['cm_browser_scan_mode'] = 'full';
cm_wc_order_attribution_consent();
cm_assert( 'browserscan met alles geaccepteerd: niets', ! $GLOBALS['cm_test_inline'] );
$GLOBALS['cm_browser_scan_mode'] = 'fresh';
cm_wc_order_attribution_consent();
cm_assert( 'browserscan vóór toestemming: wel (zoals een nieuwe bezoeker)', count( $GLOBALS['cm_test_inline'] ) === 1 );
unset( $GLOBALS['cm_browser_scan_mode'] );

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( $node ) {
    $run = function( $cookie ) use ( $node, $inl ) {
        $f = tempnam( sys_get_temp_dir(), 'cmwc' ) . '.js';
        file_put_contents( $f, 'globalThis.window=globalThis;globalThis.document={cookie:' . json_encode( $cookie ) . '};globalThis.wc_order_attribution={params:{allowTracking:true}};' . $inl[1] . 'console.log(wc_order_attribution.params.allowTracking);' );
        $out = trim( (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $f ) . ' 2>&1' ) );
        unlink( $f );
        return $out;
    };
    $c = function( $a ) { return 'cc_cm_consent=' . rawurlencode( json_encode( $a ) ); };
    cm_assert( 'node: geen keuze → tracking uit', $run( 'foo=1' ) === 'false' );
    cm_assert( 'node: alleen analytisch → tracking uit', $run( 'x=1; ' . $c( array( 'analytics' => true, 'marketing' => false ) ) ) === 'false' );
    cm_assert( 'node: marketing → tracking blijft aan', $run( 'x=1; ' . $c( array( 'analytics' => false, 'marketing' => true ) ) ) === 'true' );
    cm_assert( 'node: kapotte cookie → tracking uit', $run( 'cc_cm_consent=%7Bkapot' ) === 'false' );
} else {
    echo "  (node niet gevonden: script niet uitgevoerd)\n";
}

cm_test_group( 'Na een keuze en bij intrekken' );
$src = file_get_contents( CM_PLUGIN_ROOT . '/includes/frontend.php' );
cm_assert( 'sendConsentSignals zet WooCommerce aan of uit', (bool) preg_match( '/function sendConsentSignals\(.*?wc_order_attribution\.setOrderTracking\(!!marketing\);.*?\n        \}/s', $src ) );
preg_match( "/var prefixes = type === 'analytics'\s*\? \[(.*?)\]\s*: \[(.*?)\]/s", $src, $p );
cm_assert( 'sbjs_ opgeruimd bij intrekken marketing, niet bij analytisch', isset( $p[2] ) && strpos( $p[2], "'sbjs_'" ) !== false && strpos( $p[1], 'sbjs_' ) === false );

cm_test_group( 'Migratie 3.2.3' );
update_option( 'cm_cookie_list', array(
    array( 'name' => 'sbjs_first', 'provider' => 'WooCommerce', 'purpose' => 'x', 'duration' => '6 maanden', 'category' => 'analytics' ),
    array( 'name' => 'sbjs_session', 'provider' => 'WooCommerce', 'purpose' => 'x', 'duration' => '30 min', 'category' => 'analytics' ),
    array( 'name' => 'sbjs_udata', 'provider' => 'WooCommerce', 'purpose' => 'x', 'duration' => '1 jaar', 'category' => 'functional' ),
    array( 'name' => 'sbjs_eigen', 'provider' => 'Eigen', 'purpose' => 'x', 'duration' => '6 maanden', 'category' => 'analytics' ),
    array( 'name' => '_ga', 'provider' => 'Google', 'purpose' => 'x', 'duration' => '2 jaar', 'category' => 'analytics' ),
) );
cm_migrate_sbjs_cookies();
$l = get_option( 'cm_cookie_list' );
cm_assert( 'sbjs_first → marketing, sessie', $l[0]['category'] === 'marketing' && $l[0]['duration'] === 'Sessie' );
cm_assert( 'sbjs_session → marketing, 30 min blijft', $l[1]['category'] === 'marketing' && $l[1]['duration'] === '30 min' );
cm_assert( 'zelf gekozen categorie en looptijd blijven', $l[2]['category'] === 'functional' && $l[2]['duration'] === '1 jaar' );
cm_assert( 'andere provider en andere cookies ongemoeid', $l[3]['category'] === 'analytics' && $l[3]['duration'] === '6 maanden' && $l[4]['category'] === 'analytics' );
$main = file_get_contents( CM_PLUGIN_ROOT . '/cookiemelding.php' );
cm_assert( 'migratie draait bij update van vóór 3.2.3', strpos( $main, "version_compare( \$stored_version, '3.2.3', '<' ) ) cm_migrate_sbjs_cookies();" ) !== false );

exit( cm_test_summary() );
