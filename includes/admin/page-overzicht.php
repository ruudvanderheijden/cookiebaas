<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   OVERZICHT — statusblokken en de compliance-check. De pagina is actueel
   bij het laden; "Opnieuw controleren" is niet meer nodig.
================================================================ */

function cm_tabs_overzicht() {
    return array(
        'overzicht' => array( 'label' => 'Overzicht', 'render' => 'cm_admin_render_overzicht' ),
    );
}

/** Gegevens voor de statusblokken (database en options). */
function cm_overzicht_data() {
    global $wpdb;
    $table = cm_log_table();
    $stats = $wpdb->get_row(
        "SELECT SUM(CASE WHEN method IN ('accept-all','embed-accept','geo-auto') THEN 1 ELSE 0 END) AS accept,
                SUM(CASE WHEN method IN ('reject-all','dnt','gpc') THEN 1 ELSE 0 END) AS reject,
                SUM(CASE WHEN method = 'custom' THEN 1 ELSE 0 END) AS custom
         FROM `{$table}` WHERE created_at >= NOW() - INTERVAL 30 DAY AND method != 'pageload'",
        ARRAY_A
    );
    $stats   = is_array( $stats ) ? $stats : array();
    $managed = array_filter( cm_get_cookie_list(), function ( $c ) { return empty( $c['builtin'] ); } );
    list( $type, $word ) = cm_license_summary( cm_license_get(), cm_license_is_valid() );
    return array(
        'license'    => $word,
        'license_ok' => $type === 'success',
        'cookies'    => count( $managed ),
        'last_scan'  => (string) get_option( 'cm_auto_scan_last', '' ),
        'accept'     => isset( $stats['accept'] ) ? (int) $stats['accept'] : 0,
        'reject'     => isset( $stats['reject'] ) ? (int) $stats['reject'] : 0,
        'custom'     => isset( $stats['custom'] ) ? (int) $stats['custom'] : 0,
        'version'    => (int) get_option( 'cm_consent_version', 1 ),
    );
}

/** Statusblokken: array( titel, waarde, toelichting, url, linktekst, status ok|warn|'' ). */
function cm_overzicht_cards( array $d ) {
    $scan = $d['last_scan'] !== ''
        ? 'Laatste automatische scan: ' . wp_date( 'j F Y', strtotime( $d['last_scan'] . ' UTC' ) ) . '.'
        : 'Nog geen automatische scan.';
    return array(
        array( 'Licentie', $d['license'], $d['license_ok'] ? 'Alle functies zijn beschikbaar.' : 'Banner, blokkering en handmatige scan werken; voor de premiumfuncties is een licentie nodig.', cm_admin_page_url( 'cookiebaas-beheer', 'licentie' ), 'Licentie beheren', $d['license_ok'] ? 'ok' : 'warn' ),
        array( 'Cookies', $d['cookies'] === 1 ? '1 cookie' : $d['cookies'] . ' cookies', $scan, cm_admin_page_url( 'cookiebaas-cookies', 'lijst' ), 'Cookielijst bekijken', $d['cookies'] > 0 ? 'ok' : 'warn' ),
        array( 'Toestemmingen', (string) ( $d['accept'] + $d['reject'] + $d['custom'] ), 'Laatste 30 dagen: ' . $d['accept'] . ' akkoord, ' . $d['reject'] . ' geweigerd, ' . $d['custom'] . ' aangepast.', cm_admin_page_url( 'cookiebaas-log', 'registraties' ), 'Consent log openen', '' ),
        array( 'Consent-versie', (string) $d['version'], 'Verhoog de versie om iedereen opnieuw te laten kiezen.', cm_admin_page_url( 'cookiebaas-log', 'bewaren' ), 'Opnieuw laten kiezen', '' ),
    );
}

/** De controles uit 2.4, met dezelfde logica en links naar de nieuwe tabs. */
function cm_compliance_checks( array $s, array $pv, array $cookies ) {
    $luminance = function ( $hex ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( strlen( $hex ) === 3 ) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        if ( strlen( $hex ) !== 6 ) return 0.5;
        return 0.299 * hexdec( substr( $hex, 0, 2 ) ) / 255 + 0.587 * hexdec( substr( $hex, 2, 2 ) ) / 255 + 0.114 * hexdec( substr( $hex, 4, 2 ) ) / 255;
    };
    $get        = function ( $k ) use ( $s ) { return isset( $s[ $k ] ) ? trim( (string) $s[ $k ] ) : ''; };
    $reject_lum = $luminance( $get( 'color_reject_bg' ) !== '' ? $get( 'color_reject_bg' ) : 'f5f2ee' );
    $accept_lum = $luminance( $get( 'color_accept_bg' ) !== '' ? $get( 'color_accept_bg' ) : '111111' );
    $prominence = $reject_lum < 0.5 || abs( $reject_lum - $accept_lum ) < 0.45;
    $body       = $get( 'txt_banner_body' );
    $has_link   = strpos( $body, 'href' ) !== false || strpos( $body, 'privac' ) !== false;
    $self_load  = preg_match( '/^G-[A-Z0-9]+$/i', $get( 'ga4_measurement_id' ) ) || preg_match( '/^GTM-[A-Z0-9]+$/i', $get( 'gtm_container_id' ) ) || preg_match( '/^UA-[0-9]+-[0-9]+$/i', $get( 'ua_tracking_id' ) );
    $blocking   = $get( 'block_analytics_patterns' ) !== '' || $get( 'block_marketing_patterns' ) !== '' || $self_load;
    $expiry     = (int) ( $get( 'expiry_months' ) !== '' ? $get( 'expiry_months' ) : 12 );
    $retention  = (int) $get( 'log_retention_months' );
    $cats_ok    = $get( 'txt_cat1_long' ) !== '' && $get( 'txt_cat2_long' ) !== '' && $get( 'txt_cat3_long' ) !== '';
    $managed    = array_filter( $cookies, function ( $c ) { return empty( $c['builtin'] ); } );
    $incomplete = array_filter( $managed, function ( $c ) { return empty( $c['purpose'] ) || empty( $c['duration'] ); } );

    $banner  = function ( $tab ) { return cm_admin_page_url( 'cookiebaas-banner', $tab ); };
    $block   = function ( $tab ) { return cm_admin_page_url( 'cookiebaas-blokkering', $tab ); };
    $privacy = cm_admin_page_url( 'cookiebaas-privacy' );
    $checks  = array();
    $add = function ( $group, $title, $desc, $ref, $status, $url, $label, $detail = '' ) use ( &$checks ) {
        $checks[] = compact( 'group', 'title', 'desc', 'ref', 'status', 'url', 'label', 'detail' );
    };

    $g = 'AP-vuistregels voor cookiebanners';
    $add( $g, '1. Weigeren is even makkelijk als accepteren', 'De banner heeft een knop “Akkoord” én een knop “Weigeren” op dezelfde laag, even opvallend. Weigeren kost geen extra klik.', 'AP-vuistregel 1 · AVG art. 7 lid 3 · EDPB 05/2020', $prominence ? 'ok' : 'warn', $banner( 'vormgeving' ), 'Knopkleuren aanpassen', $prominence ? '' : 'De weigerknop is veel lichter dan de akkoordknop.' );
    $add( $g, '2. Geen vooraf aangevinkte keuzes', 'Analytische en marketingcookies staan in het voorkeurenvenster standaard uit. Toestemming vraagt een actieve handeling (opt-in).', 'AP-vuistregel 2 · HvJEU Planet49 (C-673/17)', empty( $s['analytics_default'] ) ? 'ok' : 'fail', $banner( 'gedrag' ), 'Standaardkeuzes aanpassen' );
    $add( $g, '4. Duidelijke uitleg over het doel', 'Het voorkeurenvenster toont per categorie een beschrijving, en per dienst of cookie het doel, de looptijd en de aanbieder.', 'AP-vuistregel 4 · AVG art. 13 · Tw art. 11.7a lid 1', $cats_ok ? 'ok' : 'warn', $banner( 'teksten' ), 'Teksten aanvullen', $cats_ok ? '' : 'Niet alle cookiecategorieën hebben een beschrijving.' );
    $add( $g, '7. Intrekken is even makkelijk als geven', 'Een zweefknop op elke pagina opent de cookievoorkeuren opnieuw, met dezelfde knoppen. Bij intrekken worden de cookies actief verwijderd.', 'AP-vuistregel 7 · AVG art. 7 lid 3 · AP-normuitleg 2024', ! empty( $s['show_float_btn'] ) ? 'ok' : 'fail', $banner( 'weergave' ), 'Zweefknop inschakelen', ! empty( $s['show_float_btn'] ) ? '' : 'De zweefknop staat uit: bezoekers kunnen hun toestemming niet makkelijk intrekken.' );
    $add( $g, '8. Geen misleidend ontwerp', 'De knoppen zijn even groot en hebben dezelfde stijl. Geen kleurverschil dat akkoord bevoordeelt, geen verwarrende teksten of dubbele ontkenningen.', 'AP-vuistregel 8 · EDPB-richtlijnen 3/2022', $prominence ? 'ok' : 'warn', $banner( 'vormgeving' ), 'Knopkleuren aanpassen' );
    $add( $g, '9. Link naar de privacyverklaring', 'De bannertekst linkt naar de privacyverklaring, zodat bezoekers zich vóór hun keuze kunnen informeren.', 'AP-vuistregel 9 · AVG art. 13/14', $has_link ? 'ok' : 'warn', $banner( 'teksten' ), 'Bannertekst aanpassen' );

    $g = 'Techniek';
    $pre_check = get_option( 'cm_preconsent_check', array() );
    list( $pre_status, $pre_detail ) = cm_preconsent_status( $pre_check, $blocking );
    $pre_note = is_array( $pre_check ) && ! empty( $pre_check['date'] ) ? ' Laatste meting: ' . (int) $pre_check['pages'] . ' pagina’s op ' . wp_date( 'j F Y', strtotime( $pre_check['date'] . ' UTC' ) ) . '.' : '';
    $add( $g, 'Niets vóór toestemming', 'Gemeten met de browserscan: bij een nieuwe bezoeker die nog niets koos, plaatst of laadt de site niets waarvoor toestemming nodig is. De blokkering werkt in drie lagen: de output-buffer in PHP, een MutationObserver in JavaScript en Google Consent Mode v2.' . $pre_note, 'Tw art. 11.7a · ePrivacyrichtlijn', $pre_status, cm_admin_page_url( 'cookiebaas-cookies', 'scannen' ), 'Browserscan uitvoeren', $pre_detail );
    $add( $g, 'Embeds geblokkeerd vóór toestemming', 'YouTube, Vimeo, Google Maps, Spotify, TikTok en meer worden automatisch geblokkeerd en vervangen door een placeholder.', 'Tw art. 11.7a · AP-standpunt over ingesloten content', ! empty( $s['embed_blocker_enabled'] ) ? 'ok' : 'warn', $block( 'embeds' ), 'Embedblokkering inschakelen' );
    $add( $g, 'Google Consent Mode v2', 'Automatische koppeling met GA4 en GTM: standaard “denied”, na toestemming “granted”.', 'Google EU User Consent Policy · Digital Markets Act', $self_load ? 'ok' : 'warn', $block( 'google' ), 'GA4- of GTM-ID invullen', $self_load ? '' : 'Vul een GA4- of GTM-ID in voor automatische Consent Mode v2.' );
    $add( $g, 'Toestemming verloopt binnen 12 maanden', 'De toestemmingscookie verloopt na de ingestelde periode. De AP adviseert maximaal 12 maanden.', 'AP-handhavingscriteria · EDPB-aanbeveling', $expiry <= 12 ? 'ok' : 'warn', $banner( 'gedrag' ), 'Geldigheid aanpassen', $expiry <= 12 ? '' : 'Nu ingesteld: ' . $expiry . ' maanden.' );

    $g = 'Verantwoordingsplicht (AVG art. 5 lid 2)';
    $ret_ok = $retention > 0 && $retention <= 36;
    $add( $g, 'Registraties worden automatisch opgeschoond', 'Registraties in de consent log worden dagelijks verwijderd na de ingestelde bewaartermijn.', 'AVG art. 5 lid 1e (opslagbeperking)', $ret_ok ? 'ok' : 'warn', cm_admin_page_url( 'cookiebaas-log', 'bewaren' ), 'Bewaartermijn instellen', $retention === 0 ? 'Registraties worden nooit automatisch verwijderd.' : ( $retention > 36 ? 'Overweeg een kortere bewaartermijn.' : '' ) );
    $last_scan = (string) get_option( 'cm_browser_scan_last', '' );
    $scan_age  = $last_scan !== '' ? ( time() - strtotime( $last_scan . ' UTC' ) ) / DAY_IN_SECONDS : null;
    $add( $g, 'Cookielijst recent gecontroleerd', 'De browserscan is in de afgelopen drie maanden gedraaid, zodat de cookielijst past bij wat de site nu echt plaatst.', 'AVG art. 5 lid 2 · Tw art. 11.7a lid 1', $scan_age !== null && $scan_age <= 90 ? 'ok' : 'warn', cm_admin_page_url( 'cookiebaas-cookies', 'scannen' ), 'Browserscan uitvoeren', $scan_age === null ? 'De browserscan is nog niet gedraaid.' : 'De laatste browserscan is van ' . wp_date( 'j F Y', strtotime( $last_scan . ' UTC' ) ) . '.' );
    $cookie_ok = count( $managed ) > 0 && count( $incomplete ) === 0;
    $add( $g, 'Elke cookie heeft een doel en looptijd', 'Per cookie staan het doel en de bewaartermijn in het voorkeurenvenster.', 'AVG art. 13 · Tw art. 11.7a lid 1', $cookie_ok ? 'ok' : 'warn', cm_admin_page_url( 'cookiebaas-cookies', 'lijst' ), 'Cookielijst aanvullen', count( $managed ) === 0 ? 'Er staan nog geen eigen cookies in de lijst.' : ( count( $incomplete ) > 0 ? count( $incomplete ) . ' cookie(s) missen een doel of looptijd.' : '' ) );

    $g = 'Privacyverklaring';
    $add( $g, 'Bedrijfsnaam ingevuld', 'De verwerkingsverantwoordelijke staat duidelijk in de privacyverklaring.', 'AVG art. 13 lid 1a', ! empty( $pv['pv_bedrijfsnaam'] ) ? 'ok' : 'fail', $privacy, 'Privacyverklaring aanvullen' );
    $add( $g, 'Contactgegevens ingevuld', 'Contactgegevens zijn verplicht, zodat betrokkenen hun rechten kunnen uitoefenen.', 'AVG art. 13 lid 1a', ! empty( $pv['pv_email'] ) ? 'ok' : 'fail', $privacy, 'Privacyverklaring aanvullen' );
    $add( $g, 'Datum van bijwerken ingevuld', 'Bezoekers zien wanneer de privacyverklaring voor het laatst is bijgewerkt.', 'AVG art. 13 · transparantiebeginsel', ! empty( $pv['pv_datum'] ) ? 'ok' : 'warn', $privacy, 'Datum invullen' );

    return $checks;
}

/**
 * Status van "Niets vóór toestemming": de laatste meting van de browserscan gaat
 * voor; zonder meting (of na drie maanden) valt hij terug op de instellingen.
 * @return array( status, toelichting )
 */
function cm_preconsent_status( $check, $blocking ) {
    if ( ! is_array( $check ) || empty( $check['date'] ) ) {
        return array( 'warn', 'Nog niet gemeten: draai de browserscan.' . ( $blocking ? '' : ' Er is ook geen GA4- of GTM-ID en geen blokkeerpatroon ingesteld.' ) );
    }
    $when = wp_date( 'j F Y', strtotime( $check['date'] . ' UTC' ) );
    $errors   = isset( $check['errors'] ) ? (int) $check['errors'] : 0;
    $warnings = isset( $check['warnings'] ) ? (int) $check['warnings'] : 0;
    if ( $errors > 0 ) return array( 'fail', 'Bij de meting van ' . $when . ' gebeurde er ' . $errors . ' keer iets vóór toestemming waarvoor toestemming nodig is.' );
    if ( $warnings > 0 ) return array( 'warn', 'Bij de meting van ' . $when . ' laadde de site ' . $warnings . ' onbekende cookie(s) of dienst(en) vóór toestemming: controleer ze.' );
    if ( ( time() - strtotime( $check['date'] . ' UTC' ) ) / DAY_IN_SECONDS > 90 ) return array( 'warn', 'De laatste meting is van ' . $when . '. Draai de browserscan opnieuw.' );
    return array( 'ok', '' );
}

function cm_render_compliance_table( array $checks ) {
    $status = array(
        'ok'   => array( 'yes-alt', 'In orde' ),
        'warn' => array( 'warning', 'Aandacht nodig' ),
        'fail' => array( 'dismiss', 'Niet in orde' ),
    );
    $group = null;
    echo '<table class="widefat striped cm-checks"><tbody>';
    foreach ( $checks as $c ) {
        if ( $c['group'] !== $group ) {
            $group = $c['group'];
            echo '<tr><th colspan="3" scope="colgroup"><strong>' . esc_html( $group ) . '</strong></th></tr>';
        }
        list( $icon, $label ) = $status[ $c['status'] ];
        echo '<tr class="cm-check-' . esc_attr( $c['status'] ) . '">';
        echo '<td class="cm-check-status"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span> ' . esc_html( $label ) . '</td>';
        echo '<td><strong>' . esc_html( $c['title'] ) . '</strong><br>' . esc_html( $c['desc'] );
        if ( $c['status'] !== 'ok' && $c['detail'] !== '' ) echo '<br><em>' . esc_html( $c['detail'] ) . '</em>';
        echo '<br><span class="description">' . esc_html( $c['ref'] ) . '</span></td>';
        echo '<td>' . ( $c['status'] !== 'ok' ? '<a href="' . esc_url( $c['url'] ) . '">Oplossen: ' . esc_html( $c['label'] ) . '</a>' : '' ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

/** Na de update naar 3.0 één keer laten zien dat de admin een nieuwe indeling heeft (niet bij een nieuwe installatie). */
function cm_flag_admin3_notice( $stored_version ) {
    if ( $stored_version !== '0' && version_compare( $stored_version, '3.0.0', '<' ) ) update_option( 'cm_show_admin3_notice', 1 );
}

function cm_render_admin3_welcome_notice() {
    if ( ! get_option( 'cm_show_admin3_notice' ) ) return;
    delete_option( 'cm_show_admin3_notice' );
    echo '<div class="notice notice-info is-dismissible"><p>De admin van Cookiebaas heeft een nieuwe indeling: elk onderwerp heeft nu een eigen menu-item. <a href="https://github.com/ruudvanderheijden/cookiebaas/blob/main/CHANGELOG.md#waar-staat-wat" target="_blank" rel="noopener">Waar staat wat?</a></p></div>';
}

/** Onbekende cookies uit de automatische scan: de beheerder kiest de categorie (of negeert ze). */
function cm_render_pending_cookies() {
    $pending = cm_auto_scan_pending();
    if ( ! $pending ) return;
    $n = count( $pending );
    echo '<div class="notice notice-warning inline cm-pending"><p><strong>'
       . esc_html( $n === 1 ? 'De automatische scan vond 1 cookie die Cookiebaas niet kent.' : 'De automatische scan vond ' . $n . ' cookies die Cookiebaas niet kent.' )
       . '</strong> Kies een categorie; pas dan komt de cookie in de cookielijst. Functioneel alleen als de site zonder deze cookie niet werkt; twijfelt u, kies dan Marketing, dan vraagt de banner altijd toestemming. Negeren: niet toevoegen en niet opnieuw melden.</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="cm_resolve_pending">';
    wp_nonce_field( 'cm_resolve_pending' );
    echo '<table class="widefat striped"><thead><tr><th scope="col">Cookie</th><th scope="col">Gevonden op</th><th scope="col">Categorie</th></tr></thead><tbody>';
    $options = array( '' => 'Kies…', 'functional' => 'Functioneel', 'analytics' => 'Analytisch', 'marketing' => 'Marketing', 'ignore' => 'Negeren' );
    $i = 0;
    foreach ( $pending as $name => $ck ) {
        $found = isset( $ck['found'] ) ? wp_date( 'j F Y', strtotime( $ck['found'] . ' UTC' ) ) : '';
        echo '<tr><td><code>' . esc_html( $name ) . '</code><input type="hidden" name="cm_pending_name[' . $i . ']" value="' . esc_attr( $name ) . '"></td>'
           . '<td>' . esc_html( $found ) . '</td><td><select name="cm_pending_cat[' . $i . ']" aria-label="' . esc_attr( 'Categorie voor ' . $name ) . '">';
        foreach ( $options as $v => $label ) echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $label ) . '</option>';
        echo '</select></td></tr>';
        $i++;
    }
    echo '</tbody></table><p><button type="submit" class="button button-primary">Opslaan</button></p></form></div>';
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'resolve_pending', function () {
        return cm_resolve_pending_cookies(
            isset( $_POST['cm_pending_name'] ) ? (array) wp_unslash( $_POST['cm_pending_name'] ) : array(),
            isset( $_POST['cm_pending_cat'] ) ? (array) wp_unslash( $_POST['cm_pending_cat'] ) : array()
        );
    } );
}

function cm_admin_render_overzicht() {
    cm_render_admin3_welcome_notice();
    cm_render_pending_cookies();
    echo '<div class="cm-cards">';
    foreach ( cm_overzicht_cards( cm_overzicht_data() ) as $card ) {
        echo '<div class="card' . ( $card[5] !== '' ? ' cm-card-' . esc_attr( $card[5] ) : '' ) . '"><h2 class="title">' . esc_html( $card[0] ) . '</h2>'
           . '<p><strong>' . esc_html( $card[1] ) . '</strong></p>'
           . '<p>' . esc_html( $card[2] ) . '</p>'
           . '<p><a href="' . esc_url( $card[3] ) . '">' . esc_html( $card[4] ) . '</a></p></div>';
    }
    echo '</div>';

    $checks = cm_compliance_checks( cm_get_settings(), cm_privacy_values(), cm_get_cookie_list() );
    // Zonder licentie is de privacyverklaring niet te bewerken: "Oplossen" wijst dan naar de licentie
    if ( cm_admin_require_license() !== '' ) {
        foreach ( $checks as &$c ) {
            if ( $c['group'] === 'Privacyverklaring' ) {
                $c['url']   = cm_admin_page_url( 'cookiebaas-beheer', 'licentie' );
                $c['label'] = 'licentie activeren om de verklaring te bewerken';
            }
        }
        unset( $c );
    }
    $ok     = count( array_filter( $checks, function ( $c ) { return $c['status'] === 'ok'; } ) );
    echo '<h2>Compliance-check</h2>';
    echo '<p>' . esc_html( $ok . ' van de ' . count( $checks ) . ' controles zijn in orde.' ) . ' Deze check is geen juridisch advies; lees de <a href="' . esc_url( cm_admin_page_url( 'cookiebaas-beheer', 'info' ) ) . '">disclaimer</a>.</p>';
    cm_render_compliance_table( $checks );
}
