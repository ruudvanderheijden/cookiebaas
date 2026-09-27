<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   CONSENT LOG — registraties (WP_List_Table) en bewaren/opnieuw vragen.
   De tabel {prefix}cm_consent_log blijft ongewijzigd.
================================================================ */

function cm_tabs_log() {
    return array(
        'registraties' => array( 'label' => 'Registraties', 'render' => 'cm_log_render_registraties', 'premium' => 'cm_log_premium_text' ),
        'bewaren'      => cm_tab_log_bewaren(),
    );
}

function cm_tab_log_bewaren() {
    return array(
        'label'      => 'Bewaren en opnieuw vragen',
        'sections'   => array(
            array(
                'title'   => 'Bewaartermijn',
                'intro'   => 'De AVG (artikel 5 lid 1e) vraagt persoonsgegevens niet langer te bewaren dan nodig. 36 maanden geeft genoeg bewijs bij een klacht, zonder onnodig lang te bewaren.',
                'content' => 'cm_render_log_retention_status',
                'fields'  => array(
                    cm_field( 'log_retention_months', 'select', 'Registraties verwijderen', array(
                        'options'     => array(
                            '0'  => 'Nooit automatisch',
                            '3'  => 'Na 3 maanden',
                            '6'  => 'Na 6 maanden',
                            '12' => 'Na 12 maanden',
                            '24' => 'Na 24 maanden',
                            '36' => 'Na 36 maanden (aanbevolen)',
                        ),
                        'description' => 'Oudere registraties worden elke dag rond 12:00 uur automatisch verwijderd.',
                    ) ),
                ),
            ),
        ),
        'after_form' => 'cm_render_log_bewaren_tools',
    );
}

/** Melding op Registraties zonder licentie: het vastleggen gaat gewoon door. */
function cm_log_premium_text() {
    global $wpdb;
    $n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . cm_log_table() . '`' );
    return 'Cookiebaas legt de toestemmingen van uw bezoekers gewoon vast (nu ' . $n . ' registraties). De registraties bekijken, het bewijs per registratie en de CSV-export vragen een licentie.';
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
    if ( cm_admin_require_license() !== '' ) {
        $code = 'premium-required';
    } elseif ( ! $ids ) {
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

function cm_log_retention_status_text( $months, $next ) {
    $months = (int) $months;
    if ( $months <= 0 ) return 'Registraties worden nu niet automatisch verwijderd.';
    $text = 'Registraties ouder dan ' . $months . ' maanden worden verwijderd.';
    return $next
        ? $text . ' Volgende controle: ' . wp_date( 'j F Y, H:i', $next ) . '.'
        : $text . ' De dagelijkse controle wordt bij het volgende paginabezoek ingepland.';
}

function cm_render_log_retention_status() {
    echo '<p>' . esc_html( cm_log_retention_status_text( cm_get( 'log_retention_months' ), wp_next_scheduled( 'cm_log_retention_cron' ) ) ) . '</p>';
}

/** Verhoog de consent-versie (iedereen kiest opnieuw) en houd de geschiedenis bij. Geeft de nieuwe versie terug. */
function cm_bump_consent_version( $reason = '' ) {
    $new = (int) get_option( 'cm_consent_version', 1 ) + 1;
    update_option( 'cm_consent_version', $new );
    $log   = get_option( 'cm_consent_changelog', array() );
    $log   = is_array( $log ) ? $log : array();
    $log[] = array(
        'date'    => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
        'version' => $new,
        'reason'  => sanitize_text_field( is_scalar( $reason ) ? (string) $reason : '' ),
    );
    update_option( 'cm_consent_changelog', array_slice( $log, -50 ) );
    return $new;
}

/** Versiegeschiedenis, nieuwste bovenaan. */
function cm_render_consent_changelog( $log ) {
    $log = is_array( $log ) ? array_reverse( $log ) : array();
    if ( ! $log ) {
        echo '<p class="description">Er is nog niet eerder om nieuwe toestemming gevraagd.</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr><th scope="col">Versie</th><th scope="col">Datum</th><th scope="col">Reden</th></tr></thead><tbody>';
    foreach ( $log as $e ) {
        $e = (array) $e;
        echo '<tr><td>' . esc_html( isset( $e['version'] ) ? $e['version'] : '' ) . '</td>'
           . '<td>' . esc_html( isset( $e['date'] ) ? $e['date'] : '' ) . '</td>'
           . '<td>' . esc_html( ! empty( $e['reason'] ) ? $e['reason'] : '—' ) . '</td></tr>';
    }
    echo '</tbody></table>';
}

function cm_render_log_bewaren_tools() {
    echo '<div class="cm-section"><h2>Iedereen opnieuw laten kiezen</h2>';
    echo '<p>Verhoogt de consent-versie. Elke bezoeker ziet de banner dan opnieuw, bijvoorbeeld na een nieuwe dienst of een gewijzigde privacyverklaring. De huidige versie is ' . esc_html( (int) get_option( 'cm_consent_version', 1 ) ) . '.</p>';
    $reason = '<p><label for="cm-bump-reason">Reden (optioneel, alleen voor uw eigen overzicht)</label><br>'
            . '<input type="text" id="cm-bump-reason" name="reason" class="regular-text" maxlength="200"></p>';
    echo '<div>' . cm_admin_action_form( 'bump_consent_version', 'Iedereen opnieuw laten kiezen', array(), 'Alle bezoekers krijgen de banner opnieuw te zien. Doorgaan?', 'button', $reason ) . '</div>';
    echo '<h3>Eerdere keren</h3>';
    cm_render_consent_changelog( get_option( 'cm_consent_changelog', array() ) );
    echo '</div>';

    echo '<div class="cm-section"><h2>Log leegmaken</h2>';
    echo '<p>Verwijdert alle registraties definitief. Exporteer eerst een CSV als u het bewijs wilt bewaren.</p>';
    echo '<div>' . cm_admin_action_form( 'clear_log', 'Log leegmaken', array(), 'Alle registraties definitief verwijderen? Dit kan niet ongedaan worden gemaakt.', 'button button-link-delete' ) . '</div>';
    echo '</div>';
}

/**
 * Leeg de hele log. False alleen bij een databasefout (0 rijen telt als
 * gelukt). TRUNCATE vereist het DROP-recht; zonder dat recht valt dit terug
 * op een gewone DELETE.
 */
function cm_log_clear() {
    global $wpdb;
    $truncate = $wpdb->query( 'TRUNCATE TABLE `' . cm_log_table() . '`' ) !== false;
    if ( $truncate ) return true;
    return $wpdb->query( 'DELETE FROM `' . cm_log_table() . '`' ) !== false;
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'delete_consent', function () {
        if ( cm_admin_require_license() !== '' ) return 'premium-required';
        $n = cm_log_delete( array( isset( $_GET['consent'] ) ? wp_unslash( $_GET['consent'] ) : '' ) );
        return $n === false ? 'action-failed' : 'log-deleted';
    } );

    cm_admin_register_action( 'export_log', function () {
        global $wpdb;
        if ( cm_admin_require_license() !== '' ) return 'premium-required';
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

    cm_admin_register_action( 'bump_consent_version', function () {
        cm_bump_consent_version( isset( $_POST['reason'] ) ? wp_unslash( $_POST['reason'] ) : '' );
        return 'consent-version-bumped';
    } );
    cm_admin_register_action( 'clear_log', function () {
        return cm_log_clear() ? 'log-cleared' : 'action-failed';
    } );
}
