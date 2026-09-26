<?php
/**
 * Admin-bugfixes v2.4.5 die buiten de nieuwe admin vallen.
 *
 * Borgt: regeleinden in de privacyverklaring, categorie/provider bij de
 * automatische scan, "none" bij de embed-diensten en het legen van de
 * paginacache bij elke inhoudswijziging. Reset, import, logfilter en
 * kleurwaarden worden sinds 3.0 getest bij hun nieuwe plek
 * (test-admin3-beheer.php, -log.php, -fields.php).
 */

// Realistischere stubs dan de bootstrap: de fixes draaien juist om wat
// sanitize_text_field wél en sanitize_textarea_field níet weghaalt.
function sanitize_text_field( $s ) {
    if ( is_array( $s ) || is_object( $s ) ) return '';
    return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function sanitize_textarea_field( $s ) {
    if ( is_array( $s ) || is_object( $s ) ) return '';
    return trim( strip_tags( (string) $s ) );
}
$GLOBALS['cm_test_purges'] = 0;
function cm_purge_page_caches() { $GLOBALS['cm_test_purges']++; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/privacy.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';

cm_test_group( 'Privacyverklaring bewaart regeleinden' );
$pv = cm_sanitize_privacy( array( 'pv_doorgifte' => "Regel een\nRegel twee", 'pv_bedrijfsnaam' => "Bedrijf\nBV" ) );
cm_assert( 'textarea pv_doorgifte houdt regeleinde', $pv['pv_doorgifte'] === "Regel een\nRegel twee" );
cm_assert( 'tekstveld pv_bedrijfsnaam blijft één regel', $pv['pv_bedrijfsnaam'] === 'Bedrijf BV' );

cm_test_group( 'Elke inhoudswijziging leegt de paginacache' );
foreach ( array( 'cm_settings', 'cm_cookie_list', 'cm_privacy', 'cm_consent_version' ) as $opt ) {
    $before = $GLOBALS['cm_test_purges'];
    update_option( $opt, array( 'x' => 1 ) );
    cm_assert( "$opt opslaan → paginacache geleegd", $GLOBALS['cm_test_purges'] === $before + 1 );
}

cm_test_group( 'Automatische scan: categorie en provider uit de cookie-DB' );
$row = array( 'platform' => 'Google Analytics', 'controller' => 'Google', 'category' => 'analytics', 'description' => 'Meet bezoek', 'retention' => '2 jaar' );
$e = cm_autoscan_entry( '_ga', $row );
cm_assert( 'DB-categorie analytics blijft analytics', $e['category'] === 'analytics' );
cm_assert( 'provider komt uit platform', $e['provider'] === 'Google Analytics' );
cm_assert( 'looptijd en omschrijving overgenomen', $e['duration'] === '2 jaar' && $e['purpose'] === 'Meet bezoek' );
$e = cm_autoscan_entry( 'x_ad', array( 'platform' => '', 'controller' => 'AdCo', 'category' => 'marketing', 'description' => '', 'retention' => '' ) );
cm_assert( 'marketing blijft marketing, provider valt terug op controller', $e['category'] === 'marketing' && $e['provider'] === 'AdCo' );
$e = cm_autoscan_entry( 'onbekend_ding', false );
cm_assert( 'zonder DB-rij: functional / Onbekend', $e['category'] === 'functional' && $e['provider'] === 'Onbekend' );

cm_test_group( 'Embeds: "none" blokkeert niets, leeg blokkeert alles' );
cm_test_set_settings( array( 'embed_blocked_services' => 'none' ) );
cm_assert( '"none" → YouTube niet geblokkeerd', cm_match_embed_domain( 'https://www.youtube.com/embed/abc' ) === null );
cm_test_set_settings( array( 'embed_blocked_services' => '' ) );
cm_assert( 'leeg → YouTube geblokkeerd', cm_match_embed_domain( 'https://www.youtube.com/embed/abc' ) !== null );

exit( cm_test_summary() );
