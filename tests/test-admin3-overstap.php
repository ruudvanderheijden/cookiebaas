<?php
/**
 * Overstap naar 3.0 — de oude admin is weg, wat nog nodig is, is verhuisd.
 *
 * Borgt: de oude bestanden, de gedeelde nonce en de oude AJAX-handlers
 * zijn weg; scan, cookiedatabase en de frontend-AJAX (consent loggen,
 * geo-check) bestaan nog, met dezelfde actienamen.
 */

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
cm_assert( 'de scanmail linkt naar de nieuwe cookielijst (Review Focus 3)', strpos( $main, "page=cookiebaas-cookies&tab=lijst" ) !== false );
cm_assert( 'cookiemelding.php laadt consent.php en scan.php', strpos( $main, "includes/consent.php" ) !== false && strpos( $main, "includes/admin/scan.php" ) !== false );

exit( cm_test_summary() );
