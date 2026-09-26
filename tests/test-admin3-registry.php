<?php
/**
 * Nieuwe admin (3.0) — volledigheid van het register (spec §8).
 *
 * De belangrijkste test van de herindeling: elke instelling uit
 * cm_default_settings() staat op precies één tab, of in een expliciete
 * lijst. Zo kan er bij het verhuizen niets verdwijnen of dubbel staan.
 * Plan 2 en 3 verwijderen sleutels uit $pending tot die leeg is.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function get_pages() { return array(); }
function absint( $v ) { return abs( (int) $v ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
foreach ( glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ) as $file ) require $file;

$no_ui   = array( 'txt_embed_btn', 'txt_embed_btn_en', 'color_always_on_bg' );          // dood, sleutel blijft voor de data
$pending = array( 'log_retention_months',                                                 // plan 3: Consent log
                  'api_key' );                                                            // plan 3: Beheer › Geavanceerd

cm_test_group( 'Elke instelling precies één keer' );
$keys   = array_map( function ( $f ) { return $f['key']; }, cm_admin_field_list( 'cm_settings' ) );
$counts = array_count_values( $keys );
$dups   = array_keys( array_filter( $counts, function ( $n ) { return $n > 1; } ) );
cm_assert( 'geen sleutel op twee plekken' . ( $dups ? ' — dubbel: ' . implode( ', ', $dups ) : '' ), ! $dups );
$missing = array_diff( array_keys( cm_default_settings() ), $keys, $no_ui, $pending );
cm_assert( 'geen instelling zonder plek' . ( $missing ? ' — ontbreekt: ' . implode( ', ', $missing ) : '' ), ! $missing );
$unknown = array_diff( $keys, array_keys( cm_default_settings() ) );
cm_assert( 'geen veld zonder default (zou nooit opgeslagen worden)' . ( $unknown ? ' — onbekend: ' . implode( ', ', $unknown ) : '' ), ! $unknown );
cm_assert( 'geen UI-loze sleutel toch op een tab', ! array_intersect( $no_ui, $keys ) );

cm_test_group( 'Idempotent met het echte register' );
$valid = cm_default_settings();
$once  = cm_sanitize_settings( $valid, $valid );
$diff  = array();
foreach ( $valid as $k => $v ) if ( is_scalar( $v ) && (string) $once[ $k ] !== (string) $v ) $diff[] = "$k: " . var_export( $v, true ) . ' → ' . var_export( $once[ $k ], true );
cm_assert( 'de defaults komen ongewijzigd door de sanitizer' . ( $diff ? ' — ' . implode( '; ', $diff ) : '' ), ! $diff );

cm_test_group( 'Embed-diensten (Review Focus 2 en 4)' );
$idx = cm_admin_field_index( 'cm_settings' );
$all = array_keys( cm_embed_service_options() );
cm_assert( 'alle diensten aangevinkt → leeg (= alles blokkeren)', cm_sanitize_field_value( $idx['embed_blocked_services'], array_merge( array( '' ), $all ), 'x' ) === '' );
cm_assert( 'niets aangevinkt → none', cm_sanitize_field_value( $idx['embed_blocked_services'], array( '' ), '' ) === 'none' );
cm_assert( 'één dienst → alleen die', cm_sanitize_field_value( $idx['embed_blocked_services'], array( '', 'YouTube' ), '' ) === 'YouTube' );
cm_assert( 'onbekende dienst wordt genegeerd', cm_sanitize_field_value( $idx['embed_blocked_services'], array( '', 'YouTube', 'Hackdienst' ), '' ) === 'YouTube' );
cm_assert( 'oude admin post een string: blijft werken', cm_sanitize_field_value( $idx['embed_blocked_services'], 'YouTube,Vimeo', '' ) === 'YouTube,Vimeo' );
cm_assert( 'reCAPTCHA (functioneel) staat niet in de lijst', ! in_array( 'reCAPTCHA', $all, true ) && ! in_array( 'Google reCAPTCHA', $all, true ) );

cm_test_group( 'Blokkering-tabs' );
$tabs = cm_tabs_blokkering();
cm_assert( 'tabs Google, Scripts, Embeds', array_keys( $tabs ) === array( 'google', 'scripts', 'embeds' ) );

exit( cm_test_summary() );
