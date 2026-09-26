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

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'export_register', function () {
        cm_admin_send_csv(
            'verwerkingsregister-' . wp_date( 'Y-m-d' ) . '.csv',
            cm_register_csv_rows( (array) get_option( 'cm_privacy', array() ), cm_get( 'log_retention_months' ), wp_date( 'd-m-Y' ) )
        );
    } );
    cm_admin_register_action( 'reset_privacy', function () {
        update_option( 'cm_privacy', cm_default_privacy() );
        return 'privacy-reset';
    } );
}
