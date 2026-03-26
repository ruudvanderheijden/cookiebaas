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

// Verwijder database-tabellen
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cm_consent_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cm_cookie_db" );

// Verwijder geplande cron-events
$timestamp = wp_next_scheduled( 'cm_log_retention_cron' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'cm_log_retention_cron' );
}
