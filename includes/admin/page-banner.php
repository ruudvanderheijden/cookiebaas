<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   PAGINA BANNER — Vormgeving · Teksten · Weergave · Gedrag
   Regel (spec §3): kleuren → Vormgeving, teksten → Teksten,
   plaats en uiterlijk-gedrag → Weergave, wanneer en hoe lang → Gedrag.
================================================================ */

function cm_tabs_banner() {
    return array(
        'vormgeving' => cm_tab_banner_vormgeving(),
        'teksten'    => cm_tab_banner_teksten(),
        'weergave'   => cm_tab_banner_weergave(),
        'gedrag'     => cm_tab_banner_gedrag(),
    );
}

/** Schakelaar "Bewerken voor: A | B" (zie admin-common.js). */
function cm_render_switch( $name, array $options, $current, $prefix ) {
    echo '<ul class="subsubsub cm-switch" data-cm-switch="' . esc_attr( $name ) . '"><li>' . esc_html( $prefix ) . ' </li>';
    $last = array_key_last( $options );
    foreach ( $options as $value => $label ) {
        $on = (string) $value === (string) $current;
        echo '<li><a href="#" data-cm-switch-to="' . esc_attr( $value ) . '"' . ( $on ? ' class="current" aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>' . ( $value === $last ? '' : ' |' ) . '</li>';
    }
    echo '</ul>';
}

/** Kleurgroepen voor één thema. Licht: color_*, radius_*, overlay_opacity. Donker: dm_*. */
function cm_color_sections( $theme ) {
    $p    = $theme === 'dark' ? 'dm_' : 'color_';
    $r    = $theme === 'dark' ? 'dm_' : '';
    $pane = array( 'data-cm-pane' => 'theme:' . $theme, 'class' => 'postbox' );
    $c    = function ( $key, $label, array $extra = array() ) use ( $p ) { return cm_field( $p . $key, 'color', $label, $extra ); };
    $o    = function ( $key, $label, $ph ) use ( $p ) { return cm_field( $p . $key, 'color_optional', $label, array( 'placeholder' => $ph ) ); };
    return array(
        array( 'title' => 'Venster', 'collapsible' => true, 'open' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'popup_bg', 'Achtergrond' ),
            $c( 'title', 'Titels' ),
            $c( 'body', 'Tekst' ),
            $c( 'link', 'Links' ),
            cm_field( $r . 'radius_popup', 'number', 'Hoekafronding venster', array( 'min' => 0, 'max' => 60, 'unit' => 'px' ) ),
            cm_field( $r . 'overlay_opacity', 'number', 'Donkerte achtergrond', array( 'min' => 0, 'max' => 90, 'unit' => '%', 'description' => 'Hoe donker de pagina achter het venster wordt.' ) ),
        ) ),
        array( 'title' => 'Knoppen', 'collapsible' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'accept_bg', 'Akkoord — achtergrond' ),
            $c( 'accept_text', 'Akkoord — tekst' ),
            $c( 'accept_hover_bg', 'Akkoord — achtergrond bij hover' ),
            $c( 'accept_hover_text', 'Akkoord — tekst bij hover' ),
            $o( 'accept_border', 'Akkoord — rand', 'Geen rand' ),
            $c( 'reject_bg', 'Weigeren — achtergrond' ),
            $c( 'reject_text', 'Weigeren — tekst' ),
            $c( 'reject_hover_bg', 'Weigeren — achtergrond bij hover' ),
            $c( 'reject_hover_text', 'Weigeren — tekst bij hover' ),
            $o( 'reject_border', 'Weigeren — rand', 'Geen rand' ),
            $c( 'prefs_border', 'Cookie voorkeuren — rand' ),
            $c( 'prefs_text', 'Cookie voorkeuren — tekst' ),
            $c( 'prefs_hover_border', 'Cookie voorkeuren — rand bij hover' ),
            $c( 'prefs_hover_text', 'Cookie voorkeuren — tekst bij hover' ),
            $c( 'allowall_bg', 'Alles toestaan — achtergrond' ),
            $c( 'allowall_text', 'Alles toestaan — tekst' ),
            $c( 'allowall_hover_bg', 'Alles toestaan — achtergrond bij hover' ),
            $c( 'allowall_hover_text', 'Alles toestaan — tekst bij hover' ),
            $o( 'allowall_border', 'Alles toestaan — rand', 'Geen rand' ),
            $c( 'outline_border', 'Alles afwijzen — rand' ),
            $c( 'outline_text', 'Alles afwijzen — tekst' ),
            $c( 'outline_hover_border', 'Alles afwijzen — rand bij hover' ),
            $c( 'outline_hover_text', 'Alles afwijzen — tekst bij hover' ),
            $o( 'outline_hover_bg', 'Alles afwijzen — achtergrond bij hover', 'Geen achtergrond' ),
            cm_field( $r . 'radius_btn', 'number', 'Hoekafronding knoppen', array( 'min' => 0, 'max' => 60, 'unit' => 'px', 'description' => 'Geldt voor alle knoppen.' ) ),
        ) ),
        array( 'title' => 'Voorkeurenvenster en cookielijst', 'collapsible' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'close_bg', 'Sluitknop — achtergrond' ),
            $c( 'close_hover_bg', 'Sluitknop — achtergrond bij hover' ),
            $c( 'close_icon', 'Sluitknop — kruisje' ),
            $c( 'toggle_on', 'Schakelaar aan' ),
            $c( 'toggle_off', 'Schakelaar uit' ),
            $c( 'always_bg', '"Altijd actief" — achtergrond' ),
            $c( 'always_on_color', '"Altijd actief" — tekst' ),
            $c( 'expand_bg', 'Uitklapicoon — achtergrond' ),
            $c( 'expand_icon', 'Uitklapicoon — icoon' ),
            $c( 'expand_open_bg', 'Uitklapicoon open — achtergrond' ),
            $c( 'expand_open_icon', 'Uitklapicoon open — icoon' ),
            $c( 'cat_header_hover', 'Categorie — achtergrond bij hover' ),
            $c( 'cat_desc', 'Categorie — omschrijving' ),
            $c( 'cat_detail', 'Detailtekst' ),
            $c( 'cookie_name', 'Cookienaam' ),
            $c( 'cookie_meta', 'Cookiegegevens' ),
            $c( 'cat_border', 'Randen', array( 'description' => 'Rand om categorieën, diensten en cookies.' ) ),
            $c( 'service_bg', 'Dienst — achtergrond' ),
            $c( 'service_name', 'Dienst — naam' ),
            $c( 'cookie_item_bg', 'Cookierij — achtergrond' ),
            $c( 'cookie_empty', 'Tekst bij lege cookielijst' ),
            $c( 'badge_text', 'Badge derde partij — tekst' ),
            $c( 'badge_bg', 'Badge derde partij — achtergrond' ),
            $c( 'badge_border', 'Badge derde partij — rand' ),
        ) ),
        array( 'title' => 'Zweefknop', 'collapsible' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'float_icon_bg', 'Icoon — achtergrond' ),
            $c( 'float_icon_color', 'Icoon — kleur' ),
            $c( 'float_icon_hover_bg', 'Icoon — achtergrond bij hover' ),
            $c( 'float_icon_hover_color', 'Icoon — kleur bij hover' ),
            $c( 'float_text_bg', 'Tekstknop — achtergrond' ),
            $c( 'float_text_color', 'Tekstknop — tekst' ),
            $c( 'float_text_border', 'Tekstknop — rand' ),
            $c( 'float_text_hover_bg', 'Tekstknop — achtergrond bij hover' ),
            $c( 'float_text_hover_color', 'Tekstknop — tekst bij hover' ),
        ) ),
        array( 'title' => 'Placeholder voor geblokkeerde video\'s', 'collapsible' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'embed_bg', 'Achtergrond' ),
            $c( 'embed_title', 'Titel' ),
            $c( 'embed_body', 'Tekst' ),
            $c( 'embed_btn_bg', 'Knop — achtergrond' ),
            $c( 'embed_btn_text', 'Knop — tekst' ),
            $c( 'embed_btn_hover_bg', 'Knop — achtergrond bij hover' ),
            $c( 'embed_btn_hover_text', 'Knop — tekst bij hover' ),
        ) ),
    );
}

function cm_tab_banner_vormgeving() {
    $head = array(
        array( 'title' => 'Thema', 'fields' => array(
            cm_field( 'color_theme', 'radio', 'Actief thema', array(
                'options'     => array( 'light' => 'Licht', 'dark' => 'Donker' ),
                'description' => 'Welk kleurenschema bezoekers zien.',
            ) ),
        ) ),
        array( 'title' => 'Kleuren', 'content' => function () {
            cm_render_switch( 'theme', array( 'light' => 'Licht', 'dark' => 'Donker' ), cm_get( 'color_theme' ) === 'dark' ? 'dark' : 'light', 'Bewerken voor:' );
        } ),
    );
    return array(
        'label'      => 'Vormgeving',
        'preview'    => true,
        'sections'   => array_merge( $head, cm_color_sections( 'light' ), cm_color_sections( 'dark' ) ),
        'after_form' => function () {
            echo '<p>Standaardkleuren herstellen: ';
            echo cm_admin_action_form( 'reset_theme', 'Licht', array( 'theme' => 'light' ), 'De kleuren van het lichte thema teruggezet naar de standaard?', 'button button-small' );
            echo ' ';
            echo cm_admin_action_form( 'reset_theme', 'Donker', array( 'theme' => 'dark' ), 'De kleuren van het donkere thema teruggezet naar de standaard?', 'button button-small' );
            echo '</p>';
        },
    );
}

/** Zet de kleuren (en afronding/overlay) van één thema terug naar de defaults. */
function cm_reset_theme_colors( array $settings, $theme ) {
    foreach ( cm_default_settings() as $key => $val ) {
        if ( $theme === 'dark' ) {
            if ( strpos( $key, 'dm_' ) === 0 ) $settings[ $key ] = $val;
            continue;
        }
        if ( $key === 'color_theme' ) continue;
        if ( strpos( $key, 'color_' ) === 0 || strpos( $key, 'radius_' ) === 0 || $key === 'overlay_opacity' ) {
            $settings[ $key ] = $val;
        }
    }
    return $settings;
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'reset_theme', function () {
        $theme = ( isset( $_POST['theme'] ) && $_POST['theme'] === 'dark' ) ? 'dark' : 'light';
        $saved = get_option( 'cm_settings', array() );
        update_option( 'cm_settings', cm_reset_theme_colors( is_array( $saved ) ? $saved : array(), $theme ) );
        return 'theme-reset-' . $theme;
    } );
}

/** Tekstsecties voor één taal; bij 'en' krijgen de sleutels het achtervoegsel _en. */
function cm_text_sections( $lang ) {
    $s      = $lang === 'en' ? '_en' : '';
    $pane   = array( 'data-cm-pane' => 'lang:' . $lang );
    $html   = 'Toegestane HTML: <code>&lt;a href=""&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>.';
    $cats   = array( 1 => 'Functionele cookies (categorie 1)', 2 => 'Analytische cookies (categorie 2)', 3 => 'Marketingcookies (categorie 3)' );
    $sections = array(
        array( 'title' => 'Hoofdbanner', 'attrs' => $pane, 'fields' => array(
            cm_field( 'txt_banner_title' . $s, 'text', 'Titel' ),
            cm_field( 'txt_banner_body' . $s, 'html', 'Tekst', array( 'description' => $html ) ),
            cm_field( 'txt_btn_prefs' . $s, 'text', 'Knop "Cookie voorkeuren"' ),
            cm_field( 'txt_btn_reject' . $s, 'text', 'Knop "Weigeren"' ),
            cm_field( 'txt_btn_accept' . $s, 'text', 'Knop "Akkoord"' ),
        ) ),
        array( 'title' => 'Voorkeurenvenster', 'attrs' => $pane, 'fields' => array(
            cm_field( 'txt_prefs_title' . $s, 'text', 'Titel' ),
            cm_field( 'txt_prefs_body' . $s, 'html', 'Tekst', array( 'description' => $html ) ),
            cm_field( 'txt_btn_allowall' . $s, 'text', 'Knop "Alles toestaan"' ),
            cm_field( 'txt_btn_rejectall' . $s, 'text', 'Knop "Alles afwijzen"' ),
            cm_field( 'txt_btn_save' . $s, 'text', 'Knop "Keuzes opslaan"' ),
        ) ),
    );
    foreach ( $cats as $i => $title ) {
        $sections[] = array( 'title' => $title, 'attrs' => $pane, 'collapsible' => true, 'fields' => array(
            cm_field( "txt_cat{$i}_name{$s}", 'text', 'Naam' ),
            cm_field( "txt_cat{$i}_short{$s}", 'text', 'Korte omschrijving' ),
            cm_field( "txt_cat{$i}_long{$s}", 'textarea', 'Uitgebreide omschrijving' ),
        ) );
    }
    $sections[] = array( 'title' => 'Zweefknop', 'attrs' => $pane, 'fields' => array(
        cm_field( 'txt_float_label' . $s, 'text', 'Tekst', array( 'description' => 'De tekst van de tekstknop, en het schermlezerlabel van het icoon.' ) ),
    ) );
    $sections[] = array( 'title' => 'Placeholder voor geblokkeerde video\'s', 'attrs' => $pane, 'fields' => array(
        cm_field( 'txt_embed_title' . $s, 'text', 'Titel' ),
        cm_field( 'txt_embed_body' . $s, 'html', 'Tekst', array(
            'allowed'     => array( 'strong' => array(), 'em' => array() ),
            'description' => 'Gebruik <code>{service}</code> voor de naam van de dienst, bijvoorbeeld YouTube. Toegestane HTML: <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>.',
        ) ),
        cm_field( 'txt_embed_accept_btn' . $s, 'text', 'Knop "Cookies accepteren"' ),
        cm_field( 'txt_embed_prefs' . $s, 'html', 'Link naar voorkeuren', array(
            'allowed'     => array( 'a' => array( 'href' => array(), 'class' => array() ), 'strong' => array(), 'em' => array() ),
            'description' => 'Houd de link <code>&lt;a href="#" class="cm-embed-open-prefs"&gt;…&lt;/a&gt;</code> intact: die opent het voorkeurenvenster.',
        ) ),
    ) );
    return $sections;
}

function cm_tab_banner_teksten() {
    $sections = array(
        array( 'title' => 'Taal', 'fields' => array(
            cm_field( 'banner_language', 'radio', 'Taal van de banner', array(
                'options'     => array( 'nl' => 'Nederlands', 'en' => 'English' ),
                'description' => 'In welke taal bezoekers de banner zien, los van de taal van de site. Gebruikt u een vertaalplugin zoals TranslatePress, laat dit dan op de standaardtaal van de site staan en vertaal via die plugin.',
            ) ),
        ) ),
        array( 'title' => 'Teksten', 'content' => function () {
            cm_render_switch( 'lang', array( 'nl' => 'Nederlands', 'en' => 'English' ), cm_detect_lang(), 'Bewerken voor:' );
        } ),
    );
    return array(
        'label'    => 'Teksten',
        'preview'  => true,
        'sections' => array_merge( $sections, cm_text_sections( 'nl' ), cm_text_sections( 'en' ) ),
    );
}

/** Welk icoon de zweefknop gebruikt, afgeleid uit de opgeslagen waarden. */
function cm_banner_icon_type( array $values ) {
    if ( ! empty( $values['float_icon_image_url'] ) )  return 'image';
    if ( ! empty( $values['float_icon_custom_svg'] ) ) return 'custom';
    return 'default';
}

/** Array (of oude komma-string) van pagina-id's → '7,12'. */
function cm_sanitize_csv_ids( $raw ) {
    if ( ! is_array( $raw ) ) return sanitize_text_field( $raw );
    return implode( ',', array_filter( array_map( 'absint', $raw ) ) );
}

function cm_tab_banner_weergave() {
    $float_on  = array( 'show_float_btn' => '1' );
    $icon_on   = array( 'show_float_btn' => '1', 'float_btn_style' => 'icon' );
    return array(
        'label'    => 'Weergave',
        'sections' => array(
            array(
                'title'  => 'Cookiebanner',
                'fields' => array(
                    cm_field( 'banner_position', 'radio', 'Positie', array(
                        'options'     => array(
                            'bottom-center' => 'Onderaan in het midden (standaard)',
                            'center'        => 'In het midden van het scherm',
                            'bottom-left'   => 'Linksonder, compact',
                            'bottom-right'  => 'Rechtsonder, compact',
                        ),
                        'description' => 'Alleen de plek verandert; de werking blijft gelijk.',
                    ) ),
                    cm_field( 'banner_width_bottom_center', 'number', 'Breedte', array( 'min' => 400, 'max' => 1200, 'unit' => 'px', 'description' => 'Standaard 760 px.', 'show_if' => array( 'banner_position' => 'bottom-center' ) ) ),
                    cm_field( 'banner_width_center', 'number', 'Breedte', array( 'min' => 400, 'max' => 1000, 'unit' => 'px', 'description' => 'Standaard 620 px.', 'show_if' => array( 'banner_position' => 'center' ) ) ),
                    cm_field( 'banner_width_compact', 'number', 'Breedte', array( 'min' => 300, 'max' => 600, 'unit' => 'px', 'description' => 'Standaard 420 px.', 'show_if' => array( 'banner_position' => array( 'bottom-left', 'bottom-right' ) ) ) ),
                    cm_field( 'banner_mobile_padding', 'checkbox', 'Mobiel', array(
                        'checkbox_label' => 'Ruimte rond de banner op kleine schermen',
                        'description'    => 'Uit: de banner loopt tot de schermranden. Aan: een kleine marge, zodat de banner zweeft.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Voorkeurenvenster',
                'fields' => array(
                    cm_field( 'prefs_cookie_detail', 'radio', 'Detailniveau', array(
                        'options'     => array(
                            '1' => 'Gedetailleerd: categorieën met de cookies per categorie',
                            '0' => 'Vereenvoudigd: alleen categorieën met hun omschrijving',
                        ),
                        'description' => 'De schakelaars per categorie zijn altijd zichtbaar.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Zweefknop',
                'intro'  => 'Met de zweefknop kunnen bezoekers hun keuze later wijzigen. De AVG vereist dat intrekken even makkelijk is als toestemming geven.',
                'fields' => array(
                    cm_field( 'show_float_btn', 'checkbox', 'Zweefknop', array(
                        'checkbox_label' => 'Zweefknop tonen',
                        'description'    => 'Zet u de zweefknop uit, plaats dan zelf een link in de footer:<br><code>&lt;a href="#" onclick="Cookiebaas.openPrefs();return false;"&gt;Cookie-instellingen&lt;/a&gt;</code><br>Of open de banner zelf: <code>Cookiebaas.showBanner()</code>.',
                    ) ),
                    cm_field( 'float_btn_style', 'radio', 'Stijl', array( 'options' => array( 'icon' => 'Rond icoon', 'text' => 'Tekstknop' ), 'show_if' => $float_on ) ),
                    cm_field( 'float_icon_size', 'radio', 'Grootte', array( 'options' => array( 'normal' => 'Normaal (52 px)', 'small' => 'Klein (40 px)' ), 'show_if' => $icon_on ) ),
                    cm_field( 'cm_icon_type', 'radio', 'Icoon', array(
                        'store'   => false,
                        'options' => array( 'default' => 'Standaard cookie-icoon', 'custom' => 'Eigen SVG-code', 'image' => 'Afbeelding uit de mediabibliotheek' ),
                        'value'   => function ( $values ) { return cm_banner_icon_type( $values ); },
                        'show_if' => $icon_on,
                    ) ),
                    cm_field( 'float_icon_custom_svg', 'code', 'SVG-code', array(
                        'placeholder' => '<svg viewBox="0 0 24 24">…</svg>',
                        'description' => 'Plak de volledige <code>&lt;svg&gt;…&lt;/svg&gt;</code>-code. De kleur volgt de icoonkleur op de tab Vormgeving.',
                        'show_if'     => array_merge( $icon_on, array( 'cm_icon_type' => 'custom' ) ),
                    ) ),
                    cm_field( 'float_icon_image_url', 'media', 'Afbeelding', array(
                        'description' => 'SVG, WebP, JPG of PNG. De afbeelding wordt getoond zoals hij is; zorg zelf voor voldoende contrast.',
                        'show_if'     => array_merge( $icon_on, array( 'cm_icon_type' => 'image' ) ),
                    ) ),
                    cm_field( 'float_position', 'radio', 'Positie', array( 'options' => array( 'left' => 'Linksonder', 'right' => 'Rechtsonder' ), 'show_if' => $float_on ) ),
                ),
            ),
        ),
    );
}

function cm_tab_banner_gedrag() {
    return array(
        'label'    => 'Gedrag',
        'sections' => array(
            array(
                'title'  => 'Standaardkeuzes',
                'fields' => array(
                    cm_field( 'analytics_default', 'checkbox', 'Analytische cookies', array(
                        'checkbox_label' => 'Standaard aangevinkt in het voorkeurenvenster',
                        'description'    => 'Marketingcookies staan altijd standaard uit (AVG-vereiste). Staat "Google-cookies direct laden" aan (Blokkering › Google), dan staat dit automatisch ook aan.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Toestemming',
                'fields' => array(
                    cm_field( 'expiry_months', 'number', 'Keuze geldig', array( 'min' => 1, 'max' => 24, 'unit' => 'maanden', 'description' => 'Daarna vraagt de banner opnieuw om toestemming. AVG-richtlijn: maximaal 12 maanden.' ) ),
                    cm_field( 'respect_dnt', 'checkbox', 'Do Not Track', array(
                        'checkbox_label' => 'Respecteer het Do Not Track-signaal (DNT) van de browser',
                        'description'    => 'Met DNT aan worden analytische en marketingcookies automatisch geweigerd. De banner wordt overgeslagen en de keuze wordt gelogd als "dnt".',
                    ) ),
                    cm_field( 'respect_gpc', 'checkbox', 'Global Privacy Control', array(
                        'checkbox_label' => 'Respecteer het Global Privacy Control-signaal (GPC) van de browser',
                        'description'    => 'GPC is de opvolger van DNT en is in 12+ Amerikaanse staten wettelijk verplicht. Europese toezichthouders (CNIL, ICO) zien GPC als geldig bezwaar (AVG art. 21). Met GPC aan worden analytische en marketingcookies automatisch geweigerd.',
                    ) ),
                    cm_field( 'reload_after_consent', 'checkbox', 'Herladen na akkoord', array(
                        'checkbox_label' => 'Herlaad de pagina ook na het geven van toestemming',
                        'description'    => 'Standaard <strong>uit</strong>: na akkoord worden scripts en embeds direct vrijgegeven zonder herladen (geen flits; scrollpositie en formuliervelden blijven behouden). Aan geeft een schone, volledig gemeten <code>page_view</code> van de landingspagina. Bij het <strong>intrekken</strong> van toestemming wordt altijd herladen, om draaiende scripts te stoppen.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Wie ziet de banner',
                'fields' => array(
                    cm_field( 'geo_enabled', 'radio', 'Zichtbaarheid', array(
                        'options'     => array( '0' => 'Altijd tonen, voor alle bezoekers wereldwijd (standaard, veiligste keuze)', '1' => 'Alleen in landen met privacywetgeving' ),
                        'description' => 'Landen met privacywetgeving: EU/EER, VK, Zwitserland, Brazilië, Canada, India, Thailand, Indonesië, Zuid-Afrika, Japan, Zuid-Korea, Australië, Nieuw-Zeeland, Singapore, Argentinië en Mexico.',
                    ) ),
                    cm_field( 'geo_outside_eu', 'radio', 'Overige landen', array(
                        'options'     => array( 'hide' => 'Geen banner; cookies worden niet geblokkeerd', 'accept' => 'Automatisch akkoord; alle cookies direct toegestaan' ),
                        'description' => 'Is er geen land-header beschikbaar (geen Cloudflare of CDN), dan wordt de banner altijd getoond.',
                        'show_if'     => array( 'geo_enabled' => '1' ),
                    ) ),
                ),
            ),
            array(
                'title'  => 'Uitzonderingen',
                'intro'  => 'Op deze pagina\'s verschijnt geen banner.',
                'fields' => array(
                    cm_field( 'exclude_login_page', 'checkbox', 'Inlogpagina', array( 'checkbox_label' => 'Geen banner op <code>wp-login.php</code>' ) ),
                    cm_field( 'exclude_woocommerce_checkout', 'checkbox', 'WooCommerce', array( 'checkbox_label' => 'Geen banner bij afrekenen, betalen en de bestelbevestiging', 'description' => 'Werkt alleen als WooCommerce actief is.' ) ),
                    cm_field( 'exclude_page_ids', 'multiselect', 'Specifieke pagina\'s', array(
                        'options'     => function () {
                            $opts = array();
                            foreach ( get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'number' => 200 ) ) as $p ) {
                                $opts[ (int) $p->ID ] = $p->post_title . ' (ID ' . (int) $p->ID . ')';
                            }
                            return $opts;
                        },
                        'sanitize'    => function ( $raw ) { return cm_sanitize_csv_ids( $raw ); },
                        'description' => 'Houd Ctrl (Windows) of Cmd (Mac) ingedrukt om meerdere pagina\'s te kiezen.',
                    ) ),
                    cm_field( 'exclude_url_patterns', 'text', 'URL-patronen', array(
                        'class'       => 'large-text',
                        'placeholder' => '/bedankt, /privacyverklaring, /checkout',
                        'description' => 'Komma-gescheiden stukjes URL. Op elke pagina waarvan de URL zo\'n stukje bevat, verschijnt geen banner. Gebruikt u TranslatePress met vertaalde slugs, voeg dan ook de vertaalde varianten toe.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Subdomeinen',
                'fields' => array(
                    cm_field( 'subdomain_sharing', 'checkbox', 'Consent delen', array(
                        'checkbox_label' => 'Deel de keuze tussen subdomeinen',
                        'description'    => 'De consent-cookie wordt op het hoofddomein gezet, zodat alle subdomeinen dezelfde keuze delen.',
                    ) ),
                    cm_field( 'subdomain_root_domain', 'text', 'Hoofddomein', array(
                        'placeholder' => '.voorbeeld.nl',
                        'description' => 'Met een punt ervoor, bijvoorbeeld <code>.voorbeeld.nl</code>. Installeer de plugin op elk subdomein met dezelfde instelling en vermeld in de bannertekst welke domeinen de keuze dekt (AVG-transparantie).',
                        'show_if'     => array( 'subdomain_sharing' => '1' ),
                    ) ),
                ),
            ),
        ),
    );
}
