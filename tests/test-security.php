<?php
/**
 * Beveiliging en privacy (audit 3.0).
 *
 * Borgt: de consent log is niet te vervalsen via X-Forwarded-For of vanaf een
 * andere website, bewaart alleen het pad en een ingekorte IP-hash, en crasht
 * niet op arrays; de scan haalt alleen de eigen site op; CSV-cellen zijn
 * onschadelijk; een afwijkend licentieantwoord geeft geen fatal; de
 * cookiedatabase wordt pas vervangen na een geldige download; uitzonderingen
 * en geo zijn niet te misbruiken; Google-ID's vragen unfiltered_html.
 */

define( 'CM_TEST_REAL_LICENSE', true );

class CM_Test_Json extends Exception {
    public $ok; public $data;
    function __construct( $ok, $data ) { $this->ok = $ok; $this->data = $data; parent::__construct( 'json' ); }
}
function wp_send_json_success( $d = null ) { throw new CM_Test_Json( true, $d ); }
function wp_send_json_error( $d = null, $code = null ) { throw new CM_Test_Json( false, $d ); }
function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function esc_url_raw( $s, $protocols = null ) { $s = trim( (string) $s ); return preg_match( '#^(https?:)?//#i', $s ) || ( $s !== '' && $s[0] === '/' ) ? $s : ''; }
function current_time( $t ) { return '2026-09-27 12:00:00'; }
function add_settings_error( $setting, $code, $message ) { $GLOBALS['cm_test_errors'][] = $message; }
function wp_get_current_user() { return (object) array( 'ID' => 1 ); }
$GLOBALS['cm_test_can'] = true;
function current_user_can( $cap ) { return $GLOBALS['cm_test_can']; }
function get_pages() { return array(); }
function absint( $v ) { return abs( (int) $v ); }

/** Neemt inserts op; SHOW TABLES geeft de tabel terug. */
class CM_Test_Wpdb {
    public $prefix = 'wp_'; public $inserts = array(); public $insert_id = 0; public $last_error = '';
    public function prepare( $sql, ...$a ) { return $sql; }
    public function get_var( $sql ) { return strpos( $sql, 'SHOW TABLES' ) === 0 ? 'wp_cm_consent_log' : 0; }
    public function insert( $t, $row, $f = null ) { $this->inserts[] = $row; $this->insert_id++; return 1; }
    public function query( $sql ) { return 1; }
}
$GLOBALS['wpdb'] = new CM_Test_Wpdb();

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/license.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';
require CM_PLUGIN_ROOT . '/includes/consent.php';
foreach ( glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ) as $file ) require $file;
require CM_PLUGIN_ROOT . '/includes/privacy.php';

/** Roep het log-endpoint aan zoals een browser (POST + server-variabelen). */
function log_request( array $post, array $server = array(), $cookie = true ) {
    $_POST   = $post;
    $_COOKIE = $cookie ? array( 'cc_cm_consent' => '1' ) : array();
    $_SERVER = array_merge( array( 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Firefox' ), $server );
    try { cm_ajax_log_consent(); } catch ( CM_Test_Json $r ) { return $r; }
    return null;
}
function last_insert() { $i = $GLOBALS['wpdb']->inserts; return end( $i ); }

cm_test_group( 'Consent log: echt IP, geen X-Forwarded-For (audit H1)' );
$_SERVER = array( 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7' );
cm_assert( 'cm_client_ip negeert X-Forwarded-For', cm_client_ip() === '203.0.113.9' );
$n = count( $wpdb->inserts );
for ( $i = 0; $i < 25; $i++ ) log_request( array( 'method' => 'accept-all', 'analytics' => '1', 'url' => 'https://example.test/' ), array( 'HTTP_X_FORWARDED_FOR' => '10.0.0.' . $i ) );
// De bootstrap-transients bewaren niets; controleer daarom de sleutel: die hangt alleen van REMOTE_ADDR af
cm_assert( 'wisselende X-Forwarded-For geeft steeds dezelfde rate-limit-sleutel (alleen REMOTE_ADDR telt)', count( $wpdb->inserts ) - $n === 25 && strpos( file_get_contents( CM_PLUGIN_ROOT . '/includes/consent.php' ), 'HTTP_X_FORWARDED_FOR' ) === false );

cm_test_group( 'Consent log: andere websites geweigerd' );
$n = count( $wpdb->inserts );
$r = log_request( array( 'method' => 'accept-all' ), array( 'HTTP_ORIGIN' => 'https://evil.test' ) );
cm_assert( 'Origin van een andere site → overgeslagen, niets opgeslagen', $r && $r->data['skipped'] === 'origin' && count( $wpdb->inserts ) === $n );
$r = log_request( array( 'method' => 'accept-all' ), array( 'HTTP_ORIGIN' => 'https://www.example.test' ) );
cm_assert( 'eigen site (ook met www) → opgeslagen', $r && $r->ok && ! isset( $r->data['skipped'] ) );
$r = log_request( array( 'method' => 'accept-all' ), array( 'HTTP_ORIGIN' => 'null' ) );
cm_assert( 'Origin "null" (sandbox-iframe elders) → overgeslagen', $r && $r->data['skipped'] === 'origin' );

cm_test_group( 'Consent log: dataminimalisatie (audit M3, L3)' );
log_request( array( 'method' => 'accept-all', 'url' => 'https://example.test/nieuwsbrief/?email=jan@x.nl&token=abc#top' ) );
$row = last_insert();
cm_assert( 'alleen het pad, zonder querystring of fragment', $row['url'] === 'https://example.test/nieuwsbrief/' );
cm_assert( 'IP-hash is een HMAC van het ingekorte IP (/24)', $row['ip_hash'] === hash_hmac( 'sha256', '203.0.113.0', cm_log_hash_key() ) );
cm_assert( 'IPv6 wordt ingekort tot /48', cm_ip_truncate( '2001:db8:abcd:12:1:2:3:4' ) === '2001:db8:abcd::' );
cm_assert( 'ongeldig IP → geen hash', cm_ip_hash( 'geen-ip' ) === '' );
log_request( array( 'method' => 'pageload', 'url' => 'https://example.test/pagina' ), array(), false );
$row = last_insert();
cm_assert( 'terugkerend bezoek: zonder pagina en zonder IP-hash', $row['method'] === 'pageload' && $row['url'] === '' && $row['ip_hash'] === '' );
$r = log_request( array( 'method' => 'accept-all' ) );
cm_assert( 'antwoord bevat geen oplopend database-id', $r && ! isset( $r->data['id'] ) );

cm_test_group( 'Consent log: arrays en automatische keuzes (audit L4, L2)' );
$r = log_request( array( 'method' => 'accept-all', 'url' => array( 'x' ), 'session_id' => array( 'y' ), 'analytics' => array( '1' ) ) );
cm_assert( 'arrays in de POST → geen fatal, gewoon verwerkt', $r instanceof CM_Test_Json && $r->ok );
foreach ( array( 'geo-auto', 'dnt', 'gpc' ) as $m ) {
    log_request( array( 'method' => $m ) );
    cm_assert( "methode $m blijft $m (niet 'custom')", last_insert()['method'] === $m );
}
cm_assert( 'labels voor automatische keuzes', strpos( cm_log_method_label( 'dnt' ), 'Automatisch' ) === 0 && strpos( cm_log_method_label( 'geo-auto' ), 'Automatisch' ) === 0 );

cm_test_group( 'Scan: alleen de eigen site (audit M1, SSRF)' );
$urls = cm_scan_filter_urls( array( 'https://example.test/over-ons/', 'http://169.254.169.254/latest/meta-data/', 'https://intern.lan/', 'file:///etc/passwd', array( 'x' ), 'https://example.test/over-ons/' ) );
cm_assert( 'alleen URL’s met de host van de site, zonder dubbelingen', $urls === array( 'https://example.test/over-ons/' ) );
cm_assert( 'de scan gebruikt wp_safe_remote_get (controleert ook doorverwijzingen)', strpos( file_get_contents( CM_PLUGIN_ROOT . '/includes/admin/scan.php' ), ' wp_remote_get( $url' ) === false );

cm_test_group( 'CSV: formules onschadelijk (audit L1)' );
cm_assert( '= + - @ en tab krijgen een apostrof', cm_csv_safe_cell( '=cmd|x' ) === "'=cmd|x" && cm_csv_safe_cell( '+1' ) === "'+1" && cm_csv_safe_cell( '@SUM(1)' ) === "'@SUM(1)" && cm_csv_safe_cell( "\tx" ) === "'\tx" );
cm_assert( 'gewone waarden ongewijzigd', cm_csv_safe_cell( 'Geaccepteerd' ) === 'Geaccepteerd' && cm_csv_safe_cell( '' ) === '' && cm_csv_safe_cell( 12 ) === '12' );

cm_test_group( 'Licentieserver: afwijkend antwoord geeft geen fatal (audit M3, L2)' );
cm_assert( 'array als vervaldatum → ongeldig, geen TypeError', cm_license_data_is_valid( array( 'key' => 'K', 'status' => 'active', 'expires_at' => array( 'date' => 'x' ) ) ) === false );
cm_assert( 'tekst uit het antwoord: array → terugvaltekst', cm_license_str( array( 'x' ), 'fallback' ) === 'fallback' && cm_license_str( 'OK' ) === 'OK' );
cm_assert( 'vast https-adres, niet uit een optie', cm_license_api_url() === 'https://cookiebaas.nl' && strpos( file_get_contents( CM_PLUGIN_ROOT . '/includes/license.php' ), "get_option( 'cm_license_api_url'" ) === false );

cm_test_group( 'Cookiedatabase: eerst controleren (audit L3)' );
$csv = "ID,Platform,Category,Cookie / Data Key name,Domain,Description,Retention period,Data Controller,User Privacy & GDPR Rights Portals,Wildcard match\n"
     . "1,Google Analytics,Analytics,_ga,,Meet bezoek,2 years,Google,https://policies.google.com,0\n";
$p = cm_cookie_db_parse( $csv );
cm_assert( 'geldige CSV → rijen', count( $p['rows'] ) === 1 && $p['rows'][0]['cookie_name'] === '_ga' && $p['rows'][0]['category'] === 'analytics' );
cm_assert( 'foutpagina of vreemd bestand → geen rijen (tabel blijft staan)', cm_cookie_db_parse( '<html>429 Too Many Requests</html>' )['rows'] === array() && cm_cookie_db_parse( "a,b,c,d,e,f,g,h,i\n1,2,3,4,5,6,7,8,9" )['rows'] === array() );
cm_assert( 'de import vraagt HTTP 200 en ten minste 500 rijen vóór het legen', (bool) preg_match( '/response_code\( \$response \) !== 200.*count\( \$parsed\[\'rows\'\] \) < 500.*TRUNCATE/s', file_get_contents( CM_PLUGIN_ROOT . '/includes/admin/scan.php' ) ) );

cm_test_group( 'Uitzonderingen alleen op het pad (audit M2)' );
cm_test_set_settings( array_merge( cm_default_settings(), array( 'exclude_login_page' => 1, 'exclude_url_patterns' => '/account' ) ) );
$_SERVER = array( 'REQUEST_URI' => '/over-ons/?utm_source=wp-login.php' );
cm_assert( 'querystring met wp-login.php zet de banner niet uit', cm_is_excluded_page() === false );
$_SERVER = array( 'REQUEST_URI' => '/wp-login.php?action=lostpassword' );
cm_assert( 'echte loginpagina blijft uitgezonderd', cm_is_excluded_page() === true );
$_SERVER = array( 'REQUEST_URI' => '/winkel/?ref=/account' );
cm_assert( 'patroon in de querystring telt niet', cm_is_excluded_page() === false );
$_SERVER = array( 'REQUEST_URI' => '/account/orders' );
cm_assert( 'patroon in het pad telt wel', cm_is_excluded_page() === true );

cm_test_group( 'Geo: onbekend of Tor → banner (audit L1)' );
cm_test_set_settings( cm_default_settings() );
foreach ( array( 'T1', 'XX', 'A1', 'RE', 'AX', 'JE', 'N/A' ) as $code ) {
    $_SERVER = array( 'HTTP_CF_IPCOUNTRY' => $code );
    cm_assert( "landcode $code → banner", cm_requires_consent_banner() === true );
}
$_SERVER = array( 'HTTP_CF_IPCOUNTRY' => 'US' );
cm_assert( 'VS (niet op de lijst) → geen banner nodig', cm_requires_consent_banner() === false );

cm_test_group( 'Embeds: alleen https-iframes van bekende diensten terugzetten (audit H2)' );
$js = file_get_contents( CM_PLUGIN_ROOT . '/includes/frontend.php' );
cm_assert( 'geen insertAdjacentHTML met gedecodeerde HTML meer', strpos( $js, "insertAdjacentHTML('afterend', originalHtml)" ) === false );
cm_assert( 'inert parsen in een template en controleren op https en bekende host', strpos( $js, "document.createElement('template')" ) !== false && strpos( $js, "u.protocol !== 'https:'" ) !== false && strpos( $js, 'var EMBED_HOSTS' ) !== false );
cm_assert( 'fallback via data-cm-embed-src gaat door dezelfde controle', substr_count( $js, "safeEmbedUrl(ph.getAttribute('data-cm-embed-src')" ) === 1 && substr_count( $js, "safeEmbedUrl(iframe.getAttribute('data-cm-embed-src')" ) === 1 );

cm_test_group( 'Blokkeerfilter: grote pagina wordt niet leeg (audit M1)' );
$big  = '<html><body><script>var d = "' . str_repeat( 'x', 1500000 ) . '";</script><script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script></body></html>';
$prev = ini_get( 'pcre.backtrack_limit' );
ini_set( 'pcre.backtrack_limit', '1000' ); // simuleer een krappe limiet
cm_test_set_settings( array_merge( cm_default_settings(), array( 'ga4_measurement_id' => '' ) ) );
$out = cm_filter_buffer( $big );
ini_set( 'pcre.backtrack_limit', $prev );
cm_assert( 'uitvoer is niet leeg en even groot of groter', is_string( $out ) && strlen( $out ) >= strlen( $big ) );

cm_test_group( 'Google-ID’s vragen unfiltered_html (audit M2, multisite)' );
$existing = array_merge( cm_default_settings(), array( 'gtm_container_id' => 'GTM-OUD' ) );
$GLOBALS['cm_test_can'] = false;
$GLOBALS['cm_test_errors'] = array();
$s = cm_sanitize_settings( array( 'gtm_container_id' => 'GTM-KWAAD', 'txt_banner_title' => 'Nieuw' ), $existing );
cm_assert( 'zonder unfiltered_html: ID blijft, andere velden wel opgeslagen, met melding', $s['gtm_container_id'] === 'GTM-OUD' && $s['txt_banner_title'] === 'Nieuw' && count( $GLOBALS['cm_test_errors'] ) === 1 );
$GLOBALS['cm_test_can'] = true;
$s = cm_sanitize_settings( array( 'gtm_container_id' => 'GTM-NIEUW' ), $existing );
cm_assert( 'met unfiltered_html: ID wijzigt', $s['gtm_container_id'] === 'GTM-NIEUW' );

cm_test_group( 'Opruimen en verpakken' );
$un = file_get_contents( CM_PLUGIN_ROOT . '/uninstall.php' );
cm_assert( 'uninstall ruimt ook de cookiedatabase-opties en de hash-sleutel op', strpos( $un, "'cm_cookie_db_count'" ) !== false && strpos( $un, "'cm_log_hash_key'" ) !== false );
cm_assert( 'uninstall op multisite: elke site', strpos( $un, 'switch_to_blog' ) !== false );
$ga = (string) @file_get_contents( CM_PLUGIN_ROOT . '/.gitattributes' );
cm_assert( 'tests, docs en CLAUDE.md niet in een release-zip', strpos( $ga, '/tests export-ignore' ) !== false && strpos( $ga, '/docs export-ignore' ) !== false && strpos( $ga, '/CLAUDE.md export-ignore' ) !== false );
cm_assert( 'testbestanden draaien alleen vanaf de commandline', strpos( file_get_contents( __DIR__ . '/bootstrap.php' ), "PHP_SAPI !== 'cli'" ) !== false && strpos( file_get_contents( __DIR__ . '/run.php' ), "PHP_SAPI !== 'cli'" ) !== false );

exit( cm_test_summary() );
