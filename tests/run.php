<?php
/**
 * Testrunner voor Cookiebaas — geen externe afhankelijkheden.
 *
 *   php tests/run.php
 *
 * Doet twee dingen:
 *   1. Lint alle PHP-bestanden van de plugin (php -l).
 *   2. Draait elk tests/test-*.php in een eigen PHP-proces (isolatie van
 *      globale staat) en telt de uitkomsten op.
 *
 * Exit-code is 0 als alles slaagt, anders 1 — geschikt voor CI of een
 * pre-commit hook.
 */

$root = dirname( __DIR__ );
$php  = PHP_BINARY;
$fail = 0;

fwrite( STDOUT, "\n=== 1. PHP lint ===\n" );
$lint_targets = array_merge(
    array( $root . '/cookiemelding.php' ),
    glob( $root . '/includes/*.php' )
);
foreach ( $lint_targets as $file ) {
    $out = array(); $code = 0;
    exec( escapeshellarg( $php ) . ' -l ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
    $rel = str_replace( $root . '/', '', $file );
    if ( $code === 0 ) {
        fwrite( STDOUT, "  \033[32mOK\033[0m    $rel\n" );
    } else {
        $fail++;
        fwrite( STDOUT, "  \033[31mFAIL\033[0m  $rel\n    " . implode( "\n    ", $out ) . "\n" );
    }
}

fwrite( STDOUT, "\n=== 2. Testsuites ===\n" );
$suites = glob( __DIR__ . '/test-*.php' );
sort( $suites );
foreach ( $suites as $suite ) {
    $name = basename( $suite );
    $out  = array(); $code = 0;
    exec( escapeshellarg( $php ) . ' ' . escapeshellarg( $suite ) . ' 2>&1', $out, $code );

    // Laatste RESULT-regel oppikken voor de samenvatting
    $result = '';
    foreach ( array_reverse( $out ) as $line ) {
        if ( strpos( $line, 'RESULT:' ) !== false ) { $result = trim( preg_replace( '/\033\[[0-9]*m/', '', $line ) ); break; }
    }
    if ( $code === 0 ) {
        fwrite( STDOUT, "  \033[32mPASS\033[0m  $name — $result\n" );
    } else {
        $fail++;
        fwrite( STDOUT, "  \033[31mFAIL\033[0m  $name — $result\n" );
        // Toon de gefaalde assertions
        foreach ( $out as $line ) {
            if ( strpos( $line, 'FAIL' ) !== false ) {
                fwrite( STDOUT, '      ' . trim( preg_replace( '/\033\[[0-9]*m/', '', $line ) ) . "\n" );
            }
        }
    }
}

fwrite( STDOUT, "\n" . ( $fail
    ? "\033[31m✗ $fail onderdeel(en) gefaald\033[0m\n"
    : "\033[32m✓ Alles groen\033[0m\n" ) );
exit( $fail ? 1 : 0 );
