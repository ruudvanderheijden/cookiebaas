<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   PAGINA PRIVACYVERKLARING — opslag in cm_privacy (eigen settings-groep),
   verwerkingsregister (AVG art. 30) en standaardtekst herstellen.
   De pagina zelf staat onderaan (plan 2, taak 7).
================================================================ */

/** De zes AVG-grondslagen (art. 6 lid 1), als waarde => label. */
function cm_avg_grondslagen() {
    $list = array(
        'Toestemming (Art. 6 lid 1 sub a AVG)',
        'Uitvoering overeenkomst (Art. 6 lid 1 sub b AVG)',
        'Wettelijke verplichting (Art. 6 lid 1 sub c AVG)',
        'Vitaal belang (Art. 6 lid 1 sub d AVG)',
        'Publiekrechtelijke taak (Art. 6 lid 1 sub e AVG)',
        'Gerechtvaardigd belang (Art. 6 lid 1 sub f AVG)',
    );
    return array_combine( $list, $list );
}

/**
 * Rijen van het verwerkingsregister (CSV). Gebruikt de ingevulde
 * bewaartermijn per doel en de gekozen grondslag van het contactformulier.
 */
function cm_register_csv_rows( array $pv, $retention_months, $date ) {
    $pv      = array_merge( cm_default_privacy(), $pv );
    $company = (string) $pv['pv_bedrijfsnaam'];
    $empty   = array( '', '', '', '', '', '', '' );
    $rows    = array(
        array( 'Verwerkingsregister — ' . $company, 'Gegenereerd door Cookiebaas', 'Datum: ' . $date, '', '', '', '' ),
        $empty,
        array( 'Verwerkingsactiviteit', 'Categorie betrokkenen', 'Doel', 'Rechtsgrondslag', 'Ontvangers / Verwerkers', 'Bewaartermijn', 'Internationale doorgifte' ),
    );
    $recipients = '';
    foreach ( cm_rows_decode( $pv['pv_ontvangers'] ) as $o ) {
        if ( ! empty( $o['partij'] ) ) $recipients .= $o['partij'] . ' (' . ( isset( $o['locatie'] ) ? $o['locatie'] : '' ) . '); ';
    }
    $transfer = (string) $pv['pv_doorgifte'] !== '' ? (string) $pv['pv_doorgifte'] : 'Nee / Niet van toepassing';
    foreach ( cm_rows_decode( $pv['pv_doeleinden'] ) as $d ) {
        $rows[] = array(
            isset( $d['doel'] ) ? $d['doel'] : '',
            isset( $d['categorie'] ) ? $d['categorie'] : 'Websitebezoekers',
            isset( $d['doel'] ) ? $d['doel'] : '',
            isset( $d['grondslag'] ) ? $d['grondslag'] : '',
            $recipients !== '' ? $recipients : '—',
            ! empty( $d['termijn'] ) ? $d['termijn'] : '—',
            $transfer,
        );
    }
    $months = (int) $retention_months;
    $rows[] = array( 'Cookietoestemming registratie', 'Websitebezoekers', 'Vastleggen en bewaren van toestemming voor cookies (AVG art. 7 verantwoordingsplicht)', 'Wettelijke verplichting (AVG art. 7 lid 1)', 'Cookiebaas plugin / ' . $company, $months > 0 ? $months . ' maanden' : 'Niet ingesteld', 'Nee — opslag op eigen server' );
    $rows[] = array( 'Contactformulier', 'Contactpersonen / Klanten', 'Beantwoorden van contactverzoeken', (string) $pv['pv_cf_grondslag'], $company, (string) $pv['pv_bewaar_contact'] !== '' ? (string) $pv['pv_bewaar_contact'] : '—', 'Nee' );
    $rows[] = $empty;
    $rows[] = array( 'Verwerkingsverantwoordelijke', $company, (string) $pv['pv_straat'], (string) $pv['pv_postcode_plaats'], 'E-mail: ' . $pv['pv_email'], '', '' );
    if ( (string) $pv['pv_dpo_enabled'] === '1' ) {
        $rows[] = array( 'Functionaris Gegevensbescherming (DPO)', (string) $pv['pv_dpo_naam'], (string) $pv['pv_dpo_email'], (string) $pv['pv_dpo_telefoon'], '', '', '' );
    }
    return $rows;
}

/** Tabs van de privacyverklaring, in de volgorde van de uitvoer. */
function cm_privacy_tab_labels() {
    return array(
        'bedrijf'      => 'Bedrijf',
        'verwerkingen' => 'Verwerkingen',
        'delen'        => 'Delen en bewaren',
        'rechten'      => 'Rechten',
        'weergave'     => 'Weergave',
    );
}

/** Eén tab per groep secties; elke tab slaat alleen zijn eigen velden op (de callback voegt samen). */
function cm_tabs_privacy() {
    $sections = cm_privacy_sections();
    $tabs     = array();
    foreach ( cm_privacy_tab_labels() as $key => $label ) {
        $tabs[ $key ] = array(
            'label'         => $label,
            'group'         => 'cookiebaas_privacy',
            'values'        => 'cm_privacy_values',
            'title_actions' => 'cm_privacy_title_actions',
            'premium'       => 'De privacyverklaring bewerken vraagt een licentie. Een verklaring die al op uw website staat, blijft daar gewoon zichtbaar.',
            'sections'      => array_values( array_filter( $sections, function ( $s ) use ( $key ) { return $s['tab'] === $key; } ) ),
        );
    }
    return $tabs;
}

/** Opslaan via options.php alleen met licentie (zonder licentie staat het formulier er niet eens). */
function cm_privacy_option_capability( $cap ) {
    return ( function_exists( 'cm_license_is_valid' ) && cm_license_is_valid() ) ? $cap : 'do_not_allow';
}
add_filter( 'option_page_capability_cookiebaas_privacy', 'cm_privacy_option_capability' );

function cm_privacy_values() {
    $saved = get_option( 'cm_privacy', array() );
    return array_merge( cm_default_privacy(), is_array( $saved ) ? $saved : array() );
}

function cm_privacy_title_actions() {
    echo '<a class="page-title-action" href="' . esc_url( cm_admin_action_url( 'export_register' ) ) . '">Verwerkingsregister exporteren</a> ';
    echo cm_admin_action_form( 'reset_privacy', 'Standaardtekst herstellen', array(), 'Alle teksten van de privacyverklaring terugzetten naar de standaard?', 'page-title-action' );
}

/** Secties in de volgorde van de uitvoer; bedrijfsgegevens en DPO bovenaan. */
function cm_privacy_sections() {
    $p = function ( $key, $type, $label, array $extra = array() ) {
        return cm_field( $key, $type, $label, array_merge( array( 'option' => 'cm_privacy' ), $extra ) );
    };
    $bases = cm_avg_grondslagen();
    return array(
        array( 'tab' => 'bedrijf', 'title' => 'Waar verschijnt de verklaring?', 'content' => function () {
            $url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
            echo '<p>Plaats de shortcode <code>[cookiebaas_privacy]</code> op uw privacypagina. De cookietabel staat ook los beschikbaar als <code>[cookiebaas_cookies]</code>. Lege velden worden niet getoond.';
            if ( $url ) echo ' Uw privacypagina: <a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>.';
            echo ' Alle shortcodes staan onder <a href="' . esc_url( admin_url( 'admin.php?page=cookiebaas-beheer&tab=info' ) ) . '">Beheer › Info</a>.</p>';
        } ),
        array( 'tab' => 'bedrijf', 'title' => 'Bedrijfsgegevens', 'fields' => array(
            $p( 'pv_bedrijfsnaam', 'text', 'Bedrijfsnaam' ),
            $p( 'pv_straat', 'text', 'Straat en huisnummer' ),
            $p( 'pv_postcode_plaats', 'text', 'Postcode en plaats', array( 'placeholder' => '1234 AB Amsterdam' ) ),
            $p( 'pv_land', 'text', 'Land' ),
            $p( 'pv_kvk', 'text', 'KVK-nummer', array( 'optional' => true ) ),
            $p( 'pv_telefoon', 'text', 'Telefoonnummer', array( 'optional' => true ) ),
            $p( 'pv_email', 'text', 'E-mailadres' ),
            $p( 'pv_versie', 'text', 'Versienummer', array( 'class' => 'small-text', 'placeholder' => '1.0' ) ),
            $p( 'pv_datum', 'text', 'Datum bijgewerkt', array( 'placeholder' => '1 januari 2026' ) ),
        ) ),
        array( 'tab' => 'bedrijf', 'title' => 'Functionaris Gegevensbescherming (DPO)', 'fields' => array(
            $p( 'pv_dpo_enabled', 'checkbox', 'DPO-sectie', array(
                'checkbox_label' => 'Wij hebben een Functionaris Gegevensbescherming aangesteld',
                'description'    => 'Verplicht voor overheidsorganisaties en organisaties die op grote schaal bijzondere persoonsgegevens verwerken (AVG art. 37). Voor de meeste mkb-organisaties optioneel.',
            ) ),
            $p( 'pv_dpo_naam', 'text', 'Naam', array( 'placeholder' => 'Voornaam Achternaam', 'show_if' => array( 'pv_dpo_enabled' => '1' ) ) ),
            $p( 'pv_dpo_email', 'text', 'E-mailadres', array( 'placeholder' => 'dpo@uwbedrijf.nl', 'show_if' => array( 'pv_dpo_enabled' => '1' ) ) ),
            $p( 'pv_dpo_telefoon', 'text', 'Telefoonnummer', array( 'optional' => true, 'show_if' => array( 'pv_dpo_enabled' => '1' ) ) ),
        ) ),
        array( 'tab' => 'verwerkingen', 'title' => '1. Inleiding', 'fields' => array(
            $p( 'pv_inleiding_naam', 'text', 'Naam in de inleiding', array(
                'placeholder' => 'Leeg = de bedrijfsnaam',
                'description' => 'Verschijnt als: <em>“<strong>[naam]</strong> respecteert uw privacy…”</em>',
            ) ),
        ) ),
        array( 'tab' => 'verwerkingen', 'title' => '2.1 Contactformulier', 'fields' => array(
            $p( 'pv_cf_fields', 'checkgroup', 'Verzamelde velden', array(
                'keys'        => array(
                    'pv_cf_voornaam'   => 'Voornaam',
                    'pv_cf_achternaam' => 'Achternaam',
                    'pv_cf_email'      => 'E-mailadres',
                    'pv_cf_website'    => 'Website (optioneel)',
                    'pv_cf_telefoon'   => 'Telefoonnummer (optioneel)',
                    'pv_cf_bericht'    => 'Uw bericht',
                    'pv_cf_bedrijf'    => 'Bedrijfsnaam (optioneel)',
                    'pv_cf_adres'      => 'Adresgegevens (optioneel)',
                    'pv_cf_privacy'    => 'Acceptatie privacyverklaring',
                ),
                'description' => 'Vink alles uit als u geen contactformulier heeft; de verklaring meldt dat dan.',
            ) ),
            $p( 'pv_cf_extra', 'textarea', 'Eigen velden', array(
                'rows'        => 3,
                'placeholder' => "Eén veld per regel, bijvoorbeeld:\nFactuurnummer\nProjectnaam",
                'description' => 'Optioneel: eigen veldnamen, één per regel. Ze worden vermeld in de verklaring.',
            ) ),
            $p( 'pv_cf_grondslag', 'select', 'Rechtsgrondslag', array( 'options' => $bases, 'context' => '2.1 Contactformulier', 'description' => 'Verplicht te vermelden (Art. 13 lid 1c AVG).' ) ),
        ) ),
        array( 'tab' => 'verwerkingen', 'title' => '2.3 Nieuwsbrief en e-mailmarketing', 'fields' => array(
            $p( 'pv_nieuwsbrief_enabled', 'checkbox', 'Nieuwsbrief', array( 'checkbox_label' => 'Wij versturen een nieuwsbrief of marketing-e-mails' ) ),
            $p( 'pv_nieuwsbrief_grondslag', 'select', 'Rechtsgrondslag', array( 'options' => $bases, 'context' => '2.3 Nieuwsbrief', 'show_if' => array( 'pv_nieuwsbrief_enabled' => '1' ) ) ),
            $p( 'pv_nieuwsbrief_afmelden', 'text', 'Afmeldpagina', array(
                'optional'    => true,
                'placeholder' => 'https://…',
                'description' => 'Leeg = de standaardtekst “via de afmeldlink onderaan elke e-mail”.',
                'show_if'     => array( 'pv_nieuwsbrief_enabled' => '1' ),
            ) ),
        ) ),
        array( 'tab' => 'verwerkingen', 'title' => '3. Doeleinden en grondslagen', 'fields' => array(
            $p( 'pv_doeleinden', 'rows', 'Doeleinden', array(
                'columns'   => array(
                    'doel'      => array( 'label' => 'Doel' ),
                    'grondslag' => array( 'label' => 'Grondslag', 'suggestions' => array_keys( $bases ) ),
                    'termijn'   => array( 'label' => 'Bewaartermijn' ),
                ),
                'add_label' => 'Rij toevoegen',
            ) ),
        ) ),
        array(
            'tab'    => 'verwerkingen',
            'title'  => '4. Cookies',
            'intro'  => 'De cookietabel in de verklaring komt uit <a href="' . esc_url( admin_url( 'admin.php?page=cookiebaas-cookies' ) ) . '">Cookies › Cookielijst</a>.',
            'fields' => array(
                $p( 'pv_gtm', 'checkbox', 'Google Tag Manager', array( 'checkbox_label' => 'Vermeld dat de website Google Tag Manager gebruikt' ) ),
                $p( 'pv_optout_links', 'rows', 'Opt-out-links', array(
                    'columns'   => array(
                        'naam' => array( 'label' => 'Naam' ),
                        'url'  => array( 'label' => 'URL', 'type' => 'url', 'placeholder' => 'https://…' ),
                    ),
                    'add_label' => 'Link toevoegen',
                ) ),
            ),
        ),
        array( 'tab' => 'delen', 'title' => '5. Ontvangers van persoonsgegevens', 'fields' => array(
            $p( 'pv_ontvangers', 'rows', 'Ontvangers', array(
                'columns'   => array(
                    'partij'  => array( 'label' => 'Partij' ),
                    'doel'    => array( 'label' => 'Doel' ),
                    'locatie' => array( 'label' => 'Locatie', 'placeholder' => 'NL / VS / VK' ),
                ),
                'add_label' => 'Partij toevoegen',
            ) ),
        ) ),
        array( 'tab' => 'delen', 'title' => '6. Internationale doorgifte', 'fields' => array(
            $p( 'pv_doorgifte', 'textarea', 'Eigen tekst', array( 'optional' => true, 'description' => 'Leeg = de standaardtekst.' ) ),
        ) ),
        array( 'tab' => 'delen', 'title' => '7. Bewaartermijnen', 'fields' => array(
            $p( 'pv_bewaar_contact', 'text', 'Contactformulier', array( 'placeholder' => '3 jaar na laatste contact' ) ),
            $p( 'pv_bewaar_logs', 'text', 'Serverlogbestanden', array( 'placeholder' => 'maximaal 6 maanden' ) ),
            $p( 'pv_bewaar_analytics', 'text', 'Analytische gegevens', array( 'placeholder' => 'Zie sectie 4 (per cookie)' ) ),
            $p( 'pv_bewaar_nieuwsbrief', 'text', 'Nieuwsbriefabonnement', array( 'optional' => true, 'placeholder' => 'Tot afmelding + 1 jaar' ) ),
        ) ),
        array( 'tab' => 'rechten', 'title' => '8. Uw rechten', 'fields' => array(
            $p( 'pv_rechten_email', 'text', 'E-mailadres voor verzoeken', array( 'placeholder' => 'Leeg = het algemene e-mailadres' ) ),
            $p( 'pv_rechten_termijn', 'text', 'Reactietermijn', array( 'placeholder' => 'één maand' ) ),
        ) ),
        array( 'tab' => 'rechten', 'title' => '10. Klachten', 'fields' => array(
            $p( 'pv_ap_tonen', 'checkbox', 'Autoriteit Persoonsgegevens', array( 'checkbox_label' => 'Toon het adres van de Autoriteit Persoonsgegevens' ) ),
        ) ),
        array( 'tab' => 'rechten', 'title' => '11. Wijzigingen', 'fields' => array(
            $p( 'pv_wijzigingen_extra', 'textarea', 'Aanvullende tekst', array( 'optional' => true, 'rows' => 3 ) ),
        ) ),
        array( 'tab' => 'rechten', 'title' => '12. Geautomatiseerde besluitvorming', 'fields' => array(
            $p( 'pv_profilering_enabled', 'checkbox', 'Profilering', array(
                'checkbox_label' => 'Wij gebruiken geautomatiseerde besluitvorming of profilering (Art. 22 AVG)',
                'description'    => 'Verplicht te vermelden als u profilering of geautomatiseerde besluitvorming toepast, bijvoorbeeld remarketing via Google of Meta.',
            ) ),
            $p( 'pv_profilering_tekst', 'textarea', 'Eigen tekst', array( 'optional' => true, 'placeholder' => 'Leeg = de standaardtekst.', 'show_if' => array( 'pv_profilering_enabled' => '1' ) ) ),
        ) ),
        array(
            'tab'    => 'weergave',
            'title'  => 'Weergave van de cookietabellen',
            'intro'  => 'Kleuren van de tabellen in <code>[cookiebaas_privacy]</code> en <code>[cookiebaas_cookies]</code>, los van uw thema.',
            'fields' => array(
                $p( 'pv_table_header_bg', 'color', 'Koprij — achtergrond' ),
                $p( 'pv_table_header_color', 'color', 'Koprij — tekst' ),
                $p( 'pv_table_border', 'color', 'Randen' ),
                $p( 'pv_table_row_bg', 'color', 'Rij — achtergrond' ),
                $p( 'pv_table_row_alt_bg', 'color', 'Rij — achtergrond (om en om)' ),
                $p( 'pv_table_text', 'color', 'Tekst' ),
            ),
        ),
    );
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'export_register', function () {
        if ( cm_admin_require_license() !== '' ) return 'premium-required';
        cm_admin_send_csv(
            'verwerkingsregister-' . wp_date( 'Y-m-d' ) . '.csv',
            cm_register_csv_rows( (array) get_option( 'cm_privacy', array() ), cm_get( 'log_retention_months' ), wp_date( 'd-m-Y' ) )
        );
    } );
    cm_admin_register_action( 'reset_privacy', function () {
        if ( cm_admin_require_license() !== '' ) return 'premium-required';
        update_option( 'cm_privacy', cm_default_privacy() );
        return 'privacy-reset';
    } );
}
