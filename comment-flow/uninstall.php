<?php
/**
 * Remove tabelas, opções e eventos agendados ao excluir o plugin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cflow_automations" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cflow_logs" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cflow_leads" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cflow_contacts" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cflow_accounts" ); // phpcs:ignore WordPress.DB

delete_option( 'cflow_settings' );
delete_option( 'cflow_db_version' );
delete_option( 'cflow_app_url' );
delete_transient( 'cflow_admin_notice' );
// Transients por usuário (aviso e cache de publicações).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_cflow\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cflow\\_%'" ); // phpcs:ignore WordPress.DB

// Os usuários cadastrados ficam; só perdem a função do plugin.
remove_role( 'cflow_member' );

wp_clear_scheduled_hook( 'cflow_daily_maintenance' );
foreach ( array( 'cflow_process_comment', 'cflow_process_message' ) as $hook ) {
	wp_unschedule_hook( $hook );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( $hook );
	}
}
