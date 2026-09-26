<?php
/**
 * Nieuwe admin (3.0) — privacyverklaring opslaan en verwerkingsregister.
 *
 * Borgt: de rijtabellen blijven JSON-strings (data blijft); de oude admin
 * blijft correct opslaan (Review Focus 3); een oude grondslag buiten de zes
 * opties blijft behouden (Review Focus 5); het verwerkingsregister gebruikt
 * de ingevulde bewaartermijnen en de gekozen grondslag van het contactformulier.
 */

function sanitize_text_field( $s )     { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function esc_url_raw( $s ) { $s = trim( (string) $s ); return preg_match( '#^https?://#i', $s ) ? $s : ''; }
$GLOBALS['cm_test_errors'] = array();
function add_settings_error( $setting, $code, $message ) { $GLOBALS['cm_test_errors'][] = $message; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/privacy.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-privacy.php';

$g = cm_avg_grondslagen();
$index = array(
    'pv_cf_grondslag' => cm_field( 'pv_cf_grondslag', 'select', 'Rechtsgrondslag', array( 'option' => 'cm_privacy', 'options' => $g ) ),
    'pv_table_border' => cm_field( 'pv_table_border', 'color', 'Randen', array( 'option' => 'cm_privacy' ) ),
    'pv_dpo_enabled'  => cm_field( 'pv_dpo_enabled', 'checkbox', 'DPO', array( 'option' => 'cm_privacy' ) ),
    'pv_doeleinden'   => cm_field( 'pv_doeleinden', 'rows', 'Doeleinden', array( 'option' => 'cm_privacy', 'columns' => array(
        'doel' => array( 'label' => 'Doel' ), 'grondslag' => array( 'label' => 'Grondslag' ), 'termijn' => array( 'label' => 'Bewaartermijn' ),
    ) ) ),
);

cm_test_group( 'Grondslagen' );
cm_assert( 'zes AVG-grondslagen, waarde = label', count( $g ) === 6 && isset( $g['Toestemming (Art. 6 lid 1 sub a AVG)'] ) && isset( $g['Gerechtvaardigd belang (Art. 6 lid 1 sub f AVG)'] ) );

cm_test_group( 'Nieuw formulier (arrays) en typen' );
$base = cm_default_privacy();
$out  = cm_sanitize_privacy( array_merge( $base, array(
    'pv_doeleinden'   => array( 2 => array( 'doel' => 'Contact', 'grondslag' => 'Toestemming', 'termijn' => '1 jaar' ) ),
    'pv_table_border' => 'DDD',
    'pv_dpo_enabled'  => '1',
) ), $base, $index );
cm_assert( 'rijtabel wordt JSON-string met dezelfde sleutels', json_decode( $out['pv_doeleinden'], true ) === array( array( 'doel' => 'Contact', 'grondslag' => 'Toestemming', 'termijn' => '1 jaar' ) ) );
cm_assert( 'geplakte hex genormaliseerd', $out['pv_table_border'] === '#dddddd' );
cm_assert( 'checkbox 1', $out['pv_dpo_enabled'] === '1' );

cm_test_group( 'Oude admin blijft werken (Review Focus 3)' );
$old = cm_sanitize_privacy( array_merge( $base, array(
    'pv_doeleinden'   => '[{"doel":"Oud","grondslag":"Toestemming","termijn":"2 jaar"}]',
    'pv_cf_voornaam'  => '0',
    'pv_cf_grondslag' => 'Toestemming (Art. 6 lid 1 sub a AVG)',
    'pv_cf_extra'     => "Factuurnummer\nProjectnaam",
) ), $base, $index );
cm_assert( 'JSON-string uit de oude admin blijft bruikbaar', json_decode( $old['pv_doeleinden'], true )[0]['doel'] === 'Oud' );
cm_assert( 'checkbox 0 blijft 0', $old['pv_cf_voornaam'] === '0' );
cm_assert( 'grondslag uit de lijst geaccepteerd', $old['pv_cf_grondslag'] === 'Toestemming (Art. 6 lid 1 sub a AVG)' );
cm_assert( 'tekstvak houdt regeleinden', $old['pv_cf_extra'] === "Factuurnummer\nProjectnaam" );

cm_test_group( 'Oude vrije grondslag blijft behouden (Review Focus 5)' );
$existing = array_merge( $base, array( 'pv_cf_grondslag' => 'Eigen oude tekst' ) );
$GLOBALS['cm_test_errors'] = array();
$keep = cm_sanitize_privacy( array_merge( $existing, array( 'pv_cf_grondslag' => 'Eigen oude tekst' ) ), $existing, $index );
cm_assert( 'waarde buiten de opties blijft staan', $keep['pv_cf_grondslag'] === 'Eigen oude tekst' );
cm_assert( 'met een melding om een geldige grondslag te kiezen', count( $GLOBALS['cm_test_errors'] ) === 1 && strpos( $GLOBALS['cm_test_errors'][0], 'Rechtsgrondslag' ) !== false );

cm_test_group( 'Settings API-callback' );
update_option( 'cm_privacy', array_merge( $base, array( 'pv_bedrijfsnaam' => 'Oud BV' ) ) );
cm_assert( 'null laat alles staan', cm_privacy_sanitize_callback( null )['pv_bedrijfsnaam'] === 'Oud BV' );
cm_assert( 'gedeeltelijke invoer laat andere velden staan', cm_privacy_sanitize_callback( array( 'pv_email' => 'info@voorbeeld.nl' ) )['pv_bedrijfsnaam'] === 'Oud BV' );

cm_test_group( 'Verwerkingsregister' );
$pv = array_merge( $base, array(
    'pv_bedrijfsnaam' => 'Voorbeeld BV',
    'pv_doeleinden'   => '[{"doel":"Nieuwsbrief","grondslag":"Toestemming","termijn":"Tot afmelding"}]',
    'pv_ontvangers'   => '[{"partij":"Mailchimp","doel":"Mail","locatie":"VS"}]',
    'pv_cf_grondslag' => 'Toestemming (Art. 6 lid 1 sub a AVG)',
    'pv_dpo_enabled'  => '0',
) );
$rows = cm_register_csv_rows( $pv, 36, '26-09-2026' );
cm_assert( 'kop met bedrijfsnaam en datum', $rows[0][0] === 'Verwerkingsregister — Voorbeeld BV' && $rows[0][2] === 'Datum: 26-09-2026' );
$doel = $rows[3];
cm_assert( 'doel-rij gebruikt de ingevulde bewaartermijn', $doel[0] === 'Nieuwsbrief' && $doel[5] === 'Tot afmelding' );
cm_assert( 'ontvangers met locatie', strpos( $doel[4], 'Mailchimp (VS)' ) === 0 );
$contact = null;
foreach ( $rows as $r ) if ( $r[0] === 'Contactformulier' ) $contact = $r;
cm_assert( 'contactformulier gebruikt de gekozen grondslag', $contact && $contact[3] === 'Toestemming (Art. 6 lid 1 sub a AVG)' );
$consent = null;
foreach ( $rows as $r ) if ( $r[0] === 'Cookietoestemming registratie' ) $consent = $r;
cm_assert( 'bewaartermijn consent log in maanden', $consent && $consent[5] === '36 maanden' );
cm_assert( 'geen DPO-rij als die uit staat', ! in_array( 'Functionaris Gegevensbescherming (DPO)', array_column( $rows, 0 ), true ) );
$pv['pv_dpo_enabled'] = '1'; $pv['pv_dpo_naam'] = 'Jan';
cm_assert( 'wel een DPO-rij als die aan staat', in_array( 'Functionaris Gegevensbescherming (DPO)', array_column( cm_register_csv_rows( $pv, 0, 'x' ), 0 ), true ) );
cm_assert( 'melding herstellen bestaat', cm_admin_notice_html( 'privacy-reset' ) !== '' );

cm_test_group( 'Pagina Privacyverklaring' );
$tab = cm_tabs_privacy()['verklaring'];
cm_assert( 'eigen settings-groep en waarden', $tab['group'] === 'cookiebaas_privacy' && is_callable( $tab['values'] ) && isset( call_user_func( $tab['values'] )['pv_bedrijfsnaam'] ) );
$titles = array_map( function ( $s ) { return isset( $s['title'] ) ? $s['title'] : ''; }, $tab['sections'] );
$pos    = function ( $t ) use ( $titles ) { $i = array_search( $t, $titles, true ); return $i === false ? -1 : $i; };
cm_assert( 'secties in de volgorde van de uitvoer (11 vóór 12)', $pos( '1. Inleiding' ) < $pos( '2.1 Contactformulier' ) && $pos( '11. Wijzigingen' ) < $pos( '12. Geautomatiseerde besluitvorming' ) && $pos( '11. Wijzigingen' ) > 0 );
cm_assert( 'tabelkleuren onderaan, niet tussen 4 en 5', end( $titles ) === 'Weergave van de cookietabellen' );
cm_assert( 'land is nu te bewerken', in_array( 'pv_land', array_column( cm_admin_field_list( 'cm_privacy' ), 'key' ), true ) );

cm_test_group( 'Ontbrekend tekstveld houdt zijn waarde (oude admin post niet elk veld)' );
$existing_land = array_merge( $base, array( 'pv_land' => 'België' ) );
$input_no_land = $existing_land;
unset( $input_no_land['pv_land'] );
$kept = cm_sanitize_privacy( $input_no_land, $existing_land );
cm_assert( 'pv_land blijft België als het veld ontbreekt in de invoer', $kept['pv_land'] === 'België' );

exit( cm_test_summary() );
