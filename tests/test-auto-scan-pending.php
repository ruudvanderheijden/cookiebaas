<?php
/**
 * Automatische scan: onbekende cookies (3.1).
 *
 * Borgt: bekende cookies (database of kennisbank) gaan zoals voorheen, onbekende
 * komen niet stil als functioneel in de lijst maar wachten op het Overzicht op
 * een keuze; gekozen → in de lijst, Negeren → niet opnieuw gemeld, geen keuze →
 * blijft wachten. De scanmail noemt categorieën in het Nederlands.
 */

class CM_Test_Wpdb { // geen cookietabel → kennisbank-fallback
    public $prefix = 'wp_';
    public function prepare( $q ) { return $q; }
    public function get_var( $q ) { return null; }
}
$GLOBALS['wpdb'] = new CM_Test_Wpdb();
function wp_date( $f, $t = null ) { return gmdate( $f, $t === null ? time() : $t ); }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="' . $a . '">'; }

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

cm_test_group( 'Indelen: bekend of onbekend' );
list( $known, $unknown ) = cm_auto_scan_classify( array( '_ga', 'redux_x', 'staat_al', 'wp-settings-1', '_ga' ), array( 'staat_al' => true ) );
cm_assert( '_ga bekend (kennisbank), met categorie', count( $known ) === 1 && $known[0]['name'] === '_ga' && $known[0]['category'] === 'analytics' );
cm_assert( 'redux_x onbekend, zonder categorie', count( $unknown ) === 1 && $unknown[0]['name'] === 'redux_x' && $unknown[0]['category'] === '' );

cm_test_group( 'Wachtlijst' );
cm_auto_scan_add_pending( $unknown );
cm_auto_scan_add_pending( array( array( 'name' => 'x_track', 'provider' => '', 'purpose' => '', 'duration' => '', 'category' => '' ) ) );
cm_assert( 'twee cookies wachten', array_keys( cm_auto_scan_pending() ) === array( 'redux_x', 'x_track' ) );
list( , $again ) = cm_auto_scan_classify( array( 'redux_x' ), array() );
cm_assert( 'een wachtende cookie wordt niet opnieuw gemeld', $again === array() );

ob_start(); cm_render_pending_cookies(); $h = ob_get_clean();
cm_assert( 'Overzicht: melding met keuze per cookie', strpos( $h, 'vond 2 cookies die Cookiebaas niet kent' ) !== false && substr_count( $h, '<select name="cm_pending_cat[' ) === 2 && strpos( $h, 'value="ignore"' ) !== false );
cm_assert( 'Overzicht: eigen nonce', strpos( $h, 'cm_resolve_pending' ) !== false );

cm_test_group( 'Keuzes verwerken' );
update_option( 'cm_cookie_list', array() );
cm_assert( 'geen keuze → niets gewijzigd', cm_resolve_pending_cookies( array( 'redux_x', 'x_track' ), array( '', '' ) ) === 'pending-none' && count( cm_auto_scan_pending() ) === 2 );
cm_assert( 'onbekende naam wordt genegeerd', cm_resolve_pending_cookies( array( 'bestaat_niet' ), array( 'marketing' ) ) === 'pending-none' );
$code = cm_resolve_pending_cookies( array( 'redux_x', 'x_track' ), array( 'ignore', 'marketing' ) );
$list = get_option( 'cm_cookie_list' );
cm_assert( 'gekozen categorie → in de cookielijst', $code === 'pending-resolved' && count( $list ) === 1 && $list[0]['name'] === 'x_track' && $list[0]['category'] === 'marketing' );
cm_assert( 'wachtlijst leeg', cm_auto_scan_pending() === array() );
cm_assert( 'Negeren → onthouden', cm_auto_scan_ignored() === array( 'redux_x' ) );
list( $k2, $u2 ) = cm_auto_scan_classify( array( 'redux_x' ), array() );
cm_assert( 'genegeerde cookie komt niet terug', $k2 === array() && $u2 === array() );
ob_start(); cm_render_pending_cookies();
cm_assert( 'Overzicht zonder wachtende cookies: geen melding', ob_get_clean() === '' );

cm_test_group( 'Automatische scan en mail' );
$main = file_get_contents( CM_PLUGIN_ROOT . '/cookiemelding.php' );
cm_assert( 'automatisch toevoegen zet onbekende op de wachtlijst', strpos( $main, 'cm_auto_scan_add_pending( $unknown )' ) !== false );
cm_assert( 'mail met Nederlandse categorie en "kies zelf een categorie"', strpos( $main, "'analytics' => 'Analytisch'" ) !== false && strpos( $main, 'onbekend: kies zelf een categorie' ) !== false );
cm_assert( 'uninstall ruimt wachtlijst en genegeerd op', strpos( file_get_contents( CM_PLUGIN_ROOT . '/uninstall.php' ), "'cm_auto_scan_pending', 'cm_auto_scan_ignored'" ) !== false );

exit( cm_test_summary() );
