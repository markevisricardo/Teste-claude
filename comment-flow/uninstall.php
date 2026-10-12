<?php
/**
 * Remove tabelas, opções e eventos agendados ao excluir o plugin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cflow_automations" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cflow_logs" ); // phpcs:ignore WordPress.DB

delete_option( 'cflow_settings' );
delete_option( 'cflow_db_version' );
delete_transient( 'cflow_recent_media' );
delete_transient( 'cflow_notice' );

wp_clear_scheduled_hook( 'cflow_daily_maintenance' );
wp_unschedule_hook( 'cflow_process_comment' );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'cflow_process_comment' );
}
