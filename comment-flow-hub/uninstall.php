<?php
/**
 * Remove tabelas, opções e eventos agendados ao excluir o hub.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfhub_licenses" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfhub_accounts" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfhub_deliveries" ); // phpcs:ignore WordPress.DB

delete_option( 'cfhub_settings' );
delete_option( 'cfhub_db_version' );

wp_clear_scheduled_hook( 'cfhub_retry_deliveries' );
wp_clear_scheduled_hook( 'cfhub_cleanup_deliveries' );
