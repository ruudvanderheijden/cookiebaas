<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   SHORTCODE: [cookiebaas_privacy]
   Genereert een volledige AVG-privacyverklaring op basis van de
   instellingen in het Privacyverklaring-tabblad.
================================================================ */

add_shortcode( 'cookiebaas_privacy', 'cm_render_privacy_page' );

function cm_render_privacy_page() {
    $p  = get_option( 'cm_privacy', cm_default_privacy() );
    $pv = function( $key ) use ( $p ) {
        $d = cm_default_privacy();
        return isset( $p[ $key ] ) ? $p[ $key ] : ( isset( $d[ $key ] ) ? $d[ $key ] : '' );
    };

    // Helper: leeg = tonen niet
    $has = function( $key ) use ( $pv ) { return trim( $pv( $key ) ) !== ''; };

    ob_start();
    ?>
    <div class="cm-privacy-page">

    <?php /* ── Bedrijfsblok ───────────────────────────────────── */ ?>
    <?php if ( $has('pv_bedrijfsnaam') ) : ?>
    <div class="cm-pv-company">
        <strong><?php echo esc_html( $pv('pv_bedrijfsnaam') ); ?></strong><br>
        <?php if ( $has('pv_straat') )          echo esc_html( $pv('pv_straat') ) . '<br>'; ?>
        <?php if ( $has('pv_postcode_plaats') )  echo esc_html( $pv('pv_postcode_plaats') ) . '<br>'; ?>
        <?php if ( $has('pv_land') )             echo esc_html( $pv('pv_land') ) . '<br>'; ?>
        <?php if ( $has('pv_kvk') )              echo 'KVK: ' . esc_html( $pv('pv_kvk') ) . '<br>'; ?>
        <?php if ( $has('pv_telefoon') )         echo 'Telefoon: ' . esc_html( $pv('pv_telefoon') ) . '<br>'; ?>
        <?php if ( $has('pv_email') )            echo 'E-mail: <a href="mailto:' . esc_attr( $pv('pv_email') ) . '">' . esc_html( $pv('pv_email') ) . '</a><br>'; ?>
    </div>
    <?php if ( $pv('pv_dpo_enabled') === '1' && $has('pv_dpo_naam') ) : ?>
    <div class="cm-pv-company" style="margin-top:12px">
        <strong>Functionaris Gegevensbescherming (DPO)</strong><br>
        <?php echo esc_html( $pv('pv_dpo_naam') ); ?><br>
        <?php if ( $has('pv_dpo_email') )    echo 'E-mail: <a href="mailto:' . esc_attr( $pv('pv_dpo_email') ) . '">' . esc_html( $pv('pv_dpo_email') ) . '</a><br>'; ?>
        <?php if ( $has('pv_dpo_telefoon') ) echo 'Telefoon: ' . esc_html( $pv('pv_dpo_telefoon') ) . '<br>'; ?>
    </div>
    <?php endif; ?>
    <p class="cm-pv-meta">
        <?php if ( $has('pv_versie') ) echo 'Versie ' . esc_html( $pv('pv_versie') ); ?>
        <?php if ( $has('pv_versie') && $has('pv_datum') ) echo ' &mdash; '; ?>
        <?php if ( $has('pv_datum') )  echo 'Laatst bijgewerkt: ' . esc_html( $pv('pv_datum') ); ?>
    </p>
    <?php endif; ?>

    <?php /* ── 1. Inleiding ───────────────────────────────────── */ ?>
    <h2>1. Inleiding</h2>
    <?php
    $naam = $has('pv_inleiding_naam') ? esc_html( $pv('pv_inleiding_naam') ) : esc_html( $pv('pv_bedrijfsnaam') );
    ?>
    <p>
        <?php if ( $naam ) echo '<strong>' . $naam . '</strong> '; ?>
        (&ldquo;wij&rdquo;, &ldquo;ons&rdquo;, &ldquo;onze&rdquo;) respecteert uw privacy en verwerkt persoonsgegevens in overeenstemming met de Algemene Verordening Gegevensbescherming (AVG). Deze privacyverklaring beschrijft welke persoonsgegevens wij verzamelen via onze website, waarom wij deze gegevens verzamelen, en hoe wij deze verwerken.
    </p>

    <?php /* ── 2. Welke persoonsgegevens ────────────────────── */ ?>
    <h2>2. Welke persoonsgegevens verzamelen wij?</h2>

    <?php
    // Toon 2.1 alleen als minstens één contactformulier-veld aangevinkt is
    $cf_velden = array();
    if ( $pv('pv_cf_voornaam')     === '1' ) $cf_velden[] = 'Voornaam';
    if ( $pv('pv_cf_achternaam')   === '1' ) $cf_velden[] = 'Achternaam';
    if ( $pv('pv_cf_bedrijf')      === '1' ) $cf_velden[] = 'Bedrijfsnaam';
    if ( $pv('pv_cf_adres')        === '1' ) $cf_velden[] = 'Adresgegevens';
    if ( $pv('pv_cf_email')        === '1' ) $cf_velden[] = 'E-mailadres';
    if ( $pv('pv_cf_website')      === '1' ) $cf_velden[] = 'Website (optioneel)';
    if ( $pv('pv_cf_telefoon')     === '1' ) $cf_velden[] = 'Telefoonnummer (optioneel)';
    if ( $pv('pv_cf_bericht')      === '1' ) $cf_velden[] = 'Uw bericht';
    if ( $pv('pv_cf_privacy')      === '1' ) $cf_velden[] = 'Acceptatie privacyverklaring';
    // Vrije velden
    $extra_raw = trim( $pv('pv_cf_extra') );
    if ( $extra_raw !== '' ) {
        foreach ( explode( "\n", $extra_raw ) as $regel ) {
            $r = trim( $regel );
            if ( $r !== '' ) $cf_velden[] = $r;
        }
    }
    ?>
    <?php if ( ! empty( $cf_velden ) ) : ?>
    <h3>2.1 Contactformulier</h3>
    <p>Wanneer u ons contactformulier invult, verzamelen wij de volgende gegevens:</p>
    <ul>
        <?php foreach ( $cf_velden as $veld ) : ?>
        <li><?php echo esc_html( $veld ); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ( $has('pv_cf_grondslag') ) : ?>
    <p><strong>Rechtsgrondslag:</strong> <?php echo esc_html( $pv('pv_cf_grondslag') ); ?></p>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ( $pv('pv_nieuwsbrief_enabled') === '1' ) : ?>
    <h3>2.3 Nieuwsbrief</h3>
    <p>Als u zich aanmeldt voor onze nieuwsbrief, verwerken wij uw e-mailadres voor het toesturen van onze nieuwsbrief en commerciële berichten.</p>
    <?php if ( $has('pv_nieuwsbrief_grondslag') ) : ?>
    <p><strong>Rechtsgrondslag:</strong> <?php echo esc_html( $pv('pv_nieuwsbrief_grondslag') ); ?></p>
    <?php endif; ?>
    <p>U kunt u op elk moment afmelden<?php if ( $has('pv_nieuwsbrief_afmelden') ) echo ' via <a href="' . esc_url( $pv('pv_nieuwsbrief_afmelden') ) . '">' . esc_html( $pv('pv_nieuwsbrief_afmelden') ) . '</a>'; else echo ' via de afmeldlink onderaan elke e-mail'; ?>.</p>
    <?php endif; ?>

    <h3><?php echo $pv('pv_nieuwsbrief_enabled') === '1' ? '2.4' : '2.2'; ?> Automatisch verzamelde gegevens</h3>
    <p>Bij uw bezoek aan onze website verzamelen wij automatisch bepaalde gegevens via cookies, vergelijkbare technologieën en serverlogbestanden:</p>
    <ul>
        <li>IP-adres</li>
        <li>Browsertype en -versie</li>
        <li>Apparaatgegevens</li>
        <li>Bezochte pagina&apos;s en klikgedrag</li>
        <li>Datum en tijd van bezoek</li>
        <li>Verwijzende website</li>
    </ul>
    <p>Serverlogbestanden worden door onze hostingprovider bewaard voor beveiligings- en analysedoeleinden.</p>

    <?php /* ── 3. Doeleinden ──────────────────────────────────── */ ?>
    <h2>3. Doeleinden en grondslagen</h2>
    <p>Wij verwerken uw persoonsgegevens voor de volgende doeleinden:</p>
    <?php
    $doeleinden = json_decode( $pv('pv_doeleinden'), true );
    if ( ! empty( $doeleinden ) && is_array( $doeleinden ) ) :
    ?>
    <table class="cm-pv-table">
        <thead>
            <tr><th>Doel</th><th>Grondslag</th><th>Bewaartermijn</th></tr>
        </thead>
        <tbody>
        <?php foreach ( $doeleinden as $rij ) : ?>
            <tr>
                <td><?php echo esc_html( $rij['doel'] ?? '' ); ?></td>
                <td><?php echo esc_html( $rij['grondslag'] ?? '' ); ?></td>
                <td><?php echo esc_html( $rij['termijn'] ?? '' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p>Voor de verwerking op basis van gerechtvaardigd belang hebben wij een afweging gemaakt tussen ons belang en uw privacybelangen. U kunt te allen tijde bezwaar maken tegen deze verwerking.</p>

    <?php /* ── 4. Cookies ───────────────────────────────────────── */ ?>
    <h2>4. Cookies</h2>
    <p>Onze website maakt gebruik van cookies. Cookies zijn kleine tekstbestanden die op uw apparaat worden opgeslagen wanneer u onze website bezoekt.<?php if ( $pv('pv_gtm') === '1' ) echo ' Wij gebruiken Google Tag Manager om cookies te beheren en te laden.'; ?></p>

    <?php echo cm_pv_cookie_tabel(); ?>

    <h3>4.4 Uw cookievoorkeuren beheren</h3>
    <p>U kunt uw cookievoorkeuren op elk moment wijzigen via de cookiebanner op onze website. Daarnaast kunt u cookies verwijderen of blokkeren via uw browserinstellingen. Let op: het blokkeren van cookies kan de functionaliteit van de website beïnvloeden.</p>
    <?php
    $optout = json_decode( $pv('pv_optout_links'), true );
    if ( ! empty( $optout ) && is_array( $optout ) ) :
    ?>
    <p>U kunt zich ook afmelden voor specifieke tracking via:</p>
    <ul>
        <?php foreach ( $optout as $link ) :
            if ( empty( $link['naam'] ) || empty( $link['url'] ) ) continue; ?>
        <li><?php echo esc_html( $link['naam'] ); ?>: <a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['url'] ); ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php /* ── 5. Ontvangers ─────────────────────────────────── */ ?>
    <h2>5. Ontvangers van persoonsgegevens</h2>
    <p>Wij delen uw persoonsgegevens met de volgende partijen:</p>
    <?php
    $ontvangers = json_decode( $pv('pv_ontvangers'), true );
    if ( ! empty( $ontvangers ) && is_array( $ontvangers ) ) :
    ?>
    <table class="cm-pv-table">
        <thead>
            <tr><th>Partij</th><th>Doel</th><th>Locatie</th></tr>
        </thead>
        <tbody>
        <?php foreach ( $ontvangers as $rij ) :
            if ( empty( $rij['partij'] ) ) continue; ?>
            <tr>
                <td><?php echo esc_html( $rij['partij'] ?? '' ); ?></td>
                <td><?php echo esc_html( $rij['doel'] ?? '' ); ?></td>
                <td><?php echo esc_html( $rij['locatie'] ?? '' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p>*Zie paragraaf 6 over internationale doorgifte.</p>
    <?php endif; ?>

    <?php /* ── 6. Internationale doorgifte ──────────────────── */ ?>
    <h2>6. Internationale doorgifte van gegevens</h2>
    <?php if ( $has('pv_doorgifte') ) : ?>
    <p><?php echo nl2br( esc_html( $pv('pv_doorgifte') ) ); ?></p>
    <?php else : ?>
    <p>Sommige van onze verwerkers zijn gevestigd buiten de Europese Economische Ruimte. De doorgifte van persoonsgegevens naar deze partijen vindt plaats op basis van passende waarborgen conform de AVG, zoals het EU-VS Data Privacy Framework of Standard Contractual Clauses (SCC&apos;s).</p>
    <?php endif; ?>

    <?php /* ── 7. Bewaartermijnen ───────────────────────────── */ ?>
    <h2>7. Bewaartermijnen</h2>
    <p>Wij bewaren uw persoonsgegevens niet langer dan noodzakelijk:</p>
    <ul>
        <?php if ( $has('pv_bewaar_contact') ) : ?>
        <li><strong>Contactformuliergegevens:</strong> <?php echo esc_html( $pv('pv_bewaar_contact') ); ?></li>
        <?php endif; ?>
        <?php if ( $has('pv_bewaar_logs') ) : ?>
        <li><strong>Serverlogbestanden:</strong> <?php echo esc_html( $pv('pv_bewaar_logs') ); ?></li>
        <?php endif; ?>
        <?php if ( $has('pv_bewaar_analytics') ) : ?>
        <li><strong>Analytische gegevens:</strong> <?php echo esc_html( $pv('pv_bewaar_analytics') ); ?></li>
        <?php endif; ?>
        <?php if ( $has('pv_bewaar_nieuwsbrief') ) : ?>
        <li><strong>Nieuwsbriefabonnement:</strong> <?php echo esc_html( $pv('pv_bewaar_nieuwsbrief') ); ?></li>
        <?php endif; ?>
        <li><strong>Cookies:</strong> zie de specifieke bewaartermijnen per cookie in sectie 4</li>
    </ul>

    <?php /* ── 8. Uw rechten ──────────────────────────────────── */ ?>
    <h2>8. Uw rechten</h2>
    <p>Op grond van de AVG heeft u de volgende rechten:</p>
    <ul>
        <li><strong>Inzage:</strong> U kunt opvragen welke persoonsgegevens wij van u verwerken.</li>
        <li><strong>Rectificatie:</strong> U kunt onjuiste gegevens laten corrigeren.</li>
        <li><strong>Verwijdering:</strong> U kunt verzoeken uw gegevens te verwijderen.</li>
        <li><strong>Beperking:</strong> U kunt verzoeken de verwerking te beperken.</li>
        <li><strong>Overdraagbaarheid:</strong> U kunt uw gegevens in een gestructureerd formaat ontvangen.</li>
        <li><strong>Bezwaar:</strong> U kunt bezwaar maken tegen verwerking op basis van gerechtvaardigd belang.</li>
        <li><strong>Intrekken toestemming:</strong> U kunt gegeven toestemming altijd intrekken.</li>
        <li><strong>Klacht indienen:</strong> U heeft het recht een klacht in te dienen bij de Autoriteit Persoonsgegevens (autoriteitpersoonsgegevens.nl).</li>
    </ul>
    <?php
    $rechten_email  = $has('pv_rechten_email')  ? $pv('pv_rechten_email')  : $pv('pv_email');
    $rechten_termijn = $has('pv_rechten_termijn') ? $pv('pv_rechten_termijn') : 'één maand';
    ?>
    <?php if ( $rechten_email ) : ?>
    <p>Om uw rechten uit te oefenen, kunt u contact met ons opnemen via <a href="mailto:<?php echo esc_attr( $rechten_email ); ?>"><?php echo esc_html( $rechten_email ); ?></a>. Wij reageren binnen <?php echo esc_html( $rechten_termijn ); ?> op uw verzoek.</p>
    <?php endif; ?>

    <?php /* ── 9. Beveiliging ─────────────────────────────────── */ ?>
    <h2>9. Beveiliging</h2>
    <p>Wij nemen passende technische en organisatorische maatregelen om uw persoonsgegevens te beschermen tegen verlies, misbruik en ongeautoriseerde toegang. Onze website maakt gebruik van een beveiligde verbinding (HTTPS).</p>

    <?php /* ── 10. Klachten ──────────────────────────────────── */ ?>
    <h2>10. Klachten</h2>
    <p>Indien u een klacht heeft over de verwerking van uw persoonsgegevens, kunt u contact met ons opnemen. U heeft ook het recht om een klacht in te dienen bij de Autoriteit Persoonsgegevens:</p>
    <?php if ( $pv('pv_ap_tonen') === '1' ) : ?>
    <p>
        Autoriteit Persoonsgegevens<br>
        Postbus 93374<br>
        2509 AJ Den Haag<br>
        <a href="https://www.autoriteitpersoonsgegevens.nl" target="_blank" rel="noopener">www.autoriteitpersoonsgegevens.nl</a>
    </p>
    <?php endif; ?>

    <?php /* ── 11. Wijzigingen ──────────────────────────────── */ ?>
    <h2>11. Wijzigingen</h2>
    <p>Wij kunnen deze privacyverklaring van tijd tot tijd wijzigen. De meest recente versie is altijd beschikbaar op onze website. Bij belangrijke wijzigingen zullen wij u hierover informeren.</p>
    <?php if ( $has('pv_wijzigingen_extra') ) : ?>
    <p><?php echo nl2br( esc_html( $pv('pv_wijzigingen_extra') ) ); ?></p>
    <?php endif; ?>

    <?php /* ── 12. Geautomatiseerde besluitvorming (Art. 22 AVG) ─── */ ?>
    <?php if ( $pv('pv_profilering_enabled') === '1' ) : ?>
    <h2>12. Geautomatiseerde besluitvorming en profilering</h2>
    <?php if ( $has('pv_profilering_tekst') ) : ?>
    <p><?php echo nl2br( esc_html( $pv('pv_profilering_tekst') ) ); ?></p>
    <?php else : ?>
    <p>Wij maken gebruik van geautomatiseerde besluitvorming en/of profilering in de zin van artikel 22 AVG. Dit houdt in dat uw persoonsgegevens worden verwerkt om uw interesses te analyseren en u relevante advertenties of content te tonen. Er worden geen besluiten genomen die uitsluitend op geautomatiseerde verwerking zijn gebaseerd en die rechtsgevolgen hebben voor u of u op vergelijkbare wijze wezenlijk treffen, zonder menselijke tussenkomst.</p>
    <?php endif; ?>
    <?php endif; ?>

    <?php /* ── 13. Functionaris Gegevensbescherming (DPO) ─── */ ?>
    <?php if ( $pv('pv_dpo_enabled') === '1' && $has('pv_dpo_naam') ) : ?>
    <h2>13. Functionaris Gegevensbescherming</h2>
    <p>Wij hebben een Functionaris Gegevensbescherming (FG) aangesteld die toeziet op de naleving van privacywetgeving binnen onze organisatie. U kunt de FG bereiken via:</p>
    <p>
        <strong><?php echo esc_html( $pv('pv_dpo_naam') ); ?></strong><br>
        <?php if ( $has('pv_dpo_email') )    echo 'E-mail: <a href="mailto:' . esc_attr( $pv('pv_dpo_email') ) . '">' . esc_html( $pv('pv_dpo_email') ) . '</a><br>'; ?>
        <?php if ( $has('pv_dpo_telefoon') ) echo 'Telefoon: ' . esc_html( $pv('pv_dpo_telefoon') ) . '<br>'; ?>
    </p>
    <?php endif; ?>

    </div><!-- /.cm-privacy-page -->

    <?php
    // Inline stijl voor de tabel (eenmalig)
    echo '<style>
    .cm-privacy-page { max-width:800px; line-height:1.7; }
    .cm-privacy-page h2 { margin-top:2em; }
    .cm-privacy-page h3 { margin-top:1.4em; }
    .cm-pv-company { margin-bottom:1em; line-height:1.9; }
    .cm-pv-meta { color:#777; font-size:.9em; margin-bottom:2em; }
    .cm-pv-table { width:100%; border-collapse:collapse; margin:1em 0 1.5em; font-size:.93em; }
    .cm-pv-table th { background:#f5f5f5; text-align:left; padding:8px 12px; border:1px solid #ddd; font-weight:600; }
    .cm-pv-table td { padding:7px 12px; border:1px solid #ddd; vertical-align:top; }
    .cm-pv-table tr:nth-child(even) td { background:#fafafa; }
    </style>';

    return ob_get_clean();
}

/**
 * Bouw de cookietabel per categorie op voor de privacyverklaring.
 * Dezelfde indeling als het voorkeuren-venster.
 */

/* ================================================================
   SHORTCODE: [cookiebaas_cookies]
   Toont alleen de cookieparagraaf (categorieën + lijst), zonder
   hoofdstuknummers. Handig om los op een pagina te plaatsen.
================================================================ */
function cm_pv_cookie_tabel( $with_numbers = true ) {
    $all_cookies = cm_get_cookie_list();
    $cats = array(
        'functional' => array( 'label' => $with_numbers ? '4.1 Functionele cookies'  : 'Functionele cookies',  'grondslag' => 'Strikt noodzakelijk / Gerechtvaardigd belang (Art. 6 lid 1 sub f AVG)', 'cookies' => array() ),
        'analytics'  => array( 'label' => $with_numbers ? '4.2 Analytische cookies'  : 'Analytische cookies',  'grondslag' => 'Toestemming (Art. 6 lid 1 sub a AVG)', 'cookies' => array() ),
        'marketing'  => array( 'label' => $with_numbers ? '4.3 Marketing cookies'    : 'Marketing cookies',    'grondslag' => 'Toestemming (Art. 6 lid 1 sub a AVG)', 'cookies' => array() ),
    );
    foreach ( $all_cookies as $ck ) {
        $cat = isset( $ck['category'] ) ? $ck['category'] : 'functional';
        if ( isset( $cats[ $cat ] ) ) $cats[ $cat ]['cookies'][] = $ck;
    }

    $out = '';
    foreach ( $cats as $cat_data ) {
        if ( empty( $cat_data['cookies'] ) ) continue;
        $out .= '<h3>' . esc_html( $cat_data['label'] ) . '</h3>';
        $out .= '<p class="cm-pv-cookie-grondslag"><strong>Rechtsgrondslag:</strong> ' . esc_html( $cat_data['grondslag'] ) . '</p>';
        $out .= '<table class="cm-pv-table"><thead><tr>'
              . '<th>Cookie</th><th>Organisatie</th><th>Doel</th><th>Looptijd</th>'
              . '</tr></thead><tbody>';
        foreach ( $cat_data['cookies'] as $ck ) {
            $out .= '<tr>'
                  . '<td><code>' . esc_html( $ck['name'] ) . '</code></td>'
                  . '<td>' . esc_html( $ck['provider'] ?? '' ) . '</td>'
                  . '<td>' . esc_html( $ck['purpose'] ?? '' ) . '</td>'
                  . '<td style="white-space:nowrap">' . esc_html( $ck['duration'] ?? '' ) . '</td>'
                  . '</tr>';
        }
        $out .= '</tbody></table>';
    }
    return $out;
}


/* ================================================================
   SHORTCODE: [cookiebaas_cookies]
   Toont alleen het cookieoverzicht (zonder hoofdstuknummers),
   geschikt voor losse plaatsing op een pagina.
================================================================ */
add_shortcode( 'cookiebaas_cookies', 'cm_render_cookies_shortcode' );
function cm_render_cookies_shortcode() {
    ob_start();
    echo '<div class="cm-privacy-page cm-cookies-only">';
    $tabel = cm_pv_cookie_tabel( false );
    if ( $tabel ) {
        echo $tabel;
        echo '<p>U kunt uw cookievoorkeuren op elk moment wijzigen via de cookiebanner op onze website.</p>';
    } else {
        echo '<p>Er zijn nog geen cookies geconfigureerd.</p>';
    }
    echo '</div>';
    return ob_get_clean();
}

/* ================================================================
   AJAX — Privacy instellingen opslaan
================================================================ */
add_action( 'wp_ajax_cm_save_privacy', 'cm_ajax_save_privacy' );
function cm_ajax_save_privacy() {
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

    $defaults = cm_default_privacy();
    $privacy  = array();

    foreach ( $defaults as $key => $default ) {
        if ( in_array( $key, array('pv_doeleinden','pv_optout_links','pv_ontvangers'), true ) ) {
            // JSON-velden: komen als geëncodeerde string binnen
            $privacy[ $key ] = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : $default;
        } elseif ( strpos( $key, 'pv_cf_' ) === 0 || in_array( $key, array('pv_gtm','pv_ap_tonen','pv_nieuwsbrief_enabled','pv_profilering_enabled'), true ) ) {
            // Checkboxes: 1 of 0
            $privacy[ $key ] = isset( $_POST[ $key ] ) && $_POST[ $key ] === '1' ? '1' : '0';
        } else {
            $privacy[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
        }
    }

    update_option( 'cm_privacy', $privacy );
    wp_send_json_success( array( 'message' => 'Privacyverklaring opgeslagen.' ) );
}
