<?php
/**
 * Leads: uma linha por pessoa por automação, com os dados capturados na DM.
 * Ao contrário dos logs, leads não são apagados automaticamente.
 * Todas as consultas filtram pelo inquilino atual (CFlow_Tenant).
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Leads {

	/** Etapas da captura em que a conversa ainda espera uma resposta. */
	const OPEN_STEPS = array( 'awaiting_optin', 'awaiting_email', 'awaiting_phone', 'awaiting_follow' );

	/* ---------------------------------------------------------------------
	 * Escrita
	 * ------------------------------------------------------------------- */

	/**
	 * Cria ou atualiza o lead de quem comentou. Retorna o ID do lead.
	 */
	public static function upsert_from_comment( $automation_id, array $log ) {
		return self::upsert( $automation_id, $log['from_id'], $log['from_username'], $log['comment_text'] );
	}

	/**
	 * Cria ou atualiza o lead de uma pessoa numa automação (por comentário ou
	 * por DM). $person_id é o ID de quem comentou ou mandou a mensagem.
	 */
	public static function upsert( $automation_id, $person_id, $username, $text ) {
		global $wpdb;
		$table = CFlow_DB::leads_table();
		$now   = current_time( 'mysql', true );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (user_id, automation_id, comment_from_id, username, last_comment, created_at, updated_at) VALUES (%d, %d, %s, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE username = IF(VALUES(username) = '', username, VALUES(username)), last_comment = VALUES(last_comment), comments_count = comments_count + 1, updated_at = VALUES(updated_at)",
				CFlow_Tenant::id(),
				$automation_id,
				$person_id,
				$username,
				$text,
				$now,
				$now
			)
		);

		return self::find_id( $automation_id, $person_id );
	}

	public static function find_id( $automation_id, $comment_from_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . CFlow_DB::leads_table() . ' WHERE user_id = %d AND automation_id = %d AND comment_from_id = %s',
				CFlow_Tenant::id(),
				$automation_id,
				$comment_from_id
			)
		);
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( CFlow_DB::leads_table(), $data, array( 'id' => (int) $id, 'user_id' => CFlow_Tenant::id() ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( CFlow_DB::leads_table(), array( 'id' => (int) $id, 'user_id' => CFlow_Tenant::id() ) );
	}

	/**
	 * Trava o lead por 60s para que duas mensagens da mesma pessoa não sejam
	 * processadas ao mesmo tempo.
	 */
	public static function lock( $id ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		return (bool) $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . CFlow_DB::leads_table() . ' SET locked_until = %s WHERE id = %d AND user_id = %d AND ( locked_until IS NULL OR locked_until < %s )',
				gmdate( 'Y-m-d H:i:s', time() + MINUTE_IN_SECONDS ),
				$id,
				CFlow_Tenant::id(),
				$now
			)
		);
	}

	public static function unlock( $id ) {
		global $wpdb;
		$wpdb->update( CFlow_DB::leads_table(), array( 'locked_until' => null ), array( 'id' => (int) $id, 'user_id' => CFlow_Tenant::id() ) );
	}

	/* ---------------------------------------------------------------------
	 * Leitura
	 * ------------------------------------------------------------------- */

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . CFlow_DB::leads_table() . ' WHERE id = %d AND user_id = %d', $id, CFlow_Tenant::id() ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Lead com conversa em aberto para quem enviou a mensagem (o mais recente).
	 */
	public static function find_open_conversation( $sender_id ) {
		global $wpdb;
		$steps = "'" . implode( "','", self::OPEN_STEPS ) . "'";
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . CFlow_DB::leads_table() . " WHERE user_id = %d AND ( ig_scoped_id = %s OR comment_from_id = %s ) AND capture_step IN ({$steps}) ORDER BY updated_at DESC, id DESC LIMIT 1",
				CFlow_Tenant::id(),
				$sender_id,
				$sender_id
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * @param array $args automation_id (0 = todas), search, limit, offset.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'automation_id' => 0,
				'search'        => '',
				'limit'         => 50,
				'offset'        => 0,
			)
		);

		list( $where, $params ) = self::where( $args );
		$sql = 'SELECT * FROM ' . CFlow_DB::leads_table() . " {$where} ORDER BY id DESC";
		if ( $args['limit'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = (int) $args['limit'];
			$params[] = (int) $args['offset'];
		}

		return $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );
	}

	public static function count( array $args = array() ) {
		global $wpdb;
		list( $where, $params ) = self::where( wp_parse_args( $args, array( 'automation_id' => 0, 'search' => '' ) ) );
		$sql = 'SELECT COUNT(*) FROM ' . CFlow_DB::leads_table() . " {$where}";
		return (int) $wpdb->get_var( $params ? $wpdb->prepare( $sql, $params ) : $sql );
	}

	private static function where( array $args ) {
		global $wpdb;
		$clauses = array( 'user_id = %d' );
		$params  = array( CFlow_Tenant::id() );

		if ( $args['automation_id'] ) {
			$clauses[] = 'automation_id = %d';
			$params[]  = (int) $args['automation_id'];
		}
		if ( '' !== $args['search'] ) {
			$like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$clauses[] = '( username LIKE %s OR email LIKE %s OR phone LIKE %s )';
			array_push( $params, $like, $like, $like );
		}

		return array( 'WHERE ' . implode( ' AND ', $clauses ), $params );
	}

	/* ---------------------------------------------------------------------
	 * Validação
	 * ------------------------------------------------------------------- */

	/**
	 * Extrai o primeiro e-mail válido de um texto livre ("é joao@x.com, valeu").
	 */
	public static function extract_email( $text ) {
		if ( ! preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $text, $m ) ) {
			return '';
		}
		$email = strtolower( rtrim( $m[0], '.' ) );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Normaliza um telefone brasileiro para +55DDNNNNNNNNN. Retorna '' se inválido.
	 * Aceita "11 91234-5678", "(11) 1234-5678", "+55 11 91234-5678" etc.
	 */
	public static function normalize_phone( $text ) {
		$digits = preg_replace( '/\D+/', '', (string) $text );
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = ltrim( $digits, '0' ); // Prefixo de operadora/discagem.
		}
		if ( strlen( $digits ) >= 12 && 0 === strpos( $digits, '55' ) ) {
			$digits = substr( $digits, 2 );
		}
		if ( ! preg_match( '/^[1-9][0-9](9[0-9]{8}|[2-5][0-9]{7})$/', $digits ) ) {
			return '';
		}
		return '+55' . $digits;
	}

	/* ---------------------------------------------------------------------
	 * Apresentação e CSV
	 * ------------------------------------------------------------------- */

	public static function step_label( $step ) {
		$labels = array(
			'none'           => __( 'Sem captura', 'comment-flow' ),
			'awaiting_optin' => __( 'Aguardando resposta', 'comment-flow' ),
			'awaiting_email' => __( 'Aguardando e-mail', 'comment-flow' ),
			'awaiting_phone' => __( 'Aguardando telefone', 'comment-flow' ),
			'awaiting_follow' => __( 'Aguardando seguir', 'comment-flow' ),
			'done'           => __( 'Concluída', 'comment-flow' ),
		);
		return isset( $labels[ $step ] ) ? $labels[ $step ] : $step;
	}

	public static function csv_headers() {
		return array(
			__( 'Usuário', 'comment-flow' ),
			__( 'ID Instagram', 'comment-flow' ),
			__( 'E-mail', 'comment-flow' ),
			__( 'Telefone', 'comment-flow' ),
			__( 'Consentimento em (UTC)', 'comment-flow' ),
			__( 'Automação', 'comment-flow' ),
			__( 'Última interação', 'comment-flow' ),
			__( 'Interações', 'comment-flow' ),
			__( 'DM enviada', 'comment-flow' ),
			__( 'Captura', 'comment-flow' ),
			__( 'Primeiro contato (UTC)', 'comment-flow' ),
		);
	}

	public static function csv_row( array $lead, array $automation_names ) {
		$automation = isset( $automation_names[ $lead['automation_id'] ] ) ? $automation_names[ $lead['automation_id'] ] : '#' . $lead['automation_id'];
		return array_map(
			array( __CLASS__, 'csv_safe' ),
			array(
				$lead['username'], // Sem "@": no Excel, "@" no início vira fórmula.
				'' !== $lead['ig_scoped_id'] ? $lead['ig_scoped_id'] : $lead['comment_from_id'],
				$lead['email'],
				$lead['phone'],
				(string) $lead['consent_at'],
				$automation,
				(string) $lead['last_comment'],
				(string) $lead['comments_count'],
				$lead['dm_sent'] ? __( 'Sim', 'comment-flow' ) : __( 'Não', 'comment-flow' ),
				self::step_label( $lead['capture_step'] ),
				$lead['created_at'],
			)
		);
	}

	/**
	 * Evita injeção de fórmulas no Excel/Planilhas (=, @, +, - no início),
	 * sem estragar telefones como "+5511...".
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		if ( '' !== $value && preg_match( '/^(?:[=@\t\r]|[+\-](?!\d+$))/', $value ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Envia o CSV ao navegador. Separador ";" e BOM UTF-8 para abrir direto
	 * no Excel em português.
	 */
	public static function stream_csv( $automation_id, $search = '' ) {
		$names    = wp_list_pluck( CFlow_DB::get_automations(), 'name', 'id' );
		$basename = $automation_id && isset( $names[ $automation_id ] ) ? sanitize_title( $names[ $automation_id ] ) : 'todas-automacoes';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="leads-' . $basename . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, self::csv_headers(), ';', '"', '' );

		$offset = 0;
		do {
			$batch = self::query(
				array(
					'automation_id' => $automation_id,
					'search'        => $search,
					'limit'         => 500,
					'offset'        => $offset,
				)
			);
			foreach ( $batch as $lead ) {
				fputcsv( $out, self::csv_row( $lead, $names ), ';', '"', '' );
			}
			$offset += 500;
		} while ( count( $batch ) === 500 );

		fclose( $out );
	}
}
