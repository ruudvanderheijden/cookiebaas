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
require CM_PLUGIN_ROOT . '/includes/admin/ajax.php';
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
cm_assert( 'scanmodus: GTM-event cm_consent_update met alles granted', strpos( $on, "'event': 'cm_consent_update', 'cm_analytics': true, 'cm_marketing': true, 'cm_method': 'scan'" ) !== false );
ob_start(); cm_browser_scan_head(); $head = ob_get_clean();
cm_assert( 'scanmodus: scancode uit de adresbalk (statistieken) en grotere meetbuffer', strpos( $head, "searchParams.delete('cm_browser_scan')" ) !== false && strpos( $head, 'setResourceTimingBufferSize' ) !== false );
$_GET = array();
$off = consent_markup();
cm_assert( 'bezoeker: advertentie-consent denied', substr_count( $off, "'denied'" ) >= 3 );
cm_assert( 'bezoeker: geen scan-event', strpos( $off, "'cm_method': 'scan'" ) === false );
ob_start(); cm_browser_scan_head(); cm_assert( 'bezoeker: geen scan-headscript', ob_get_clean() === '' );
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
        'https://www.googletagmanager.com/ns.html',
        'https://fonts.googleapis.com/css2',
        'https://fonts.gstatic.com/s/x.woff2',
        'https://cdn.onbekend.example/lib.js',
    ),
    array( 'redux_current_tab', 'redux_current_tab_get', '_gid' ) // stonden al in de browser (Brinckers: Redux uit het Salient-optiescherm)
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
cm_assert( 'onbekend domein gemeld; herkende host (GTM) en Google Fonts niet', $r['hosts'] === array( 'cdn.onbekend.example' ) );
cm_assert( 'al aanwezige onbekende cookies (redux_*) niet gemeld', ! in_array( 'redux_current_tab', $names, true ) && ! in_array( 'redux_current_tab_get', $names, true ) );
cm_assert( 'al aanwezige bekende cookie (_gid) wel', isset( $by['_gid'] ) && $by['_gid']['how'] === 'browser' );
cm_assert( 'Google Fonts: toelichting over IP-adres naar Google', count( $r['notes'] ) === 1 && strpos( $r['notes'][0], 'Google Fonts' ) === 0 );
$r2 = cm_browser_scan_rows( array(), array(), array( 'https://www.googletagmanager.com/gtm.js' ) );
cm_assert( 'zonder Google Fonts geen toelichting', $r2['notes'] === array() );

cm_test_group( 'Onbekende cookies: eerst een categorie kiezen' );
$js = file_get_contents( CM_PLUGIN_ROOT . '/assets/js/admin-cookies.js' );
cm_assert( 'keuzelijst bij onbekend, Toevoegen pas na keuze', strpos( $js, "el('select', null, 'cm-scan-cat')" ) !== false && strpos( $js, 'if (!known) add.disabled = true' ) !== false && strpos( $js, 'btn.disabled = !sel.value' ) !== false );
cm_assert( 'alles toevoegen slaat onbekende zonder keuze over en meldt dat', strpos( $js, 'overgeslagen: kies eerst een categorie' ) !== false );
cm_assert( 'gekozen categorie gaat mee naar de lijst', cm_scan_result_to_row( array( 'name' => 'x_track', 'type' => 'marketing' ) )['category'] === 'marketing' );

exit( cm_test_summary() );
