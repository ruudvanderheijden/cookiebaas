<?php
/**
 * Nieuwe admin (3.0) — pagina Cookies.
 *
 * Borgt: de cookielijst gaat via de Settings API en alle rijen verwijderen
 * leegt de lijst echt (Review Focus 1); de editor toont de opgeslagen lijst
 * met de juiste categorie; de ingebouwde cookies staan erbij als tabel.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function settings_fields( $group ) { echo '<!--group:' . $group . '-->'; }
function submit_button( $text = '' ) { echo '<!--submit:' . $text . '-->'; }
function wp_kses_post( $s ) { return (string) $s; }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-cookies.php';

cm_test_group( 'Cookielijst opslaan via de Settings API' );
update_option( 'cm_cookie_list', array( array( 'name' => '_ga', 'provider' => 'Google Analytics', 'purpose' => 'Meet bezoek', 'duration' => '2 jaar', 'category' => 'analytics', 'builtin' => false ) ) );
cm_assert( 'null (ontbreekt in de POST) laat de lijst staan', count( cm_cookie_list_sanitize_callback( null ) ) === 1 );
cm_assert( 'lege string (alle rijen verwijderd) maakt de lijst leeg', cm_cookie_list_sanitize_callback( '' ) === array() );
$out = cm_cookie_list_sanitize_callback( array(
    4 => array( 'name' => '<b>_fbp</b>', 'provider' => '', 'purpose' => '', 'duration' => '', 'category' => 'marketing' ),
    9 => array( 'name' => '', 'category' => 'analytics' ),
) );
cm_assert( 'rijen opgeschoond, lege naam weg, opnieuw geïndexeerd', count( $out ) === 1 && $out[0]['name'] === '_fbp' && $out[0]['category'] === 'marketing' );

cm_test_group( 'Cookielijst-tab' );
ob_start(); cm_render_cookie_list_tab(); $h = ob_get_clean();
cm_assert( 'formulier naar options.php met groep cookiebaas_cookies', strpos( $h, 'action="https://example.test/wp-admin/options.php"' ) !== false && strpos( $h, '<!--group:cookiebaas_cookies-->' ) !== false );
cm_assert( 'opgeslagen cookie als rij', strpos( $h, 'name="cm_cookie_list[0][name]" value="_ga"' ) !== false );
cm_assert( 'categorie-keuze staat goed', preg_match( '/name="cm_cookie_list\[0\]\[category\]"[^>]*>.*?<option value="analytics" selected>/s', $h ) === 1 );
cm_assert( 'ingebouwde cookies als alleen-lezen tabel', strpos( $h, 'Ingebouwde cookies' ) !== false && strpos( $h, '<code>cc_cm_consent</code>' ) !== false );
cm_assert( 'pagina Cookies staat in het menu', isset( cm_admin_pages()['cookiebaas-cookies'] ) );
cm_assert( 'tab Cookielijst bestaat', isset( cm_tabs_cookies()['lijst'] ) );

exit( cm_test_summary() );
