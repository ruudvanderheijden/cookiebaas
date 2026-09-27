<?php
/**
 * Cookiebaas — Uninstall
 * Ruimt alle plugin-data op bij verwijdering via WordPress: opties,
 * transients, tabellen (met de consent log, persoonsgegevens) en cron-events.
 * Op multisite voor elke site in het netwerk.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function cm_uninstall_site() {
    global $wpdb;

    foreach ( array(
        'cm_settings', 'cm_cookie_list', 'cm_privacy', 'cm_version',
        'cm_consent_version', 'cm_consent_changelog',
        'cm_license_data', 'cm_license_api_url', 'cm_license_valid_seen',
        'cm_github_token',
        'cm_auto_scan_next', 'cm_auto_scan_last', 'cm_auto_scan_last_added', 'cm_auto_scan_last_found',
        'cm_cookie_db_count', 'cm_cookie_db_updated',
        'cm_log_hash_key', 'cm_show_admin3_notice',
    ) as $option ) {
        delete_option( $option );
    }

    // Transients (GitHub release cache, CSS cache, rate limits)
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_cm\_%' OR option_name LIKE '\_transient\_timeout\_cm\_%'" );

    // Tabellen
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cm_consent_log" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cm_cookie_db" );

    // Geplande cron-events
    foreach ( array( 'cm_log_retention_cron', 'cm_auto_scan_cron', 'cm_license_cron' ) as $hook ) {
        $timestamp = wp_next_scheduled( $hook );
        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook );
            $timestamp = wp_next_scheduled( $hook );
        }
    }
}

if ( is_multisite() && function_exists( 'get_sites' ) ) {
    foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
        switch_to_blog( $site_id );
        cm_uninstall_site();
        restore_current_blog();
    }
} else {
    cm_uninstall_site();
}
