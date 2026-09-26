<?php
/**
 * Overzicht (3.0) — statusblokken, compliance-check en de eenmalige melding.
 *
 * Borgt: alle vijftien controles uit 2.4 blijven, met dezelfde logica;
 * elke "Oplossen"-link wijst naar een bestaande pagina en tab van de nieuwe
 * admin; de melding "nieuwe indeling" verschijnt één keer, alleen na een
 * update vanaf 2.x.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function get_pages() { return array(); }
function absint( $v ) { return abs( (int) $v ); }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}
function wp_date( $format, $ts = null ) { return 'wpdate:' . $ts; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
foreach ( glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ) as $file ) require $file;
require CM_PLUGIN_ROOT . '/includes/privacy.php';

cm_test_group( 'Statusblokken' );
$cards = cm_overzicht_cards( array( 'license' => 'Actief', 'license_ok' => true, 'cookies' => 1, 'last_scan' => '2026-09-01 10:00:00', 'accept' => 5, 'reject' => 2, 'custom' => 1, 'version' => 3 ) );
cm_assert( 'vier blokken', count( $cards ) === 4 );
cm_assert( 'licentie, met link naar Beheer › Licentie', $cards[0][1] === 'Actief' && strpos( $cards[0][3], 'page=cookiebaas-beheer&tab=licentie' ) !== false );
cm_assert( 'cookies (enkelvoud) met de laatste scan', $cards[1][1] === '1 cookie' && strpos( $cards[1][2], 'wpdate:' ) !== false );
cm_assert( 'toestemmingen in 30 dagen', $cards[2][1] === '8' && strpos( $cards[2][2], '5 akkoord, 2 geweigerd, 1 aangepast' ) !== false );
cm_assert( 'consent-versie, met link naar Bewaren', $cards[3][1] === '3' && strpos( $cards[3][3], 'tab=bewaren' ) !== false );
$cards = cm_overzicht_cards( array( 'license' => 'Geen licentie', 'license_ok' => false, 'cookies' => 0, 'last_scan' => '', 'accept' => 0, 'reject' => 0, 'custom' => 0, 'version' => 1 ) );
cm_assert( 'nog geen scan', $cards[1][1] === '0 cookies' && strpos( $cards[1][2], 'Nog geen automatische scan' ) !== false );

cm_test_group( 'Compliance-check' );
$s      = cm_default_settings();
$pv     = cm_default_privacy();
$checks = cm_compliance_checks( $s, $pv, cm_default_cookies() );
cm_assert( 'alle vijftien controles uit 2.4', count( $checks ) === 15 );
cm_assert( 'alleen de statussen ok, warn en fail', ! array_diff( array_unique( array_column( $checks, 'status' ) ), array( 'ok', 'warn', 'fail' ) ) );
$tabs = cm_admin_tabs();
$bad  = array();
foreach ( $checks as $c ) {
    parse_str( (string) parse_url( $c['url'], PHP_URL_QUERY ), $q );
    $page = isset( $q['page'] ) ? $q['page'] : '';
    $tab  = isset( $q['tab'] ) ? $q['tab'] : '';
    if ( ! isset( $tabs[ $page ] ) || ( $tab !== '' && ! isset( $tabs[ $page ][ $tab ] ) ) ) $bad[] = $c['title'] . ' → ' . $c['url'];
}
cm_assert( 'elke "Oplossen"-link wijst naar een bestaande pagina en tab' . ( $bad ? ': ' . implode( '; ', $bad ) : '' ), ! $bad );
$ent = array_filter( $checks, function ( $c ) { return preg_match( '/&[a-z]+;/', $c['title'] . $c['desc'] . $c['ref'] . $c['detail'] ); } );
cm_assert( 'teksten met echte tekens, zonder HTML-entities', ! $ent );
$by = array();
foreach ( cm_compliance_checks( array_merge( $s, array( 'analytics_default' => '1', 'show_float_btn' => '0', 'expiry_months' => 24, 'log_retention_months' => 0 ) ), array_merge( $pv, array( 'pv_bedrijfsnaam' => '' ) ), cm_default_cookies() ) as $c ) $by[ $c['title'] ] = $c;
cm_assert( 'vooraf aangevinkt → niet in orde', $by['2. Geen vooraf aangevinkte keuzes']['status'] === 'fail' );
cm_assert( 'geen zweefknop → niet in orde', $by['7. Intrekken is even makkelijk als geven']['status'] === 'fail' );
cm_assert( 'geldigheid langer dan 12 maanden → aandacht', $by['Toestemming verloopt binnen 12 maanden']['status'] === 'warn' );
cm_assert( 'geen bewaartermijn → aandacht, met uitleg', $by['Registraties worden automatisch opgeschoond']['status'] === 'warn' && $by['Registraties worden automatisch opgeschoond']['detail'] !== '' );
cm_assert( 'alleen ingebouwde cookies → aandacht', $by['Elke cookie heeft een doel en looptijd']['status'] === 'warn' );
cm_assert( 'lege bedrijfsnaam → niet in orde', $by['Bedrijfsnaam ingevuld']['status'] === 'fail' );

cm_test_group( 'Weergave van de check' );
ob_start(); cm_render_compliance_table( $checks ); $h = ob_get_clean();
cm_assert( 'statusicoon per controle', substr_count( $h, 'class="dashicons dashicons-' ) === 15 );
cm_assert( '"Oplossen" alleen bij wat niet in orde is', substr_count( $h, 'Oplossen:' ) === count( array_filter( $checks, function ( $c ) { return $c['status'] !== 'ok'; } ) ) );

cm_test_group( 'Eenmalige melding na de update (spec §6.4)' );
cm_flag_admin3_notice( '0' );
cm_assert( 'nieuwe installatie → geen melding', get_option( 'cm_show_admin3_notice' ) === false );
cm_flag_admin3_notice( '3.0.0' );
cm_assert( 'al op 3.0 → geen melding', get_option( 'cm_show_admin3_notice' ) === false );
cm_flag_admin3_notice( '2.4.6' );
cm_assert( 'update vanaf 2.x → melding klaarzetten', (bool) get_option( 'cm_show_admin3_notice' ) );
ob_start(); cm_render_admin3_welcome_notice(); $h = ob_get_clean();
cm_assert( 'melding met link naar "Waar staat wat?"', strpos( $h, 'notice-info' ) !== false && strpos( $h, '#waar-staat-wat' ) !== false );
ob_start(); cm_render_admin3_welcome_notice(); $h = ob_get_clean();
cm_assert( 'maar één keer', $h === '' );

exit( cm_test_summary() );
