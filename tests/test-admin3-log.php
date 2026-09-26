<?php
/**
 * Consent log (3.0) — registraties: filters, zoeken, verwijderen.
 *
 * Borgt: het filter werkt serverside en 'Akkoord' telt embed-accept mee;
 * alleen geldige consent-ID's worden verwijderd; de bulkactie werkt ook
 * vanuit het onderste keuzemenu; de lijsttabel laadt niet zonder WordPress.
 */

function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}
function wp_nonce_url( $url, $action ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . '_wpnonce=nonce-' . $action; }

/** Neemt query's op; prepare() vult de placeholders zichtbaar in. */
class CM_Test_Wpdb {
    public $prefix  = 'wp_';
    public $queries = array();
    public $result  = 2;
    public function prepare( $sql, $args ) {
        foreach ( (array) $args as $a ) $sql = preg_replace( '/%[sd]/', is_int( $a ) ? (string) $a : "'" . addslashes( $a ) . "'", $sql, 1 );
        return $sql;
    }
    public function query( $sql ) { $this->queries[] = $sql; return $this->result; }
}
$GLOBALS['wpdb'] = new CM_Test_Wpdb();

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-log.php';

$good = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

cm_test_group( 'Filter en zoeken (serverside)' );
list( $sql, $args ) = cm_log_where( '', 'accept-all' );
cm_assert( 'akkoord telt embed-accept mee (zoals de statistiek)', $sql === 'WHERE method IN (%s,%s)' && $args === array( 'accept-all', 'embed-accept' ) );
list( $sql, $args ) = cm_log_where( '%abc%', 'reject-all' );
cm_assert( 'zoeken + filter combineren', $sql === 'WHERE consent_id LIKE %s AND method IN (%s)' && $args === array( '%abc%', 'reject-all' ) );
list( $sql, $args ) = cm_log_where( '', 'all' );
cm_assert( 'alles = geen WHERE', $sql === '' && $args === array() );
list( $sql, $args ) = cm_log_where( '', "x' OR 1=1" );
cm_assert( 'onbekend filter wordt genegeerd', $sql === '' && $args === array() );
$_GET = array( 'filter' => 'reject-all' );
cm_assert( 'bekend filter uit de URL', cm_log_current_filter() === 'reject-all' );
$_GET = array( 'filter' => 'bestaat-niet' );
cm_assert( 'onbekend filter uit de URL → alle', cm_log_current_filter() === 'all' );
$_GET = array();

cm_test_group( "Alleen geldige consent-ID's" );
cm_assert( 'geldige ID blijft, hoofdletters worden kleine letters', cm_log_valid_ids( array( strtoupper( $good ) ) ) === array( $good ) );
cm_assert( 'onzin, SQL en arrays vallen af', cm_log_valid_ids( array( "x' OR 1=1", array( $good ), '', 'a1b2' ) ) === array() );
cm_assert( 'dubbele ID telt één keer', cm_log_valid_ids( array( $good, $good ) ) === array( $good ) );
cm_assert( 'één ID als string werkt ook', cm_log_valid_ids( $good ) === array( $good ) );

cm_test_group( 'Bulkactie, boven of onder (Review Focus 1)' );
list( $a, $ids ) = cm_log_bulk_request( array( 'action' => 'cm_delete', 'consent' => array( $good, 'fout' ) ) );
cm_assert( 'bovenste keuzemenu', $a === 'cm_delete' && $ids === array( $good ) );
list( $a, $ids ) = cm_log_bulk_request( array( 'action' => '-1', 'action2' => 'cm_delete', 'consent' => array( $good ) ) );
cm_assert( 'onderste keuzemenu telt ook', $a === 'cm_delete' && $ids === array( $good ) );
list( $a, $ids ) = cm_log_bulk_request( array( 'action' => 'cm_delete' ) );
cm_assert( 'zonder selectie: actie wel, geen ID\'s', $a === 'cm_delete' && $ids === array() );
list( $a, $ids ) = cm_log_bulk_request( array() );
cm_assert( 'geen actie → -1', $a === '-1' && $ids === array() );

cm_test_group( 'Verwijderen' );
$n = cm_log_delete( array( $good, 'fout' ) );
cm_assert( 'één DELETE op de geldige ID', $n === 2 && strpos( end( $wpdb->queries ), "DELETE FROM `wp_cm_consent_log` WHERE consent_id IN ('" . $good . "')" ) === 0 );
$before = count( $wpdb->queries );
cm_assert( 'zonder geldige ID: geen query, 0', cm_log_delete( array( 'fout' ) ) === 0 && count( $wpdb->queries ) === $before );
$wpdb->result = false;
cm_assert( 'databasefout → false', cm_log_delete( array( $good ) ) === false );
$wpdb->result = 2;

cm_test_group( 'Rij-acties' );
$ra = cm_log_row_actions( $good );
cm_assert( 'Bewijs opent het detailscherm', strpos( $ra['proof'], 'page=cookiebaas-log' ) !== false && strpos( $ra['proof'], 'consent=' . $good ) !== false );
cm_assert( 'Verwijderen via admin-post, met eigen nonce en bevestiging', strpos( $ra['delete'], 'action=cm_delete_consent' ) !== false && strpos( $ra['delete'], 'nonce-cm_delete_consent' ) !== false && strpos( $ra['delete'], 'data-cm-confirm=' ) !== false );

cm_test_group( 'Labels' );
cm_assert( 'bekende methodes', cm_log_method_label( 'embed-accept' ) === 'Geaccepteerd via embed' && cm_log_method_label( 'pageload' ) === 'Terugkerend bezoek' );
cm_assert( 'onbekende methode blijft zichtbaar', cm_log_method_label( 'iets' ) === 'iets' );
cm_assert( 'toestemming per categorie', cm_log_categories_text( array( 'analytics' => '1', 'marketing' => '0' ) ) === 'Analytisch: ja · Marketing: nee' );

cm_test_group( 'Pagina en menu' );
cm_assert( 'menu-item Consent log', isset( cm_admin_pages()['cookiebaas-log'] ) );
cm_assert( 'tab Registraties', isset( cm_tabs_log()['registraties'] ) );
cm_assert( 'paginalink met tab en extra argumenten', cm_admin_page_url( 'cookiebaas-log', 'registraties', array( 'filter' => 'custom' ) ) === 'https://example.test/wp-admin/admin.php?page=cookiebaas-log&tab=registraties&filter=custom' );
require CM_PLUGIN_ROOT . '/includes/admin/class-cm-log-list-table.php';
cm_assert( 'lijsttabel laadt niet zonder WP_List_Table (geen fatal in tests of op de frontend)', ! class_exists( 'CM_Log_List_Table', false ) );

exit( cm_test_summary() );
