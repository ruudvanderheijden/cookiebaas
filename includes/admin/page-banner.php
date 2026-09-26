<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   PAGINA BANNER — Vormgeving · Teksten · Weergave · Gedrag
   Regel (spec §3): kleuren → Vormgeving, teksten → Teksten,
   plaats en uiterlijk-gedrag → Weergave, wanneer en hoe lang → Gedrag.
================================================================ */

function cm_tabs_banner() {
    return array(
        'weergave' => cm_tab_banner_weergave(),
        'gedrag'   => cm_tab_banner_gedrag(),
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
