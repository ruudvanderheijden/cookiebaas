<?php
/**
 * Nieuwe admin (3.0) — acties via admin-post.php.
 *
 * Borgt: een actieformulier post naar admin-post.php met eigen action en
 * nonce, vraagt om bevestiging als dat gevraagd is, en na afloop landt de
 * gebruiker op dezelfde pagina met precies één melding.
 */

function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { return '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function remove_query_arg( $keys, $url ) {
    $p = parse_url( $url ); parse_str( isset( $p['query'] ) ? $p['query'] : '', $q );
    foreach ( (array) $keys as $k ) unset( $q[ $k ] );
    return $p['scheme'] . '://' . $p['host'] . $p['path'] . ( $q ? '?' . http_build_query( $q ) : '' );
}
function add_query_arg( $key, $value, $url ) {
    return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';

cm_test_group( 'Actieformulier' );
$f = cm_admin_action_form( 'reset_theme', 'Herstellen', array( 'theme' => 'dark' ), 'Zeker weten?' );
cm_assert( 'post naar admin-post.php', strpos( $f, 'action="https://example.test/wp-admin/admin-post.php"' ) !== false );
cm_assert( 'eigen action en nonce', strpos( $f, 'name="action" value="cm_reset_theme"' ) !== false && strpos( $f, 'nonce-cm_reset_theme' ) !== false );
cm_assert( 'extra argumenten als hidden velden', strpos( $f, 'name="theme" value="dark"' ) !== false );
cm_assert( 'bevestiging op het formulier', strpos( $f, 'data-cm-confirm="Zeker weten?"' ) !== false );

cm_test_group( 'Terug naar de pagina met één melding' );
$ref = 'https://example.test/wp-admin/admin.php?page=cookiebaas-banner&tab=vormgeving&cm_notice=oud&settings-updated=true';
$to  = cm_admin_redirect_url( $ref, 'theme-reset-dark' );
cm_assert( 'pagina en tab blijven', strpos( $to, 'page=cookiebaas-banner' ) !== false && strpos( $to, 'tab=vormgeving' ) !== false );
cm_assert( 'oude melding en settings-updated weg, nieuwe erbij', substr_count( $to, 'cm_notice=' ) === 1 && strpos( $to, 'cm_notice=theme-reset-dark' ) !== false && strpos( $to, 'settings-updated' ) === false );
cm_assert( 'zonder referer naar het overzicht', strpos( cm_admin_redirect_url( false, '' ), 'page=cookiebaas' ) !== false );

cm_test_group( 'Meldingen' );
cm_assert( 'bekende code → succesmelding', strpos( cm_admin_notice_html( 'theme-reset-light' ), 'notice notice-success is-dismissible' ) !== false );
cm_assert( 'onbekende code → niets', cm_admin_notice_html( 'bestaat-niet' ) === '' );

exit( cm_test_summary() );
