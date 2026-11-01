<?php
/**
 * Conta do Instagram de cada usuário (uma por usuário, e cada conta do
 * Instagram só pode estar ligada a um usuário).
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Accounts {

	/**
	 * Conta do inquilino atual, com o token já descriptografado. Sempre
	 * retorna as chaves (vazias quando não há conta).
	 */
	public static function current() {
		return self::for_user( CFlow_Tenant::id() );
	}

	public static function for_user( $user_id ) {
		global $wpdb;
		$row = $user_id ? $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . CFlow_DB::accounts_table() . ' WHERE user_id = %d', $user_id ),
			ARRAY_A
		) : null;

		return self::decode( $row ? $row : array( 'user_id' => (int) $user_id ) );
	}

	public static function by_ig_user( $ig_user_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . CFlow_DB::accounts_table() . ' WHERE ig_user_id = %s', (string) $ig_user_id ),
			ARRAY_A
		);
		return $row ? self::decode( $row ) : null;
	}

	public static function is_connected() {
		$account = self::current();
		return '' !== $account['access_token'] && '' !== $account['ig_user_id'];
	}

	/**
	 * Liga a conta do Instagram ao usuário (substitui a anterior dele).
	 *
	 * @return true|WP_Error
	 */
	public static function connect( $user_id, $ig_user_id, $username, $access_token, $expires_in ) {
		global $wpdb;
		$owner = self::by_ig_user( $ig_user_id );
		if ( $owner && (int) $owner['user_id'] !== (int) $user_id ) {
			return new WP_Error( 'cflow_account', __( 'Esta conta do Instagram já está conectada a outro usuário da plataforma.', 'comment-flow' ) );
		}

		$existing = self::for_user( $user_id );
		$wpdb->replace(
			CFlow_DB::accounts_table(),
			array(
				'user_id'          => (int) $user_id,
				'ig_user_id'       => (string) $ig_user_id,
				'ig_username'      => (string) $username,
				'access_token'     => CFlow_Crypto::encrypt( (string) $access_token ),
				'token_expires_at' => time() + (int) $expires_in,
				'delay_seconds'    => (int) $existing['delay_seconds'],
				'connected_at'     => current_time( 'mysql', true ),
			)
		);
		return true;
	}

	public static function update( $user_id, array $data ) {
		global $wpdb;
		if ( isset( $data['access_token'] ) ) {
			$data['access_token'] = CFlow_Crypto::encrypt( (string) $data['access_token'] );
		}
		$wpdb->update( CFlow_DB::accounts_table(), $data, array( 'user_id' => (int) $user_id ) );
	}

	/**
	 * Preferências guardadas mesmo sem Instagram conectado.
	 */
	public static function save_preferences( $user_id, $delay_seconds ) {
		global $wpdb;
		$table = CFlow_DB::accounts_table();
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (user_id, delay_seconds) VALUES (%d, %d) ON DUPLICATE KEY UPDATE delay_seconds = VALUES(delay_seconds)",
				$user_id,
				$delay_seconds
			)
		);
	}

	public static function disconnect( $user_id ) {
		self::update(
			$user_id,
			array(
				'ig_user_id'       => null,
				'ig_username'      => '',
				'access_token'     => '',
				'token_expires_at' => 0,
			)
		);
	}

	public static function disconnect_ig_user( $ig_user_id ) {
		$account = self::by_ig_user( $ig_user_id );
		if ( $account ) {
			self::disconnect( $account['user_id'] );
		}
	}

	/**
	 * Contas cujo token vence nos próximos $seconds (para renovação).
	 */
	public static function expiring( $seconds ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . CFlow_DB::accounts_table() . " WHERE access_token <> '' AND token_expires_at < %d",
				time() + (int) $seconds
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'decode' ), $rows );
	}

	private static function decode( array $row ) {
		$row += array(
			'ig_user_id'       => '',
			'ig_username'      => '',
			'access_token'     => '',
			'token_expires_at' => 0,
			'delay_seconds'    => 5,
			'connected_at'     => null,
		);
		$row['ig_user_id']   = (string) $row['ig_user_id'];
		$row['access_token'] = CFlow_Crypto::decrypt( (string) $row['access_token'] );
		return $row;
	}
}
