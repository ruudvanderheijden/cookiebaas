<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   BROWSERSCAN (3.1) — scanmodus op de website.
   De admin laadt pagina's in een verborgen iframe met ?cm_browser_scan=<code>.
   Alleen voor een ingelogde beheerder met een geldige code gedraagt de pagina
   zich alsof er "alles accepteren" is gekozen: geen blokkering, geen banner,
   niets gelogd, en nooit in de paginacache. Zo zien we welke cookies en
   diensten er echt laden, ook via GTM. Een bezoeker kan dit nooit activeren.
================================================================ */

/** Persoonlijke scancode voor de ingelogde beheerder (WordPress-nonce, dus gebonden aan gebruiker en sessie). */
function cm_browser_scan_token() {
    return wp_create_nonce( 'cm_browser_scan' );
}

/** Staat dit verzoek in scanmodus? Alleen met een geldige code én als ingelogde beheerder. */
function cm_is_browser_scan() {
    $token = isset( $_GET['cm_browser_scan'] ) && is_string( $_GET['cm_browser_scan'] ) ? $_GET['cm_browser_scan'] : '';
    if ( $token === '' ) return false;
    if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) return false;
    if ( ! current_user_can( 'manage_options' ) ) return false;
    return (bool) wp_verify_nonce( $token, 'cm_browser_scan' );
}

/** Scanmodus: nooit cachen, niet indexeren, geen adminbalk in de iframe. */
add_action( 'template_redirect', 'cm_browser_scan_prepare', 0 );
function cm_browser_scan_prepare() {
    if ( ! cm_is_browser_scan() ) return;
    if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
    do_action( 'litespeed_control_set_nocache', 'Cookiebaas browserscan' );
    nocache_headers();
    header( 'X-Robots-Tag: noindex, nofollow' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' ); // scancode nooit in de referrer naar derden
    show_admin_bar( false );
}
