<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   LIVE PREVIEW — de échte banner-markup (cm_banner_markup) met
   frontend.css in een iframe zonder scripts. De JS zet alleen
   CSS-variabelen en teksten. De variabelenkaart hieronder is getest
   tegen de echte frontend-output (tests/test-admin3-preview.php).
================================================================ */

/** [licht-sleutel, donker-sleutel|null, css-var, eenheid, waarde-als-leeg, donker-standaard-bij-0|null] */
function cm_preview_var_map() {
    $m = array(
        array( 'overlay_opacity', 'dm_overlay_opacity', '--cm-overlay-alpha', 'alpha', '', 75 ),
        array( 'radius_popup', 'dm_radius_popup', '--cm-popup-radius', 'px', '', 18 ),
        array( 'radius_btn', 'dm_radius_btn', '--cm-btn-radius', 'px', '', 6 ),
        array( 'banner_width_bottom_center', null, '--cm-banner-w-bottom', 'px', '', null ),
        array( 'banner_width_center', null, '--cm-banner-w-center', 'px', '', null ),
        array( 'banner_width_compact', null, '--cm-banner-w-compact', 'px', '', null ),
        array( 'color_always_on_bg', null, '--cm-always-on-bg', '', '', null ),
    );
    // Kleuren waarvan de variabelenaam het achtervoegsel volgt: color_<x> / dm_<x> → --cm-<var>
    $colours = array(
        'popup_bg' => 'popup-bg', 'title' => 'title-color', 'body' => 'body-color', 'link' => 'link-color',
        'accept_bg' => 'accept-bg', 'accept_text' => 'accept-text', 'accept_hover_bg' => 'accept-hover-bg', 'accept_hover_text' => 'accept-hover-text',
        'reject_bg' => 'reject-bg', 'reject_text' => 'reject-text', 'reject_hover_bg' => 'reject-hover-bg', 'reject_hover_text' => 'reject-hover-text',
        'prefs_border' => 'prefs-border', 'prefs_text' => 'prefs-text', 'prefs_hover_border' => 'prefs-hover-border', 'prefs_hover_text' => 'prefs-hover-text',
        'allowall_bg' => 'allowall-bg', 'allowall_text' => 'allowall-text', 'allowall_hover_bg' => 'allowall-hover-bg', 'allowall_hover_text' => 'allowall-hover-text',
        'outline_border' => 'outline-border', 'outline_text' => 'outline-text', 'outline_hover_border' => 'outline-hover-border', 'outline_hover_text' => 'outline-hover-text',
        'close_bg' => 'close-bg', 'close_hover_bg' => 'close-hover-bg', 'close_icon' => 'close-icon',
        'toggle_on' => 'toggle-on', 'toggle_off' => 'toggle-off', 'always_bg' => 'always-bg', 'always_on_color' => 'always-on-color',
        'badge_text' => 'badge-text', 'badge_bg' => 'badge-bg', 'badge_border' => 'badge-border',
        'service_name' => 'service-name-color', 'cookie_empty' => 'cookie-empty-color',
        'float_icon_bg' => 'float-icon-bg', 'float_icon_color' => 'float-icon-color', 'float_icon_hover_bg' => 'float-icon-hover-bg', 'float_icon_hover_color' => 'float-icon-hover-color',
        'float_text_bg' => 'float-text-bg', 'float_text_color' => 'float-text-color', 'float_text_border' => 'float-text-border', 'float_text_hover_bg' => 'float-text-hover-bg', 'float_text_hover_color' => 'float-text-hover-color',
        'cat_border' => 'cat-border', 'service_bg' => 'service-bg', 'cookie_item_bg' => 'cookie-item-bg', 'cat_header_hover' => 'cat-header-hover',
        'cat_desc' => 'cat-desc-color', 'cat_detail' => 'cat-detail-color', 'cookie_name' => 'cookie-name-color', 'cookie_meta' => 'cookie-meta-color',
        'expand_bg' => 'expand-bg', 'expand_icon' => 'expand-icon', 'expand_open_bg' => 'expand-open-bg', 'expand_open_icon' => 'expand-open-icon',
        'embed_bg' => 'embed-bg', 'embed_title' => 'embed-title', 'embed_body' => 'embed-body',
        'embed_btn_bg' => 'embed-btn-bg', 'embed_btn_text' => 'embed-btn-text', 'embed_btn_hover_bg' => 'embed-btn-hover-bg', 'embed_btn_hover_text' => 'embed-btn-hover-text',
    );
    foreach ( $colours as $suffix => $var ) {
        $m[] = array( 'color_' . $suffix, 'dm_' . $suffix, '--cm-' . $var, '', '', null );
    }
    // Optionele randen/achtergrond: leeg = transparant (zelfde als de frontend)
    foreach ( array( 'accept_border' => 'accept-border', 'reject_border' => 'reject-border', 'allowall_border' => 'allowall-border', 'outline_hover_bg' => 'outline-hover-bg' ) as $suffix => $var ) {
        $m[] = array( 'color_' . $suffix, 'dm_' . $suffix, '--cm-' . $var, '', 'transparent', null );
    }
    return $m;
}

function cm_preview_format( $v, $unit, $empty ) {
    if ( $v === '' || $v === null ) return $empty;
    if ( $unit === 'px' )    return intval( $v ) . 'px';
    if ( $unit === 'alpha' ) return (string) ( intval( $v ) / 100 );
    return (string) $v;
}

/** CSS-variabelen voor een thema, zoals de frontend ze effectief zet. */
function cm_preview_vars( array $s, $theme ) {
    $vars = array();
    foreach ( cm_preview_var_map() as $row ) {
        list( $light, $dark, $var, $unit, $empty, $dark_default ) = $row;
        $key = ( $theme === 'dark' && $dark ) ? $dark : $light;
        $v   = isset( $s[ $key ] ) ? $s[ $key ] : '';
        // Spiegelt de `?:` van de frontend (donker 0 of leeg wordt de standaard), zodat de preview de site toont; weg zodra de frontend dat niet meer doet.
        if ( $theme === 'dark' && $dark_default !== null && ! $v ) $v = $dark_default;
        $vars[ $var ] = cm_preview_format( $v, $unit, $empty );
    }
    return $vars;
}

/** Tekstveld → plek in de banner-markup. */
function cm_preview_text_map() {
    return array(
        'txt_banner_title'     => array( 'sel' => '#cm-banner-title', 'html' => false ),
        'txt_banner_body'      => array( 'sel' => '#cm-banner-desc', 'html' => true ),
        'txt_btn_prefs'        => array( 'sel' => '#cm-btn-prefs', 'html' => false ),
        'txt_btn_reject'       => array( 'sel' => '#cm-btn-reject', 'html' => false ),
        'txt_btn_accept'       => array( 'sel' => '#cm-btn-accept', 'html' => false ),
        'txt_prefs_title'      => array( 'sel' => '#cm-prefs-title-h2', 'html' => false ),
        'txt_prefs_body'       => array( 'sel' => '#cm-prefs-desc', 'html' => true ),
        'txt_btn_allowall'     => array( 'sel' => '#cm-allowall-btn', 'html' => false ),
        'txt_btn_rejectall'    => array( 'sel' => '#cm-rejectall-btn', 'html' => false ),
        'txt_btn_save'         => array( 'sel' => '#cm-save-btn', 'html' => false ),
        'txt_float_label'      => array( 'sel' => '#cm-float-btn', 'html' => false ),
        'txt_embed_title'      => array( 'sel' => '.cm-embed-title', 'html' => false ),
        'txt_embed_body'       => array( 'sel' => '.cm-embed-body', 'html' => true ),
        'txt_embed_accept_btn' => array( 'sel' => '.cm-embed-accept-btn', 'html' => false ),
        'txt_embed_prefs'      => array( 'sel' => '.cm-embed-prefs-link', 'html' => true ),
    );
}

function cm_admin_preview_assets() {
    $s = cm_get_settings();
    wp_enqueue_script( 'cm-admin-preview', CM_PLUGIN_URL . 'assets/js/admin-preview.js', array( 'cm-admin-common' ), CM_VERSION, true );
    wp_localize_script( 'cm-admin-preview', 'CM_PREVIEW', array(
        'vars'        => cm_preview_var_map(),
        'initial'     => array( 'light' => cm_preview_vars( $s, 'light' ), 'dark' => cm_preview_vars( $s, 'dark' ) ),
        'theme'       => cm_get( 'color_theme' ) === 'dark' ? 'dark' : 'light',
        'lang'        => cm_detect_lang(),
        'texts'       => cm_preview_text_map(),
        'frontendCss' => CM_PLUGIN_URL . 'assets/css/frontend.css?ver=' . CM_VERSION,
        'stageCss'    => CM_PLUGIN_URL . 'assets/css/preview-stage.css?ver=' . CM_VERSION,
    ) );
}

/** De preview-kolom naast het formulier. */
function cm_admin_render_preview( $tab ) {
    $views = array( 'banner' => 'Banner', 'prefs' => 'Voorkeuren', 'float' => 'Zweefknop', 'embed' => 'Video-placeholder' );
    echo '<div class="postbox cm-preview"><div class="postbox-header"><h2 class="hndle">Voorbeeld</h2></div><div class="inside">';
    cm_render_switch( 'view', $views, 'banner', 'Toon:' );
    cm_render_switch( 'device', array( 'desktop' => 'Desktop', 'mobile' => 'Mobiel' ), 'desktop', 'Scherm:' );
    echo '<div class="cm-preview-stage" inert><iframe id="cm-preview-frame" title="Voorbeeld van de banner" sandbox="allow-same-origin" tabindex="-1"></iframe></div>';
    echo '<p class="description">Past zich direct aan terwijl u kiest. Het voorbeeld is niet klikbaar.</p>';
    echo '<template id="cm-preview-markup">';
    cm_banner_markup();
    $info = array( 'service' => 'YouTube', 'category' => 'marketing' );
    $src  = 'https://www.youtube.com/embed/aqz-KE-bpKQ';
    echo '<div class="cm-stage-embed">' . cm_build_embed_placeholder( '<iframe src="' . esc_attr( $src ) . '" width="560" height="315"></iframe>', $src, $info ) . '</div>';
    echo '</template>';
    echo '</div></div>';
}
