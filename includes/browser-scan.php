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

/**
 * Scanmodus van dit verzoek: 'full' (alles geaccepteerd), 'fresh' (nieuwe bezoeker
 * zonder keuze, voor de controle vóór toestemming) of '' (geen scan). Alleen met
 * een geldige code én als ingelogde beheerder. Wordt op 'init' vastgelegd, want
 * daarna meldt de scan de beheerder af voor de rest van het verzoek.
 */
function cm_browser_scan_mode() {
    if ( isset( $GLOBALS['cm_browser_scan_mode'] ) ) return $GLOBALS['cm_browser_scan_mode'];
    $token = isset( $_GET['cm_browser_scan'] ) && is_string( $_GET['cm_browser_scan'] ) ? $_GET['cm_browser_scan'] : '';
    if ( $token === '' ) return '';
    if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) return '';
    if ( ! current_user_can( 'manage_options' ) ) return '';
    if ( ! wp_verify_nonce( $token, 'cm_browser_scan' ) ) return '';
    return isset( $_GET['cm_scan_fresh'] ) && $_GET['cm_scan_fresh'] === '1' ? 'fresh' : 'full';
}

/** Scan met alles geaccepteerd: geen blokkering, geen banner, consent granted. */
function cm_is_browser_scan() {
    return cm_browser_scan_mode() === 'full';
}

/**
 * Scanmodus: nooit cachen, niet indexeren, en de rest van het verzoek als
 * niet-ingelogde bezoeker (plugins die beheerders overslaan, zoals sommige
 * analyticsplugins, geven hun tags dan gewoon mee; geen adminbalk).
 */
add_action( 'init', 'cm_browser_scan_prepare', 0 );
function cm_browser_scan_prepare() {
    if ( is_admin() || wp_doing_ajax() ) return;
    $mode = cm_browser_scan_mode();
    if ( $mode === '' ) return;
    $GLOBALS['cm_browser_scan_mode'] = $mode;
    if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
    do_action( 'litespeed_control_set_nocache', 'Cookiebaas browserscan' );
    nocache_headers();
    if ( ! headers_sent() ) {
        header( 'X-Robots-Tag: noindex, nofollow' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' ); // scancode niet in de referrer naar derden
    }
    wp_set_current_user( 0 );
}

/**
 * Als eerste in de head: de scancode uit de adresbalk van de iframe halen, zodat
 * statistieken (GA4, Meta) het gewone paginadres zien, en meer geladen adressen
 * bewaren (standaard 250) zodat laat geladen tags niet uit de meting vallen.
 * Als nieuwe bezoeker ziet de pagina bovendien de eigen keuze van de beheerder
 * (cc_cm_consent) niet en kan ze die niet overschrijven; er wordt niets gelogd.
 */
add_action( 'wp_head', 'cm_browser_scan_head', -1001 );
function cm_browser_scan_head() {
    $mode = cm_browser_scan_mode();
    if ( $mode === '' ) return;
    $js = "performance.setResourceTimingBufferSize(3000);var u=new URL(location.href);u.searchParams.delete('cm_browser_scan');u.searchParams.delete('cm_scan_fresh');history.replaceState(history.state,'',u.toString());";
    if ( $mode === 'fresh' ) {
        $js .= "window.cmScanFresh=true;var d=Object.getOwnPropertyDescriptor(Document.prototype,'cookie');"
             . "if(d&&d.get&&d.set)Object.defineProperty(document,'cookie',{configurable:true,"
             . "get:function(){return d.get.call(document).split(/;\\s*/).filter(function(c){return c.indexOf('cc_cm_consent=')!==0;}).join('; ');},"
             . "set:function(v){if(String(v).trim().indexOf('cc_cm_consent=')!==0)d.set.call(document,v);}});";
    }
    echo '<script data-no-defer="1">try{' . $js . "}catch(e){}</script>\n";
}
