<?php
/**
 * Overstap naar 3.0 — de oude admin is weg, wat nog nodig is, is verhuisd.
 *
 * Borgt: de oude bestanden, de gedeelde nonce en de oude AJAX-handlers
 * zijn weg; scan, cookiedatabase en de frontend-AJAX (consent loggen,
 * geo-check) bestaan nog, met dezelfde actienamen.
 */

function add_menu_page( $page_title, $menu_title, $cap, $slug, $cb, $icon, $pos ) { $GLOBALS['cm_test_menu'] = array( $menu_title, $slug, $icon, $pos ); return 'toplevel_page_' . $slug; }
function add_submenu_page( $parent, $page_title, $menu_title, $cap, $slug, $cb ) { $GLOBALS['cm_test_sub'][] = $slug; return 'cookiebaas_page_' . $slug; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';

cm_test_group( 'Oude admin is weg' );
foreach ( array( 'includes/admin.php', 'assets/js/admin.js', 'assets/css/admin.css' ) as $f ) {
    cm_assert( "$f verwijderd", ! file_exists( CM_PLUGIN_ROOT . '/' . $f ) );
}
$src = '';
foreach ( array_merge( array( CM_PLUGIN_ROOT . '/cookiemelding.php' ), glob( CM_PLUGIN_ROOT . '/includes/*.php' ), glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ), glob( CM_PLUGIN_ROOT . '/assets/js/*.js' ) ) as $f ) {
    $src .= file_get_contents( $f );
}
cm_assert( 'geen gedeelde nonce cm_save_settings meer', strpos( $src, 'cm_save_settings' ) === false );
cm_assert( 'geen links naar de oude slugs', strpos( $src, 'page=cookiemelding' ) === false );
cm_assert( 'geen oude AJAX-handlers', ! preg_match( '/wp_ajax_cm_(save_settings|save_privacy|save_cookie_list|get_cookie_list|get_log|export_|import_settings|reset_|bump_consent|license_|save_scan_settings|clear_log|delete_log_row|save_license_url)/', $src ) );

cm_test_group( 'Verhuisd, niet verdwenen' );
require CM_PLUGIN_ROOT . '/includes/admin/scan.php';
require CM_PLUGIN_ROOT . '/includes/consent.php';
foreach ( array( 'cm_ajax_import_cookie_db', 'cm_parse_csv_line', 'cm_ajax_scan_urls', 'cm_ajax_scan_batch', 'cm_cookie_prefix_match', 'cm_fallback_cookies', 'cm_server_env_cookies', 'cm_script_signatures', 'cm_secs_to_human', 'cm_ajax_geo_check', 'cm_ajax_log_consent' ) as $fn ) {
    cm_assert( "$fn bestaat", function_exists( $fn ) );
}
foreach ( array( 'wp_ajax_cm_scan_urls', 'wp_ajax_cm_scan_batch', 'wp_ajax_cm_import_cookie_db', 'wp_ajax_cm_log_consent', 'wp_ajax_nopriv_cm_log_consent', 'wp_ajax_cm_geo_check', 'wp_ajax_nopriv_cm_geo_check' ) as $hook ) {
    cm_assert( "$hook geregistreerd", has_action( $hook ) );
}
$main = file_get_contents( CM_PLUGIN_ROOT . '/cookiemelding.php' );
cm_assert( 'de scanmail linkt naar de nieuwe scanpagina (Review Focus 3; 3.1: daar kiest u ook de categorie van onbekende cookies)', strpos( $main, "page=cookiebaas-cookies&tab=scannen" ) !== false );
cm_assert( 'cookiemelding.php laadt consent.php en scan.php', strpos( $main, "includes/consent.php" ) !== false && strpos( $main, "includes/admin/scan.php" ) !== false );

cm_test_group( 'Menu' );
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-log.php';
$GLOBALS['cm_test_sub'] = array();
cm_admin3_register_menu();
cm_assert( 'topmenu Cookiebaas op 81 met dashicons-privacy', $GLOBALS['cm_test_menu'] === array( 'Cookiebaas', 'cookiebaas', 'dashicons-privacy', 81 ) );
cm_assert( 'zeven pagina’s in de volgorde van de spec', $GLOBALS['cm_test_sub'] === array( 'cookiebaas', 'cookiebaas-banner', 'cookiebaas-blokkering', 'cookiebaas-cookies', 'cookiebaas-privacy', 'cookiebaas-log', 'cookiebaas-beheer' ) );
cm_assert( 'bulk verwijderen hangt aan de load-hook van Consent log', has_action( 'load-cookiebaas_page_cookiebaas-log' ) );

cm_test_group( 'Oude adressen (Review Focus 3)' );
$map = array(
    'cookiemelding'         => 'cookiebaas',
    'cookiemelding-cookies' => 'cookiebaas-cookies',
    'cookiemelding-privacy' => 'cookiebaas-privacy',
    'cookiemelding-log'     => 'cookiebaas-log',
    'cookiemelding-beheer'  => 'cookiebaas-beheer',
);
foreach ( $map as $old => $new ) cm_assert( "$old → $new", cm_admin_old_slug_target( $old ) === $new );
cm_assert( 'andere slug → niets', cm_admin_old_slug_target( 'cookiebaas-banner' ) === '' && cm_admin_old_slug_target( 'iets' ) === '' );
cm_assert( 'doorverwijzing vóór de "geen toestemming"-melding van WordPress', has_action( 'admin_page_access_denied' ) );

exit( cm_test_summary() );
