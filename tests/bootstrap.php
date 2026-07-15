<?php
/**
 * Gedeelde testbootstrap voor Cookiebaas.
 *
 * De plugin heeft bewust geen build-systeem (geen composer, geen PHPUnit).
 * Deze bootstrap levert daarom een minimale, zelfstandige testomgeving:
 *   - stubs voor de WordPress-functies die de plugin aanroept
 *   - een lichte hook-emulatie (add_action / do_action / update_option), zodat
 *     de option-hooks van de plugin (o.a. de cache-flush) echt kunnen vuren
 *   - een kleine assertie-helper (cm_assert / cm_test_summary)
 *
 * Elk testbestand draait in zijn eigen PHP-proces (zie run.php): dat voorkomt
 * herdeclaratie-botsingen en houdt globale staat (constanten, opties) schoon.
 *
 * Alle stubs zijn met function_exists() omhuld, zodat een test desgewenst een
 * echt plugin-bestand kan requiren dat dezelfde functie definieert.
 */

error_reporting( E_ALL & ~E_DEPRECATED );

if ( ! defined( 'ABSPATH' ) )         define( 'ABSPATH', '/tmp/' );
if ( ! defined( 'CM_PLUGIN_ROOT' ) )  define( 'CM_PLUGIN_ROOT', dirname( __DIR__ ) );
if ( ! defined( 'DB_NAME' ) )         define( 'DB_NAME', 'test_db' );

// ---------------------------------------------------------------------------
// Configureerbare testtoestand (per test aan te passen vóór het requiren van
// de plugin-bestanden).
// ---------------------------------------------------------------------------
$GLOBALS['cm_test_options']    = array();   // option-store
$GLOBALS['cm_test_hooks']      = array();   // hook => [callbacks]
$GLOBALS['cm_test_is_admin']   = false;
$GLOBALS['cm_test_license_ok'] = true;

/** Zet cm_settings en leeg de interne cache (zodat cm_get() de nieuwe waarde ziet). */
function cm_test_set_settings( array $settings ) {
    $GLOBALS['cm_test_options']['cm_settings'] = $settings;
    if ( function_exists( 'cm_get_flush' ) ) cm_get_flush();
}

// ---------------------------------------------------------------------------
// Optie-store + minimale hook-emulatie
// ---------------------------------------------------------------------------
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $key, $default = false ) {
        return array_key_exists( $key, $GLOBALS['cm_test_options'] ) ? $GLOBALS['cm_test_options'][ $key ] : $default;
    }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $key, $value ) {
        $existed = array_key_exists( $key, $GLOBALS['cm_test_options'] );
        $GLOBALS['cm_test_options'][ $key ] = $value;
        do_action( ( $existed ? 'update_option_' : 'add_option_' ) . $key, $value );
        return true;
    }
}
if ( ! function_exists( 'add_option' ) ) {
    function add_option( $key, $value = '' ) {
        $GLOBALS['cm_test_options'][ $key ] = $value;
        do_action( 'add_option_' . $key, $value );
        return true;
    }
}
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $key ) {
        unset( $GLOBALS['cm_test_options'][ $key ] );
        do_action( 'delete_option_' . $key );
        return true;
    }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
        $GLOBALS['cm_test_hooks'][ $hook ][] = $cb;
        return true;
    }
}
if ( ! function_exists( 'do_action' ) ) {
    function do_action( $hook, ...$a ) {
        foreach ( $GLOBALS['cm_test_hooks'][ $hook ] ?? array() as $cb ) {
            if ( is_callable( $cb ) ) call_user_func_array( $cb, $a );
        }
    }
}
if ( ! function_exists( 'has_action' ) ) {
    function has_action( $hook, $cb = false ) { return ! empty( $GLOBALS['cm_test_hooks'][ $hook ] ); }
}
if ( ! function_exists( 'add_filter' ) )     { function add_filter() { return true; } }
if ( ! function_exists( 'apply_filters' ) )  { function apply_filters( $tag, $value ) { return $value; } }
if ( ! function_exists( 'remove_action' ) )  { function remove_action() { return true; } }
if ( ! function_exists( 'add_shortcode' ) )  { function add_shortcode() { return true; } }
if ( ! function_exists( 'shortcode_exists' ) ) { function shortcode_exists( $s ) { return false; } }

// ---------------------------------------------------------------------------
// Omgeving / escaping / diverse WP-helpers
// ---------------------------------------------------------------------------
if ( ! function_exists( 'is_admin' ) )       { function is_admin() { return (bool) $GLOBALS['cm_test_is_admin']; } }
if ( ! function_exists( 'esc_attr' ) )       { function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_html' ) )       { function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_js' ) )         { function esc_js( $s ) { return (string) $s; } }
if ( ! function_exists( 'esc_url' ) )        { function esc_url( $s ) { return (string) $s; } }
if ( ! function_exists( 'esc_url_raw' ) )    { function esc_url_raw( $s ) { return (string) $s; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $v ) { return json_encode( $v ); } }
if ( ! function_exists( 'wp_kses' ) )        { function wp_kses( $s, $a = array() ) { return $s; } }
if ( ! function_exists( 'wp_unslash' ) )     { function wp_unslash( $s ) { return is_string( $s ) ? stripslashes( $s ) : $s; } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'sanitize_email' ) ) { function sanitize_email( $s ) { return (string) $s; } }
if ( ! function_exists( 'home_url' ) )       { function home_url( $p = '' ) { return 'https://example.test' . $p; } }
if ( ! function_exists( 'site_url' ) )       { function site_url( $p = '' ) { return 'https://example.test' . $p; } }
if ( ! function_exists( 'admin_url' ) )      { function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; } }
if ( ! function_exists( 'get_bloginfo' ) )   { function get_bloginfo( $k = '' ) { return 'nl-NL'; } }
if ( ! function_exists( 'get_locale' ) )     { function get_locale() { return 'nl_NL'; } }
if ( ! function_exists( '__' ) )             { function __( $s, $d = null ) { return $s; } }
if ( ! function_exists( 'wp_create_nonce' ) ){ function wp_create_nonce( $a = '' ) { return 'testnonce'; } }
if ( ! function_exists( 'wp_upload_dir' ) )  { function wp_upload_dir() { return array( 'basedir' => '/tmp', 'baseurl' => 'https://example.test/up' ); } }
if ( ! function_exists( 'trailingslashit' ) ){ function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; } }
if ( ! function_exists( 'cm_license_is_valid' ) ) { function cm_license_is_valid() { return (bool) $GLOBALS['cm_test_license_ok']; } }

// ---------------------------------------------------------------------------
// Assertie-helpers
// ---------------------------------------------------------------------------
$GLOBALS['cm_test_pass'] = 0;
$GLOBALS['cm_test_fail'] = 0;

function cm_assert( $label, $cond ) {
    if ( $cond ) { $GLOBALS['cm_test_pass']++; echo "  \033[32mPASS\033[0m  $label\n"; }
    else         { $GLOBALS['cm_test_fail']++; echo "  \033[31mFAIL\033[0m  $label\n"; }
    return (bool) $cond;
}

/** Groepskop in de output. */
function cm_test_group( $title ) { echo "\n== $title ==\n"; }

/** Print samenvatting en geef exit-code terug (0 = alles goed). */
function cm_test_summary() {
    $p = $GLOBALS['cm_test_pass'];
    $f = $GLOBALS['cm_test_fail'];
    echo "\n" . ( $f ? "\033[31mRESULT: $f FAILED\033[0m" : "\033[32mRESULT: ALL PASS ($p)\033[0m" ) . "\n";
    return $f ? 1 : 0;
}
