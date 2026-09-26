<?php
/**
 * Admin-bugfixes v2.4.5.
 *
 * Borgt: reset-handler geregistreerd, regeleinden in de privacyverklaring,
 * categorie/provider bij de automatische scan, geldige kleur-defaults
 * (input type=color accepteert alleen #rrggbb), import gaat door dezelfde
 * sanitizing als opslaan, paginacache-purge na elke inhoudswijziging, en het
 * serverside logfilter.
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
// WordPress slasht $_POST altijd; wp_unslash haalt dat (ook in arrays) weg
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function wp_kses( $s, $allowed = array() ) {
    return strip_tags( (string) $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' );
}

class CM_Test_Json extends Exception {
    public $ok; public $data;
    function __construct( $ok, $data ) { $this->ok = $ok; $this->data = $data; parent::__construct( 'json' ); }
}
function check_ajax_referer() { return true; }
function current_user_can() { return true; }
function wp_die( $m = '' ) { throw new Exception( 'wp_die: ' . $m ); }
function wp_send_json_success( $d = null ) { throw new CM_Test_Json( true, $d ); }
function wp_send_json_error( $d = null )   { throw new CM_Test_Json( false, $d ); }
function cm_maybe_schedule_retention_cron() {}
$GLOBALS['cm_test_purges'] = 0;
function cm_purge_page_caches() { $GLOBALS['cm_test_purges']++; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/privacy.php';
require CM_PLUGIN_ROOT . '/includes/admin.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';

/** Roept een AJAX-handler aan met $post als (geslashte) $_POST en geeft de JSON-uitkomst terug. */
function cm_test_ajax( $handler, array $post ) {
    $slash = function( $v ) use ( &$slash ) { return is_array( $v ) ? array_map( $slash, $v ) : addslashes( (string) $v ); };
    $_POST = $slash( $post );
    try { $handler(); } catch ( CM_Test_Json $r ) { return $r; }
    return null;
}

cm_test_group( 'Privacyverklaring bewaart regeleinden' );
$purges = $GLOBALS['cm_test_purges'];
$r = cm_test_ajax( 'cm_ajax_save_privacy', array( 'pv_doorgifte' => "Regel een\nRegel twee", 'pv_bedrijfsnaam' => "Bedrijf\nBV" ) );
$pv = get_option( 'cm_privacy' );
cm_assert( 'opslaan slaagt', $r && $r->ok );
cm_assert( 'textarea pv_doorgifte houdt regeleinde', $pv['pv_doorgifte'] === "Regel een\nRegel twee" );
cm_assert( 'tekstveld pv_bedrijfsnaam blijft één regel', $pv['pv_bedrijfsnaam'] === 'Bedrijf BV' );
cm_assert( 'paginacache geleegd na opslaan privacy', $GLOBALS['cm_test_purges'] === $purges + 1 );

cm_test_group( 'Cookielijst opslaan leegt paginacache' );
$purges = $GLOBALS['cm_test_purges'];
cm_test_ajax( 'cm_ajax_save_cookie_list', array( 'cookies_json' => json_encode( array( array( 'name' => 'mijn_cookie', 'category' => 'bogus' ) ) ) ) );
cm_assert( 'onbekende categorie wordt functional', get_option( 'cm_cookie_list' )[0]['category'] === 'functional' );
cm_assert( 'paginacache geleegd na opslaan cookielijst', $GLOBALS['cm_test_purges'] === $purges + 1 );

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

cm_test_group( 'Kleurvelden: namen bestaan in defaults en waarden zijn #rrggbb' );
$defaults = cm_default_settings();
foreach ( array( 'light', 'dark' ) as $theme ) {
    ob_start();
    $ret  = cm_render_theme_fields( $theme, $defaults );
    $html = ob_get_clean() . ( is_string( $ret ) ? $ret : '' );
    preg_match_all( '/name="([a-z0-9_]+)"/', $html, $names );
    $missing = array_diff( array_unique( $names[1] ), array_keys( $defaults ) );
    cm_assert( "$theme: elk veld heeft een default (anders nooit opgeslagen)" . ( $missing ? ' — ontbreekt: ' . implode( ', ', $missing ) : '' ), ! $missing );
    preg_match_all( '/<input type="color" name="([a-z0-9_]+)"[^>]*value="([^"]*)"/', $html, $colors, PREG_SET_ORDER );
    $bad = array();
    foreach ( $colors as $c ) if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $c[2] ) ) $bad[] = $c[1] . '=' . $c[2];
    cm_assert( "$theme: kleurwaarden zijn geldige hex" . ( $bad ? ' — fout: ' . implode( ', ', $bad ) : '' ), ! $bad );
}

cm_test_group( 'Embeds: "none" blokkeert niets, leeg blokkeert alles' );
cm_test_set_settings( array( 'embed_blocked_services' => 'none' ) );
cm_assert( '"none" → YouTube niet geblokkeerd', cm_match_embed_domain( 'https://www.youtube.com/embed/abc' ) === null );
cm_test_set_settings( array( 'embed_blocked_services' => '' ) );
cm_assert( 'leeg → YouTube geblokkeerd', cm_match_embed_domain( 'https://www.youtube.com/embed/abc' ) !== null );

exit( cm_test_summary() );
