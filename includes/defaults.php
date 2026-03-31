<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   TAALDETECTIE — hier gedefinieerd zodat defaults.php, frontend.php
   en alle andere includes cm_detect_lang() kunnen aanroepen
================================================================ */

/**
 * Geeft de gekozen bannertaal terug: 'nl' of 'en'.
 * Gebaseerd op de expliciete keuze van de beheerder (banner_language instelling).
 * Onafhankelijk van de WordPress site-taal.
 */
function cm_detect_lang() {
    $lang = cm_get('banner_language');
    return ( $lang === 'en' ) ? 'en' : 'nl';
}

function cm_default_settings() {
    return array(
        // Popup
        'color_popup_bg'            => '#ffffff',
        'color_title'               => '#111111',
        'color_body'                => '#494949',
        'color_link'                => '#111111',
        // Akkoord button
        'color_accept_bg'           => '#111111',
        'color_accept_text'         => '#ffffff',
        'color_accept_hover_bg'     => '#0091ff',
        'color_accept_hover_text'   => '#ffffff',
        'color_accept_border'       => '',
        // Weigeren button
        'color_reject_bg'           => '#111111',
        'color_reject_hover_bg'     => '#0091ff',
        'color_reject_text'         => '#ffffff',
        'color_reject_hover_text'   => '#ffffff',
        'color_reject_border'       => '',
        // Cookie voorkeuren button
        'color_prefs_border'        => '#d1d1d1',
        'color_cat_border'          => '#eaeaea',
        'color_cat_header_hover'    => 'rgb(250 252 255)',
        'color_cat_desc'            => '#1d2327',
        'color_cat_detail'          => '#666666',
        'color_cookie_name'         => '#333333',
        'color_cookie_meta'         => '#4e4e4e',
        'color_toggle_off'          => '#dddddd',
        'color_always_on_bg'        => '#e8f4ff',
        'color_service_bg'          => '#f9f5f2',
        'color_cookie_item_bg'      => '#ffffff',
        'color_prefs_text'          => '#111111',
        'color_prefs_hover_border'  => '#111111',
        'color_prefs_hover_text'    => '#111111',
        // Alle cookies toestaan button
        'color_allowall_bg'         => '#0091ff',
        'color_allowall_text'       => '#ffffff',
        'color_allowall_hover_bg'   => '#111111',
        'color_allowall_hover_text' => '#ffffff',
        'color_allowall_border'     => '',
        // Alles afwijzen button (outline stijl in prefs)
        'color_outline_border'       => '#d1d1d1',
        'color_outline_text'         => '#111111',
        'color_outline_hover_border' => '#111111',
        'color_outline_hover_text'   => '#111111',
        'color_outline_hover_bg'     => '',
        // Sluit-knop
        'color_close_bg'            => '#eaeaea',
        'color_close_hover_bg'      => '#38342e',
        'color_close_icon'          => '#555555',
        // Toggles & badges
        'color_toggle_on'           => '#0091ff',
        'color_always_bg'           => '#e8f4ff',
        'color_always_on_color'     => '#0091ff',   // "Altijd actief" badge tekstkleur
        // Derde-partij badge
        'color_badge_text'          => '#0091ff',
        'color_badge_bg'            => '#e8f4ff',
        'color_badge_border'        => '#0091ff',
        // Cookielijst overig
        'color_service_name'        => '#333333',   // Servicenaam tekstkleur
        'color_cookie_empty'        => '#bbbbbb',   // Lege cookielijst tekst
        // Expand-icoon
        'color_expand_bg'           => '#eaeaea',
        'color_expand_icon'         => '#111111',
        'color_expand_open_bg'      => '#0091ff',
        'color_expand_open_icon'    => '#ffffff',
        // Stijl
        'radius_popup'              => 18,
        'radius_btn'                => 6,
        'overlay_opacity'           => 20,
        // Gedrag
        'google_load_default'       => 0,
        'analytics_default'         => 0,
        'show_float_btn'            => 1,
        'expiry_months'             => 12,
        // Google integratie — beheerd door plugin (verwijder GA snippet uit Header/Footer plugin)
        'ga4_measurement_id'        => '',
        'gtm_container_id'          => '',
        'ua_tracking_id'            => '',
        // Script blocking — optionele extra patronen (ingebouwde kennisbank werkt automatisch)
        'block_analytics_patterns'  => '',
        'block_marketing_patterns'  => '',
        // Meertaligheid
        'banner_language'            => 'nl',  // 'nl' = Nederlands (standaard), 'en' = Engels
        // Automatische cookie scan
        'auto_scan_mode'             => 'off',   // 'off' = handmatig, 'auto' = automatisch toevoegen, 'notify' = melding per mail
        'auto_scan_interval'         => '30',    // aantal dagen: 10, 30, 180
        'auto_scan_email'            => '',      // e-mailadres voor notificatie (alleen bij mode=notify)

        // Teksten — hoofdbanner
        'txt_banner_title'          => 'Mogen we cookies plaatsen?',
        'txt_banner_body'           => 'Wij gebruiken cookies om de website goed te laten werken, uw bezoek te analyseren en gepersonaliseerde advertenties te tonen. Door op <strong>Akkoord</strong> te klikken gaat u akkoord met alle cookies. U kunt uw voorkeuren altijd aanpassen. Lees meer in ons <a href="/privacybeleid">privacybeleid</a>.',
        'txt_btn_prefs'             => 'Cookie voorkeuren',
        'txt_btn_reject'            => 'Weigeren',
        'txt_btn_accept'            => 'Akkoord',
        // Teksten — voorkeuren venster
        'txt_prefs_title'           => 'Uw cookievoorkeuren beheren',
        'txt_prefs_body'            => 'Hieronder kunt u per categorie aangeven welke cookies u toestaat. Functionele cookies zijn altijd actief omdat de website anders niet werkt. U kunt uw keuze op elk moment wijzigen. Meer informatie in ons <a href="/privacybeleid">privacybeleid</a>.',
        'txt_btn_allowall'          => 'Alle cookies toestaan',
        'txt_btn_rejectall'         => 'Alles afwijzen',
        'txt_btn_save'              => 'Mijn keuzes opslaan',
        // Teksten — categorieën
        'txt_cat1_name'             => 'Functionele cookies',
        'txt_cat1_short'            => 'Noodzakelijk voor de werking van de website',
        'txt_cat1_long'             => 'Functionele cookies zijn strikt noodzakelijk voor het functioneren van de website. Zonder deze cookies kunnen basisfuncties zoals navigatie en toegang tot beveiligde pagina\'s niet werken.',
        'txt_cat2_name'             => 'Analytische cookies',
        'txt_cat2_short'            => 'Helpt ons de website te verbeteren',
        'txt_cat2_long'             => 'Analytische cookies helpen ons te begrijpen hoe bezoekers de website gebruiken. Alle gegevens worden geanonimiseerd verwerkt.',
        'txt_cat3_name'             => 'Marketing & tracking cookies',
        'txt_cat3_short'            => 'Gepersonaliseerde advertenties en remarketing',
        'txt_cat3_long'             => 'Marketing cookies worden gebruikt om advertenties beter af te stemmen op uw interesses. Deze cookies kunnen uw surfgedrag over meerdere websites volgen.',
        'txt_float_label'           => 'Cookie-instellingen',

        // Teksten â hoofdbanner (EN)
        'txt_banner_title_en'       => 'May we use cookies?',
        'txt_banner_body_en'        => 'We use cookies to keep our website running, analyse your visit and show personalised advertisements. By clicking <strong>Accept</strong> you agree to all cookies. You can adjust your preferences at any time. Read more in our <a href="/privacy-policy">privacy policy</a>.',
        'txt_btn_prefs_en'          => 'Cookie preferences',
        'txt_btn_reject_en'         => 'Decline',
        'txt_btn_accept_en'         => 'Accept',
        // Teksten â voorkeuren venster (EN)
        'txt_prefs_title_en'        => 'Manage your cookie preferences',
        'txt_prefs_body_en'         => 'Below you can indicate per category which cookies you allow. Functional cookies are always active because the website would not work otherwise. You can change your choice at any time. More information in our <a href="/privacy-policy">privacy policy</a>.',
        'txt_btn_allowall_en'       => 'Allow all cookies',
        'txt_btn_rejectall_en'      => 'Decline all',
        'txt_btn_save_en'           => 'Save my choices',
        // Teksten â categorieën (EN)
        'txt_cat1_name_en'          => 'Functional cookies',
        'txt_cat1_short_en'         => 'Necessary for the website to function',
        'txt_cat1_long_en'          => 'Functional cookies are strictly necessary for the website to work. Without these cookies, basic functions such as navigation and access to secure pages cannot function.',
        'txt_cat2_name_en'          => 'Analytical cookies',
        'txt_cat2_short_en'         => 'Helps us improve the website',
        'txt_cat2_long_en'          => 'Analytical cookies help us understand how visitors use the website. All data is processed anonymously.',
        'txt_cat3_name_en'          => 'Marketing & tracking cookies',
        'txt_cat3_short_en'         => 'Personalised advertising and remarketing',
        'txt_cat3_long_en'          => 'Marketing cookies are used to better tailor advertisements to your interests. These cookies may track your browsing behaviour across multiple websites.',
        'txt_float_label_en'        => 'Cookie settings',
        // Zweefknop stijl: 'text' = tekstknop (standaard), 'icon' = rond icoontje
        'float_btn_style'              => 'icon',
        'float_position'               => 'left',    // left | right
        'color_float_icon_bg'          => '#ffffff',
        'color_float_icon_color'       => '#111111',
        'color_float_icon_hover_bg'    => '#0091ff',
        'color_float_icon_hover_color' => '#ffffff',
        // Dark mode kleuren (leeg = dark mode uitgeschakeld / zelfde als light)
        'color_theme'                  => 'light',  // 'light' | 'dark'
        'dm_popup_bg'                  => '#111111',
        'dm_title'                     => '#f2f2f2',
        'dm_body'                      => '#a8a8a8',
        'dm_link'                      => '#6eb8ff',
        'dm_accept_bg'                 => '#f2f2f2',
        'dm_accept_text'               => '#111111',
        'dm_accept_hover_bg'           => '#0091ff',
        'dm_accept_hover_text'         => '#ffffff',
        'dm_reject_bg'                 => '#f2f2f2',
        'dm_reject_hover_bg'           => '#0091ff',
        'dm_reject_text'               => '#111111',
        'dm_reject_hover_text'         => '#ffffff',
        'dm_reject_border'             => '',
        'dm_prefs_border'              => '#ffffff',
        'dm_prefs_text'                => '#ffffff',
        'dm_prefs_hover_border'        => '#666666',
        'dm_prefs_hover_text'          => '#f2f2f2',
        'dm_allowall_bg'               => '#0091ff',
        'dm_allowall_text'             => '#ffffff',
        'dm_allowall_hover_bg'         => '#ffffff',
        'dm_allowall_hover_text'       => '#111111',
        'dm_allowall_border'           => '',
        'dm_outline_border'            => '#3a3a3a',
        'dm_outline_text'              => '#ffffff',
        'dm_outline_hover_border'      => '#888888',
        'dm_outline_hover_text'        => '#f2f2f2',
        'dm_outline_hover_bg'          => '',
        'dm_close_bg'                  => '#2a2a2a',
        'dm_close_hover_bg'            => '#f2f2f2',
        'dm_close_icon'                => '#888888',
        'dm_toggle_on'                 => '#0091ff',
        'dm_always_bg'                 => '#0c2a45',
        'dm_always_on_color'           => '#6eb8ff',   // "Altijd actief" badge tekstkleur
        // Derde-partij badge (dark)
        'dm_badge_text'                => '#ffd97a',
        'dm_badge_bg'                  => '#3a2e00',
        'dm_badge_border'              => '#6b5200',
        // Cookielijst overig (dark)
        'dm_service_name'              => '#d0d0d0',
        'dm_cookie_empty'              => '#666666',
        'dm_expand_bg'                 => '#2a2a2a',
        'dm_expand_icon'               => '#aaaaaa',
        'dm_expand_open_bg'            => '#6eb8ff',
        'dm_expand_open_icon'          => '#111111',
        'dm_cat_border'                => '#2e2e2e',
        'dm_service_bg'                => '#252525',
        'dm_cookie_item_bg'            => '#1e1e1e',
        'dm_cat_header_hover'          => '#383838',
        'dm_cat_desc'                  => '#acacac',
        'dm_cat_detail'                => '#a6a6a6',
        'dm_cookie_name'               => '#acacac',
        'dm_cookie_meta'               => '#acacac',
        'dm_toggle_off'                => '#6d6d6d',
        'dm_float_icon_bg'             => '#111111',
        'dm_float_icon_color'          => '#ffffff',
        'dm_float_icon_hover_bg'       => '#0091ff',
        'dm_float_icon_hover_color'    => '#ffffff',
        'dm_float_text_bg'             => '#1e1e1e',
        'dm_float_text_color'          => '#f2f2f2',
        'dm_float_text_border'         => '#3a3a3a',
        'dm_float_text_hover_bg'       => '#2a2a2a',
        'dm_float_text_hover_color'    => '#ffffff',
        'dm_embed_bg'                  => '#111111',
        'dm_embed_title'               => '#f2f2f2',
        'dm_embed_body'                => '#a8a8a8',
        'dm_embed_btn_bg'              => '#f2f2f2',
        'dm_embed_btn_text'            => '#111111',
        'dm_embed_btn_hover_bg'        => '#6eb8ff',
        'dm_embed_btn_hover_text'      => '#111111',
        'dm_radius_popup'              => 18,
        'dm_radius_btn'                => 6,
        'dm_overlay_opacity'           => 20,
        // Geo-targeting
        'geo_enabled'                  => '0',   // '0' = altijd tonen, '1' = alleen landen met wetgeving
        'respect_dnt'                  => 0,      // 1 = respecteer Do Not Track signaal van de browser
        'respect_gpc'                  => 0,      // 1 = respecteer Global Privacy Control signaal
        'subdomain_sharing'            => 0,      // 1 = deel consent cookie tussen subdomeinen
        'subdomain_root_domain'        => '',     // bijv. .voorbeeld.nl
        'geo_outside_eu'               => 'hide', // 'hide' of 'accept'
        // Pagina-uitzonderingen — geen banner op deze pagina's
        'exclude_page_ids'             => '',   // komma-gescheiden post/page IDs
        'exclude_url_patterns'         => '',   // komma-gescheiden URL-stukken (bijv. /bedankt, /checkout)
        'exclude_login_page'           => 1,    // wp-login.php
        'exclude_woocommerce_checkout' => 0,    // WooCommerce checkout & betaalpagina's
        'banner_position'              => 'bottom-center',  // bottom-center | bottom-left | bottom-right | center
        'banner_width_bottom_center'   => 760,    // px — breedte bij onderaan gecentreerd
        'banner_width_center'          => 620,    // px — breedte bij midden scherm
        'banner_width_compact'         => 420,    // px — breedte bij linksonder/rechtsonder
        'banner_mobile_padding'        => 1,      // 1 = padding rondom op mobiel, 0 = geen padding
        'prefs_cookie_detail'          => 1,      // 1 = cookies per categorie tonen, 0 = alleen categoriebeschrijving
        // Zweefknop — tekstknop kleuren
        'color_float_text_bg'          => '#ffffff',
        'color_float_text_color'       => '#111111',
        'color_float_text_border'      => '#d1d1d1',
        'color_float_text_hover_bg'    => '#ffffff',
        'color_float_text_hover_color' => '#0091ff',
        // Zweefknop — icoontje grootte en custom SVG
        'float_icon_size'              => 'normal',   // normal | small
        'float_icon_custom_svg'        => '',          // leeg = standaard icoontje, anders: volledige SVG markup
        // Embed blocker — blokkeert iframes (YouTube, Vimeo, etc.) tot consent
        'embed_blocker_enabled'        => 1,    // 1 = actief, 0 = uit
        'embed_blocked_services'       => '',   // leeg = alle diensten blokkeren (standaard). Komma-gescheiden lijst om specifieke diensten te blokkeren.
        // Embed blocker teksten (NL)
        'txt_embed_title'              => 'Accepteer om te bekijken',
        'txt_embed_body'               => 'Deze inhoud wordt gehost door <strong>{service}</strong> en kan cookies plaatsen. Klik hieronder om de inhoud te laden.',
        'txt_embed_btn'                => 'Inhoud laden',
        'txt_embed_accept_btn'         => 'Accepteer cookies',
        'txt_embed_prefs'              => 'Of pas uw <a href="#" class="cm-embed-open-prefs">cookievoorkeuren</a> aan.',
        // Embed blocker teksten (EN)
        'txt_embed_title_en'           => 'External content blocked',
        'txt_embed_body_en'            => 'This content is hosted by <strong>{service}</strong> and may set cookies. Click below to load the content.',
        'txt_embed_btn_en'             => 'Load content',
        'txt_embed_accept_btn_en'      => 'Accept cookies',
        'txt_embed_prefs_en'           => 'Or adjust your <a href="#" class="cm-embed-open-prefs">cookie preferences</a>.',
        // Embed blocker kleuren
        'color_embed_bg'               => '#111111',   // Achtergrond placeholder
        'color_embed_title'            => '#ffffff',   // Titel kleur
        'color_embed_body'             => '#ffffff',   // Tekst kleur
        'color_embed_btn_bg'           => '#ffffff',   // Knop achtergrond
        'color_embed_btn_text'         => '#111111',   // Knop tekst
        'color_embed_btn_hover_bg'     => '#0091ff',   // Knop hover achtergrond
        'color_embed_btn_hover_text'   => '#111111',   // Knop hover tekst
        // API
        'api_key'                      => '',
        'log_retention_months'      => 36, // 36 maanden = aanbevolen AVG-termijn
    );
}

/**
 * Centrale service-mapping: normaliseert cookienamen naar een vaste dienstnaam.
 * Geeft array( 'service' => 'Dienstnaam', 'group' => 'Groepeer-sleutel' ) terug,
 * of null als de cookie niet herkend wordt.
 *
 * Matching volgorde:
 *   1. Exacte naam
 *   2. Prefix (cookienaam begint met patroon)
 *
 * Zo worden _ga, _ga_XXXXXX, _gid, _gat allemaal onder "Google Analytics" gebundeld,
 * _fbp en _fbc onder "Meta / Facebook Pixel", etc.
 * Toekomstige cookies worden automatisch herkend als ze matchen op prefix of naam.
 */
function cm_service_for_cookie( $name ) {
    static $map = null;
    if ( $map === null ) {
        $map = array(
            // ── Google Analytics ─────────────────────────────────────────
            '_ga'          => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '_ga_'         => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '_gid'         => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '_gat'         => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '_gat_'        => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '__utma'       => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '__utmb'       => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '__utmc'       => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '__utmt'       => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '__utmz'       => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            '_gac_'        => array('service' => 'Google Analytics',    'group' => 'google-analytics', 'third_party' => true, 'country' => 'VS'),
            // ── Google Ads ───────────────────────────────────────────────
            '_gcl_au'      => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            '_gcl_aw'      => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            '_gcl_gs'      => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            '_gcl_dc'      => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            'IDE'          => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            'test_cookie'  => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            'NID'          => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            'PREF'         => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            'CONSENT'      => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            'SOCS'         => array('service' => 'Google Ads',          'group' => 'google-ads',       'third_party' => true, 'country' => 'VS'),
            // ── Meta / Facebook ───────────────────────────────────────────
            '_fbp'         => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            '_fbc'         => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            'fr'           => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            'datr'         => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            'sb'           => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            'c_user'       => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            'xs'           => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            'wd'           => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            'spin'         => array('service' => 'Meta / Facebook',     'group' => 'meta-facebook',    'third_party' => true, 'country' => 'VS'),
            // ── LinkedIn ──────────────────────────────────────────────────
            'bcookie'      => array('service' => 'LinkedIn',            'group' => 'linkedin',         'third_party' => true, 'country' => 'VS'),
            'lidc'         => array('service' => 'LinkedIn',            'group' => 'linkedin',         'third_party' => true, 'country' => 'VS'),
            'li_gc'        => array('service' => 'LinkedIn',            'group' => 'linkedin',         'third_party' => true, 'country' => 'VS'),
            'li_fat_id'    => array('service' => 'LinkedIn',            'group' => 'linkedin',         'third_party' => true, 'country' => 'VS'),
            'UserMatchHistory' => array('service' => 'LinkedIn',        'group' => 'linkedin',         'third_party' => true, 'country' => 'VS'),
            'AnalyticsSyncHistory' => array('service' => 'LinkedIn',    'group' => 'linkedin',         'third_party' => true, 'country' => 'VS'),
            'li_'          => array('service' => 'LinkedIn',            'group' => 'linkedin',         'third_party' => true, 'country' => 'VS'),
            'ln_'          => array('service' => 'LinkedIn',            'group' => 'linkedin',         'third_party' => true, 'country' => 'VS'),
            // ── Hotjar ────────────────────────────────────────────────────
            '_hj'          => array('service' => 'Hotjar',              'group' => 'hotjar',           'third_party' => true, 'country' => 'MT'),
            'hjViewportId' => array('service' => 'Hotjar',              'group' => 'hotjar',           'third_party' => true, 'country' => 'MT'),
            // ── Microsoft Clarity ─────────────────────────────────────────
            '_cl'          => array('service' => 'Microsoft Clarity',   'group' => 'ms-clarity',       'third_party' => true, 'country' => 'VS'),
            'CLID'         => array('service' => 'Microsoft Clarity',   'group' => 'ms-clarity',       'third_party' => true, 'country' => 'VS'),
            'MUID'         => array('service' => 'Microsoft Clarity',   'group' => 'ms-clarity',       'third_party' => true, 'country' => 'VS'),
            'MR'           => array('service' => 'Microsoft Clarity',   'group' => 'ms-clarity',       'third_party' => true, 'country' => 'VS'),
            // ── Microsoft Advertising (UET) ───────────────────────────────
            '_uetsid'      => array('service' => 'Microsoft Advertising', 'group' => 'ms-ads',           'third_party' => true, 'country' => 'VS'),
            '_uetsid_exp'  => array('service' => 'Microsoft Advertising', 'group' => 'ms-ads',           'third_party' => true, 'country' => 'VS'),
            '_uetvid'      => array('service' => 'Microsoft Advertising', 'group' => 'ms-ads',           'third_party' => true, 'country' => 'VS'),
            '_uetvid_exp'  => array('service' => 'Microsoft Advertising', 'group' => 'ms-ads',           'third_party' => true, 'country' => 'VS'),
            'MSCLKID'      => array('service' => 'Microsoft Advertising', 'group' => 'ms-ads',           'third_party' => true, 'country' => 'VS'),
            '_msclkid'     => array('service' => 'Microsoft Advertising', 'group' => 'ms-ads',           'third_party' => true, 'country' => 'VS'),
            // ── TikTok ────────────────────────────────────────────────────
            '_tt_'         => array('service' => 'TikTok',              'group' => 'tiktok',           'third_party' => true, 'country' => 'CN/VS'),
            'tt_'          => array('service' => 'TikTok',              'group' => 'tiktok',           'third_party' => true, 'country' => 'CN/VS'),
            'ttwid'        => array('service' => 'TikTok',              'group' => 'tiktok',           'third_party' => true, 'country' => 'CN/VS'),
            // ── Pinterest ─────────────────────────────────────────────────
            '_pinterest_ct_' => array('service' => 'Pinterest',         'group' => 'pinterest',        'third_party' => true, 'country' => 'VS'),
            '_pin_unauth'  => array('service' => 'Pinterest',           'group' => 'pinterest',        'third_party' => true, 'country' => 'VS'),
            '_derived_epik'=> array('service' => 'Pinterest',           'group' => 'pinterest',        'third_party' => true, 'country' => 'VS'),
            // ── X / Twitter ───────────────────────────────────────────────
            '_twitter_sess'=> array('service' => 'X / Twitter',         'group' => 'twitter',          'third_party' => true, 'country' => 'VS'),
            'twid'         => array('service' => 'X / Twitter',         'group' => 'twitter',          'third_party' => true, 'country' => 'VS'),
            'ct0'          => array('service' => 'X / Twitter',         'group' => 'twitter',          'third_party' => true, 'country' => 'VS'),
            'guest_id'     => array('service' => 'X / Twitter',         'group' => 'twitter',          'third_party' => true, 'country' => 'VS'),
            // ── YouTube ───────────────────────────────────────────────────
            'YSC'          => array('service' => 'YouTube',             'group' => 'youtube',          'third_party' => true, 'country' => 'VS'),
            'VISITOR_INFO1_LIVE' => array('service' => 'YouTube',       'group' => 'youtube',          'third_party' => true, 'country' => 'VS'),
            'yt-remote-'   => array('service' => 'YouTube',             'group' => 'youtube',          'third_party' => true, 'country' => 'VS'),
            'yt-player-'   => array('service' => 'YouTube',             'group' => 'youtube',          'third_party' => true, 'country' => 'VS'),
            // ── Intercom ──────────────────────────────────────────────────
            'intercom-'    => array('service' => 'Intercom',            'group' => 'intercom',         'third_party' => true, 'country' => 'VS'),
            // ── HubSpot ───────────────────────────────────────────────────
            '__hs'         => array('service' => 'HubSpot',             'group' => 'hubspot',          'third_party' => true, 'country' => 'VS'),
            'hubspotutk'   => array('service' => 'HubSpot',             'group' => 'hubspot',          'third_party' => true, 'country' => 'VS'),
            'hs-'          => array('service' => 'HubSpot',             'group' => 'hubspot',          'third_party' => true, 'country' => 'VS'),
            // ── Stripe ────────────────────────────────────────────────────
            '__stripe_'    => array('service' => 'Stripe',              'group' => 'stripe',           'third_party' => true, 'country' => 'VS'),
            // ── Cloudflare ────────────────────────────────────────────────
            '__cf_'        => array('service' => 'Cloudflare',          'group' => 'cloudflare',       'third_party' => true, 'country' => 'VS'),
            'cf_clearance' => array('service' => 'Cloudflare',          'group' => 'cloudflare',       'third_party' => true, 'country' => 'VS'),
            '_cfuvid'      => array('service' => 'Cloudflare',          'group' => 'cloudflare',       'third_party' => true, 'country' => 'VS'),
        );
    }

    // 1. Exacte match
    if ( isset( $map[ $name ] ) ) return $map[ $name ];

    // 2. Prefix-match
    foreach ( $map as $pattern => $info ) {
        $last = substr( $pattern, -1 );
        if ( ( $last === '_' || $last === '-' ) && strpos( $name, $pattern ) === 0 ) {
            return $info;
        }
    }

    return null;
}

/**
 * Bekende embed-domeinen die cookies plaatsen via iframes.
 * Geeft array terug van domein-patronen → service-info.
 * category: 'marketing' of 'analytics' (bepaalt welke consent nodig is)
 */
function cm_get_embed_domains() {
    return array(
        // ── YouTube ──────────────────────────────────────────────
        'youtube.com'           => array( 'service' => 'YouTube',        'category' => 'marketing', 'icon' => '▶' ),
        'youtube-nocookie.com'  => array( 'service' => 'YouTube',        'category' => 'marketing', 'icon' => '▶' ),
        'youtu.be'              => array( 'service' => 'YouTube',        'category' => 'marketing', 'icon' => '▶' ),
        // ── Vimeo ────────────────────────────────────────────────
        'vimeo.com'             => array( 'service' => 'Vimeo',          'category' => 'marketing', 'icon' => '▶' ),
        'player.vimeo.com'      => array( 'service' => 'Vimeo',          'category' => 'marketing', 'icon' => '▶' ),
        // ── Google Maps ──────────────────────────────────────────
        'google.com/maps'       => array( 'service' => 'Google Maps',    'category' => 'marketing', 'icon' => '📍' ),
        'maps.google.com'       => array( 'service' => 'Google Maps',    'category' => 'marketing', 'icon' => '📍' ),
        'maps.googleapis.com'   => array( 'service' => 'Google Maps',    'category' => 'marketing', 'icon' => '📍' ),
        // ── Spotify ──────────────────────────────────────────────
        'open.spotify.com'      => array( 'service' => 'Spotify',        'category' => 'marketing', 'icon' => '🎵' ),
        // ── SoundCloud ───────────────────────────────────────────
        'soundcloud.com'        => array( 'service' => 'SoundCloud',     'category' => 'marketing', 'icon' => '🎵' ),
        'w.soundcloud.com'      => array( 'service' => 'SoundCloud',     'category' => 'marketing', 'icon' => '🎵' ),
        // ── Dailymotion ──────────────────────────────────────────
        'dailymotion.com'       => array( 'service' => 'Dailymotion',    'category' => 'marketing', 'icon' => '▶' ),
        'geo.dailymotion.com'   => array( 'service' => 'Dailymotion',    'category' => 'marketing', 'icon' => '▶' ),
        // ── TikTok ───────────────────────────────────────────────
        'tiktok.com'            => array( 'service' => 'TikTok',         'category' => 'marketing', 'icon' => '▶' ),
        // ── Facebook / Meta ──────────────────────────────────────
        'facebook.com'          => array( 'service' => 'Facebook',       'category' => 'marketing', 'icon' => 'f' ),
        'web.facebook.com'      => array( 'service' => 'Facebook',       'category' => 'marketing', 'icon' => 'f' ),
        // ── Instagram ────────────────────────────────────────────
        'instagram.com'         => array( 'service' => 'Instagram',      'category' => 'marketing', 'icon' => '📷' ),
        // ── Twitter / X ──────────────────────────────────────────
        'twitter.com'           => array( 'service' => 'X (Twitter)',    'category' => 'marketing', 'icon' => '𝕏' ),
        'platform.twitter.com'  => array( 'service' => 'X (Twitter)',    'category' => 'marketing', 'icon' => '𝕏' ),
        'x.com'                 => array( 'service' => 'X (Twitter)',    'category' => 'marketing', 'icon' => '𝕏' ),
        // ── Pinterest ────────────────────────────────────────────
        'pinterest.com'         => array( 'service' => 'Pinterest',      'category' => 'marketing', 'icon' => '📌' ),
        'assets.pinterest.com'  => array( 'service' => 'Pinterest',      'category' => 'marketing', 'icon' => '📌' ),
        // ── Google reCAPTCHA ─────────────────────────────────────
        'recaptcha.net'         => array( 'service' => 'Google reCAPTCHA', 'category' => 'functional', 'icon' => '🔒' ),
        'google.com/recaptcha'  => array( 'service' => 'Google reCAPTCHA', 'category' => 'functional', 'icon' => '🔒' ),
    );
}

/**
 * Match een iframe-src tegen bekende embed-domeinen.
 * Retourneert service-info array of null.
 */
function cm_match_embed_domain( $src ) {
    $domains = cm_get_embed_domains();
    $src_lower = strtolower( $src );

    // Bepaal welke diensten geblokkeerd moeten worden
    $blocked_raw = cm_get('embed_blocked_services');
    // Leeg = alles blokkeren (standaard)
    $blocked_list = $blocked_raw ? array_map('trim', explode(',', $blocked_raw)) : array();
    $block_all = empty($blocked_raw);

    foreach ( $domains as $pattern => $info ) {
        if ( stripos( $src_lower, $pattern ) !== false ) {
            // Functionele embeds (reCAPTCHA) nooit blokkeren
            if ( $info['category'] === 'functional' ) return null;
            // Check of deze dienst geblokkeerd moet worden
            if ( ! $block_all && ! in_array( $info['service'], $blocked_list, true ) ) return null;
            return $info;
        }
    }
    return null;
}

/**
 * Standaard ingebouwde cookies — altijd aanwezig, ongeacht wat de gebruiker instelt.
 * Dit zijn de cookies die de plugin zelf en WordPress altijd plaatst.
 */
function cm_default_cookies() {
    // Taalafhankelijke teksten voor de ingebouwde plugin-cookies
    $lang = cm_detect_lang();
    if ( $lang === 'en' ) {
        return array(
            array(
                'name'     => 'cc_cm_consent',
                'provider' => 'Cookie Plugin',
                'purpose'  => 'Stores your cookie preferences so you are not asked again on every visit.',
                'duration' => '12 months',
                'category' => 'functional',
                'builtin'  => true,
            ),
            array(
                'name'     => 'PHPSESSID',
                'provider' => 'This website',
                'purpose'  => 'Maintains your session during your visit to the website.',
                'duration' => 'Session',
                'category' => 'functional',
                'builtin'  => true,
            ),
        );
    }
    return array(
        array(
            'name'     => 'cc_cm_consent',
            'provider' => 'Cookiemelding Plugin',
            'purpose'  => 'Slaat uw cookievoorkeuren op zodat u niet bij elk bezoek opnieuw gevraagd wordt.',
            'duration' => '12 maanden',
            'category' => 'functional',
            'builtin'  => true,
        ),
        array(
            'name'     => 'PHPSESSID',
            'provider' => 'Deze website',
            'purpose'  => 'Houdt uw sessie bij tijdens uw bezoek aan de website.',
            'duration' => 'Sessie',
            'category' => 'functional',
            'builtin'  => true,
        ),
    );
}

/**
 * Haal de volledige cookielijst op: ingebouwde cookies + door gebruiker beheerde cookies.
 */
function cm_get_cookie_list() {
    $builtin = cm_default_cookies();
    $managed = get_option( 'cm_cookie_list', array() );
    if ( ! is_array($managed) ) $managed = array();
    // Normaliseer oude waarde "Eigen website" → "Deze website" on-the-fly
    foreach ( $managed as &$ck ) {
        if ( isset($ck['provider']) && $ck['provider'] === 'Eigen website' ) {
            $ck['provider'] = 'Deze website';
        }
    }
    unset($ck);
    return array_merge( $builtin, $managed );
}

function cm_get( $key ) {
    static $merged = null;
    if ( $merged === null ) {
        $merged = cm_get_settings();
    }
    return isset( $merged[ $key ] ) ? $merged[ $key ] : '';
}

/**
 * Geeft de volledige instellingen-array terug waarbij lege DB-waarden
 * terugvallen op de default (zodat een reset altijd de juiste kleuren toont).
 */
function cm_get_settings() {
    $defaults = cm_default_settings();
    $saved    = (array) get_option( 'cm_settings', array() );
    $merged   = $defaults;
    foreach ( $saved as $k => $v ) {
        if ( $v === '' && isset( $defaults[ $k ] ) && $defaults[ $k ] !== '' ) {
            continue; // lege DB-waarde → gebruik default
        }
        $merged[ $k ] = $v;
    }
    return $merged;
}

/**
 * Reset de cm_get cache — aanroepen na opslaan van instellingen.
 */
function cm_get_flush() {
    // Force re-read bij volgende cm_get() call
    // We doen dit door de static te resetten via een wrapper
    // In PHP is dit niet mogelijk op een static in een andere functie,
    // dus we gebruiken een globale flag
    $GLOBALS['cm_settings_cache'] = null;
}

/**
 * Zoek een cookie op in de Open Cookie Database (wp_cm_cookie_db).
 * Past automatisch Nederlandse omschrijvingen en looptijden toe.
 */
function cm_lookup_cookie( $name ) {
    global $wpdb;
    $table = $wpdb->prefix . 'cm_cookie_db';

    if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) ) return false;

    // Exacte match
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE cookie_name = %s AND wildcard = 0 LIMIT 1",
        $name
    ), ARRAY_A );

    // Wildcard match
    if ( ! $row ) {
        $wildcards = $wpdb->get_results( "SELECT * FROM {$table} WHERE wildcard = 1", ARRAY_A );
        foreach ( $wildcards as $wc ) {
            if ( strpos( $name, rtrim( $wc['cookie_name'], '_' ) ) === 0 ) { $row = $wc; break; }
        }
    }

    if ( ! $row ) return false;

    $lang = cm_detect_lang();

    if ( $lang === 'nl' ) {
        // Nederlandse omschrijving override — vervangt de Engelse DB-tekst
        $nl_desc = cm_nl_descriptions();
        if ( isset( $nl_desc[ $name ] ) ) {
            $row['description'] = $nl_desc[ $name ];
        } else {
            foreach ( $nl_desc as $pat => $desc ) {
                if ( substr( $pat, -1 ) === '_' && strpos( $name, $pat ) === 0 ) {
                    $row['description'] = $desc;
                    break;
                }
            }
        }
        // Looptijd vertalen naar Nederlands
        $row['retention'] = cm_translate_retention( $row['retention'] );
    }
    // Bij EN: originele Engelse DB-beschrijving en onvertaalde retention blijven staan

    return $row;
}

/**
 * Vertaal een Engelse looptijdstring naar Nederlands.
 * Voorbeelden: "1 year" → "1 jaar", "30 days" → "30 dagen", "Session" → "Sessie"
 */
function cm_translate_retention( $retention ) {
    if ( empty( $retention ) ) return $retention;

    // Exacte woorden (case-insensitief)
    $exact = array(
        'session'        => 'Sessie',
        'persistent'     => 'Permanent',
        'never'          => 'Nooit',
        'immediately'    => 'Direct',
        'end of session' => 'Einde sessie',
    );
    $lower = strtolower( trim( $retention ) );
    if ( isset( $exact[ $lower ] ) ) return $exact[ $lower ];

    // Patronen: getal + eenheid
    $units = array(
        'years?'   => 'jaar',
        'months?'  => 'maanden',
        'weeks?'   => 'weken',
        'days?'    => 'dagen',
        'hours?'   => 'uur',
        'minutes?' => 'minuten',
        'seconds?' => 'seconden',
    );
    foreach ( $units as $en => $nl ) {
        if ( preg_match( '/^(\d+[\.,]?\d*)\s+' . $en . '$/i', $retention, $m ) ) {
            $n = $m[1];
            // "1 jaar" ipv "1 jaren"
            if ( $nl === 'jaar' ) return $n . ' jaar';
            if ( (float) $n === 1.0 ) {
                $singular = array( 'maanden'=>'maand', 'weken'=>'week', 'dagen'=>'dag', 'uur'=>'uur', 'minuten'=>'minuut', 'seconden'=>'seconde' );
                $nl = isset( $singular[ $nl ] ) ? $singular[ $nl ] : $nl;
            }
            return $n . ' ' . $nl;
        }
    }

    // Samengestelde notaties zoals "1 year 30 days" of "13 months"
    $result = preg_replace_callback(
        '/(\d+[\.,]?\d*)\s+(years?|months?|weeks?|days?|hours?|minutes?|seconds?)/i',
        function( $m ) use ( $units ) {
            $n = $m[1];
            foreach ( $units as $en => $nl ) {
                if ( preg_match( '/^' . $en . '$/i', $m[2] ) ) {
                    if ( $nl === 'jaar' ) return $n . ' jaar';
                    if ( (float) $n === 1.0 ) {
                        $singular = array( 'maanden'=>'maand', 'weken'=>'week', 'dagen'=>'dag', 'uur'=>'uur', 'minuten'=>'minuut', 'seconden'=>'seconde' );
                        $nl = isset( $singular[ $nl ] ) ? $singular[ $nl ] : $nl;
                    }
                    return $n . ' ' . $nl;
                }
            }
            return $m[0];
        },
        $retention
    );

    return $result !== $retention ? $result : $retention;
}

/**
 * Nederlandse omschrijvingen voor de meest voorkomende cookies.
 * Sleutels eindigend op _ zijn prefixes (wildcard match).
 */
function cm_nl_descriptions() {
    return array(
        // ── Google Analytics ──────────────────────────────────────────
        '_ga'                          => 'Registreert een unieke gebruikers-ID om statistieken te genereren over hoe de bezoeker de website gebruikt.',
        '_ga_'                         => 'Gebruikt door Google Analytics 4 om de sessiestatus bij te houden.',
        '_gid'                         => 'Registreert een unieke gebruikers-ID om statistieken bij te houden. Vervalt na 24 uur.',
        '_gat'                         => 'Wordt gebruikt om het aantal verzoeken naar Google Analytics te beperken.',
        '_gat_'                        => 'Wordt gebruikt om het aantal verzoeken naar Google Analytics te beperken.',
        '_gac_'                        => 'Bevat campagne-informatie voor de gebruiker, gebruikt door Google Ads en Analytics.',
        '__utma'                       => 'Verzamelt gegevens over het aantal bezoeken en de bezochte pagina\'s voor Google Analytics.',
        '__utmb'                       => 'Registreert het tijdstip van aankomst op de website om het bouncepercentage te berekenen.',
        '__utmc'                       => 'Registreert het tijdstip van vertrek van de website om de duur van het bezoek te berekenen.',
        '__utmz'                       => 'Verzamelt gegevens over de herkomst van het bezoek, zoals zoekmachine of zoekwoord.',
        '__utmt'                       => 'Wordt gebruikt om het aantal verzoeken naar Google Analytics te beperken.',
        // ── Google Ads / DoubleClick ──────────────────────────────────
        '_gcl_au'                      => 'Wordt door Google Ads gebruikt om de effectiviteit van advertenties te meten via conversietracking.',
        '_gcl_aw'                      => 'Slaat klikgegevens op van Google Ads-advertenties om conversies bij te houden.',
        '_gcl_gs'                      => 'Slaat gegevens op voor Google Ads-conversietracking via Google Zoeken.',
        'IDE'                          => 'Wordt door Google DoubleClick gebruikt om de effectiviteit van advertenties te meten en te rapporteren.',
        'test_cookie'                  => 'Controleert of de browser cookies ondersteunt. Geplaatst door Google DoubleClick.',
        'NID'                          => 'Slaat gebruikersvoorkeuren op voor Google-diensten, zoals taalinstelling en zoekresultaten.',
        'PREF'                         => 'Slaat voorkeuren op voor YouTube en Google, zoals taal en videokwaliteit.',
        'CONSENT'                      => 'Slaat de toestemmingsstatus van de gebruiker op voor cookies van Google-diensten.',
        'SOCS'                         => 'Slaat de toestemmingsstatus van de gebruiker op voor Google-domeinen.',
        'AEC'                          => 'Zorgt ervoor dat verzoeken tijdens een browsersessie door de juiste gebruiker worden gedaan om fraude te voorkomen.',
        // ── Google / YouTube ──────────────────────────────────────────
        'VISITOR_INFO1_LIVE'           => 'Schat de bandbreedte van de gebruiker in om de videokwaliteit op YouTube aan te passen.',
        'YSC'                          => 'Registreert een unieke ID om statistieken bij te houden over welke YouTube-video\'s zijn bekeken.',
        'yt-remote-device-id'          => 'Slaat de videovoorkeuren van de gebruiker op voor ingesloten YouTube-video\'s.',
        'yt-remote-connected-devices'  => 'Slaat de videovoorkeuren van de gebruiker op voor ingesloten YouTube-video\'s.',
        'yt.innertube::requests'       => 'Registreert de activiteit van de gebruiker op YouTube.',
        // ── Meta / Facebook ───────────────────────────────────────────
        '_fbp'                         => 'Wordt door Meta (Facebook) gebruikt om een reeks advertentieproducten aan te bieden, zoals realtime bieden van externe adverteerders.',
        '_fbc'                         => 'Slaat de klik-ID op van de laatste Facebook-advertentie waarop de bezoeker heeft geklikt.',
        'fr'                           => 'Wordt door Facebook gebruikt om relevante advertenties te tonen en de effectiviteit ervan te meten.',
        'datr'                         => 'Wordt door Facebook gebruikt als beveiligings- en verificatiecookie om de identiteit van gebruikers te controleren.',
        'sb'                           => 'Wordt door Facebook gebruikt om de inlogervaring van gebruikers te verbeteren.',
        'wd'                           => 'Wordt door Facebook gebruikt om de afmetingen van het browservenster bij te houden voor optimale weergave.',
        // ── LinkedIn ──────────────────────────────────────────────────
        'bcookie'                      => 'Wordt door LinkedIn gebruikt als browsercookie om advertenties te optimaliseren.',
        'bscookie'                     => 'Wordt door LinkedIn gebruikt als beveiligde browsercookie voor authenticatie.',
        'lidc'                         => 'Wordt door LinkedIn gebruikt om de taakverdeling over datacenters te vergemakkelijken.',
        'li_gc'                        => 'Slaat de toestemmingsstatus op van de bezoeker voor het gebruik van cookies door LinkedIn.',
        'li_sugr'                      => 'Wordt door LinkedIn gebruikt om de gebruiker te identificeren buiten het LinkedIn-netwerk.',
        'UserMatchHistory'             => 'Wordt door LinkedIn gebruikt voor remarketing om bezoekers opnieuw te benaderen.',
        'AnalyticsSyncHistory'         => 'Wordt door LinkedIn gebruikt om de synchronisatie van analysegegevens bij te houden.',
        'li_fat_id'                    => 'Wordt door LinkedIn gebruikt als lidspecifieke cookie voor conversietracking.',
        'li_mc'                        => 'Slaat de toestemmingsstatus op van de bezoeker voor het gebruik van marketingcookies door LinkedIn.',
        // ── Twitter / X ───────────────────────────────────────────────
        'guest_id'                     => 'Wordt door Twitter/X geplaatst om anonieme bezoekers te identificeren.',
        'personalization_id'           => 'Wordt door Twitter/X gebruikt om gepersonaliseerde inhoud en advertenties te tonen.',
        'muc_ads'                      => 'Wordt door Twitter/X gebruikt om gerichte advertenties te tonen.',
        'guest_id_ads'                 => 'Wordt door Twitter/X gebruikt voor advertentiedoeleinden.',
        'guest_id_marketing'           => 'Wordt door Twitter/X gebruikt voor marketingdoeleinden.',
        'ct0'                          => 'Beveiligingscookie van Twitter/X ter bescherming tegen cross-site request forgery.',
        'att'                          => 'Wordt door Twitter/X gebruikt om de account-authenticatiestatus bij te houden.',
        // ── Hotjar ────────────────────────────────────────────────────
        '_hjid'                        => 'Wordt door Hotjar gebruikt om een unieke gebruikers-ID in te stellen en bij te houden hoe de bezoeker de website gebruikt.',
        '_hjFirstSeen'                 => 'Wordt door Hotjar gebruikt om te registreren of de bezoeker voor het eerst op de website is.',
        '_hjIncludedInPageviewSample'  => 'Wordt door Hotjar gebruikt om te bepalen of de bezoeker in een steekproef voor pageview-tracking wordt opgenomen.',
        '_hjIncludedInSessionSample'   => 'Wordt door Hotjar gebruikt om te bepalen of de bezoeker in een steekproef voor sessietracking wordt opgenomen.',
        '_hjAbsoluteSessionInProgress' => 'Wordt door Hotjar gebruikt om het eerste paginabezoek van een sessie te detecteren.',
        '_hjTLDTest'                   => 'Wordt door Hotjar gebruikt om het cookiedomein te bepalen bij het opstarten.',
        '_hjSession_'                  => 'Houdt de huidige sessiegegevens bij voor Hotjar, zoals paginaweergaven en activiteit.',
        '_hjSessionUser_'              => 'Slaat de Hotjar gebruikers-ID op en de voortgang van de sessie voor anonieme gebruikers.',
        '_hjCachedUserAttributes'      => 'Slaat gebruikersattributen op die zijn ingesteld via de Hotjar Identify API.',
        // ── Microsoft Clarity ─────────────────────────────────────────
        '_clck'                        => 'Wordt door Microsoft Clarity gebruikt om een unieke gebruikers-ID op te slaan en bij te houden hoe de bezoeker de website gebruikt.',
        '_clsk'                        => 'Wordt door Microsoft Clarity gebruikt om meerdere paginabezoeken samen te voegen als één sessie.',
        'CLID'                         => 'Wordt door Microsoft Clarity gebruikt om de gebruiker te identificeren voor heatmaps en sessie-opnames.',
        'MUID'                         => 'Wordt door Microsoft gebruikt als unieke gebruikersidentificatie voor advertentiediensten.',
        'MR'                           => 'Wordt door Microsoft gebruikt om te bepalen of de cookie MUID opnieuw ingesteld moet worden.',
        'SM'                           => 'Wordt door Microsoft gebruikt voor synchronisatie van de MUID-cookie over domeinen.',
        // ── Microsoft Advertising (UET) ───────────────────────────────
        '_uetsid'                      => 'Wordt door Microsoft Advertising gebruikt om een sessie-ID op te slaan voor conversie- en remarketingdoeleinden via de Universal Event Tracking (UET)-tag.',
        '_uetsid_exp'                  => 'Slaat de vervaldatum op van de Microsoft Advertising UET-sessiecookie.',
        '_uetvid'                      => 'Wordt door Microsoft Advertising gebruikt om een bezoeker te herkennen bij een terugkerend bezoek voor retargeting en conversietracking via UET.',
        '_uetvid_exp'                  => 'Slaat de vervaldatum op van de Microsoft Advertising UET-bezoekercookie.',
        'MSCLKID'                      => 'Slaat de Microsoft Click ID op die wordt gegenereerd wanneer een bezoeker via een Microsoft Advertising-advertentie op de website terechtkomt. Wordt gebruikt voor conversietracking.',
        '_msclkid'                     => 'Variant van de Microsoft Click ID-cookie voor het bijhouden van klikken via Microsoft Advertising-campagnes.',
        // ── Snapchat ──────────────────────────────────────────────────
        'sc_at'                        => 'Wordt door Snapchat gebruikt om gebruikersactiviteit bij te houden en advertenties te optimaliseren.',
        '_scid'                        => 'Wordt door Snapchat gebruikt om bezoekers te identificeren voor advertentiedoeleinden.',
        '_sctr'                        => 'Wordt door Snapchat gebruikt om conversies bij te houden na blootstelling aan advertenties.',
        // ── Pinterest ─────────────────────────────────────────────────
        '_pin_unauth'                  => 'Wordt door Pinterest gebruikt om anonieme bezoekers bij te houden die geen Pinterest-account hebben.',
        '_derived_epik'                => 'Wordt door Pinterest gebruikt om conversies te meten en advertenties te personaliseren.',
        'csrftoken'                    => 'Beveiligingscookie van Pinterest ter bescherming tegen cross-site request forgery.',
        // ── TikTok ────────────────────────────────────────────────────
        'tt_webid'                     => 'Wordt door TikTok gebruikt om de gebruiker te identificeren voor gerichte advertenties.',
        'ttwid'                        => 'Wordt door TikTok gebruikt om de sessie van de gebruiker bij te houden.',
        'tt_webid_v2'                  => 'Wordt door TikTok gebruikt om de gebruiker te identificeren voor advertentiedoeleinden.',
        'ttcsid'                       => 'Wordt door TikTok gebruikt voor conversie-attributie op de website.',
        '_ttp'                         => 'Wordt door TikTok gebruikt om het gedrag van de gebruiker te meten en advertenties te optimaliseren.',
        // ── Cloudflare ────────────────────────────────────────────────
        'cf_clearance'                 => 'Wordt door Cloudflare gebruikt om te registreren dat de bezoeker een beveiligingscontrole heeft doorstaan.',
        '__cf_bm'                      => 'Wordt door Cloudflare gebruikt om onderscheid te maken tussen mensen en geautomatiseerde bots.',
        '_cfuvid'                      => 'Wordt door Cloudflare gebruikt om individuele gebruikers te identificeren die hetzelfde IP-adres delen.',
        '__cfruid'                     => 'Wordt door Cloudflare gebruikt voor taakverdeling en om server-gerelateerde informatie op te slaan.',
        // ── HubSpot ───────────────────────────────────────────────────
        '__hstc'                       => 'Wordt door HubSpot gebruikt als hoofdcookie om bezoekers bij te houden, inclusief sessies en paginabezoeken.',
        '__hssc'                       => 'Wordt door HubSpot gebruikt om bij te houden of sessies moeten worden geregistreerd.',
        '__hssrc'                      => 'Wordt door HubSpot gebruikt om te detecteren wanneer een nieuwe sessie begint.',
        'hubspotutk'                   => 'Wordt door HubSpot gebruikt om de identiteit van de bezoeker bij te houden voor het bijhouden van formulierinzendingen.',
        // ── Intercom ──────────────────────────────────────────────────
        'intercom-id-'                 => 'Wordt door Intercom gebruikt om een anonieme bezoeker te identificeren.',
        'intercom-session-'            => 'Wordt door Intercom gebruikt om de sessie van de bezoeker bij te houden.',
        'intercom-device-id-'          => 'Wordt door Intercom gebruikt om het apparaat van de bezoeker te identificeren.',
        // ── Crisp ─────────────────────────────────────────────────────
        'crisp-client%2Fsession%2F'    => 'Wordt door Crisp gebruikt om de chatsessie van de bezoeker bij te houden.',
        // ── WordPress / eigen website ─────────────────────────────────
        'PHPSESSID'                    => 'Bewaart de sessie-informatie van de bezoeker op de server. Verdwijnt zodra de browser wordt gesloten.',
        'wordpress_logged_in'          => 'Wordt door WordPress gebruikt om de inlogstatus van de gebruiker bij te houden.',
        'wordpress_sec'                => 'Beveiligingscookie van WordPress voor de beheerdersomgeving.',
        'wordpress_test_cookie'        => 'Controleert of cookies werken in de browser van de bezoeker.',
        'wp-settings-'                 => 'Slaat persoonlijke instellingen op van de ingelogde WordPress-gebruiker.',
        'wp_lang'                      => 'Slaat de taalkeuze op van de ingelogde WordPress-gebruiker.',
        'comment_author_'              => 'Slaat de naam op van de bezoeker die een reactie heeft achtergelaten.',
        'comment_author_email_'        => 'Slaat het e-mailadres op van de bezoeker die een reactie heeft achtergelaten.',
        // ── WooCommerce ───────────────────────────────────────────────
        'woocommerce_cart_hash'        => 'Helpt WooCommerce om te bepalen wanneer de inhoud van de winkelwagen is gewijzigd.',
        'woocommerce_items_in_cart'    => 'Helpt WooCommerce om bij te houden of de winkelwagen artikelen bevat.',
        'woocommerce_session_'         => 'Bevat een unieke code voor elke bezoeker die wordt gebruikt om de winkelwagengegevens op te slaan.',
        'wc_cart_created'              => 'Slaat het tijdstip op waarop de winkelwagen is aangemaakt.',
        // ── Cookiemelding plugin ──────────────────────────────────────
        'cc_cm_consent'                => 'Slaat de cookievoorkeur van de bezoeker op zodat de cookiemelding niet bij elk bezoek opnieuw hoeft te worden getoond.',
        // ── Amplitude ─────────────────────────────────────────────────
        'amplitude_id_'                => 'Wordt door Amplitude gebruikt om gebruikers te identificeren en het gedrag op de website te analyseren.',
        // ── Mixpanel ──────────────────────────────────────────────────
        'mp_'                          => 'Wordt door Mixpanel gebruikt om gebruikers anoniem te identificeren en productanalyses uit te voeren.',
        // ── Stripe ────────────────────────────────────────────────────
        '__stripe_mid'                 => 'Wordt door Stripe gebruikt om fraude te voorkomen en een veilige betaalomgeving te bieden.',
        '__stripe_sid'                 => 'Wordt door Stripe gebruikt om sessies bij te houden tijdens het betaalproces.',
        // ── Mailchimp ─────────────────────────────────────────────────
        '_mc_id'                       => 'Wordt door Mailchimp gebruikt om campagneresultaten te meten.',
        // ── Vimeo ─────────────────────────────────────────────────────
        'vuid'                         => 'Wordt door Vimeo gebruikt om de activiteit van de bezoeker bij te houden, waaronder bekeken video\'s.',
        // ── Segment ───────────────────────────────────────────────────
        'ajs_anonymous_id'             => 'Wordt door Segment gebruikt om anonieme bezoekers bij te houden voor analytics.',
        'ajs_user_id'                  => 'Wordt door Segment gebruikt om ingelogde gebruikers te identificeren voor analytics.',
        // ── Clarity / Mouseflow / FullStory ───────────────────────────
        'mf_user'                      => 'Wordt door Mouseflow gebruikt om een unieke bezoeker te identificeren voor sessie-opnames.',
        'fs_uid'                       => 'Wordt door FullStory gebruikt om een unieke gebruikerssessie te registreren.',
        // ── Cookiebot ─────────────────────────────────────────────────
        'CookieConsent'                => 'Slaat de toestemmingsstatus van de bezoeker op voor cookies, ingesteld door Cookiebot.',
    );
}

/**
 * Vertaal Open Cookie Database categorie naar interne categorienaam.
 */
function cm_map_category( $ocd_category ) {
    $map = array(
        'Functional'       => 'functional',
        'Analytics'        => 'analytics',
        'Marketing'        => 'marketing',
        'Personalization'  => 'functional',
        'Security'         => 'functional',
    );
    return isset( $map[ $ocd_category ] ) ? $map[ $ocd_category ] : 'functional';
}

/**
 * Standaardinstellingen voor de privacyverklaring.
 */
function cm_default_privacy() {
    return array(
        // Bedrijfsgegevens
        'pv_bedrijfsnaam'       => '',
        'pv_straat'             => '',
        'pv_postcode_plaats'    => '',
        'pv_land'               => 'Nederland',
        'pv_kvk'                => '',
        'pv_telefoon'           => '',
        'pv_email'              => '',
        'pv_versie'             => '1.0',
        'pv_datum'              => '',

        // Functionaris Gegevensbescherming (DPO) — optioneel
        'pv_dpo_enabled'        => '0',   // 0 = verbergen, 1 = tonen
        'pv_dpo_naam'           => '',
        'pv_dpo_email'          => '',
        'pv_dpo_telefoon'       => '',

        // 1. Inleiding
        'pv_inleiding_naam'     => '',   // naam die in "X respecteert uw privacy" staat

        // 2.1 Contactformulier — aanvinkbare velden
        'pv_cf_voornaam'        => '1',
        'pv_cf_achternaam'      => '1',
        'pv_cf_email'           => '1',
        'pv_cf_website'         => '0',
        'pv_cf_telefoon'        => '0',
        'pv_cf_bericht'         => '1',
        'pv_cf_bedrijf'         => '0',
        'pv_cf_adres'           => '0',
        'pv_cf_privacy'         => '0',
        'pv_cf_extra'           => '',
        'pv_cf_grondslag'       => 'Gerechtvaardigd belang (Art. 6 lid 1 sub f AVG)',

        // 2.3 Nieuwsbrief / e-mailmarketing
        'pv_nieuwsbrief_enabled'   => '0',
        'pv_nieuwsbrief_grondslag' => 'Toestemming (Art. 6 lid 1 sub a AVG)',
        'pv_nieuwsbrief_afmelden'  => '',

        // 3. Doeleinden — bewerkbare tabel (JSON)
        'pv_doeleinden'         => json_encode( array(
            array('doel'=>'Beantwoorden van uw contactverzoek',               'grondslag'=>'Gerechtvaardigd belang', 'termijn'=>'3 jaar'),
            array('doel'=>'Analyseren van websitegebruik',                    'grondslag'=>'Toestemming',            'termijn'=>'Zie sectie 4'),
            array('doel'=>'Verbeteren van onze website',                      'grondslag'=>'Gerechtvaardigd belang', 'termijn'=>'Geanonimiseerd'),
            array('doel'=>'Marketing en remarketing',                         'grondslag'=>'Toestemming',            'termijn'=>'Zie sectie 4'),
            array('doel'=>'Beveiliging en fraudepreventie (serverlogbestanden)','grondslag'=>'Gerechtvaardigd belang','termijn'=>'Max. 6 maanden'),
        )),

        // 4. Cookies — optionele GTM-zin
        'pv_gtm'                => '1',

        // 4.4 Opt-out links (JSON)
        'pv_optout_links'       => json_encode( array(
            array('naam'=>'Google',   'url'=>'https://tools.google.com/dlpage/gaoptout'),
            array('naam'=>'Meta',     'url'=>'https://www.facebook.com/settings?tab=ads'),
            array('naam'=>'LinkedIn', 'url'=>'https://www.linkedin.com/psettings/guest-controls/retargeting-opt-out'),
        )),

        // 5. Ontvangers (JSON)
        'pv_ontvangers'         => json_encode( array(
            array('partij'=>'Hostingprovider',                  'doel'=>'Websitehosting en serverlogbestanden', 'locatie'=>'Nederland'),
            array('partij'=>'Google LLC (Analytics/Tag Manager)','doel'=>'Cookiebeheer en analytics',          'locatie'=>'VS*'),
        )),

        // 6. Internationale doorgifte — vrije tekst (optioneel)
        'pv_doorgifte'          => '',

        // 7. Bewaartermijnen — vrije aanvulling
        'pv_bewaar_contact'     => '3 jaar na laatste contact',
        'pv_bewaar_logs'        => 'maximaal 6 maanden',
        'pv_bewaar_analytics'   => 'Zie sectie 4 (per cookie)',
        'pv_bewaar_nieuwsbrief' => '',

        // 8. Rechten — contactemail en reactietermijn
        'pv_rechten_email'      => '',
        'pv_rechten_termijn'    => 'één maand',

        // 10. Klachten — AP-adres tonen (checkbox)
        'pv_ap_tonen'           => '1',

        // 11. Wijzigingen — vrije aanvulling
        'pv_wijzigingen_extra'  => '',

        // 12. Geautomatiseerde besluitvorming (Art. 22 AVG)
        'pv_profilering_enabled' => '0',
        'pv_profilering_tekst'   => '',
    );
}
