<?php
/**
 * Gedeelde blocker-beslislogica (cm_blocker_config / cm_blocker_match).
 *
 * Sinds v2.1.0 volgen de server-side buffer én de browser-runtime dezelfde
 * regels uit één bron. Deze matrix legt de beslissing vast; de JS-spiegel
 * (cmMatch) wordt bovendien end-to-end gecontroleerd door tests/smoke.mjs en —
 * bij ontwikkeling — door een node-pariteitscheck op dezelfde matrix.
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

function cfg_for( $advanced ) {
    cm_test_set_settings( array(
        'google_consent_mode_advanced' => $advanced ? 1 : 0,
        'block_analytics_patterns'     => '',
        'block_marketing_patterns'     => '',
    ) );
    return cm_blocker_config();
}

// Matrix: [omschrijving, src, text, advanced, allowA, allowM, verwacht]
$cases = array(
    array( 'extern Hotjar (adv, geen consent)',        'https://static.hotjar.com/c/hotjar-1.js', '', true,  false, false, 'analytics' ),
    array( 'extern Facebook (adv)',                    'https://connect.facebook.net/en_US/fbevents.js', '', true, false, false, 'marketing' ),
    array( 'extern GTM (adv) — Google-uitzondering',   'https://www.googletagmanager.com/gtm.js?id=GTM-X', '', true, false, false, false ),
    array( 'extern GTM (basic) — wél blokkeren',       'https://www.googletagmanager.com/gtm.js?id=GTM-X', '', false, false, false, 'analytics' ),
    array( 'inline GTM-heuristiek (basic)',            '', '(function(){window.dataLayer=[];gtag("config","GTM-X");})()', false, false, false, 'analytics' ),
    array( 'inline GTM-heuristiek (adv) — overslaan',  '', '(function(){window.dataLayer=[];gtag("config","GTM-X");})()', true,  false, false, false ),
    array( 'inline fbq (adv)',                         '', 'fbq("init","123");fbq("track","PageView");', true,  false, false, 'marketing' ),
    array( 'inline fbq (basic)',                       '', 'fbq("init","123");', false, false, false, 'marketing' ),
    array( 'plugin-eigen (cc_cm_consent)',             '', 'var x="cc_cm_consent";', false, false, false, false ),
    array( 'plugin-eigen gtag-stub (dataLayer.push(arguments))', '', 'function gtag(){dataLayer.push(arguments);}', false, false, false, false ),
    array( 'Hotjar maar analytics al toegestaan',      'https://static.hotjar.com/c/hotjar-1.js', '', true, true, false, false ),
    array( 'Facebook maar marketing al toegestaan',    'https://connect.facebook.net/en_US/fbevents.js', '', true, false, true, false ),
    array( 'neutraal script (jQuery)',                 'https://code.jquery.com/jquery.min.js', '', true, false, false, false ),
);

cm_test_group( 'Blocker-beslislogica (matrix)' );
foreach ( $cases as $c ) {
    list( $desc, $src, $text, $adv, $allowA, $allowM, $expect ) = $c;
    $cfg = cfg_for( $adv );
    $got = cm_blocker_match( $src, $text, $cfg, array( 'analytics' => $allowA, 'marketing' => $allowM ) );
    $ok  = ( $got === $expect );
    cm_assert( $desc . '  →  ' . var_export( $got, true ), $ok );
}

// Exporteer de matrix zodat de node-pariteitscheck exact dezelfde cases draait.
if ( getenv( 'CM_DUMP_MATRIX' ) ) {
    $dump = array();
    foreach ( $cases as $c ) {
        list( $desc, $src, $text, $adv, $allowA, $allowM, $expect ) = $c;
        $cfg = cfg_for( $adv );
        $dump[] = array( 'cfg' => $cfg, 'src' => $src, 'text' => $text, 'allowA' => $allowA, 'allowM' => $allowM, 'expect' => $expect === false ? null : $expect );
    }
    file_put_contents( getenv( 'CM_DUMP_MATRIX' ), json_encode( $dump ) );
}

exit( cm_test_summary() );
