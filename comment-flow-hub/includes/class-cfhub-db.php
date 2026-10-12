<?php
/**
 * Tabelas do hub: licenças, contas conectadas e fila de entregas de webhooks.
 */

defined( 'ABSPATH' ) || exit;

class CFHub_DB {

	public static function licenses_table() {
		global $wpdb;
		return $wpdb->prefix . 'cfhub_licenses';
	}

	public static function accounts_table() {
		global $wpdb;
		return $wpdb->prefix . 'cfhub_accounts';
	}

	public static function deliveries_table() {
		global $wpdb;
		return $wpdb->prefix . 'cfhub_deliveries';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$licenses   = self::licenses_table();
		$accounts   = self::accounts_table();
		$deliveries = self::deliveries_table();

		dbDelta(
			"CREATE TABLE {$licenses} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				license_key varchar(64) NOT NULL,
				client_name varchar(191) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'active',
				site_url varchar(255) NOT NULL DEFAULT '',
				callback_url varchar(255) NOT NULL DEFAULT '',
				site_secret text NULL,
				activated_at datetime NULL,
				last_delivery_at datetime NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY license_key (license_key)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$accounts} (
				ig_user_id varchar(64) NOT NULL,
				license_id bigint(20) unsigned NOT NULL,
				username varchar(191) NOT NULL DEFAULT '',
				connected_at datetime NOT NULL,
				PRIMARY KEY  (ig_user_id),
				KEY license_id (license_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$deliveries} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				license_id bigint(20) unsigned NOT NULL,
				payload longtext NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				last_error text NULL,
				next_attempt_at datetime NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_next (status,next_attempt_at),
				KEY license_id (license_id)
			) {$charset};"
		);

		update_option( 'cfhub_db_version', CFHUB_DB_VERSION, false );
		CFHub_Settings::ensure_verify_token();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'cfhub_db_version' ) !== CFHUB_DB_VERSION ) {
			self::install();
		}
	}

	public static function now() {
		return current_time( 'mysql', true );
	}
}
