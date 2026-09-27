<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   BEHEER — licentie, backup, geavanceerd (REST API), reset en info.
   Alle acties via admin-post.php; geen AJAX.
================================================================ */

function cm_tabs_beheer() {
    return array(
        'licentie'    => array( 'label' => 'Licentie', 'render' => 'cm_render_beheer_licentie' ),
        'backup'      => array( 'label' => 'Backup', 'render' => 'cm_render_beheer_backup' ),
        'geavanceerd' => array(
            'label'    => 'Geavanceerd',
            'render'   => 'cm_render_beheer_geavanceerd',
            // Alleen voor het register en de sanitizer: de sleutel wordt nooit via een
            // formulier gepost, alleen gezet door de acties Sleutel maken en Intrekken.
            'sections' => array( array( 'fields' => array(
                cm_field( 'api_key', 'custom', 'API-sleutel', array( 'sanitize' => 'cm_sanitize_api_key' ) ),
            ) ) ),
        ),
        'reset'       => array( 'label' => 'Reset', 'render' => 'cm_render_beheer_reset' ),
        'info'        => array( 'label' => 'Info', 'render' => 'cm_render_beheer_info' ),
    );
}

/** Licentiestatus: array( notice-type, woord, uitleg ), voor Beheer en Overzicht. */
function cm_license_summary( array $lic, $valid ) {
    if ( $valid ) return array( 'success', 'Actief', 'Alle functies zijn beschikbaar, ook ' . cm_premium_features_text() . '.' );
    if ( empty( $lic['key'] ) ) return array( 'warning', 'Geen licentie', 'De cookiebanner, de blokkering en de handmatige scan werken gewoon. Voor ' . cm_premium_features_text() . ' is een licentie nodig.' );
    $word = isset( $lic['status'] ) && $lic['status'] === 'expired' ? 'Verlopen' : 'Ongeldig';
    return array( 'warning', $word, 'De cookiebanner, de blokkering en de handmatige scan blijven werken. Voor ' . cm_premium_features_text() . ' is een geldige licentie nodig: verleng de licentie of activeer een andere sleutel.' );
}

/** Antwoord van de licentieserver → melding. Nooit "gelukt" zonder success. */
function cm_license_flash( array $result ) {
    if ( ! empty( $result['success'] ) && isset( $result['remote'] ) && $result['remote'] === false ) {
        cm_admin_flash( 'warning', ! empty( $result['message'] ) ? $result['message'] : 'Gelukt.' );
    } elseif ( ! empty( $result['success'] ) ) {
        cm_admin_flash( 'success', ! empty( $result['message'] ) ? $result['message'] : 'Gelukt.' );
    } else {
        cm_admin_flash( 'error', ! empty( $result['error'] ) ? $result['error'] : 'De licentieserver gaf geen bruikbaar antwoord. Probeer het later opnieuw.' );
    }
}

/**
 * Antwoord van cm_license_check_status() → melding. Zonder 'valid' in het
 * antwoord was de server niet bereikbaar: de status blijft dan ongewijzigd,
 * dus nooit een succesmelding tonen.
 */
function cm_license_check_notice( $result, $word ) {
    if ( is_array( $result ) && isset( $result['valid'] ) ) {
        return array( 'info', 'Status gecontroleerd: ' . $word . '.' );
    }
    $error = is_array( $result ) && ! empty( $result['error'] ) ? ': ' . $result['error'] : '';
    return array( 'error', 'De licentieserver was niet bereikbaar' . $error . '. De status is niet gewijzigd.' );
}

/** Activeren: een lege sleutel gaat niet naar de server. */
function cm_license_activate_request( $key ) {
    $key = trim( (string) $key );
    return $key === '' ? array( 'success' => false, 'error' => 'Vul een licentiesleutel in.' ) : cm_license_activate( $key );
}

function cm_render_beheer_licentie() {
    $lic = cm_license_get();
    list( $type, $word, $text ) = cm_license_summary( $lic, cm_license_is_valid() );
    echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p><strong>' . esc_html( $word ) . '.</strong> ' . esc_html( $text ) . '</p></div>';

    if ( ! empty( $lic['key'] ) ) {
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Licentiesleutel</th><td><code>' . esc_html( $lic['key'] ) . '</code></td></tr>';
        echo '<tr><th scope="row">Status</th><td>' . esc_html( $word );
        if ( ! empty( $lic['expires_at'] ) ) echo esc_html( ' — verloopt op ' . date_i18n( 'j F Y', strtotime( $lic['expires_at'] ) ) );
        echo '</td></tr>';
        echo '<tr><th scope="row">Domein</th><td><code>' . esc_html( ! empty( $lic['domain'] ) ? $lic['domain'] : cm_license_get_domain() ) . '</code></td></tr>';
        echo '<tr><th scope="row">Laatste controle</th><td>' . esc_html( ! empty( $lic['last_check'] ) ? wp_date( 'j F Y, H:i', $lic['last_check'] ) : 'Nog niet gecontroleerd' ) . '</td></tr>';
        echo '</tbody></table>';
        echo '<div>' . cm_admin_action_form( 'license_check', 'Status controleren' ) . ' '
           . cm_admin_action_form( 'license_deactivate', 'Deactiveren', array(), 'De licentie op deze website deactiveren? De consent log, de privacyverklaring-generator en de automatische scan pauzeren tot u opnieuw activeert.', 'button button-link-delete' ) . '</div>';
    }

    echo '<h2>' . esc_html( ! empty( $lic['key'] ) ? 'Andere sleutel activeren' : 'Licentie activeren' ) . '</h2>';
    $field = '<p><label for="cm-license-key">Licentiesleutel</label><br>'
           . '<input type="text" id="cm-license-key" name="license_key" class="regular-text code" placeholder="CB-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off" required></p>';
    echo '<div>' . cm_admin_action_form( 'license_activate', 'Activeren', array(), '', 'button button-primary', $field ) . '</div>';
}

/** Waarschuwing bij een ontbrekende of ongeldige licentie: alleen op de schermen van Cookiebaas. */
add_action( 'admin_notices', 'cm_admin_license_notice' );
function cm_admin_license_notice() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ! cm_admin_is_own_screen( $screen->id ) || cm_license_is_valid() ) return;
    // Op de tab Licentie zelf staat de status al
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
    if ( $page === 'cookiebaas-beheer' && in_array( $tab, array( '', 'licentie' ), true ) ) return;
    $lic  = cm_license_get();
    if ( empty( $lic['key'] ) ) {
        $text = 'Geen licentie geactiveerd. De cookiebanner, de blokkering en de handmatige scan werken gewoon; voor ' . cm_premium_features_text() . ' is een licentie nodig.';
        $link = 'Licentie activeren';
    } else {
        $text = 'Uw licentie is ' . ( isset( $lic['status'] ) && $lic['status'] === 'expired' ? 'verlopen' : 'ongeldig' ) . '. De cookiebanner, de blokkering en de handmatige scan blijven werken; ' . cm_premium_features_text() . ' zijn gepauzeerd tot u de licentie verlengt.';
        $link = 'Licentie beheren';
    }
    echo '<div class="notice notice-warning"><p><strong>Cookiebaas:</strong> ' . esc_html( $text ) . ' <a href="' . esc_url( cm_admin_page_url( 'cookiebaas-beheer', 'licentie' ) ) . '">' . esc_html( $link ) . '</a></p></div>';
}

/** Inhoud van de backup: alles wat de admin instelt, zonder API-sleutel en licentie. */
function cm_backup_payload() {
    $settings = get_option( 'cm_settings', cm_default_settings() );
    $settings = is_array( $settings ) ? $settings : array();
    unset( $settings['api_key'] );
    return array(
        '_meta'       => array(
            'plugin'   => 'cookiebaas',
            'version'  => CM_VERSION,
            'exported' => current_time( 'c' ),
            'site'     => get_bloginfo( 'url' ),
        ),
        'settings'    => $settings,
        'cookie_list' => get_option( 'cm_cookie_list', array() ),
        'privacy'     => get_option( 'cm_privacy', cm_default_privacy() ),
    );
}

/**
 * Backup terugzetten. Een ongeldig bestand schrijft niets. Alles gaat door
 * dezelfde sanitizers als opslaan; ongeldige waarden worden de standaard.
 * De huidige API-sleutel blijft staan: die zit niet in een backup.
 */
function cm_import_backup( $raw ) {
    $data = is_string( $raw ) && $raw !== '' ? json_decode( $raw, true ) : null;
    $meta = is_array( $data ) && isset( $data['_meta'] ) && is_array( $data['_meta'] ) ? $data['_meta'] : array();
    if ( ! isset( $meta['plugin'] ) || ! in_array( $meta['plugin'], array( 'cookiebaas', 'cookiemelding' ), true ) ) {
        return array( 'ok' => false, 'warning' => false, 'message' => 'Dit is geen backup van Cookiebaas. Er is niets gewijzigd.' );
    }
    // De API-sleutel zit nooit in een backup: een sleutel (leeg of gevuld) in
    // het bestand mag de huidige sleutel nooit overschrijven.
    if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
        unset( $data['settings']['api_key'] );
    }
    $has          = function ( $k ) use ( $data ) { return isset( $data[ $k ] ) && is_array( $data[ $k ] ); };
    $has_settings = $has( 'settings' ) && $data['settings'];
    $has_cookies  = $has( 'cookie_list' ); // een lege lijst is een geldige backup
    $has_privacy  = $has( 'privacy' ) && $data['privacy'];
    // De privacyverklaring bewerken is premium, ook via een backup
    $privacy_skipped = $has_privacy && cm_admin_require_license() !== '';
    if ( $privacy_skipped ) {
        $has_privacy = false;
        if ( ! $has_settings && ! $has_cookies ) {
            return array( 'ok' => false, 'warning' => false, 'message' => 'De privacyverklaring terugzetten vraagt een licentie. Er is niets gewijzigd.' );
        }
    }
    if ( ! $has_settings && ! $has_cookies && ! $has_privacy ) {
        return array( 'ok' => false, 'warning' => false, 'message' => 'De backup bevat geen instellingen, cookielijst of privacyverklaring. Er is niets gewijzigd.' );
    }

    // Eerst valideren, dan pas schrijven: een niet-lege cookielijst die na
    // sanitizing leeg is (alleen rommelrijen) wijst op een corrupt bestand.
    $cookie_list = array();
    if ( $has_cookies ) {
        $cookie_list = cm_sanitize_cookie_list( $data['cookie_list'] );
        if ( $data['cookie_list'] && ! $cookie_list ) {
            return array( 'ok' => false, 'warning' => false, 'message' => 'De cookielijst in de backup is ongeldig. Er is niets gewijzigd.' );
        }
    }

    $errors = function_exists( 'get_settings_errors' ) ? count( get_settings_errors() ) : 0;
    $done   = array();
    if ( $has_settings ) {
        $base = array_merge( cm_default_settings(), array( 'api_key' => (string) cm_get( 'api_key' ) ) );
        update_option( 'cm_settings', cm_sanitize_settings( $data['settings'], $base ) );
        $done[] = 'instellingen';
    }
    if ( $has_cookies ) {
        update_option( 'cm_cookie_list', $cookie_list );
        $done[] = count( $cookie_list ) === 1 ? '1 cookie' : count( $cookie_list ) . ' cookies';
    }
    if ( $has_privacy ) {
        update_option( 'cm_privacy', cm_sanitize_privacy( array_merge( cm_default_privacy(), $data['privacy'] ) ) );
        $done[] = 'privacyverklaring';
    }
    $message = 'Teruggezet: ' . implode( ', ', $done ) . '.';
    $warning = function_exists( 'get_settings_errors' ) && count( get_settings_errors() ) > $errors;
    if ( $warning ) {
        $message .= ' Sommige waarden in de backup waren ongeldig; daar staat nu de standaardwaarde.';
    }
    if ( $privacy_skipped ) {
        $warning  = true;
        $message .= ' De privacyverklaring is overgeslagen: die terugzetten vraagt een licentie.';
    }
    return array( 'ok' => true, 'warning' => $warning, 'message' => $message );
}

function cm_license_reset_local() {
    delete_option( 'cm_license_data' );
    delete_option( 'cm_license_api_url' );
}

/** Zet alles terug. Geeft de onderdelen terug die mislukten (leeg = alles gelukt). */
function cm_reset_everything() {
    $failed = array();
    update_option( 'cm_settings', cm_default_settings() );
    update_option( 'cm_cookie_list', array() );
    update_option( 'cm_privacy', cm_default_privacy() );
    if ( ! cm_log_clear() ) $failed[] = 'consent log';
    cm_bump_consent_version( 'Alles gereset' );
    cm_license_reset_local();
    return $failed;
}

function cm_render_beheer_backup() {
    echo '<h2>Backup maken</h2>';
    echo '<p>Download de instellingen, de cookielijst en de privacyverklaring als één JSON-bestand: als backup, of om over te zetten naar een andere website. De consent log, de cookiedatabase, de licentie en de API-sleutel gaan niet mee.</p>';
    echo '<p><a class="button button-primary" href="' . esc_url( cm_admin_action_url( 'export_backup' ) ) . '">Backup downloaden (.json)</a></p>';

    echo '<h2>Backup terugzetten</h2>';
    echo '<p>Zet een eerder gemaakte backup terug. Ongeldige waarden worden de standaardwaarde; een bestand dat geen backup van Cookiebaas is, wijzigt niets.</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" class="cm-action-form" data-cm-confirm="' . esc_attr( 'De huidige instellingen, cookielijst en privacyverklaring worden overschreven door de backup. Doorgaan?' ) . '">';
    echo '<input type="hidden" name="action" value="cm_import_backup">';
    wp_nonce_field( 'cm_import_backup' );
    echo '<p><label for="cm-backup-file">Backupbestand (.json)</label><br><input type="file" id="cm-backup-file" name="cm_backup" accept=".json,application/json" required></p>';
    echo '<button type="submit" class="button">Terugzetten en overschrijven</button>';
    echo '</form>';
}

function cm_render_beheer_reset() {
    echo '<p>Losse onderdelen zet u op hun eigen plek terug: de kleuren onder Banner › Vormgeving, de cookielijst onder Cookies, de privacyverklaring op de pagina Privacyverklaring, en de consent log onder Consent log › Bewaren en opnieuw vragen.</p>';

    echo '<h2>Licentie lokaal wissen</h2>';
    echo '<p>Wist de licentiegegevens op deze website, zonder de licentieserver te benaderen. Gebruik dit als deactiveren niet lukt. De cookiebanner, de blokkering en de handmatige scan blijven werken; ' . esc_html( cm_premium_features_text() ) . ' pauzeren.</p>';
    echo '<div>' . cm_admin_action_form( 'license_reset', 'Licentie lokaal wissen', array(), 'De licentiegegevens op deze website wissen?' ) . '</div>';

    echo '<h2>Alles resetten</h2>';
    echo '<p>Zet alles in één keer terug. De instellingen (ook de API-sleutel), de cookielijst en de privacyverklaring gaan naar de standaard, de consent log wordt leeggemaakt, elke bezoeker ziet de banner opnieuw en de licentie wordt lokaal gewist. Dit kan niet ongedaan worden gemaakt.</p>';
    echo '<div>' . cm_admin_action_form( 'reset_all', 'Alles resetten', array(), 'Alles resetten? Instellingen, cookielijst, privacyverklaring, consent log en licentie worden gewist. Dit kan niet ongedaan worden gemaakt.', 'button button-link-delete' ) . '</div>';
}

/**
 * API-sleutel: leeg, of 40 hex-tekens zoals "Sleutel maken" die maakt. Iets
 * anders laat de huidige sleutel staan, ook een zelfgekozen sleutel uit 2.x
 * (WordPress haalt elke opslag van cm_settings door deze sanitizer).
 */
function cm_sanitize_api_key( $raw, $current ) {
    if ( ! is_string( $raw ) ) return (string) $current;
    $raw = strtolower( trim( $raw ) );
    return ( $raw === '' || preg_match( '/^[a-f0-9]{40}$/', $raw ) ) ? $raw : (string) $current;
}

function cm_set_api_key( $key ) {
    $s = get_option( 'cm_settings', array() );
    $s = is_array( $s ) ? $s : array();
    $s['api_key'] = (string) $key;
    update_option( 'cm_settings', $s );
}

function cm_render_beheer_geavanceerd() {
    if ( cm_admin_require_license() !== '' ) {
        echo cm_admin_premium_notice( 'Consent controleren via de REST API vraagt een licentie; zonder licentie geeft het endpoint een foutmelding.' );
    }
    $key      = (string) cm_get( 'api_key' );
    $endpoint = rest_url( 'cookiebaas/v1/consent/' );
    echo '<h2>REST API</h2>';
    echo '<p>Controleer een toestemming vanuit een CRM, e-mailplatform of andere externe dienst. Het endpoint geeft de keuze terug zonder persoonsgegevens (geen IP-adres, geen browser).</p>';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">Endpoint</th><td><code>' . esc_html( $endpoint . '{consent_id}' ) . '</code></td></tr>';
    echo '<tr><th scope="row">API-sleutel</th><td>';
    if ( $key !== '' ) {
        echo '<code>' . esc_html( $key ) . '</code>';
        echo '<p class="description">Stuur de sleutel mee als HTTP-header <code>X-Cookiebaas-Key</code>.</p>';
    } else {
        echo '<p>Geen sleutel.</p><p class="description">Zonder sleutel is het endpoint alleen bereikbaar met een WordPress-applicatiewachtwoord.</p>';
    }
    echo '<div>' . cm_admin_action_form( 'api_key_generate', $key !== '' ? 'Nieuwe sleutel maken' : 'Sleutel maken', array(), $key !== '' ? 'Een nieuwe sleutel maken? De huidige sleutel werkt daarna niet meer.' : '' );
    if ( $key !== '' ) {
        echo ' ' . cm_admin_action_form( 'api_key_revoke', 'Intrekken', array(), 'De API-sleutel intrekken? Externe koppelingen die hem gebruiken, verliezen direct toegang.', 'button button-link-delete' );
    }
    echo '</div></td></tr>';
    echo '</tbody></table>';

    if ( $key !== '' ) {
        echo '<h3>Voorbeeld</h3>';
        echo '<pre><code>' . esc_html( 'curl -H "X-Cookiebaas-Key: ' . $key . '" "' . $endpoint . '{consent_id}"' ) . '</code></pre>';
    }
    echo '<h3>Voorbeeldantwoord</h3>';
    echo '<pre><code>' . esc_html( "{\n  \"consent_id\": \"a1b2c3d4-...\",\n  \"status\": \"Geaccepteerd\",\n  \"method\": \"accept-all\",\n  \"analytics\": true,\n  \"marketing\": true,\n  \"config_hash\": \"a3f9d2b1c4e87f20\",\n  \"timestamp\": \"2026-03-17 10:25:00\",\n  \"verified\": true\n}" ) . '</code></pre>';
    echo '<p class="description">Statuswaarden: <code>Geaccepteerd</code>, <code>Geweigerd</code>, <code>Aangepast</code>, <code>Terugkerend bezoek</code>. HTTP 404 als de consent-ID niet bestaat.</p>';
}

/** Tekst van de disclaimer: array( kop, alinea ). */
function cm_disclaimer_paragraphs() {
    return array(
        array( '1. Geen juridisch advies', 'De Cookiebaas plugin is een technisch hulpmiddel en biedt geen juridisch advies. De plugin vervangt op geen enkele wijze de noodzaak om een gekwalificeerde juridisch adviseur te raadplegen over uw specifieke situatie met betrekking tot de AVG/GDPR, de ePrivacy-richtlijn, de Telecommunicatiewet of andere toepasselijke wet- en regelgeving. Het gebruik van deze plugin garandeert niet dat uw website voldoet aan geldende privacywetgeving.' ),
        array( '2. “Zoals beschikbaar”', 'De Cookiebaas plugin wordt aangeboden “as is” en “as available”, zonder enige garantie van welke aard dan ook, uitdrukkelijk noch stilzwijgend. Dit omvat, maar is niet beperkt tot, garanties van verkoopbaarheid, geschiktheid voor een bepaald doel, niet-inbreuk, juistheid, volledigheid, of ononderbroken en foutloze werking.' ),
        array( '3. Beperking van aansprakelijkheid', 'Ruud van der Heijden en eventuele bijdragers zijn in geen geval aansprakelijk voor enige directe, indirecte, incidentele, speciale, gevolg- of voorbeeldschade (inclusief maar niet beperkt tot boetes van toezichthouders, verlies van gegevens, gederfde winst, bedrijfsonderbreking of reputatieschade) die voortvloeit uit of verband houdt met het gebruik of het onvermogen tot gebruik van deze plugin, zelfs indien op de hoogte gesteld van de mogelijkheid van dergelijke schade.' ),
        array( '4. Verantwoordelijkheid van de gebruiker', 'De website-eigenaar blijft te allen tijde zelf verantwoordelijk voor de naleving van privacywetgeving. Dit omvat onder meer: het correct configureren van de plugin, het actueel houden van de cookielijst en privacyverklaring, het testen of cookies daadwerkelijk geblokkeerd worden vóór consent, het inschakelen van een juridisch adviseur bij twijfel, en het periodiek controleren van de compliance-check.' ),
        array( '5. Geen garantie op compliance', 'Hoewel de Cookiebaas plugin is ontworpen met de AVG, EDPB-richtlijnen en AP-handhavingscriteria als uitgangspunt, kan de ontwikkelaar niet garanderen dat de plugin in alle situaties en jurisdicties volledige compliance biedt. Wet- en regelgeving verandert regelmatig en de interpretatie ervan kan per toezichthouder en per rechtsgebied verschillen.' ),
        array( '6. Diensten van derden', 'De plugin interageert met diensten van derden (Google Analytics, Google Tag Manager, YouTube, Vimeo, Meta/Facebook, etc.). De ontwikkelaar heeft geen controle over en is niet verantwoordelijk voor het gedrag, de cookiepraktijken of het privacybeleid van deze diensten. Het is de verantwoordelijkheid van de website-eigenaar om te controleren of het gebruik van deze diensten in overeenstemming is met de toepasselijke wetgeving.' ),
        array( '7. Updates en ondersteuning', 'Er is geen verplichting tot het leveren van updates, bugfixes, beveiligingspatches of ondersteuning. Eventuele updates worden naar eigen inzicht van de ontwikkelaar beschikbaar gesteld.' ),
        array( '8. Aanvaarding', 'Door deze plugin te installeren, te activeren en/of te gebruiken, verklaart u dat u deze disclaimer en de daarin vervatte beperkingen van aansprakelijkheid hebt gelezen, begrepen en aanvaard. Indien u niet akkoord gaat met deze voorwaarden, dient u de plugin onmiddellijk te deactiveren en te verwijderen.' ),
    );
}

function cm_render_beheer_info() {
    echo '<h2>Plugin</h2>';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">Versie</th><td>' . esc_html( CM_VERSION ) . '</td></tr>';
    echo '<tr><th scope="row">Gemaakt door</th><td><a href="https://www.cookiebaas.nl/" target="_blank" rel="noopener">Ruud van der Heijden</a></td></tr>';
    echo '<tr><th scope="row">Naam van de toestemmingscookie</th><td><code>cc_cm_consent</code></td></tr>';
    echo '<tr><th scope="row">AVG-compliant</th><td>Ja — opt-in, geen vooraf aangevinkte marketingcookies</td></tr>';
    echo '</tbody></table>';

    echo '<h2>Snel aan de slag</h2><ol>';
    foreach ( array(
        'Vul onder Blokkering › Google uw GA4- of GTM-ID in.',
        'Pas onder Banner de kleuren, teksten en weergave aan naar uw huisstijl.',
        'Laad onder Cookies › Scannen de Open Cookie Database en voer een scan uit.',
        'Vul de Privacyverklaring in en plaats de shortcode [cookiebaas_privacy] op uw privacypagina.',
        'Test: verwijder de cookie cc_cm_consent en controleer met de ontwikkelaarstools (F12) dat cookies pas na akkoord verschijnen.',
    ) as $step ) {
        echo '<li>' . esc_html( $step ) . '</li>';
    }
    echo '</ol>';

    echo '<h2>Shortcodes</h2>';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">Volledige privacyverklaring</th><td><code>[cookiebaas_privacy]</code></td></tr>';
    echo '<tr><th scope="row">Alleen de cookieparagraaf</th><td><code>[cookiebaas_cookies]</code></td></tr>';
    echo '<tr><th scope="row">Pagina met cookievoorkeuren</th><td><code>[cookiebaas_voorkeuren]</code></td></tr>';
    echo '</tbody></table>';

    echo '<h2>Disclaimer en aansprakelijkheid</h2>';
    foreach ( cm_disclaimer_paragraphs() as $p ) {
        echo '<h3>' . esc_html( $p[0] ) . '</h3><p>' . esc_html( $p[1] ) . '</p>';
    }

    echo '<h2>Contact en ondersteuning</h2>';
    echo '<p>Voor vragen: <a href="https://www.cookiebaas.nl/" target="_blank" rel="noopener">cookiebaas.nl</a>.</p>';
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'license_activate', function () {
        cm_license_flash( cm_license_activate_request( isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '' ) );
        return '';
    } );
    cm_admin_register_action( 'license_check', function () {
        $r = cm_license_check_status();
        list( , $word ) = cm_license_summary( cm_license_get(), cm_license_is_valid() );
        list( $t, $x ) = cm_license_check_notice( $r, $word );
        cm_admin_flash( $t, $x );
        return '';
    } );
    cm_admin_register_action( 'license_deactivate', function () {
        cm_license_flash( cm_license_deactivate() );
        return '';
    } );
    cm_admin_register_action( 'export_backup', function () {
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cookiebaas-backup-' . wp_date( 'Y-m-d' ) . '.json"' );
        echo wp_json_encode( cm_backup_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    } );
    cm_admin_register_action( 'import_backup', function () {
        $f   = isset( $_FILES['cm_backup'] ) && is_array( $_FILES['cm_backup'] ) ? $_FILES['cm_backup'] : array();
        $ok  = isset( $f['error'], $f['size'], $f['tmp_name'] ) && (int) $f['error'] === UPLOAD_ERR_OK
            && (int) $f['size'] <= MB_IN_BYTES && is_uploaded_file( $f['tmp_name'] );
        if ( ! $ok ) {
            cm_admin_flash( 'error', 'Kies een backupbestand (.json, maximaal 1 MB). Er is niets gewijzigd.' );
            return '';
        }
        $r = cm_import_backup( (string) file_get_contents( $f['tmp_name'] ) );
        cm_admin_flash( ! $r['ok'] ? 'error' : ( $r['warning'] ? 'warning' : 'success' ), $r['message'] );
        return '';
    } );
    cm_admin_register_action( 'reset_all', function () {
        $failed = cm_reset_everything();
        if ( ! $failed ) return 'reset-all-done';
        cm_admin_flash( 'error', 'Niet gelukt: ' . implode( ', ', $failed ) . '. De rest is wel teruggezet.' );
        return '';
    } );
    cm_admin_register_action( 'license_reset', function () {
        cm_license_reset_local();
        return 'license-cleared';
    } );
    cm_admin_register_action( 'api_key_generate', function () {
        cm_set_api_key( bin2hex( random_bytes( 20 ) ) );
        return 'api-key-created';
    } );
    cm_admin_register_action( 'api_key_revoke', function () {
        cm_set_api_key( '' );
        return 'api-key-revoked';
    } );
}
