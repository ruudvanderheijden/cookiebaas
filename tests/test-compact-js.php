<?php
/**
 * Compacte inline scripts (Lighthouse "JavaScript verkleinen").
 *
 * Borgt: cm_compact_js() haalt alleen inspringing, lege regels en regels met
 * alleen //-commentaar weg; de regeleindes blijven. Dat is alleen veilig
 * zolang de scripts geen meerregelige strings hebben (backticks of een
 * backslash aan het regeleinde). Is node aanwezig, dan controleert die ook
 * de syntax van elk gerenderd script.
 */

function is_singular() { return false; }
function get_pages() { return array(); }
function wp_kses_post( $s ) { return (string) $s; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $s ) ); }
function date_i18n( $f, $t = null ) { return 'date'; }
function current_user_can() { return false; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

cm_test_group( 'cm_compact_js' );
$in  = "<script>\n    // uitleg\n\n    var u = 'https://x.nl'; // staat achter code\n        if (a) {\n            b();\n        }\n</script>\n";
$out = cm_compact_js( $in );
cm_assert( 'inspringing, lege regels en //-regels weg', $out === "<script>\nvar u = 'https://x.nl'; // staat achter code\nif (a) {\nb();\n}\n</script>\n" );

cm_test_group( 'Gerenderde scripts' );
cm_test_set_settings( array( 'ga4_measurement_id' => 'G-TEST123', 'gtm_container_id' => 'GTM-TEST123', 'google_consent_mode_advanced' => 1 ) );
ob_start();
cm_inject_google_consent_mode();
cm_output_script_blocker();
$GLOBALS['cm_rendered'] = false;
cm_render_frontend();
$html = ob_get_clean();

preg_match_all( '#<script[^>]*>(.*?)</script>#s', $html, $m );
$scripts = array_filter( $m[1], 'strlen' );
$main = '';
foreach ( $scripts as $js ) if ( strlen( $js ) > strlen( $main ) ) $main = $js;
cm_assert( 'consent mode, blocker en hoofdscript gerenderd', count( $scripts ) >= 4 && strpos( $html, 'id="cm-blocker"' ) !== false && strpos( $main, 'cc_cm_consent' ) !== false );
cm_assert( 'geen backticks (meerregelige strings breken bij het trimmen)', strpos( implode( '', $scripts ), '`' ) === false );
cm_assert( 'geen regel eindigt op een backslash', ! preg_match( '/\\\\$/m', implode( "\n", $scripts ) ) );
cm_assert( 'geen scriptregel begint met inspringing', ! preg_match( '/^[ \t]/m', implode( "\n", $scripts ) ) );
cm_assert( 'hoofdscript onder 35 KB (was 50 KB)', strlen( $main ) < 35000 );

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( $node ) {
    $bad = array();
    foreach ( $scripts as $i => $js ) {
        $f = tempnam( sys_get_temp_dir(), 'cmjs' ) . '.js';
        file_put_contents( $f, $js );
        exec( escapeshellarg( $node ) . ' --check ' . escapeshellarg( $f ) . ' 2>&1', $o, $rc );
        if ( $rc !== 0 ) $bad[] = $i;
        unlink( $f );
    }
    cm_assert( 'node --check: alle scripts geldige JavaScript', ! $bad );
} else {
    echo "  (node niet gevonden: syntaxcontrole overgeslagen)\n";
}

exit( cm_test_summary() );
