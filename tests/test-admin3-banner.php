<?php
/**
 * Nieuwe admin (3.0) — pagina Banner.
 *
 * Borgt per tab dat de juiste instellingen er staan (spec §3.1), en de
 * randgevallen uit Review Focus 2: niets geselecteerd = leeg opgeslagen.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function get_pages() { return array( (object) array( 'ID' => 7, 'post_title' => 'Contact' ), (object) array( 'ID' => 12, 'post_title' => 'Bedankt' ) ); }
function absint( $v ) { return abs( (int) $v ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-banner.php';

function tab_keys( $tab ) {
    $keys = array();
    foreach ( cm_tabs_banner()[ $tab ]['sections'] as $s ) foreach ( isset( $s['fields'] ) ? $s['fields'] : array() as $f ) $keys[] = $f['key'];
    return $keys;
}

cm_test_group( 'Banner › Weergave' );
$w = tab_keys( 'weergave' );
foreach ( array( 'banner_position', 'banner_width_bottom_center', 'banner_width_center', 'banner_width_compact', 'banner_mobile_padding', 'prefs_cookie_detail', 'show_float_btn', 'float_btn_style', 'float_icon_size', 'cm_icon_type', 'float_icon_custom_svg', 'float_icon_image_url', 'float_position' ) as $k ) {
    cm_assert( "Weergave bevat $k", in_array( $k, $w, true ) );
}
cm_assert( 'icoontype: afbeelding wint van SVG', cm_banner_icon_type( array( 'float_icon_image_url' => 'x', 'float_icon_custom_svg' => '<svg/>' ) ) === 'image' );
cm_assert( 'icoontype: zonder beide standaard', cm_banner_icon_type( array() ) === 'default' );

cm_test_group( 'Banner › Gedrag' );
$g = tab_keys( 'gedrag' );
foreach ( array( 'analytics_default', 'expiry_months', 'respect_dnt', 'respect_gpc', 'reload_after_consent', 'geo_enabled', 'geo_outside_eu', 'exclude_login_page', 'exclude_woocommerce_checkout', 'exclude_page_ids', 'exclude_url_patterns', 'subdomain_sharing', 'subdomain_root_domain' ) as $k ) {
    cm_assert( "Gedrag bevat $k", in_array( $k, $g, true ) );
}

cm_test_group( 'Pagina-uitsluiting: niets gekozen = leeg' );
$index = cm_admin_field_index( 'cm_settings', array( 'cookiebaas-banner' => cm_tabs_banner() ) );
cm_assert( 'twee pagina\'s gekozen', cm_sanitize_field_value( $index['exclude_page_ids'], array( '', '7', '12' ), '' ) === '7,12' );
cm_assert( 'niets gekozen → leeg', cm_sanitize_field_value( $index['exclude_page_ids'], array( '' ), '7' ) === '' );
cm_assert( 'pagina-opties komen uit get_pages', cm_admin_field_options( $index['exclude_page_ids'] ) === array( 7 => 'Contact (ID 7)', 12 => 'Bedankt (ID 12)' ) );

exit( cm_test_summary() );
