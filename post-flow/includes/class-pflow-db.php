<?php
/**
 * Tabelas próprias. Todas têm user_id (o dono dos dados); as classes que as
 * leem filtram pelo inquilino atual (PFlow_Tenant).
 *
 * - channels: contas conectadas (uma página do Facebook, um perfil do
 *   Instagram, do LinkedIn, do X ou do Threads) e os tokens criptografados;
 * - posts:    o conteúdo (texto, link, imagens) e quando sai;
 * - targets:  um registro por post × canal, com o resultado da publicação.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_DB {

	public static function channels_table() {
		global $wpdb;
		return $wpdb->prefix . 'pflow_channels';
	}

	public static function posts_table() {
		global $wpdb;
		return $wpdb->prefix . 'pflow_posts';
	}

	public static function targets_table() {
		global $wpdb;
		return $wpdb->prefix . 'pflow_targets';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$channels = self::channels_table();
		$posts    = self::posts_table();
		$targets  = self::targets_table();

		dbDelta(
			"CREATE TABLE {$channels} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				network varchar(20) NOT NULL DEFAULT '',
				external_id varchar(191) NOT NULL DEFAULT '',
				provider_user_id varchar(191) NOT NULL DEFAULT '',
				name varchar(191) NOT NULL DEFAULT '',
				username varchar(191) NOT NULL DEFAULT '',
				avatar_url varchar(2048) NOT NULL DEFAULT '',
				access_token text NOT NULL,
				refresh_token text NOT NULL,
				token_expires_at bigint(20) unsigned NOT NULL DEFAULT 0,
				meta longtext NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				last_error text NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_network_external (user_id,network,external_id),
				KEY provider_user_id (provider_user_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$posts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				content longtext NOT NULL,
				link varchar(2048) NOT NULL DEFAULT '',
				media_ids varchar(255) NOT NULL DEFAULT '',
				channel_ids varchar(255) NOT NULL DEFAULT '',
				video_url varchar(2048) NOT NULL DEFAULT '',
				video_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'draft',
				in_queue tinyint(1) NOT NULL DEFAULT 0,
				scheduled_at datetime NULL,
				sent_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_status (user_id,status),
				KEY scheduled_at (scheduled_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$targets} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				channel_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'pending',
				due_at datetime NULL,
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				remote_id varchar(191) NOT NULL DEFAULT '',
				remote_url varchar(2048) NOT NULL DEFAULT '',
				error text NULL,
				locked_at datetime NULL,
				published_at datetime NULL,
				PRIMARY KEY  (id),
				KEY post_id (post_id),
				KEY status_due (status,due_at),
				KEY channel_id (channel_id)
			) {$charset};"
		);

		update_option( 'pflow_db_version', PFLOW_DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( (string) get_option( 'pflow_db_version' ) !== (string) PFLOW_DB_VERSION ) {
			self::install();
			PFlow_Frontend::request_setup();
			PFlow_Publisher::schedule();
		}
	}

	public static function now() {
		return current_time( 'mysql', true );
	}
}
