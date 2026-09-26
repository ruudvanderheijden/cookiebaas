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
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { $f = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; if ( $echo ) echo $f; return $f; }
function wp_nonce_url( $url, $action ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . '_wpnonce=nonce-' . $action; }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin.php';
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

cm_test_group( 'Plakken vanuit F12' );
$now = strtotime( '2026-09-26 10:00:00 UTC' );
$raw = "Name\tValue\tDomain\tPath\tExpires / Max-Age\tSize\n"
     . "_ga\tGA1.2.3\t.voorbeeld.nl\t/\t2027-09-26T10:00:00.000Z\t30\n"
     . "mijn_eigen\tx\tvoorbeeld.nl\t/\tSession\t10\n"
     . "cookielawinfo-checkbox\tyes\tvoorbeeld.nl\t/\t2026-10-06T10:00:00.000Z\t20\n"
     . "_GA\tdubbel\t.voorbeeld.nl\t/\tSession\t5\n"
     . "bestaat_al\tx\tvoorbeeld.nl\t/\tSession\t5\n"
     . "\n";
$rows = cm_parse_f12_cookies( $raw, array( 'bestaat_al' ), $now );
$names = array_column( $rows, 'name' );
cm_assert( 'kopregel overgeslagen, bestaande en dubbele (hoofdletterongevoelig) namen overgeslagen', $names === array( '_ga', 'mijn_eigen', 'cookielawinfo-checkbox' ) );
cm_assert( 'een cookie die met "cookie" begint is geen kopregel', in_array( 'cookielawinfo-checkbox', $names, true ) );
cm_assert( 'verloopdatum over een jaar → "1 jaar"', $rows[0]['duration'] === '1 jaar' );
cm_assert( 'sessiecookie zonder kennis → "Sessie", functioneel, geen provider', $rows[1]['duration'] === 'Sessie' && $rows[1]['category'] === 'functional' && $rows[1]['provider'] === '' );
cm_assert( 'verloopdatum over 10 dagen → "10 dagen"', $rows[2]['duration'] === '10 dagen' );
cm_assert( 'bekende cookie krijgt categorie en provider uit de kennisbank', cm_cookie_fallback_info( 'YSC' )[0] === 'marketing' && cm_cookie_fallback_info( 'YSC' )[1] === 'YouTube' );
cm_assert( 'prefixpatroon uit de kennisbank werkt', cm_cookie_fallback_info( 'wp-settings-1' ) !== null && cm_cookie_fallback_info( 'wp-settings-1' )[1] === 'WordPress' );
cm_assert( 'enkelvoud en meervoud', cm_f12_duration( gmdate( 'c', $now + 1 * DAY_IN_SECONDS ), $now ) === '1 dag' && cm_f12_duration( gmdate( 'c', $now + 45 * DAY_IN_SECONDS ), $now ) === '2 maanden' && cm_f12_duration( gmdate( 'c', $now + 31 * DAY_IN_SECONDS ), $now ) === '1 maand' );
cm_assert( 'verlopen of onleesbare datum → leeg (dan geldt de kennisbank of "Sessie")', cm_f12_duration( '2020-01-01', $now ) === '' && cm_f12_duration( 'Session', $now ) === '' );

cm_test_group( 'CSV-export' );
$csv = cm_cookie_list_csv_rows( array( array( 'name' => '_ga', 'provider' => 'Google, Inc. "GA"', 'purpose' => 'Meet', 'duration' => '2 jaar', 'category' => 'analytics' ) ) );
cm_assert( 'kopregel met de vaste kolommen', $csv[0] === array( 'Cookie naam', 'Aanbieder', 'Categorie', 'Grondslag', 'Doel', 'Looptijd', 'Domein', 'Wildcard' ) );
cm_assert( 'rij met leesbare categorie en grondslag', $csv[1] === array( '_ga', 'Google, Inc. "GA"', 'Analytisch', 'Toestemming', 'Meet', '2 jaar', '', 'Nee' ) );

cm_test_group( 'Hulpmiddelen onder de cookielijst' );
ob_start(); cm_render_cookie_list_tools(); $t = ob_get_clean();
cm_assert( 'F12-formulier post naar admin-post met eigen nonce', strpos( $t, 'name="action" value="cm_import_f12"' ) !== false && strpos( $t, 'nonce-cm_import_f12' ) !== false && strpos( $t, 'name="cm_f12"' ) !== false );
cm_assert( 'exportlink met eigen nonce', strpos( $t, 'action=cm_export_cookies' ) !== false && strpos( $t, 'nonce-cm_export_cookies' ) !== false );
cm_assert( 'leegmaken vraagt om bevestiging', strpos( $t, 'value="cm_clear_cookie_list"' ) !== false && strpos( $t, 'data-cm-confirm=' ) !== false );
cm_assert( 'meldingen bestaan', cm_admin_notice_html( 'f12-imported' ) !== '' && cm_admin_notice_html( 'f12-none' ) !== '' && cm_admin_notice_html( 'cookie-list-cleared' ) !== '' );

exit( cm_test_summary() );
