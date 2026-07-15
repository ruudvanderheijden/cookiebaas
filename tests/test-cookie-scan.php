<?php
/**
 * Cookiescan — kennisbank, prefix-matcher en omgevingsdetectie.
 *
 * Borgt de detectie-reparaties uit v1.7.2 en v1.7.4:
 *   - prefix-matcher herkent patronen op _ én - (wp-settings- was dood)
 *   - Google-cookies op google.com (NID / __Secure-ENID / __Secure-BUCKET)
 *   - omgevingscookies die een anonieme crawl niet kan zien (login, reacties,
 *     wachtwoordposts, WooCommerce Order Attribution, LiteSpeed)
 *
 * One-way-door globals (class WooCommerce, constante LSCWP_V) worden bewust als
 * laatste gedefinieerd, zodat de "afwezig"-assertions eerst kunnen draaien.
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin.php';

// --- Prefix-matcher --------------------------------------------------------
cm_test_group( 'Prefix-matcher (_ en -)' );
cm_assert( 'wp-settings-3 matcht wp-settings- (trailing -)', cm_cookie_prefix_match( 'wp-settings-3', 'wp-settings-' ) );
cm_assert( 'wordpress_logged_in_abc matcht wordpress_logged_in_', cm_cookie_prefix_match( 'wordpress_logged_in_abc123', 'wordpress_logged_in_' ) );
cm_assert( 'sbjs_first matcht sbjs_', cm_cookie_prefix_match( 'sbjs_first', 'sbjs_' ) );
cm_assert( 'naam zonder trailing _/- matcht niet als prefix', ! cm_cookie_prefix_match( 'NID_extra', 'NID' ) );

// --- Kennisbank ------------------------------------------------------------
cm_test_group( 'Kennisbank (fallback + reparaties)' );
$fb = cm_fallback_cookies();
cm_assert( '__Secure-ENID als YouTube/marketing', isset( $fb['__Secure-ENID'] ) && $fb['__Secure-ENID'][0] === 'marketing' && $fb['__Secure-ENID'][1] === 'YouTube' );
cm_assert( '__Secure-BUCKET aanwezig', isset( $fb['__Secure-BUCKET'] ) );
cm_assert( 'NID aanwezig', isset( $fb['NID'] ) );
cm_assert( 'wordpress_sec_ (met underscore) aanwezig', isset( $fb['wordpress_sec_'] ) );
cm_assert( 'wp-postpass_ aanwezig', isset( $fb['wp-postpass_'] ) );
cm_assert( 'sbjs_ als analytics', isset( $fb['sbjs_'] ) && $fb['sbjs_'][0] === 'analytics' );
cm_assert( 'comment_author_ aanwezig', isset( $fb['comment_author_'] ) );

$sig = cm_script_signatures();
$yt  = array_map( function( $e ) { return $e[0]; }, $sig['youtube.com/embed'] );
cm_assert( 'youtube.com/embed signature bevat __Secure-ENID', in_array( '__Secure-ENID', $yt, true ) );
cm_assert( 'youtube.com/embed signature bevat NID + __Secure-BUCKET', in_array( 'NID', $yt, true ) && in_array( '__Secure-BUCKET', $yt, true ) );

// --- Service-mapping -------------------------------------------------------
cm_test_group( 'Service-mapping' );
$svc = cm_service_for_cookie( '__Secure-ENID' );
cm_assert( '__Secure-ENID mapt naar YouTube', $svc && $svc['service'] === 'YouTube' );

// --- Omgevingsdetectie: minimale installatie (geen woo/litespeed) ----------
cm_test_group( 'Omgevingsdetectie — minimale site' );
$c = cm_server_env_cookies( false );
cm_assert( 'wordpress_test_cookie altijd aanwezig', isset( $c['wordpress_test_cookie'] ) );
cm_assert( 'wordpress_logged_in_ altijd aanwezig', isset( $c['wordpress_logged_in_'] ) );
cm_assert( 'wordpress_sec_ altijd aanwezig', isset( $c['wordpress_sec_'] ) );
cm_assert( '_lscache_vary AFWEZIG zonder LiteSpeed', ! isset( $c['_lscache_vary'] ) );
cm_assert( 'comment_author_ afwezig bij gesloten reacties', ! isset( $c['comment_author_'] ) );

cm_test_group( 'Omgevingsdetectie — LiteSpeed via response-header' );
$c2 = cm_server_env_cookies( true );
cm_assert( '_lscache_vary aanwezig via x-litespeed-cache header', isset( $c2['_lscache_vary'] ) && $c2['_lscache_vary'][0] === 'functional' );

// --- One-way-door globals: WooCommerce + reacties + wachtwoordpost ---------
class WooCommerce {}
$GLOBALS['cm_test_options']['woocommerce_feature_order_attribution_enabled'] = 'yes';
if ( ! function_exists( 'get_default_comment_status' ) ) {
    function get_default_comment_status() { return 'open'; }
}
class CM_Test_WPDB { public $posts = 'wp_posts'; public function get_var( $q ) { return 123; } }
$GLOBALS['wpdb'] = new CM_Test_WPDB();

cm_test_group( 'Omgevingsdetectie — WooCommerce + reacties + wachtwoordpost' );
$c = cm_server_env_cookies( false );
cm_assert( 'comment_author_ bij open reacties', isset( $c['comment_author_'] ) );
cm_assert( 'wp-postpass_ bij wachtwoordbeveiligde post', isset( $c['wp-postpass_'] ) );
cm_assert( 'wp_woocommerce_session_ bij WooCommerce', isset( $c['wp_woocommerce_session_'] ) );
cm_assert( 'sbjs_current (Order Attribution) als analytics', isset( $c['sbjs_current'] ) && $c['sbjs_current'][0] === 'analytics' );
cm_assert( 'sbjs_session aanwezig', isset( $c['sbjs_session'] ) );

exit( cm_test_summary() );
