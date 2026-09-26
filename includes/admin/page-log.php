<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   CONSENT LOG — registraties (WP_List_Table) en bewaren/opnieuw vragen.
   De tabel {prefix}cm_consent_log blijft ongewijzigd.
================================================================ */

function cm_tabs_log() {
    return array(
        'registraties' => array( 'label' => 'Registraties', 'render' => 'cm_log_render_registraties' ),
    );
}

function cm_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'cm_consent_log';
}

/** Filters boven de lijst: sleutel → label. */
function cm_log_filters() {
    return array( 'all' => 'Alle', 'accept-all' => 'Akkoord', 'reject-all' => 'Geweigerd', 'custom' => 'Aangepast' );
}

/** Opgeslagen methode → leesbare keuze (lijst, bewijs en CSV). */
function cm_log_method_label( $method ) {
    $labels = array(
        'accept-all'   => 'Geaccepteerd',
        'reject-all'   => 'Geweigerd',
        'custom'       => 'Aangepast',
        'embed-accept' => 'Geaccepteerd via embed',
        'pageload'     => 'Terugkerend bezoek',
    );
    return isset( $labels[ $method ] ) ? $labels[ $method ] : (string) $method;
}

function cm_log_categories_text( array $item ) {
    return 'Analytisch: ' . ( ! empty( $item['analytics'] ) ? 'ja' : 'nee' ) . ' · Marketing: ' . ( ! empty( $item['marketing'] ) ? 'ja' : 'nee' );
}

/**
 * WHERE-clausule voor de consent log: zoeken op consent-ID en filteren op
 * keuze. Serverside, zodat het filter over alle pagina's werkt. 'Akkoord'
 * telt embed-accept mee, net als de statistiek.
 *
 * @param string $like   Al ge-escapete LIKE-waarde, of '' voor niet zoeken.
 * @param string $filter all | accept-all | reject-all | custom
 * @return array [ $sql, $args ] voor $wpdb->prepare()
 */
function cm_log_where( $like, $filter ) {
    $methods = array(
        'accept-all' => array( 'accept-all', 'embed-accept' ),
        'reject-all' => array( 'reject-all' ),
        'custom'     => array( 'custom' ),
    );
    $where = array();
    $args  = array();
    if ( $like !== '' ) {
        $where[] = 'consent_id LIKE %s';
        $args[]  = $like;
    }
    if ( isset( $methods[ $filter ] ) ) {
        $where[] = 'method IN (' . implode( ',', array_fill( 0, count( $methods[ $filter ] ), '%s' ) ) . ')';
        $args    = array_merge( $args, $methods[ $filter ] );
    }
    return array( $where ? 'WHERE ' . implode( ' AND ', $where ) : '', $args );
}

/** Het filter uit de URL, alleen als het bestaat. */
function cm_log_current_filter() {
    $f = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all';
    return array_key_exists( $f, cm_log_filters() ) ? $f : 'all';
}

/** Aantal registraties per filter, voor de links boven de lijst. */
function cm_log_counts() {
    global $wpdb;
    $table  = cm_log_table();
    $counts = array();
    // ponytail: één COUNT per filter; één GROUP BY-query als de log erg groot wordt
    foreach ( array_keys( cm_log_filters() ) as $filter ) {
        list( $where, $args ) = cm_log_where( '', $filter );
        $sql = "SELECT COUNT(*) FROM `{$table}` {$where}";
        $counts[ $filter ] = (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql, $args ) : $sql );
    }
    return $counts;
}

/** Consent-ID's uit een verzoek: alleen geldige UUID's, in kleine letters, zonder dubbelingen. */
function cm_log_valid_ids( $raw ) {
    $ids = array();
    foreach ( (array) $raw as $id ) {
        if ( ! is_scalar( $id ) ) continue;
        $id = strtolower( trim( (string) $id ) );
        if ( preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $id ) ) $ids[ $id ] = $id;
    }
    return array_values( $ids );
}

/** Verwijder registraties op consent-ID. Het aantal verwijderde rijen, of false bij een databasefout. */
function cm_log_delete( array $ids ) {
    global $wpdb;
    $ids = cm_log_valid_ids( $ids );
    if ( ! $ids ) return 0;
    $table = cm_log_table();
    $in    = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
    return $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE consent_id IN ({$in})", $ids ) );
}

/** Bulkactie uit de lijst: het bovenste of het onderste keuzemenu, plus de aangevinkte ID's. */
function cm_log_bulk_request( array $req ) {
    $action = isset( $req['action'] ) && is_string( $req['action'] ) ? $req['action'] : '-1';
    if ( $action === '-1' || $action === '' ) {
        $action = isset( $req['action2'] ) && is_string( $req['action2'] ) ? $req['action2'] : '-1';
    }
    return array( $action, cm_log_valid_ids( isset( $req['consent'] ) ? $req['consent'] : array() ) );
}

/** Bulk verwijderen. Hangt aan de load-hook van de pagina, dus vóór er output is. */
function cm_log_handle_bulk() {
    list( $action, $ids ) = cm_log_bulk_request( wp_unslash( $_REQUEST ) );
    if ( $action !== 'cm_delete' ) return;
    check_admin_referer( 'bulk-consents' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang.', '', array( 'response' => 403 ) );
    if ( ! $ids ) {
        $code = 'log-none-selected';
    } else {
        $code = cm_log_delete( $ids ) === false ? 'action-failed' : 'log-deleted';
    }
    wp_safe_redirect( cm_admin_page_url( 'cookiebaas-log', 'registraties', array( 'cm_notice' => $code ) ) );
    exit;
}

/** Rij-acties: Bewijs | Verwijderen. */
function cm_log_row_actions( $consent_id ) {
    $proof  = cm_admin_page_url( 'cookiebaas-log', 'registraties', array( 'consent' => $consent_id ) );
    $delete = cm_admin_action_url( 'delete_consent', array( 'consent' => $consent_id ) );
    return array(
        'proof'  => '<a href="' . esc_url( $proof ) . '">Bewijs</a>',
        'delete' => '<a href="' . esc_url( $delete ) . '" class="submitdelete" data-cm-confirm="' . esc_attr( 'Deze registratie definitief verwijderen?' ) . '">Verwijderen</a>',
    );
}

/** Eén registratie op consent-ID, of null. */
function cm_log_get( $consent_id ) {
    global $wpdb;
    $ids = cm_log_valid_ids( array( $consent_id ) );
    if ( ! $ids ) return null;
    $table = cm_log_table();
    $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE consent_id = %s LIMIT 1", $ids[0] ), ARRAY_A );
    return is_array( $row ) ? $row : null;
}

/** Alle opgeslagen velden van een registratie als array( label, waarde ), voor het bewijs. */
function cm_log_proof_rows( array $row ) {
    $get = function ( $key ) use ( $row ) { return isset( $row[ $key ] ) ? (string) $row[ $key ] : ''; };
    $yes = function ( $key ) use ( $get ) { return $get( $key ) === '1' ? 'Ja' : 'Nee'; };
    return array(
        array( 'Consent-ID', $get( 'consent_id' ) ),
        array( 'Datum en tijd', $get( 'created_at' ) !== '' ? mysql2date( 'j F Y, H:i:s', $get( 'created_at' ) ) : '' ),
        array( 'Keuze', cm_log_method_label( $get( 'method' ) ) ),
        array( 'Analytische cookies', $yes( 'analytics' ) ),
        array( 'Marketingcookies', $yes( 'marketing' ) ),
        array( 'Pagina', $get( 'url' ) ),
        array( 'Browser en apparaat', $get( 'user_agent' ) ),
        array( 'IP-adres (gehasht)', $get( 'ip_hash' ) ),
        array( 'Sessie', $get( 'session_id' ) ),
        array( 'Configuratie-hash', $get( 'config_hash' ) ),
        array( 'Pluginversie', $get( 'plugin_version' ) ),
    );
}

function cm_log_render_proof( $consent_id ) {
    echo '<p class="cm-no-print"><a href="' . esc_url( cm_admin_page_url( 'cookiebaas-log', 'registraties' ) ) . '">Terug naar de registraties</a></p>';
    $row = cm_log_get( $consent_id );
    if ( ! $row ) {
        echo '<div class="notice notice-error inline"><p>Deze registratie bestaat niet (meer).</p></div>';
        return;
    }
    echo '<h2>Bewijs van toestemming</h2>';
    echo '<p>Dit is alles wat Cookiebaas over deze keuze heeft opgeslagen. Het IP-adres is alleen gehasht bewaard en niet terug te rekenen.</p>';
    echo '<table class="form-table" role="presentation"><tbody>';
    foreach ( cm_log_proof_rows( $row ) as $r ) {
        echo '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . ( $r[1] !== '' ? esc_html( $r[1] ) : '—' ) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p class="cm-no-print"><button type="button" class="button" data-cm-print>Afdrukken of opslaan als pdf</button></p>';
}

/** 'JJJJ-MM-DD' van/tot → datetime-grenzen (null = open). Een omgedraaid bereik wordt rechtgezet. */
function cm_log_date_range( $from, $to ) {
    $valid = function ( $d ) { return is_string( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : ''; };
    $from  = $valid( $from );
    $to    = $valid( $to );
    if ( $from !== '' && $to !== '' && $from > $to ) {
        $swap = $from; $from = $to; $to = $swap;
    }
    return array( $from !== '' ? $from . ' 00:00:00' : null, $to !== '' ? $to . ' 23:59:59' : null );
}

/** WHERE voor de export: nooit terugkerende bezoeken, optioneel binnen het bereik. */
function cm_log_export_where( $from_dt, $to_dt ) {
    $where = array( "method != 'pageload'" );
    $args  = array();
    if ( $from_dt ) { $where[] = 'created_at >= %s'; $args[] = $from_dt; }
    if ( $to_dt )   { $where[] = 'created_at <= %s'; $args[] = $to_dt; }
    return array( 'WHERE ' . implode( ' AND ', $where ), $args );
}

/** Rijen voor de CSV, met dezelfde kolommen als in 2.4. */
function cm_log_csv_rows( array $rows ) {
    $out = array( array( 'Consent ID', 'Consent Status', 'Analytisch', 'Marketing', 'Pagina', 'Plugin versie', 'Datum/Tijd' ) );
    foreach ( $rows as $r ) {
        $out[] = array(
            ! empty( $r['consent_id'] ) ? $r['consent_id'] : '—',
            cm_log_method_label( isset( $r['method'] ) ? $r['method'] : '' ),
            ! empty( $r['analytics'] ) ? 'Ja' : 'Nee',
            ! empty( $r['marketing'] ) ? 'Ja' : 'Nee',
            isset( $r['url'] ) ? $r['url'] : '',
            isset( $r['plugin_version'] ) ? $r['plugin_version'] : '',
            isset( $r['created_at'] ) ? $r['created_at'] : '',
        );
    }
    return $out;
}

function cm_log_render_registraties() {
    if ( isset( $_GET['consent'] ) && is_string( $_GET['consent'] ) ) {
        cm_log_render_proof( sanitize_text_field( wp_unslash( $_GET['consent'] ) ) );
        return;
    }
    if ( ! class_exists( 'WP_List_Table' ) ) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    require_once __DIR__ . '/class-cm-log-list-table.php';
    $table = new CM_Log_List_Table();
    $table->prepare_items();
    $table->views();
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="cookiebaas-log"><input type="hidden" name="tab" value="registraties">';
    $filter = cm_log_current_filter();
    if ( $filter !== 'all' ) echo '<input type="hidden" name="filter" value="' . esc_attr( $filter ) . '">';
    $table->search_box( 'Zoeken op consent-ID', 'cm-log' );
    $table->display();
    echo '</form>';
    echo '<form id="cm-log-export" method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="cm_export_log">';
    wp_nonce_field( 'cm_export_log', '_wpnonce', false );
    echo '</form>';
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'delete_consent', function () {
        $n = cm_log_delete( array( isset( $_GET['consent'] ) ? wp_unslash( $_GET['consent'] ) : '' ) );
        return $n ? 'log-deleted' : 'action-failed';
    } );

    cm_admin_register_action( 'export_log', function () {
        global $wpdb;
        list( $from, $to ) = cm_log_date_range(
            isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
            isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''
        );
        list( $where, $args ) = cm_log_export_where( $from, $to );
        $table = cm_log_table();
        $sql   = "SELECT consent_id, method, analytics, marketing, url, plugin_version, created_at FROM `{$table}` {$where} ORDER BY created_at DESC";
        $rows  = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );
        cm_admin_send_csv( 'consent-log-' . wp_date( 'Y-m-d' ) . '.csv', cm_log_csv_rows( $rows ? $rows : array() ) );
    } );
}
