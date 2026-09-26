<?php
/**
 * Beheer (3.0) — licentie, meldingen met eigen tekst, backup, reset, geavanceerd en info.
 *
 * Borgt: de licentieserver bepaalt of een melding "gelukt" of "mislukt" is,
 * een lege sleutel gaat niet naar de server, en de licentiemelding staat
 * alleen op de eigen schermen van Cookiebaas.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function wp_kses( $s, $allowed = array() ) { return strip_tags( (string) $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
function get_pages() { return array(); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}
function wp_nonce_url( $url, $action ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . '_wpnonce=nonce-' . $action; }
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { $f = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; if ( $echo ) echo $f; return $f; }
function date_i18n( $format, $ts = null ) { return 'datum'; }
function wp_date( $format, $ts = null ) { return 'wpdate:' . $ts; }
function current_time( $type, $gmt = 0 ) { return $type === 'timestamp' ? time() : date( $type === 'mysql' ? 'Y-m-d H:i:s' : 'c' ); }
function get_current_user_id() { return 7; }
function get_current_screen() { return (object) array( 'id' => $GLOBALS['cm_test_screen'] ); }
$GLOBALS['cm_test_transients'] = array();
function get_transient( $k ) { return isset( $GLOBALS['cm_test_transients'][ $k ] ) ? $GLOBALS['cm_test_transients'][ $k ] : false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['cm_test_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['cm_test_transients'][ $k ] ); return true; }
$GLOBALS['cm_test_errors'] = array();
function add_settings_error( $setting, $code, $message ) { $GLOBALS['cm_test_errors'][] = $message; }
function get_settings_errors() { return $GLOBALS['cm_test_errors']; }
$GLOBALS['cm_test_purges'] = 0;
function cm_purge_page_caches() { $GLOBALS['cm_test_purges']++; }

// Licentie: stubs in plaats van includes/license.php (die praat met de server)
$GLOBALS['cm_test_lic']         = array( 'key' => '', 'status' => '', 'expires_at' => '', 'domain' => '', 'last_check' => 0 );
$GLOBALS['cm_test_valid']       = false;
$GLOBALS['cm_test_activations'] = array();
function cm_license_get() { return $GLOBALS['cm_test_lic']; }
function cm_license_is_valid() { return $GLOBALS['cm_test_valid']; }
function cm_license_get_domain() { return 'example.test'; }
function cm_license_activate( $key ) { $GLOBALS['cm_test_activations'][] = $key; return array( 'success' => true, 'message' => 'Licentie geactiveerd.' ); }

class CM_Test_Wpdb {
    public $prefix  = 'wp_';
    public $queries = array();
    public $result  = 0;
    public function prepare( $sql, $args ) { return $sql; }
    public function query( $sql ) { $this->queries[] = $sql; return $this->result; }
}
$GLOBALS['wpdb'] = new CM_Test_Wpdb();

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
foreach ( glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ) as $file ) require $file;
require CM_PLUGIN_ROOT . '/includes/privacy.php';

cm_test_group( 'Pagina en menu' );
cm_assert( 'menu-item Beheer', isset( cm_admin_pages()['cookiebaas-beheer'] ) );
cm_assert( 'tab Licentie', isset( cm_tabs_beheer()['licentie'] ) );

cm_test_group( 'Licentiestatus in woorden' );
cm_assert( 'actief', cm_license_summary( array( 'key' => 'K' ), true )[1] === 'Actief' );
cm_assert( 'geen sleutel', cm_license_summary( array( 'key' => '' ), false )[1] === 'Geen licentie' );
cm_assert( 'verlopen', cm_license_summary( array( 'key' => 'K', 'status' => 'expired' ), false )[1] === 'Verlopen' );
cm_assert( 'anders ongeldig', cm_license_summary( array( 'key' => 'K', 'status' => 'invalid' ), false )[1] === 'Ongeldig' );

cm_test_group( 'Meldingen met eigen tekst' );
$_GET = array();
cm_license_flash( array( 'success' => true, 'message' => 'Licentie geactiveerd.' ) );
ob_start(); cm_admin_render_notices(); $h = ob_get_clean();
cm_assert( 'succes van de server → succesmelding', strpos( $h, 'notice-success' ) !== false && strpos( $h, 'Licentie geactiveerd.' ) !== false );
ob_start(); cm_admin_render_notices(); $h = ob_get_clean();
cm_assert( 'de melding verschijnt één keer', $h === '' );
cm_license_flash( array( 'success' => false ) );
ob_start(); cm_admin_render_notices(); $h = ob_get_clean();
cm_assert( 'fout zonder tekst → nette foutmelding, nooit "gelukt"', strpos( $h, 'notice-error' ) !== false && strpos( $h, 'licentieserver' ) !== false );
cm_admin_flash( 'onzin', '<b>x</b>' );
ob_start(); cm_admin_render_notices(); $h = ob_get_clean();
cm_assert( 'onbekend type → info, tekst ge-escaped', strpos( $h, 'notice-info' ) !== false && strpos( $h, '<b>' ) === false );

cm_test_group( 'Activeren' );
cm_assert( 'lege sleutel → fout, de server wordt niet benaderd', cm_license_activate_request( '  ' )['success'] === false && ! $GLOBALS['cm_test_activations'] );
cm_assert( 'sleutel → naar de server', cm_license_activate_request( 'CB-1' )['success'] === true && $GLOBALS['cm_test_activations'] === array( 'CB-1' ) );

cm_test_group( 'Licentie-tab' );
$GLOBALS['cm_test_lic'] = array( 'key' => 'CB-AAAA', 'status' => 'expired', 'expires_at' => '2026-01-01', 'domain' => 'example.test', 'last_check' => 1767261600 );
ob_start(); cm_render_beheer_licentie(); $h = ob_get_clean();
cm_assert( 'status en sleutel zichtbaar', strpos( $h, 'Verlopen' ) !== false && strpos( $h, 'CB-AAAA' ) !== false );
cm_assert( 'controleren en deactiveren als eigen acties', strpos( $h, 'value="cm_license_check"' ) !== false && strpos( $h, 'value="cm_license_deactivate"' ) !== false );
cm_assert( 'activeren met een sleutelveld', strpos( $h, 'value="cm_license_activate"' ) !== false && strpos( $h, 'name="license_key"' ) !== false );
cm_assert( 'geen formulier binnen een alinea', ! preg_match( '#<p>(?:(?!</p>).)*<form#s', $h ) );

cm_test_group( 'Licentiemelding alleen op eigen schermen' );
$GLOBALS['cm_admin_hooks'] = array( 'toplevel_page_cookiebaas', 'cookiebaas_page_cookiebaas-log' );
$GLOBALS['cm_test_lic']    = array( 'key' => '', 'status' => '' );
$GLOBALS['cm_test_screen'] = 'dashboard';
ob_start(); cm_admin_license_notice(); $h = ob_get_clean();
cm_assert( 'niet op het dashboard', $h === '' );
$GLOBALS['cm_test_screen'] = 'cookiebaas_page_cookiebaas-log';
ob_start(); cm_admin_license_notice(); $h = ob_get_clean();
cm_assert( 'wel op een Cookiebaas-scherm, met link naar Beheer › Licentie', strpos( $h, 'notice-warning' ) !== false && strpos( $h, 'page=cookiebaas-beheer&tab=licentie' ) !== false );
$_GET = array( 'page' => 'cookiebaas-beheer' );
$GLOBALS['cm_admin_hooks'][] = 'cookiebaas_page_cookiebaas-beheer';
$GLOBALS['cm_test_screen']   = 'cookiebaas_page_cookiebaas-beheer';
ob_start(); cm_admin_license_notice(); $h = ob_get_clean();
cm_assert( 'niet dubbel op de tab Licentie zelf', $h === '' );
$_GET = array();
$GLOBALS['cm_test_valid'] = true;
$GLOBALS['cm_test_screen'] = 'toplevel_page_cookiebaas';
ob_start(); cm_admin_license_notice(); $h = ob_get_clean();
cm_assert( 'geldige licentie → geen melding', $h === '' );
$GLOBALS['cm_test_valid'] = false;

cm_test_group( 'Backup maken' );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'api_key' => str_repeat( 'a', 40 ), 'gtm_container_id' => 'GTM-ABC' ) ) );
$b = cm_backup_payload();
cm_assert( 'metadata van Cookiebaas', $b['_meta']['plugin'] === 'cookiebaas' && isset( $b['_meta']['version'], $b['_meta']['exported'] ) );
cm_assert( 'instellingen zonder API-sleutel', $b['settings']['gtm_container_id'] === 'GTM-ABC' && ! array_key_exists( 'api_key', $b['settings'] ) );
cm_assert( 'cookielijst en privacy gaan mee', array_key_exists( 'cookie_list', $b ) && array_key_exists( 'privacy', $b ) );

cm_test_group( 'Ongeldig bestand wijzigt niets (Review Focus 2)' );
$snap = $GLOBALS['cm_test_options'];
$junk = array(
    'leeg'            => '',
    'geen JSON'       => 'geen json',
    'zonder _meta'    => json_encode( array( 'settings' => array( 'gtm_container_id' => 'X' ) ) ),
    'andere plugin'   => json_encode( array( '_meta' => array( 'plugin' => 'andere-plugin' ), 'settings' => array( 'gtm_container_id' => 'X' ) ) ),
    'zonder inhoud'   => json_encode( array( '_meta' => array( 'plugin' => 'cookiebaas' ) ) ),
);
foreach ( $junk as $label => $raw ) {
    $r = cm_import_backup( $raw );
    cm_assert( "geweigerd: $label", $r['ok'] === false && $r['message'] !== '' );
}
cm_assert( 'geen enkele option gewijzigd', $GLOBALS['cm_test_options'] === $snap );

cm_test_group( 'Import gaat door dezelfde sanitizing als opslaan' );
$purges = $GLOBALS['cm_test_purges'];
$export = array(
    '_meta'       => array( 'plugin' => 'cookiemelding', 'version' => '2.4.4' ),
    'settings'    => array( 'txt_banner_title' => '<script>alert(1)</script>Hallo', 'txt_banner_body' => '<a href="/p">Lees</a><script>x</script>', 'onbekende_sleutel' => 'x', 'google_load_default' => '1', 'analytics_default' => '0' ),
    'cookie_list' => array( array( 'name' => '<b>_ga</b>', 'category' => 'bogus' ), array( 'name' => '' ) ),
    'privacy'     => array( 'pv_doorgifte' => "A\nB", 'pv_bedrijfsnaam' => '<i>X</i>' ),
);
$r = cm_import_backup( json_encode( $export ) );
$s = get_option( 'cm_settings' );
cm_assert( 'import slaagt, ook een oude export (cookiemelding)', $r['ok'] === true && strpos( $r['message'], 'instellingen' ) !== false );
cm_assert( 'script-tag uit tekstveld verwijderd', $s['txt_banner_title'] === 'alert(1)Hallo' );
cm_assert( 'HTML-veld houdt link, verliest script', $s['txt_banner_body'] === '<a href="/p">Lees</a>x' );
cm_assert( 'onbekende sleutel niet opgeslagen', ! array_key_exists( 'onbekende_sleutel', $s ) );
cm_assert( 'ontbrekende sleutels krijgen de standaard', $s['gtm_container_id'] === '' );
cm_assert( 'google_load_default forceert analytics_default', (int) $s['analytics_default'] === 1 );
cm_assert( 'de huidige API-sleutel blijft staan', $s['api_key'] === str_repeat( 'a', 40 ) );
$cl = get_option( 'cm_cookie_list' );
cm_assert( 'cookielijst gesanitized: lege naam weg, tags weg, categorie gevalideerd', count( $cl ) === 1 && $cl[0]['name'] === '_ga' && $cl[0]['category'] === 'functional' );
$pv = get_option( 'cm_privacy' );
cm_assert( 'privacy: regeleinde behouden, tags weg', $pv['pv_doorgifte'] === "A\nB" && $pv['pv_bedrijfsnaam'] === 'X' );
cm_assert( 'privacy: ontbrekende checkbox krijgt de standaard', $pv['pv_ap_tonen'] === cm_default_privacy()['pv_ap_tonen'] );
cm_assert( 'paginacache geleegd na import', $GLOBALS['cm_test_purges'] > $purges );

cm_test_group( 'Ongeldige waarde in de backup → melding' );
$GLOBALS['cm_test_errors'] = array();
$r = cm_import_backup( json_encode( array( '_meta' => array( 'plugin' => 'cookiebaas' ), 'settings' => array( 'color_popup_bg' => 'rood' ) ) ) );
cm_assert( 'import slaagt, met een waarschuwing', $r['ok'] === true && strpos( $r['message'], 'ongeldig' ) !== false );
cm_assert( 'ongeldige kleur wordt de standaard', get_option( 'cm_settings' )['color_popup_bg'] === cm_default_settings()['color_popup_bg'] );

cm_test_group( 'Alles resetten (Review Focus 4)' );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'gtm_container_id' => 'GTM-WEG' ) ) );
update_option( 'cm_cookie_list', array( array( 'name' => 'x' ) ) );
update_option( 'cm_license_data', array( 'key' => 'K' ) );
update_option( 'cm_consent_version', 2 );
$wpdb->result = 0;
cm_assert( 'alles gelukt → geen fouten', cm_reset_everything() === array() );
cm_assert( 'instellingen terug naar de standaard', get_option( 'cm_settings' )['gtm_container_id'] === '' );
cm_assert( 'cookielijst leeg', get_option( 'cm_cookie_list' ) === array() );
cm_assert( 'privacy terug naar de standaard', get_option( 'cm_privacy' ) === cm_default_privacy() );
cm_assert( 'consent log leeggemaakt', strpos( end( $wpdb->queries ), 'TRUNCATE' ) === 0 );
cm_assert( 'iedereen kiest opnieuw', (int) get_option( 'cm_consent_version' ) === 3 );
cm_assert( 'licentie lokaal gewist', get_option( 'cm_license_data' ) === false );
$wpdb->result = false;
cm_assert( 'mislukte log → gemeld, niet "alles gelukt"', cm_reset_everything() === array( 'consent log' ) );
$wpdb->result = 0;

cm_test_group( 'Tabs Backup en Reset' );
cm_assert( 'volgorde Licentie, Backup, Reset', array_keys( cm_tabs_beheer() ) === array( 'licentie', 'backup', 'reset' ) );
ob_start(); cm_render_beheer_backup(); $h = ob_get_clean();
cm_assert( 'download en upload', strpos( $h, 'action=cm_export_backup' ) !== false && strpos( $h, 'enctype="multipart/form-data"' ) !== false && strpos( $h, 'name="cm_backup"' ) !== false );
ob_start(); cm_render_beheer_reset(); $h = ob_get_clean();
cm_assert( 'alles resetten en licentie wissen, met bevestiging', strpos( $h, 'value="cm_reset_all"' ) !== false && strpos( $h, 'value="cm_license_reset"' ) !== false && substr_count( $h, 'data-cm-confirm=' ) === 2 );

exit( cm_test_summary() );
