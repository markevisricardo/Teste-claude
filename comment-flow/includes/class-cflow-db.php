<?php
/**
 * Tabelas próprias: automações e log de comentários processados.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_DB {

	public static function automations_table() {
		global $wpdb;
		return $wpdb->prefix . 'cflow_automations';
	}

	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'cflow_logs';
	}

	public static function leads_table() {
		global $wpdb;
		return $wpdb->prefix . 'cflow_leads';
	}

	public static function contacts_table() {
		global $wpdb;
		return $wpdb->prefix . 'cflow_contacts';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset     = $wpdb->get_charset_collate();
		$automations = self::automations_table();
		$logs        = self::logs_table();
		$leads       = self::leads_table();
		$contacts    = self::contacts_table();

		dbDelta(
			"CREATE TABLE {$automations} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'active',
				trigger_type varchar(20) NOT NULL DEFAULT 'comment',
				media_id varchar(64) NOT NULL DEFAULT '',
				keywords text NOT NULL,
				match_mode varchar(20) NOT NULL DEFAULT 'contains',
				public_replies text NOT NULL,
				dm_text text NOT NULL,
				dm_button_label varchar(80) NOT NULL DEFAULT '',
				dm_button_url varchar(2048) NOT NULL DEFAULT '',
				capture_email tinyint(1) NOT NULL DEFAULT 0,
				capture_phone tinyint(1) NOT NULL DEFAULT 0,
				optin_text text NULL,
				optin_button varchar(20) NOT NULL DEFAULT '',
				email_question text NULL,
				phone_question text NULL,
				require_follow tinyint(1) NOT NULL DEFAULT 0,
				follow_prompt text NULL,
				follow_button varchar(20) NOT NULL DEFAULT '',
				follow_retry text NULL,
				priority int(11) NOT NULL DEFAULT 10,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_priority (status,priority),
				KEY trigger_type (trigger_type)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				comment_id varchar(64) NOT NULL,
				media_id varchar(64) NOT NULL DEFAULT '',
				from_id varchar(64) NOT NULL DEFAULT '',
				from_username varchar(191) NOT NULL DEFAULT '',
				comment_text text NOT NULL,
				automation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'queued',
				public_reply_sent tinyint(1) NOT NULL DEFAULT 0,
				dm_sent tinyint(1) NOT NULL DEFAULT 0,
				attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				error text NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY comment_id (comment_id),
				KEY automation_status (automation_id,status),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$leads} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				automation_id bigint(20) unsigned NOT NULL,
				comment_from_id varchar(64) NOT NULL,
				ig_scoped_id varchar(64) NOT NULL DEFAULT '',
				username varchar(191) NOT NULL DEFAULT '',
				last_comment text NULL,
				comments_count int(10) unsigned NOT NULL DEFAULT 1,
				dm_sent tinyint(1) NOT NULL DEFAULT 0,
				email varchar(191) NOT NULL DEFAULT '',
				phone varchar(32) NOT NULL DEFAULT '',
				consent_at datetime NULL,
				capture_step varchar(20) NOT NULL DEFAULT 'none',
				capture_attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				last_mid varchar(191) NOT NULL DEFAULT '',
				locked_until datetime NULL,
				last_error text NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY automation_person (automation_id,comment_from_id),
				KEY ig_scoped_id (ig_scoped_id),
				KEY comment_from_id (comment_from_id),
				KEY created_at (created_at)
			) {$charset};"
		);

		// Pessoas com quem a conta já conversou (para detectar a primeira DM).
		dbDelta(
			"CREATE TABLE {$contacts} (
				ig_scoped_id varchar(64) NOT NULL,
				first_seen_at datetime NOT NULL,
				PRIMARY KEY  (ig_scoped_id)
			) {$charset};"
		);

		update_option( 'cflow_db_version', CFLOW_DB_VERSION, false );
		CFlow_Settings::ensure_verify_token();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'cflow_db_version' ) !== CFLOW_DB_VERSION ) {
			self::install();
		}
	}

	/* ---------------------------------------------------------------------
	 * Automações
	 * ------------------------------------------------------------------- */

	public static function get_automation( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::automations_table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * @param bool        $only_active Só as ativas.
	 * @param string|null $group       'comment' (comentários), 'dm' (gatilhos de DM) ou null (todas).
	 */
	public static function get_automations( $only_active = false, $group = null ) {
		global $wpdb;
		$clauses = array();
		if ( $only_active ) {
			$clauses[] = "status = 'active'";
		}
		if ( 'comment' === $group ) {
			$clauses[] = "trigger_type = 'comment'";
		} elseif ( 'dm' === $group ) {
			$clauses[] = "trigger_type IN ('dm_keyword', 'dm_welcome')";
		}
		$where = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';
		return $wpdb->get_results(
			'SELECT * FROM ' . self::automations_table() . " {$where} ORDER BY priority ASC, id ASC",
			ARRAY_A
		);
	}

	public static function has_active_dm_automations() {
		global $wpdb;
		return (bool) $wpdb->get_var(
			'SELECT 1 FROM ' . self::automations_table() . " WHERE status = 'active' AND trigger_type IN ('dm_keyword', 'dm_welcome') LIMIT 1"
		);
	}

	public static function save_automation( array $data, $id = 0 ) {
		global $wpdb;
		$now                = current_time( 'mysql', true );
		$data['updated_at'] = $now;

		if ( $id ) {
			$wpdb->update( self::automations_table(), $data, array( 'id' => (int) $id ) );
			return (int) $id;
		}

		$data['created_at'] = $now;
		$wpdb->insert( self::automations_table(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function delete_automation( $id ) {
		global $wpdb;
		$wpdb->delete( self::automations_table(), array( 'id' => (int) $id ) );
	}

	/* ---------------------------------------------------------------------
	 * Logs
	 * ------------------------------------------------------------------- */

	/**
	 * Registra o comentário. Retorna o ID do log, ou 0 se o comentário já
	 * havia sido recebido (deduplicação pela chave única comment_id).
	 */
	public static function claim_comment( array $comment ) {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::logs_table() . ' (comment_id, media_id, from_id, from_username, comment_text, status, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)',
				$comment['id'],
				$comment['media_id'],
				$comment['from_id'],
				$comment['from_username'],
				$comment['text'],
				'queued',
				$now,
				$now
			)
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Marca o log como "processing" de forma atômica, para que dois workers
	 * nunca enviem a mesma resposta. Logs presos em "processing" há mais de
	 * 10 minutos (worker que morreu) podem ser retomados.
	 */
	public static function lock_log( $id ) {
		global $wpdb;
		$now   = current_time( 'mysql', true );
		$stale = gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS );

		return (bool) $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::logs_table() . " SET status = 'processing', attempts = attempts + 1, updated_at = %s WHERE id = %d AND ( status IN ('queued', 'retry') OR ( status = 'processing' AND updated_at < %s ) )",
				$now,
				$id,
				$stale
			)
		);
	}

	public static function get_log( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::logs_table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	public static function update_log( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( self::logs_table(), $data, array( 'id' => (int) $id ) );
	}

	public static function get_logs( $limit = 50, $offset = 0 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::logs_table() . ' ORDER BY id DESC LIMIT %d OFFSET %d', $limit, $offset ),
			ARRAY_A
		);
	}

	public static function count_logs() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::logs_table() );
	}

	/**
	 * Estatísticas por automação: [automation_id => [matched, dms, leads, emails]].
	 */
	public static function automation_stats() {
		global $wpdb;
		$rows  = $wpdb->get_results(
			'SELECT automation_id, COUNT(*) AS matched, SUM(dm_sent) AS dms FROM ' . self::logs_table() . ' WHERE automation_id > 0 GROUP BY automation_id',
			ARRAY_A
		);
		$stats = array();
		foreach ( $rows as $row ) {
			$stats[ (int) $row['automation_id'] ] = array(
				'matched' => (int) $row['matched'],
				'dms'     => (int) $row['dms'],
				'leads'   => 0,
				'emails'  => 0,
			);
		}

		$leads = $wpdb->get_results(
			"SELECT automation_id, COUNT(*) AS leads, SUM(email <> '') AS emails FROM " . self::leads_table() . ' GROUP BY automation_id',
			ARRAY_A
		);
		foreach ( $leads as $row ) {
			$id = (int) $row['automation_id'];
			if ( ! isset( $stats[ $id ] ) ) {
				$stats[ $id ] = array( 'matched' => 0, 'dms' => 0 );
			}
			$stats[ $id ]['leads']  = (int) $row['leads'];
			$stats[ $id ]['emails'] = (int) $row['emails'];
		}
		return $stats;
	}

	public static function purge_logs_older_than( $days ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::logs_table() . ' WHERE created_at < %s',
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) $days )
			)
		);
	}
}
