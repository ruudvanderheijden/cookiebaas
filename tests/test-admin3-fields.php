<?php
/**
 * Nieuwe admin (3.0) — renderer van het veldregister.
 *
 * Borgt: elk veld is gekoppeld aan zijn label (for/id), checkboxes posten een
 * 0 als ze uit staan, kleurvelden tonen nooit een ongeldige waarde in het
 * kleurvlak, optionele kleuren zijn herkenbaar en leeg toegestaan,
 * voorwaardelijke rijen dragen hun show_if.
 */

function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses_post( $s ) { return (string) $s; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';

function row( array $f, array $values ) {
    ob_start();
    cm_admin_render_field_row( $f, $values );
    return ob_get_clean();
}

cm_test_group( 'Tekstveld' );
$h = row( cm_field( 'ga4_measurement_id', 'text', 'GA4 Measurement ID' ), array( 'ga4_measurement_id' => 'G-1' ) );
cm_assert( 'label for verwijst naar het veld', strpos( $h, '<label for="cm-f-ga4_measurement_id">' ) !== false && strpos( $h, 'id="cm-f-ga4_measurement_id"' ) !== false );
cm_assert( 'name volgt de Settings API (option[key])', strpos( $h, 'name="cm_settings[ga4_measurement_id]"' ) !== false );
cm_assert( 'data-cm-key voor de JS', strpos( $h, 'data-cm-key="ga4_measurement_id"' ) !== false );

cm_test_group( 'Checkbox' );
$on  = row( cm_field( 'respect_dnt', 'checkbox', 'Do Not Track', array( 'checkbox_label' => 'Respecteer DNT' ) ), array( 'respect_dnt' => 1 ) );
$off = row( cm_field( 'respect_dnt', 'checkbox', 'Do Not Track' ), array( 'respect_dnt' => '0' ) );
$hid = strpos( $on, 'type="hidden" name="cm_settings[respect_dnt]" value="0"' );
cm_assert( 'verborgen 0 staat vóór de checkbox', $hid !== false && $hid < strpos( $on, 'type="checkbox"' ) );
cm_assert( 'aangevinkt bij 1', strpos( $on, ' checked' ) !== false );
cm_assert( 'niet aangevinkt bij 0', strpos( $off, ' checked' ) === false );

cm_test_group( 'Kleur' );
$ok  = row( cm_field( 'color_title', 'color', 'Titels' ), array( 'color_title' => '#ABCDEF' ) );
$bad = row( cm_field( 'color_title', 'color', 'Titels' ), array( 'color_title' => 'rgb(250 252 255)' ) );
cm_assert( 'geldige hex staat in kleurvlak (lowercase) en hex-veld', strpos( $ok, 'type="color" value="#abcdef"' ) !== false && strpos( $ok, 'class="code cm-hex" value="#ABCDEF"' ) !== false );
cm_assert( 'ongeldige waarde komt niet in het kleurvlak', ! preg_match( '/type="color"[^>]*value=/', $bad ) );
cm_assert( 'hex-veld (niet het kleurvlak) draagt id en name', preg_match( '/<input type="text" id="cm-f-color_title" name="cm_settings\[color_title\]"[^>]*class="code cm-hex"/', $ok ) === 1 );
$opt_empty = row( cm_field( 'color_accept_border', 'color_optional', 'Akkoord — rand', array( 'placeholder' => 'Geen rand' ) ), array( 'color_accept_border' => '' ) );
$opt_full  = row( cm_field( 'color_accept_border', 'color_optional', 'Akkoord — rand' ), array( 'color_accept_border' => '#444444' ) );
cm_assert( 'optioneel: label krijgt (optioneel)', strpos( $opt_empty, '(optioneel)' ) !== false );
cm_assert( 'optioneel leeg: placeholder en Wissen verborgen', strpos( $opt_empty, 'placeholder="Geen rand"' ) !== false && strpos( $opt_empty, 'cm-color-clear" hidden' ) !== false );
cm_assert( 'optioneel gevuld: Wissen zichtbaar', strpos( $opt_full, 'cm-color-clear"' ) !== false && strpos( $opt_full, 'cm-color-clear" hidden' ) === false );

cm_test_group( 'Radio en show_if' );
$r = row( cm_field( 'float_btn_style', 'radio', 'Stijl', array( 'options' => array( 'icon' => 'Rond icoon', 'text' => 'Tekstknop' ), 'show_if' => array( 'show_float_btn' => '1' ) ) ), array( 'float_btn_style' => 'text' ) );
cm_assert( 'fieldset met legend', strpos( $r, '<fieldset><legend class="screen-reader-text">' ) !== false );
cm_assert( 'juiste optie aangevinkt', preg_match( '/value="text" checked/', $r ) === 1 && preg_match( '/value="icon" checked/', $r ) === 0 );
cm_assert( 'rij draagt show_if als JSON', strpos( $r, 'data-cm-show-if="{&quot;show_float_btn&quot;:&quot;1&quot;}"' ) !== false );

cm_test_group( 'Checkboxlijst' );
$cb = cm_field( 'embed_blocked_services', 'checkboxes', 'Diensten', array( 'options' => array( 'YouTube' => 'YouTube', 'Vimeo' => 'Vimeo' ), 'all_when_empty' => true ) );
cm_assert( "leeg + all_when_empty: alles aangevinkt", substr_count( row( $cb, array( 'embed_blocked_services' => '' ) ), ' checked' ) === 2 );
cm_assert( "'none': niets aangevinkt", substr_count( row( $cb, array( 'embed_blocked_services' => 'none' ) ), ' checked' ) === 0 );
cm_assert( "'YouTube': één aangevinkt", substr_count( row( $cb, array( 'embed_blocked_services' => 'YouTube' ) ), ' checked' ) === 1 );
cm_assert( 'verborgen lege waarde zodat alles uit ook post', strpos( row( $cb, array() ), 'type="hidden" name="cm_settings[embed_blocked_services][]" value=""' ) !== false );

cm_test_group( 'Index en lijst' );
$tabs = array( 'p' => array(
    'a' => array( 'label' => 'A', 'sections' => array( array( 'fields' => array(
        cm_field( 'x', 'text', 'X' ),
        cm_field( 'ui_only', 'radio', 'UI', array( 'store' => false ) ),
        cm_field( 'pv_iets', 'text', 'P', array( 'option' => 'cm_privacy' ) ),
    ) ) ) ),
    'b' => array( 'label' => 'B', 'sections' => array( array( 'fields' => array( cm_field( 'x', 'text', 'X nogmaals' ) ) ) ) ),
) );
cm_assert( 'lijst toont dubbelingen', count( cm_admin_field_list( 'cm_settings', $tabs ) ) === 2 );
cm_assert( 'index: alleen opgeslagen velden van deze option', array_keys( cm_admin_field_index( 'cm_settings', $tabs ) ) === array( 'x' ) );
cm_assert( 'index per option', array_keys( cm_admin_field_index( 'cm_privacy', $tabs ) ) === array( 'pv_iets' ) );

exit( cm_test_summary() );
