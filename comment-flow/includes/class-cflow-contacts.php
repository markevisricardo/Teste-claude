<?php
/**
 * Registro das pessoas com quem a conta do inquilino já conversou pela DM.
 * Serve para saber se uma mensagem é a primeira (gatilho de boas-vindas).
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Contacts {

	/**
	 * Registra a pessoa. Retorna true só se ela ainda não estava registrada.
	 */
	public static function remember( $ig_scoped_id ) {
		global $wpdb;
		$ig_scoped_id = (string) $ig_scoped_id;
		if ( '' === $ig_scoped_id ) {
			return false;
		}

		return (bool) $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . CFlow_DB::contacts_table() . ' (user_id, ig_scoped_id, first_seen_at) VALUES (%d, %s, %s)',
				CFlow_Tenant::id(),
				$ig_scoped_id,
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * É a primeira conversa com essa pessoa?
	 *
	 * O plugin só conhece quem falou depois que ele foi instalado. Para não dar
	 * boas-vindas a quem já conversava antes, consulta o histórico da conversa
	 * na API: se houver mensagens com mais de 10 minutos, não é a primeira.
	 */
	public static function is_first_conversation( $ig_scoped_id, CFlow_Instagram_API $api, $ig_user_id ) {
		if ( ! self::remember( $ig_scoped_id ) ) {
			return false;
		}

		$oldest = $api->oldest_message_time( $ig_user_id, $ig_scoped_id );
		if ( is_wp_error( $oldest ) || null === $oldest ) {
			return true; // Sem histórico disponível: trata como primeira conversa.
		}
		return $oldest > time() - 10 * MINUTE_IN_SECONDS;
	}
}
