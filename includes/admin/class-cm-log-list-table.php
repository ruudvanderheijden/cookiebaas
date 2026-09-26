<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Alleen als WordPress' lijsttabel geladen is (cm_log_render_registraties doet dat eerst).
// Zo geeft dit bestand geen fatal op de frontend of in de tests.
if ( ! class_exists( 'WP_List_Table' ) ) return;

class CM_Log_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array( 'singular' => 'consent', 'plural' => 'consents', 'ajax' => false ) );
    }

    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox">',
            'consent_id' => 'Consent-ID',
            'method'     => 'Keuze',
            'categories' => 'Toestemming',
            'url'        => 'Pagina',
            'created_at' => 'Datum',
        );
    }

    protected function get_bulk_actions() {
        return array( 'cm_delete' => 'Verwijderen' );
    }

    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        echo '<div class="alignleft actions">';
        echo '<label for="cm-log-from">Van</label> <input type="date" id="cm-log-from" name="from" form="cm-log-export"> ';
        echo '<label for="cm-log-to">tot en met</label> <input type="date" id="cm-log-to" name="to" form="cm-log-export"> ';
        echo '<button type="submit" class="button" form="cm-log-export">CSV exporteren</button>';
        echo '</div>';
    }

    protected function get_views() {
        $current = cm_log_current_filter();
        $counts  = cm_log_counts();
        $views   = array();
        foreach ( cm_log_filters() as $key => $label ) {
            $url  = cm_admin_page_url( 'cookiebaas-log', 'registraties', array( 'filter' => $key ) );
            $attr = $key === $current ? ' class="current" aria-current="page"' : '';
            $views[ $key ] = '<a href="' . esc_url( $url ) . '"' . $attr . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $counts[ $key ] ) ) . ')</span></a>';
        }
        return $views;
    }

    public function prepare_items() {
        global $wpdb;
        $per    = 25;
        $table  = cm_log_table();
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        list( $where, $args ) = cm_log_where( $search !== '' ? '%' . $wpdb->esc_like( $search ) . '%' : '', cm_log_current_filter() );
        $count = "SELECT COUNT(*) FROM `{$table}` {$where}";
        $total = (int) $wpdb->get_var( $args ? $wpdb->prepare( $count, $args ) : $count );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT consent_id, analytics, marketing, method, url, created_at FROM `{$table}` {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge( $args, array( $per, ( $this->get_pagenum() - 1 ) * $per ) )
        ), ARRAY_A );
        $this->items = $rows ? $rows : array();
        $this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
        $this->_column_headers = array( $this->get_columns(), array(), array(), 'consent_id' );
    }

    public function no_items() {
        echo 'Er zijn geen registraties gevonden.';
    }

    protected function column_cb( $item ) {
        return '<input type="checkbox" name="consent[]" value="' . esc_attr( $item['consent_id'] ) . '">';
    }

    protected function column_consent_id( $item ) {
        return '<code>' . esc_html( $item['consent_id'] ) . '</code>';
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'method':     return esc_html( cm_log_method_label( $item['method'] ) );
            case 'categories': return esc_html( cm_log_categories_text( $item ) );
            case 'url':        return esc_html( $item['url'] );
            case 'created_at': return esc_html( mysql2date( 'j F Y, H:i', $item['created_at'] ) );
        }
        return '';
    }

    protected function handle_row_actions( $item, $column_name, $primary ) {
        return $column_name === $primary ? $this->row_actions( cm_log_row_actions( $item['consent_id'] ) ) : '';
    }
}
