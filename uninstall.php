<?php
/**
 * Cookiebaas — Uninstall
 * Ruimt alle plugin-data op bij verwijdering via WordPress.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Verwijder opties
delete_option( 'cm_settings' );
delete_option( 'cm_cookie_list' );
delete_option( 'cm_privacy' );
delete_option( 'cm_version' );
delete_option( 'cm_consent_version' );
delete_option( 'cm_consent_changelog' );
delete_option( 'cm_license_data' );
delete_option( 'cm_license_api_url' );
delete_option( 'cm_github_token' );
delete_option( 'cm_auto_scan_next' );
delete_option( 'cm_auto_scan_last' );
delete_option( 'cm_auto_scan_last_added' );
delete_option( 'cm_auto_scan_last_found' );

// Verwijder transients (GitHub release cache, CSS cache, rate limits)
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_cm\_%' OR option_name LIKE '\_transient\_timeout\_cm\_%'" );

// Verwijder database-tabellen
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cm_consent_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cm_cookie_db" );

// Verwijder geplande cron-events
foreach ( array( 'cm_log_retention_cron', 'cm_auto_scan_cron', 'cm_license_cron' ) as $hook ) {
    $timestamp = wp_next_scheduled( $hook );
    while ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
        $timestamp = wp_next_scheduled( $hook );
    }
}
