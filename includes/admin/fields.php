<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   VELDREGISTER — elke tab van de nieuwe admin is data:
   pagina → tab → secties → velden. Eén renderer maakt er
   WordPress-native form-table-rijen van; dezelfde definities sturen
   de type-bewuste sanitizing (settings.php).
================================================================ */

/** Korte constructor voor een veld-definitie. */
function cm_field( $key, $type, $label, array $extra = array() ) {
    return array_merge( array( 'key' => $key, 'type' => $type, 'label' => $label ), $extra );
}

/**
 * Alle tabs per menupagina. Elke pagina levert zijn deel via een eigen
 * functie; pagina's uit latere plannen worden overgeslagen tot ze bestaan.
 */
function cm_admin_tabs() {
    $sources = array(
        'cookiebaas'            => 'cm_tabs_overzicht',
        'cookiebaas-banner'     => 'cm_tabs_banner',
        'cookiebaas-blokkering' => 'cm_tabs_blokkering',
        'cookiebaas-cookies'    => 'cm_tabs_cookies',
        'cookiebaas-privacy'    => 'cm_tabs_privacy',
        'cookiebaas-log'        => 'cm_tabs_log',
        'cookiebaas-beheer'     => 'cm_tabs_beheer',
    );
    $tabs = array();
    foreach ( $sources as $page => $fn ) {
        if ( function_exists( $fn ) ) $tabs[ $page ] = call_user_func( $fn );
    }
    return $tabs;
}

/** Alle opgeslagen velden van één option, in registervolgorde (dubbelingen blijven zichtbaar). */
function cm_admin_field_list( $option = 'cm_settings', $tabs = null ) {
    $list = array();
    foreach ( ( $tabs === null ? cm_admin_tabs() : $tabs ) as $page_tabs ) {
        foreach ( $page_tabs as $tab ) {
            foreach ( isset( $tab['sections'] ) ? $tab['sections'] : array() as $section ) {
                foreach ( isset( $section['fields'] ) ? $section['fields'] : array() as $f ) {
                    if ( isset( $f['store'] ) && $f['store'] === false ) continue;
                    if ( ( isset( $f['option'] ) ? $f['option'] : 'cm_settings' ) !== $option ) continue;
                    $list[] = $f;
                }
            }
        }
    }
    return $list;
}

/** Sleutel → veld, voor de sanitizer. */
function cm_admin_field_index( $option = 'cm_settings', $tabs = null ) {
    $index = array();
    foreach ( cm_admin_field_list( $option, $tabs ) as $f ) $index[ $f['key'] ] = $f;
    return $index;
}

/** Opties van een radio/select/lijst; een Closure wordt pas hier uitgevoerd. */
function cm_admin_field_options( array $f ) {
    if ( ! isset( $f['options'] ) ) return array();
    return $f['options'] instanceof Closure ? call_user_func( $f['options'] ) : $f['options'];
}

/** Toegestane HTML in teksten van banner en voorkeurenvenster. */
function cm_html_allowed() {
    return array( 'a' => array( 'href' => array(), 'target' => array() ), 'strong' => array(), 'em' => array() );
}

/** Komma-string (of array) → lijst niet-lege strings. */
function cm_csv_list( $value ) {
    $parts = is_array( $value ) ? $value : explode( ',', (string) $value );
    return array_values( array_filter( array_map( 'trim', array_map( 'strval', $parts ) ), 'strlen' ) );
}

function cm_admin_render_sections( array $sections, array $values ) {
    foreach ( $sections as $section ) cm_admin_render_section( $section, $values );
}

function cm_admin_render_section( array $section, array $values ) {
    $collapsible = ! empty( $section['collapsible'] );
    $extra = isset( $section['attrs'] ) ? $section['attrs'] : array();
    $class = ( $collapsible ? 'cm-details' : 'cm-section' ) . ( isset( $extra['class'] ) ? ' ' . $extra['class'] : '' );
    unset( $extra['class'] );
    $attrs = ' class="' . esc_attr( $class ) . '"';
    foreach ( $extra as $k => $v ) {
        $attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
    }
    if ( $collapsible ) {
        echo '<details' . $attrs . ( ! empty( $section['open'] ) ? ' open' : '' ) . '><summary>' . esc_html( $section['title'] ) . '</summary><div class="cm-details-body">';
    } else {
        echo '<div' . $attrs . '>';
        if ( ! empty( $section['title'] ) ) echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
    }
    if ( ! empty( $section['intro'] ) ) echo '<p>' . wp_kses_post( $section['intro'] ) . '</p>';
    if ( ! empty( $section['content'] ) ) call_user_func( $section['content'], $values );
    if ( ! empty( $section['fields'] ) ) {
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ( $section['fields'] as $f ) cm_admin_render_field_row( $f, $values );
        echo '</tbody></table>';
    }
    echo $collapsible ? '</div></details>' : '</div>';
}

function cm_admin_render_field_row( array $f, array $values ) {
    $key   = $f['key'];
    $id    = 'cm-f-' . $key;
    $name  = ( isset( $f['option'] ) ? $f['option'] : 'cm_settings' ) . '[' . $key . ']';
    $value = ( isset( $f['value'] ) && $f['value'] instanceof Closure )
        ? call_user_func( $f['value'], $values )
        : ( array_key_exists( $key, $values ) ? $values[ $key ] : '' );
    $show  = ! empty( $f['show_if'] ) ? ' data-cm-show-if="' . esc_attr( wp_json_encode( $f['show_if'] ) ) . '"' : '';
    $mark  = ( $f['type'] === 'color_optional' || ! empty( $f['optional'] ) ) ? ' <span class="description">(optioneel)</span>' : '';
    $plain = in_array( $f['type'], array( 'checkbox', 'radio', 'checkboxes', 'media', 'custom' ), true );

    echo '<tr' . $show . '><th scope="row">';
    echo $plain
        ? esc_html( $f['label'] ) . $mark
        : '<label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . $mark . '</label>';
    echo '</th><td>';
    cm_admin_render_control( $f, $id, $name, $value );
    if ( ! empty( $f['notice'] ) ) {
        echo '<div class="notice notice-' . esc_attr( $f['notice']['type'] ) . ' inline"><p>' . wp_kses_post( $f['notice']['text'] ) . '</p></div>';
    }
    if ( ! empty( $f['description'] ) ) echo '<p class="description">' . wp_kses_post( $f['description'] ) . '</p>';
    echo '</td></tr>';
}

function cm_admin_render_control( array $f, $id, $name, $value ) {
    $key  = esc_attr( $f['key'] );
    $attr = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-cm-key="' . $key . '"';
    $ph   = isset( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';

    switch ( $f['type'] ) {
        case 'textarea':
        case 'html':
        case 'code':
            $class = $f['type'] === 'code' ? 'large-text code' : 'large-text';
            $rows  = isset( $f['rows'] ) ? (int) $f['rows'] : 4;
            echo '<textarea' . $attr . ' class="' . $class . '" rows="' . $rows . '"' . $ph . '>' . esc_textarea( (string) $value ) . '</textarea>';
            break;

        case 'number':
            echo '<input type="number"' . $attr . ' class="small-text" value="' . esc_attr( (string) $value ) . '"'
                . ( isset( $f['min'] ) ? ' min="' . (int) $f['min'] . '"' : '' )
                . ( isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : '' ) . '>';
            if ( ! empty( $f['unit'] ) ) echo ' ' . esc_html( $f['unit'] );
            break;

        case 'checkbox':
            echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
            echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox"' . $attr . ' value="1"' . ( (string) $value === '1' ? ' checked' : '' ) . '> '
                . wp_kses_post( isset( $f['checkbox_label'] ) ? $f['checkbox_label'] : $f['label'] ) . '</label>';
            break;

        case 'radio':
            echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html( $f['label'] ) . '</span></legend>';
            $i = 0;
            foreach ( cm_admin_field_options( $f ) as $opt => $label ) {
                $oid = $id . '-' . $i++;
                echo '<label for="' . esc_attr( $oid ) . '"><input type="radio" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name ) . '" data-cm-key="' . $key . '" value="' . esc_attr( $opt ) . '"'
                    . ( (string) $value === (string) $opt ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label><br>';
            }
            echo '</fieldset>';
            break;

        case 'select':
            echo '<select' . $attr . '>';
            foreach ( cm_admin_field_options( $f ) as $opt => $label ) {
                echo '<option value="' . esc_attr( $opt ) . '"' . ( (string) $value === (string) $opt ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            break;

        case 'multiselect':
            $list = cm_csv_list( $value );
            echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
            echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]" data-cm-key="' . $key . '" multiple size="8">';
            foreach ( cm_admin_field_options( $f ) as $opt => $label ) {
                echo '<option value="' . esc_attr( $opt ) . '"' . ( in_array( (string) $opt, $list, true ) ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            break;

        case 'checkboxes':
            $list = cm_csv_list( $value );
            $all  = ! empty( $f['all_when_empty'] ) && (string) $value === '';
            echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html( $f['label'] ) . '</span></legend>';
            echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
            $i = 0;
            foreach ( cm_admin_field_options( $f ) as $opt => $label ) {
                $oid = $id . '-' . $i++;
                echo '<label for="' . esc_attr( $oid ) . '"><input type="checkbox" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name ) . '[]" data-cm-key="' . $key . '" value="' . esc_attr( $opt ) . '"'
                    . ( $all || in_array( (string) $opt, $list, true ) ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label><br>';
            }
            echo '</fieldset>';
            break;

        case 'color':
        case 'color_optional':
            $v     = (string) $value;
            $valid = preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) === 1;
            echo '<span class="cm-color' . ( $valid ? '' : ' cm-color-empty' ) . '">';
            echo '<input type="color"' . ( $valid ? ' value="' . esc_attr( strtolower( $v ) ) . '"' : '' ) . ' aria-label="' . esc_attr( 'Kleurkiezer: ' . $f['label'] ) . '">';
            echo '<input type="text"' . $attr . ' class="code cm-hex" value="' . esc_attr( $v ) . '" maxlength="7" size="8" spellcheck="false" autocomplete="off"' . $ph . '>';
            if ( $f['type'] === 'color_optional' ) {
                echo ' <button type="button" class="button-link cm-color-clear"' . ( $v === '' ? ' hidden' : '' ) . '>Wissen</button>';
            }
            echo '</span>';
            break;

        case 'media':
            $v = (string) $value;
            echo '<span class="cm-media"><input type="hidden"' . $attr . ' value="' . esc_attr( $v ) . '">';
            echo '<img class="cm-media-img" alt=""' . ( $v !== '' ? ' src="' . esc_url( $v ) . '"' : ' hidden' ) . '> ';
            echo '<button type="button" class="button cm-media-pick">Afbeelding kiezen</button> ';
            echo '<button type="button" class="button-link cm-media-remove"' . ( $v === '' ? ' hidden' : '' ) . '>Verwijderen</button></span>';
            break;

        case 'custom':
            call_user_func( $f['render'], $f, $id, $name, $value );
            break;

        default: // text
            echo '<input type="text"' . $attr . ' class="' . esc_attr( isset( $f['class'] ) ? $f['class'] : 'regular-text' ) . '" value="' . esc_attr( (string) $value ) . '"' . $ph . '>';
    }
}
