<?php
/**
 * Browserscan (3.1).
 *
 * Borgt: de scanmodus is alleen actief met een geldige code én als ingelogde
 * beheerder (een bezoeker kan hem nooit aanzetten); in scanmodus geen banner,
 * geen blokkering en consent op granted; de lookup filtert eigen, ingebouwde
 * en beheerderscookies, herkent bekende diensten aan geladen adressen en
 * meldt onbekende domeinen.
 */

$GLOBALS['cm_test_logged_in'] = true;
$GLOBALS['cm_test_can']       = true;
function is_user_logged_in() { return (bool) $GLOBALS['cm_test_logged_in']; }
function current_user_can( $c ) { return (bool) $GLOBALS['cm_test_can']; }
function wp_verify_nonce( $n, $a = -1 ) { return $n === 'goed' && $a === 'cm_browser_scan' ? 1 : false; }
function nocache_headers() {}
function show_admin_bar( $b ) {}

class CM_Test_Wpdb { // geen cookietabel → kennisbank-fallback
    public $prefix = 'wp_';
    public function prepare( $q ) { return $q; }
    public function get_var( $q ) { return null; }
}
$GLOBALS['wpdb'] = new CM_Test_Wpdb();

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin/scan.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-cookies.php';
require CM_PLUGIN_ROOT . '/includes/browser-scan.php';

cm_test_group( 'Scanmodus alleen voor de beheerder' );
$_GET = array();
cm_assert( 'zonder code: uit', ! cm_is_browser_scan() );
$_GET['cm_browser_scan'] = 'fout';
cm_assert( 'verkeerde code: uit', ! cm_is_browser_scan() );
$_GET['cm_browser_scan'] = array( 'goed' );
cm_assert( 'code als array: uit', ! cm_is_browser_scan() );
$_GET['cm_browser_scan'] = 'goed';
cm_assert( 'geldige code + beheerder: aan', cm_is_browser_scan() );
$GLOBALS['cm_test_logged_in'] = false;
cm_assert( 'niet ingelogd: uit', ! cm_is_browser_scan() );
$GLOBALS['cm_test_logged_in'] = true;
$GLOBALS['cm_test_can'] = false;
cm_assert( 'ingelogd zonder manage_options: uit', ! cm_is_browser_scan() );
$GLOBALS['cm_test_can'] = true;

cm_test_group( 'Frontend in scanmodus' );
$fe = file_get_contents( CM_PLUGIN_ROOT . '/includes/frontend.php' );
cm_assert( 'blokkering, scriptblocker en banner slaan scanmodus over', substr_count( $fe, 'cm_is_browser_scan()' ) >= 4 );
require CM_PLUGIN_ROOT . '/includes/frontend.php';
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'gtm_container_id' => 'GTM-TEST1' ) ) );
function consent_markup() { ob_start(); cm_inject_google_consent_mode(); return ob_get_clean(); }
$on = consent_markup();
cm_assert( 'scanmodus: alle consent-defaults granted', strpos( $on, "'ad_storage':         'granted'" ) !== false && strpos( $on, "'denied'" ) === false );
cm_assert( 'scanmodus: eerdere keuze uit de cookie overschrijft niets', strpos( $on, 'cc_cm_consent' ) === false );
$_GET = array();
$off = consent_markup();
cm_assert( 'bezoeker: advertentie-consent denied', substr_count( $off, "'denied'" ) >= 3 );
$_GET['cm_browser_scan'] = 'goed';
$bs = file_get_contents( CM_PLUGIN_ROOT . '/includes/browser-scan.php' );
cm_assert( 'scanmodus nooit in de paginacache', strpos( $bs, 'DONOTCACHEPAGE' ) !== false && strpos( $bs, 'nocache_headers()' ) !== false );

cm_test_group( 'Lookup: filteren en herkennen' );
$r = cm_browser_scan_rows(
    array( '_ga', '_ga_ABC123', 'cc_cm_consent', 'cm_sid', 'wp-settings-1', 'wp-saving-post', 'wordpress_test_cookie', '_ga' ),
    array( 'lenis-state' ),
    array(
        'https://www.googletagmanager.com/gtm.js',
        'https://example.test/wp-content/themes/x.js',
        'https://cdn.onbekend.example/lib.js',
    )
);
$names = array_column( $r['cookies'], 'name' );
$by    = array_column( $r['cookies'], null, 'name' );
cm_assert( '_ga gevonden, één keer', count( array_keys( $names, '_ga', true ) ) === 1 );
cm_assert( '_ga uit de browser, als analytics', $by['_ga']['how'] === 'browser' && $by['_ga']['type'] === 'analytics' );
cm_assert( 'ingebouwde cookie niet opnieuw', ! in_array( 'cc_cm_consent', $names, true ) );
cm_assert( 'eigen opslag (cm_sid) niet', ! in_array( 'cm_sid', $names, true ) );
cm_assert( 'beheerderscookies niet', ! array_intersect( array( 'wp-settings-1', 'wp-saving-post', 'wordpress_test_cookie' ), $names ) );
cm_assert( 'opslag gemeld als opslag', isset( $by['lenis-state'] ) && $by['lenis-state']['how'] === 'storage' );
cm_assert( 'GTM geladen → _gcl_au afgeleid', isset( $by['_gcl_au'] ) && $by['_gcl_au']['how'] === 'host' );
cm_assert( '_ga_ niet naast _ga_ABC123', ! in_array( '_ga_', $names, true ) );
cm_assert( 'eigen domein niet als onbekend', ! in_array( 'example.test', $r['hosts'], true ) );
cm_assert( 'onbekend domein gemeld', $r['hosts'] === array( 'cdn.onbekend.example' ) );

exit( cm_test_summary() );
