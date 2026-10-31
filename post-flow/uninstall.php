<?php
/**
 * Remove tabelas, opções e eventos agendados ao excluir o plugin.
 * As imagens enviadas ficam na biblioteca de mídia.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pflow_targets" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pflow_posts" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pflow_channels" ); // phpcs:ignore WordPress.DB

foreach ( array( 'pflow_settings', 'pflow_db_version', 'pflow_app_url', 'pflow_app_page_id', 'pflow_setup_page' ) as $option ) {
	delete_option( $option );
}
// Transients (avisos, "state" do OAuth, contas aguardando escolha).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_pflow\\_%' OR option_name LIKE '\\_transient\\_timeout\\_pflow\\_%'" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('pflow_slots','pflow_timezone')" ); // phpcs:ignore WordPress.DB

// Os usuários cadastrados ficam; só perdem a função do plugin.
remove_role( 'pflow_member' );

wp_clear_scheduled_hook( 'pflow_publish_due' );
wp_clear_scheduled_hook( 'pflow_daily_maintenance' );
