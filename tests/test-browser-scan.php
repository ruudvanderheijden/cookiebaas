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
function wp_set_current_user( $id ) { $GLOBALS['cm_test_logged_in'] = $id > 0; }
function wp_doing_ajax() { return false; }
function wp_date( $f, $t = null ) { return gmdate( $f, $t === null ? time() : $t ); }

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
require CM_PLUGIN_ROOT . '/includes/admin/page-overzicht.php';
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
$_GET['cm_scan_fresh'] = '1';
cm_assert( 'als nieuwe bezoeker: modus fresh, niet de modus met alles geaccepteerd', cm_browser_scan_mode() === 'fresh' && ! cm_is_browser_scan() );
unset( $_GET['cm_scan_fresh'] );
cm_assert( 'zonder fresh: modus full', cm_browser_scan_mode() === 'full' );

cm_test_group( 'Scan als niet-ingelogde bezoeker' );
cm_browser_scan_prepare();
cm_assert( 'na het voorbereiden is de beheerder afgemeld voor dit verzoek', ! is_user_logged_in() );
cm_assert( 'de scanmodus blijft gelden (vastgelegd vóór het afmelden)', cm_is_browser_scan() );
unset( $GLOBALS['cm_browser_scan_mode'] );
$GLOBALS['cm_test_logged_in'] = true;

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
$_GET['cm_scan_fresh'] = '1';
$fresh = consent_markup();
cm_assert( 'als nieuwe bezoeker: consent-defaults denied, zoals voor elke bezoeker', substr_count( $fresh, "'denied'" ) >= 3 && strpos( $fresh, "'cm_method': 'scan'" ) === false );
ob_start(); cm_browser_scan_head(); $fh = ob_get_clean();
cm_assert( 'als nieuwe bezoeker: eigen keuze van de beheerder onzichtbaar en niet te overschrijven', strpos( $fh, 'window.cmScanFresh=true' ) !== false && strpos( $fh, "indexOf('cc_cm_consent=')!==0" ) !== false && strpos( $fh, "searchParams.delete('cm_scan_fresh')" ) !== false );
cm_assert( 'als nieuwe bezoeker: niets in de consent log', strpos( file_get_contents( CM_PLUGIN_ROOT . '/includes/frontend.php' ), 'if (window.cmScanFresh) return;' ) !== false );
cm_assert( 'headscript vóór Consent Mode (die leest de cookie)', strpos( file_get_contents( CM_PLUGIN_ROOT . '/includes/browser-scan.php' ), "add_action( 'wp_head', 'cm_browser_scan_head', -1001 )" ) !== false && strpos( file_get_contents( CM_PLUGIN_ROOT . '/includes/frontend.php' ), "add_action( 'wp_head', 'cm_inject_google_consent_mode', -1000 )" ) !== false );
$_GET = array();
$off = consent_markup();
cm_assert( 'bezoeker: advertentie-consent denied', substr_count( $off, "'denied'" ) >= 3 );
cm_assert( 'bezoeker: geen scan-event', strpos( $off, "'cm_method': 'scan'" ) === false );
ob_start(); cm_browser_scan_head(); cm_assert( 'bezoeker: geen scan-headscript', ob_get_clean() === '' );
$_GET['cm_browser_scan'] = 'goed';
$bs = file_get_contents( CM_PLUGIN_ROOT . '/includes/browser-scan.php' );
cm_assert( 'scanmodus nooit in de paginacache', strpos( $bs, 'DONOTCACHEPAGE' ) !== false && strpos( $bs, 'nocache_headers()' ) !== false );

cm_test_group( 'Na toestemming: filteren, herkennen, looptijd' );
$r = cm_browser_scan_rows( array(
    'cookies'   => array( '_ga', '_ga_ABC123', 'cc_cm_consent', 'cm_sid', 'wp-settings-1', 'wp-saving-post', 'wordpress_test_cookie', '_ga', 'raar_ding' ),
    'existing'  => array( 'redux_current_tab', 'redux_current_tab_get', '_gid' ), // stonden al in de browser (Brinckers: Redux uit het Salient-optiescherm)
    'local'     => array( 'lenis-state' ),
    'session'   => array( 'tab-state' ),
    'durations' => array( '_ga' => 400 * 86400, 'raar_ding' => 0 ),
    'resources' => array(
        'https://www.googletagmanager.com/gtm.js',
        'https://example.test/wp-content/themes/x.js',
        'https://www.googletagmanager.com/ns.html',
        'https://fonts.googleapis.com/css2',
        'https://cdn.jsdelivr.net/npm/x.js',
        'https://cdn.onbekend.example/lib.js',
    ),
) );
$names = array_column( $r['cookies'], 'name' );
$by    = array_column( $r['cookies'], null, 'name' );
cm_assert( '_ga gevonden, één keer', count( array_keys( $names, '_ga', true ) ) === 1 );
cm_assert( '_ga uit de browser, als analytics, met gemeten looptijd', $by['_ga']['how'] === 'browser' && $by['_ga']['type'] === 'analytics' && $by['_ga']['duration'] === '1.1 jaar' );
cm_assert( 'onbekende sessiecookie: gemeten "Sessie"', $by['raar_ding']['duration'] === 'Sessie' && $by['raar_ding']['type'] === 'unknown' );
cm_assert( 'ingebouwde cookie niet opnieuw', ! in_array( 'cc_cm_consent', $names, true ) );
cm_assert( 'eigen opslag (cm_sid) niet', ! in_array( 'cm_sid', $names, true ) );
cm_assert( 'beheerderscookies niet', ! array_intersect( array( 'wp-settings-1', 'wp-saving-post', 'wordpress_test_cookie' ), $names ) );
cm_assert( 'localStorage: blijvend, niet "Sessie"', $by['lenis-state']['how'] === 'storage' && $by['lenis-state']['duration'] === 'Blijvend (tot verwijderd)' );
cm_assert( 'sessionStorage: sessie', $by['tab-state']['duration'] === 'Sessie' );
cm_assert( 'GTM geladen → _gcl_au afgeleid', isset( $by['_gcl_au'] ) && $by['_gcl_au']['how'] === 'host' );
cm_assert( '_ga_ niet naast _ga_ABC123', ! in_array( '_ga_', $names, true ) );
cm_assert( 'onbekend domein gemeld; eigen, herkende en cookieloze hosts niet', $r['hosts'] === array( 'cdn.onbekend.example' ) );
cm_assert( 'ontvangers zonder cookies (IP-adres) apart, niet verborgen', $r['external'] === array( 'fonts.googleapis.com', 'cdn.jsdelivr.net' ) );
cm_assert( 'al aanwezige onbekende cookies (redux_*) niet gemeld', ! in_array( 'redux_current_tab', $names, true ) && ! in_array( 'redux_current_tab_get', $names, true ) );
cm_assert( 'al aanwezige bekende cookie (_gid) wel', isset( $by['_gid'] ) && $by['_gid']['how'] === 'browser' );

cm_test_group( 'Vóór toestemming' );
update_option( 'cm_cookie_list', array( array( 'name' => 'eigen_marketing', 'category' => 'marketing' ), array( 'name' => 'eigen_functioneel', 'category' => 'functional' ) ) );
$p = cm_browser_scan_preconsent( array(
    'cookies'   => array( '_ga_ABC123', 'cm_sid', 'cc_cm_consent', 'PHPSESSID', 'raar_ding', 'eigen_marketing', 'eigen_functioneel', 'wp-settings-1' ),
    'local'     => array( '_hjSessionUser_1' ),
    'session'   => array(),
    'resources' => array(
        'https://connect.facebook.net/en_US/fbevents.js',
        'https://www.googletagmanager.com/gtag/js',
        'https://fonts.gstatic.com/s/x.woff2',
        'https://cdn.onbekend.example/lib.js',
        'https://example.test/wp-content/x.js',
    ),
) );
$it = array_column( $p['items'], null, 'name' );
cm_assert( 'analytische cookie vóór toestemming → fout', $it['_ga_ABC123']['level'] === 'error' );
cm_assert( 'eigen indeling van de beheerder gaat voor (marketing → fout, functioneel → mag)', $it['eigen_marketing']['level'] === 'error' && ! isset( $it['eigen_functioneel'] ) );
cm_assert( 'functioneel (PHPSESSID), eigen opslag en beheerderscookies: geen bevinding', ! isset( $it['PHPSESSID'] ) && ! isset( $it['cm_sid'] ) && ! isset( $it['cc_cm_consent'] ) && ! isset( $it['wp-settings-1'] ) );
cm_assert( 'onbekende cookie → controleren', $it['raar_ding']['level'] === 'warn' );
cm_assert( 'Meta laadt vóór toestemming → fout, met blokkeerknop marketing', $it['connect.facebook.net']['level'] === 'error' && $it['connect.facebook.net']['block'] === 'marketing' );
cm_assert( 'Google-tag in Consent Mode advanced → alleen info', $it['www.googletagmanager.com']['level'] === 'info' && $it['www.googletagmanager.com']['block'] === '' );
cm_assert( 'lettertypen van Google → info (IP-adres), geen blokkeerknop', $it['fonts.gstatic.com']['level'] === 'info' && $it['fonts.gstatic.com']['block'] === '' );
cm_assert( 'onbekende dienst → controleren, blokkeerbaar', $it['cdn.onbekend.example']['level'] === 'warn' && $it['cdn.onbekend.example']['block'] === 'marketing' );
cm_assert( 'eigen site geen bevinding', ! isset( $it['example.test'] ) );
cm_assert( 'onbekende opslag in de browser (zonder cookiedatabase) → controleren', $it['_hjSessionUser_1']['level'] === 'warn' && $it['_hjSessionUser_1']['kind'] === 'storage' );
cm_assert( 'tellingen', $p['errors'] === 3 && $p['warnings'] === 3 );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'google_consent_mode_advanced' => 0 ) ) );
$p2 = cm_browser_scan_preconsent( array( 'resources' => array( 'https://www.googletagmanager.com/gtag/js' ) ) );
cm_assert( 'zonder advanced mode: Google-tag vóór toestemming → fout', $p2['items'][0]['level'] === 'error' );
update_option( 'cm_cookie_list', array() );

cm_test_group( 'Blokkeren vanuit de scan' );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'block_marketing_patterns' => 'bestaand.nl' ) ) );
cm_assert( 'host toegevoegd aan de marketingpatronen', cm_block_host( 'cdn.onbekend.example', 'marketing' ) && cm_get_settings()['block_marketing_patterns'] === 'bestaand.nl, cdn.onbekend.example' );
cm_assert( 'geen dubbele', cm_block_host( 'cdn.onbekend.example', 'marketing' ) && cm_get_settings()['block_marketing_patterns'] === 'bestaand.nl, cdn.onbekend.example' );
cm_assert( 'geen rommel of andere categorie', ! cm_block_host( 'evil.com,<script>', 'marketing' ) && ! cm_block_host( 'x.nl', 'functional' ) );

cm_test_group( 'Overzicht: meting gaat voor instellingen' );
list( $st ) = cm_preconsent_status( array(), true );
cm_assert( 'nog niet gemeten → aandacht nodig', $st === 'warn' );
list( $st ) = cm_preconsent_status( array( 'date' => gmdate( 'Y-m-d H:i:s' ), 'errors' => 2, 'warnings' => 0 ), true );
cm_assert( 'meting met fouten → niet in orde, ook als de instellingen kloppen', $st === 'fail' );
list( $st ) = cm_preconsent_status( array( 'date' => gmdate( 'Y-m-d H:i:s' ), 'errors' => 0, 'warnings' => 1 ), true );
cm_assert( 'alleen onbekende → aandacht nodig', $st === 'warn' );
list( $st ) = cm_preconsent_status( array( 'date' => gmdate( 'Y-m-d H:i:s' ), 'errors' => 0, 'warnings' => 0 ), false );
cm_assert( 'schone meting → in orde', $st === 'ok' );
list( $st ) = cm_preconsent_status( array( 'date' => gmdate( 'Y-m-d H:i:s', time() - 100 * 86400 ), 'errors' => 0, 'warnings' => 0 ), true );
cm_assert( 'meting ouder dan drie maanden → opnieuw', $st === 'warn' );

cm_test_group( 'Onbekende cookies: eerst een categorie kiezen' );
$js = file_get_contents( CM_PLUGIN_ROOT . '/assets/js/admin-cookies.js' );
cm_assert( 'keuzelijst bij onbekend, Toevoegen pas na keuze', strpos( $js, "el('select', null, 'cm-scan-cat')" ) !== false && strpos( $js, 'if (!known) add.disabled = true' ) !== false && strpos( $js, 'btn.disabled = !sel.value' ) !== false );
cm_assert( 'alles toevoegen slaat onbekende zonder keuze over en meldt dat', strpos( $js, 'overgeslagen: kies eerst een categorie' ) !== false );
cm_assert( 'gekozen categorie gaat mee naar de lijst', cm_scan_result_to_row( array( 'name' => 'x_track', 'type' => 'marketing' ) )['category'] === 'marketing' );

exit( cm_test_summary() );
