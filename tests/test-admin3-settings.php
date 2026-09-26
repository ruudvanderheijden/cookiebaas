<?php
/**
 * Nieuwe admin (3.0) — type-bewuste sanitizing en Settings API-callback.
 *
 * Borgt (spec §5.3, Review Focus 1, 3, 4):
 *   - een tab post alleen zijn eigen velden; de rest blijft staan
 *   - kleuren: klanten plakken hex in allerlei vormen → genormaliseerd;
 *     echt ongeldig → vorige waarde + foutmelding met de veldnaam
 *   - getallen geclampt, radio/select tegen de opties, checkbox 0/1
 *   - de oude admin (stringformaten, cm_icon_type) blijft correct opslaan
 *   - idempotent: een geldige array komt ongewijzigd door de callback
 *   - options.php stuurt null voor een ontbrekende option → niets wissen
 *   - elke wijziging van cm_settings leegt de paginacache (option-hook)
 */

function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function sanitize_text_field( $s )     { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function wp_kses( $s, $allowed = array() ) { return strip_tags( (string) $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
$GLOBALS['cm_test_errors'] = array();
function add_settings_error( $setting, $code, $message ) { $GLOBALS['cm_test_errors'][] = $message; }
$GLOBALS['cm_test_purges'] = 0;
function cm_purge_page_caches() { $GLOBALS['cm_test_purges']++; }
function absint( $v ) { return abs( (int) $v ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';

$index = array(
    'color_title'         => cm_field( 'color_title', 'color', 'Titels' ),
    'color_accept_border' => cm_field( 'color_accept_border', 'color_optional', 'Akkoord — rand' ),
    'radius_popup'        => cm_field( 'radius_popup', 'number', 'Hoekafronding', array( 'min' => 0, 'max' => 60 ) ),
    'banner_position'     => cm_field( 'banner_position', 'radio', 'Positie', array( 'options' => array( 'bottom-center' => 'a', 'center' => 'b' ) ) ),
    'respect_dnt'         => cm_field( 'respect_dnt', 'checkbox', 'DNT' ),
    'txt_cat1_long'       => cm_field( 'txt_cat1_long', 'textarea', 'Uitgebreid' ),
    'txt_banner_body'     => cm_field( 'txt_banner_body', 'html', 'Tekst' ),
    'exclude_page_ids'    => cm_field( 'exclude_page_ids', 'multiselect', 'Pagina\'s', array( 'sanitize' => function ( $raw ) {
        return is_array( $raw ) ? implode( ',', array_filter( array_map( 'absint', $raw ) ) ) : sanitize_text_field( $raw );
    } ) ),
);

cm_test_group( 'Kleuren' );
cm_assert( '"FFFFFF" wordt #ffffff', cm_sanitize_field_value( $index['color_title'], 'FFFFFF', '#111111' ) === '#ffffff' );
cm_assert( '" #FFF " wordt #ffffff', cm_sanitize_field_value( $index['color_title'], ' #FFF ', '#111111' ) === '#ffffff' );
cm_assert( '#ABCDEF wordt #abcdef', cm_sanitize_field_value( $index['color_title'], '#ABCDEF', '#111111' ) === '#abcdef' );
$GLOBALS['cm_test_errors'] = array();
cm_assert( 'ongeldig houdt de vorige kleur', cm_sanitize_field_value( $index['color_title'], 'rood', '#111111' ) === '#111111' );
cm_assert( 'ongeldig geeft een foutmelding met de veldnaam', count( $GLOBALS['cm_test_errors'] ) === 1 && strpos( $GLOBALS['cm_test_errors'][0], 'Titels' ) !== false );
cm_assert( 'optioneel mag leeg', cm_sanitize_field_value( $index['color_accept_border'], '', '#444444' ) === '' );
cm_assert( 'verplicht mag niet leeg', cm_sanitize_field_value( $index['color_title'], '', '#111111' ) === '#111111' );

cm_test_group( 'Getal, keuze, checkbox, tekst' );
cm_assert( 'getal boven max wordt max', cm_sanitize_field_value( $index['radius_popup'], '99', '18' ) === '60' );
cm_assert( 'getal onder min wordt min', cm_sanitize_field_value( $index['radius_popup'], '-4', '18' ) === '0' );
cm_assert( 'geen getal houdt de vorige waarde', cm_sanitize_field_value( $index['radius_popup'], 'abc', '18' ) === '18' );
cm_assert( 'onbekende radio-optie houdt de vorige waarde', cm_sanitize_field_value( $index['banner_position'], 'links', 'center' ) === 'center' );
cm_assert( 'checkbox 1 → "1", al het andere → "0"', cm_sanitize_field_value( $index['respect_dnt'], '1', '0' ) === '1' && cm_sanitize_field_value( $index['respect_dnt'], 'ja', '1' ) === '0' );
cm_assert( 'textarea houdt regeleinden', cm_sanitize_field_value( $index['txt_cat1_long'], "a\nb", '' ) === "a\nb" );
cm_assert( 'html laat link staan, script niet', cm_sanitize_field_value( $index['txt_banner_body'], '<a href="/x">x</a><script>y</script>', '' ) === '<a href="/x">x</a>y' );
cm_assert( 'eigen sanitize: array van id\'s wordt komma-string', cm_sanitize_field_value( $index['exclude_page_ids'], array( '', '12', '7' ), '' ) === '12,7' );
cm_assert( 'eigen sanitize: niets gekozen wordt leeg', cm_sanitize_field_value( $index['exclude_page_ids'], array( '' ), '5' ) === '' );
cm_assert( 'oude admin: string blijft werken', cm_sanitize_field_value( $index['exclude_page_ids'], '3,4', '' ) === '3,4' );

cm_test_group( 'Gedeeltelijke invoer (één tab)' );
$existing = array_merge( cm_default_settings(), array( 'gtm_container_id' => 'GTM-BLIJFT', 'color_title' => '#222222' ) );
$out = cm_sanitize_settings( array( 'color_title' => '#333333' ), $existing, $index );
cm_assert( 'het geposte veld verandert', $out['color_title'] === '#333333' );
cm_assert( 'een veld van een andere tab blijft staan', $out['gtm_container_id'] === 'GTM-BLIJFT' );

cm_test_group( 'Icoontype (alleen UI) wist pas bij opslaan' );
$base = array_merge( cm_default_settings(), array( 'float_icon_custom_svg' => '<svg></svg>', 'float_icon_image_url' => 'https://x.test/a.png' ) );
$o = cm_sanitize_settings( array( 'cm_icon_type' => 'custom' ), $base, $index );
cm_assert( 'custom: SVG blijft, afbeelding weg', $o['float_icon_custom_svg'] === '<svg></svg>' && $o['float_icon_image_url'] === '' );
$o = cm_sanitize_settings( array( 'cm_icon_type' => 'default' ), $base, $index );
cm_assert( 'standaard: beide weg', $o['float_icon_custom_svg'] === '' && $o['float_icon_image_url'] === '' );
cm_assert( 'cm_icon_type wordt zelf niet opgeslagen', ! array_key_exists( 'cm_icon_type', $o ) );

cm_test_group( 'Idempotent' );
$valid = array_merge( cm_default_settings(), array( 'exclude_page_ids' => '3,4', 'respect_dnt' => '1', 'color_title' => '#123456' ) );
$once  = cm_sanitize_settings( $valid, $valid, $index );
$twice = cm_sanitize_settings( $once, $once, $index );
$diff  = array();
foreach ( $index as $k => $f ) if ( (string) $once[ $k ] !== (string) $valid[ $k ] ) $diff[] = $k;
cm_assert( 'geldige waarden komen ongewijzigd door (per veld als string)' . ( $diff ? ' — veranderd: ' . implode( ', ', $diff ) : '' ), ! $diff );
cm_assert( 'twee keer sanitizen = één keer', $once === $twice );

cm_test_group( 'Settings API-callback' );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'gtm_container_id' => 'GTM-OK' ) ) );
cm_assert( 'null (option ontbreekt in POST) wist niets', cm_settings_sanitize_callback( null )['gtm_container_id'] === 'GTM-OK' );

cm_test_group( 'Cache-purge via option-hook' );
$p = $GLOBALS['cm_test_purges'];
update_option( 'cm_settings', array( 'x' => 1 ) );
update_option( 'cm_cookie_list', array() );
update_option( 'cm_privacy', array() );
update_option( 'cm_consent_version', 2 );
cm_assert( 'elke inhoudswijziging leegt de cache', $GLOBALS['cm_test_purges'] === $p + 4 );

exit( cm_test_summary() );
