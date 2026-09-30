<?php
/**
 * Licentie bij een haperende verbinding (3.1.2).
 *
 * Borgt: geeft de licentieserver geen antwoord (time-out, of een pagina die
 * geen JSON is), dan is de sleutel daarmee niet "ongeldig" en blijft een
 * bestaande licentie staan; de melding legt uit dat het aan de verbinding
 * ligt. Deactiveren haalt de licentie altijd van de website af, ook de
 * sleutel, ook als de server niet bereikbaar is.
 */

define( 'CM_TEST_REAL_LICENSE', true );
function cm_purge_page_caches() {}
function cm_maybe_schedule_auto_scan_cron() {}

class WP_Error {
    private $m;
    public function __construct( $c = '', $m = '' ) { $this->m = $m; }
    public function get_error_message() { return $this->m; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
$GLOBALS['cm_test_http']  = array(); // wachtrij met antwoorden
$GLOBALS['cm_test_calls'] = array(); // aangeroepen endpoints
function wp_remote_post( $url, $args = array() ) {
    $GLOBALS['cm_test_calls'][] = basename( $url );
    return array_shift( $GLOBALS['cm_test_http'] );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function http_ok( array $json, $code = 200 ) { return array( 'code' => $code, 'body' => json_encode( $json ) ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/license.php';

$timeout = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 10001 milliseconds with 0 out of 0 bytes received' );
$active  = array( 'key' => 'CB-OUD', 'status' => 'active', 'expires_at' => gmdate( 'Y-m-d', time() + 86400 * 30 ), 'max_sites' => 1, 'domain' => 'example.test', 'last_check' => 1, 'last_success' => 1 );

cm_test_group( 'Activeren zonder antwoord van de server' );
delete_option( 'cm_license_data' );
$GLOBALS['cm_test_http'] = array( $timeout );
$r = cm_license_activate( 'CB-NIEUW' );
cm_assert( 'mislukt, met uitleg dat het aan de verbinding ligt en de technische melding erbij', $r['success'] === false && strpos( $r['error'], 'niet aan uw sleutel' ) !== false && strpos( $r['error'], 'cURL error 28' ) !== false );
cm_assert( 'de sleutel wordt niet als "ongeldig" opgeslagen', empty( cm_license_get()['key'] ) );

update_option( 'cm_license_data', $active );
$GLOBALS['cm_test_http']  = array( $timeout );
$GLOBALS['cm_test_calls'] = array();
cm_license_activate( 'CB-NIEUW' );
cm_assert( 'een bestaande actieve licentie blijft staan', cm_license_get()['key'] === 'CB-OUD' && cm_license_is_valid() );
cm_assert( 'de oude sleutel wordt niet vooraf bij de server afgemeld', $GLOBALS['cm_test_calls'] === array( 'activate' ) );

$GLOBALS['cm_test_http'] = array( array( 'code' => 403, 'body' => '<html>Geblokkeerd door de firewall</html>' ) );
$r = cm_license_activate( 'CB-NIEUW' );
cm_assert( 'een blokkadepagina (geen JSON) telt ook als verbindingsprobleem', $r['success'] === false && strpos( $r['error'], 'HTTP 403' ) !== false && cm_license_get()['key'] === 'CB-OUD' );

cm_test_group( 'Activeren met antwoord van de server' );
$GLOBALS['cm_test_http']  = array( http_ok( array( 'success' => true, 'expires_at' => '2030-01-01', 'max_sites' => 1 ) ), http_ok( array( 'success' => true ) ) );
$GLOBALS['cm_test_calls'] = array();
$r = cm_license_activate( 'CB-NIEUW' );
cm_assert( 'gelukt: nieuwe sleutel actief', $r['success'] === true && cm_license_get()['key'] === 'CB-NIEUW' && cm_license_is_valid() );
cm_assert( 'de oude sleutel wordt daarna afgemeld', $GLOBALS['cm_test_calls'] === array( 'activate', 'deactivate' ) );
$GLOBALS['cm_test_http'] = array( http_ok( array( 'success' => false, 'error' => 'Maximum aantal sites bereikt (1). Deactiveer eerst een domein.' ), 403 ) );
$r = cm_license_activate( 'CB-VOL' );
cm_assert( 'weigert de server, dan komt zijn reden door', $r['success'] === false && strpos( $r['error'], 'Maximum aantal sites' ) === 0 );

cm_test_group( 'Deactiveren verwijdert de licentie van de website' );
update_option( 'cm_license_data', $active );
$GLOBALS['cm_test_http'] = array( http_ok( array( 'success' => true, 'message' => 'ok' ) ) );
$r = cm_license_deactivate();
cm_assert( 'gelukt: ook de sleutel is weg', $r['success'] === true && empty( cm_license_get()['key'] ) && ! cm_license_is_valid() );
update_option( 'cm_license_data', $active );
$GLOBALS['cm_test_http'] = array( $timeout );
$r = cm_license_deactivate();
cm_assert( 'server niet bereikbaar: lokaal toch verwijderd, met waarschuwing dat het domein daar nog staat', $r['success'] === true && $r['remote'] === false && empty( cm_license_get()['key'] ) && strpos( $r['message'], 'nog als geactiveerd' ) !== false );
update_option( 'cm_license_data', $active );
$GLOBALS['cm_test_http'] = array( http_ok( array( 'success' => false, 'error' => 'Domein was niet gekoppeld aan deze licentie.' ), 404 ) );
$r = cm_license_deactivate();
cm_assert( 'domein stond niet bij de server: gewoon verwijderd, zonder waarschuwing', $r['success'] === true && ! isset( $r['remote'] ) && empty( cm_license_get()['key'] ) );

exit( cm_test_summary() );
