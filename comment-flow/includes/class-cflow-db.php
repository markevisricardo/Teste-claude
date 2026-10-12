<?php
/**
 * Tabelas próprias. Todas têm user_id (o dono dos dados) e as funções
 * públicas filtram pelo inquilino atual (CFlow_Tenant).
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

	public static function accounts_table() {
		global $wpdb;
		return $wpdb->prefix . 'cflow_accounts';
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
		$accounts    = self::accounts_table();
		$previous    = (int) get_option( 'cflow_db_version', 0 );

		// A v4 mudou a chave primária dos contatos (agora por usuário). A
		// tabela é só um cache para detectar a primeira DM: pode ser recriada.
		if ( $previous > 0 && $previous < 4 ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$contacts}" ); // phpcs:ignore WordPress.DB
		}

		dbDelta(
			"CREATE TABLE {$automations} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
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
				KEY user_status_priority (user_id,status,priority),
				KEY trigger_type (trigger_type)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
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
				KEY user_id (user_id),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$leads} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
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
				KEY user_scoped (user_id,ig_scoped_id),
				KEY user_from (user_id,comment_from_id),
				KEY created_at (created_at)
			) {$charset};"
		);

		// Pessoas com quem a conta já conversou (para detectar a primeira DM).
		dbDelta(
			"CREATE TABLE {$contacts} (
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				ig_scoped_id varchar(64) NOT NULL,
				first_seen_at datetime NOT NULL,
				PRIMARY KEY  (user_id,ig_scoped_id)
			) {$charset};"
		);

		// Conta do Instagram de cada usuário.
		dbDelta(
			"CREATE TABLE {$accounts} (
				user_id bigint(20) unsigned NOT NULL,
				ig_user_id varchar(64) NULL,
				ig_username varchar(191) NOT NULL DEFAULT '',
				access_token text NULL,
				token_expires_at bigint(20) NOT NULL DEFAULT 0,
				delay_seconds smallint(5) unsigned NOT NULL DEFAULT 5,
				connected_at datetime NULL,
				PRIMARY KEY  (user_id),
				UNIQUE KEY ig_user_id (ig_user_id)
			) {$charset};"
		);

		if ( $previous > 0 && $previous < 4 ) {
			self::migrate_single_site();
		}

		update_option( 'cflow_db_version', CFLOW_DB_VERSION, false );
		CFlow_Settings::ensure_verify_token();
	}

	/**
	 * Instalações de antes da plataforma (um site = uma conta): os dados e a
	 * conta conectada passam a pertencer ao primeiro administrador.
	 */
	private static function migrate_single_site() {
		global $wpdb;
		$admins = get_users( array( 'role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID' ) );
		if ( empty( $admins ) ) {
			return;
		}
		$owner = (int) $admins[0];

		foreach ( array( self::automations_table(), self::logs_table(), self::leads_table() ) as $table ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET user_id = %d WHERE user_id = 0", $owner ) ); // phpcs:ignore WordPress.DB
		}

		$old = get_option( 'cflow_settings', array() );
		if ( is_array( $old ) && ! empty( $old['access_token'] ) && ! empty( $old['ig_user_id'] ) ) {
			CFlow_Accounts::connect(
				$owner,
				$old['ig_user_id'],
				isset( $old['ig_username'] ) ? $old['ig_username'] : '',
				CFlow_Crypto::decrypt( $old['access_token'] ),
				max( 0, (int) $old['token_expires_at'] - time() )
			);
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( 'cflow_db_version' ) !== CFLOW_DB_VERSION ) {
			self::install();
			// Atualização sem reativar: cria/define a página do painel se ainda não houver.
			if ( ! get_option( CFlow_Frontend::PAGE_OPTION ) ) {
				CFlow_Frontend::request_setup();
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Automações
	 * ------------------------------------------------------------------- */

	public static function get_automation( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::automations_table() . ' WHERE id = %d AND user_id = %d', $id, CFlow_Tenant::id() ),
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
		$clauses = array( $wpdb->prepare( 'user_id = %d', CFlow_Tenant::id() ) );
		if ( $only_active ) {
			$clauses[] = "status = 'active'";
		}
		if ( 'comment' === $group ) {
			$clauses[] = "trigger_type = 'comment'";
		} elseif ( 'dm' === $group ) {
			$clauses[] = "trigger_type IN ('dm_keyword', 'dm_welcome')";
		}
		$where = 'WHERE ' . implode( ' AND ', $clauses );
		return $wpdb->get_results(
			'SELECT * FROM ' . self::automations_table() . " {$where} ORDER BY priority ASC, id ASC",
			ARRAY_A
		);
	}

	public static function has_active_dm_automations() {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . self::automations_table() . " WHERE user_id = %d AND status = 'active' AND trigger_type IN ('dm_keyword', 'dm_welcome') LIMIT 1",
				CFlow_Tenant::id()
			)
		);
	}

	public static function save_automation( array $data, $id = 0 ) {
		global $wpdb;
		$now                = current_time( 'mysql', true );
		$data['updated_at'] = $now;

		unset( $data['user_id'] );
		if ( $id ) {
			$wpdb->update( self::automations_table(), $data, array( 'id' => (int) $id, 'user_id' => CFlow_Tenant::id() ) );
			return (int) $id;
		}

		$data['created_at'] = $now;
		$data['user_id']    = CFlow_Tenant::id();
		$wpdb->insert( self::automations_table(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function delete_automation( $id ) {
		global $wpdb;
		$wpdb->delete( self::automations_table(), array( 'id' => (int) $id, 'user_id' => CFlow_Tenant::id() ) );
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
				'INSERT IGNORE INTO ' . self::logs_table() . ' (user_id, comment_id, media_id, from_id, from_username, comment_text, status, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s)',
				CFlow_Tenant::id(),
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

	/**
	 * Lê um log pelo ID sem filtrar pelo inquilino: usado só pela fila, que
	 * descobre o dono (user_id) a partir do próprio log.
	 */
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
			$wpdb->prepare( 'SELECT * FROM ' . self::logs_table() . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', CFlow_Tenant::id(), $limit, $offset ),
			ARRAY_A
		);
	}

	public static function count_logs() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::logs_table() . ' WHERE user_id = %d', CFlow_Tenant::id() ) );
	}

	/**
	 * Estatísticas por automação: [automation_id => [matched, dms, leads, emails]].
	 */
	public static function automation_stats() {
		global $wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT automation_id, COUNT(*) AS matched, SUM(dm_sent) AS dms FROM ' . self::logs_table() . ' WHERE user_id = %d AND automation_id > 0 GROUP BY automation_id', CFlow_Tenant::id() ),
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
			$wpdb->prepare( "SELECT automation_id, COUNT(*) AS leads, SUM(email <> '') AS emails FROM " . self::leads_table() . ' WHERE user_id = %d GROUP BY automation_id', CFlow_Tenant::id() ),
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
