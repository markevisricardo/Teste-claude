<?php
/**
 * Executa a automação de um comentário: resposta pública + Private Reply.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Processor {

	const MAX_ATTEMPTS = 4;

	public static function process( $log_id ) {
		$log_id = (int) $log_id;
		if ( ! CFlow_DB::lock_log( $log_id ) ) {
			return; // Já processado, em processamento ou inexistente.
		}

		$log = CFlow_DB::get_log( $log_id );
		if ( ! $log ) {
			return;
		}

		// Daqui em diante, tudo pertence ao dono do log.
		CFlow_Tenant::set( (int) $log['user_id'] );
		$settings = CFlow_Accounts::current();
		if ( '' === $settings['access_token'] || '' === $settings['ig_user_id'] ) {
			CFlow_DB::update_log( $log_id, array( 'status' => 'failed', 'error' => __( 'Conta do Instagram não conectada.', 'comment-flow' ) ) );
			return;
		}

		// Numa nova tentativa, mantém a automação escolhida na primeira vez.
		$automation = $log['automation_id'] ? CFlow_DB::get_automation( $log['automation_id'] ) : null;
		if ( ! $automation ) {
			$automation = CFlow_Matcher::find(
				array(
					'text'     => $log['comment_text'],
					'media_id' => $log['media_id'],
				),
				CFlow_DB::get_automations( true, 'comment' )
			);
		}

		if ( ! $automation ) {
			CFlow_DB::update_log( $log_id, array( 'status' => 'no_match' ) );
			return;
		}

		CFlow_DB::update_log( $log_id, array( 'automation_id' => (int) $automation['id'] ) );

		// Em novas tentativas o lead já existe; não contar o comentário de novo.
		$lead_id = $log['automation_id']
			? CFlow_Leads::find_id( (int) $automation['id'], $log['from_id'] )
			: CFlow_Leads::upsert_from_comment( (int) $automation['id'], $log );

		$api   = new CFlow_Instagram_API( $settings['access_token'] );
		$error = null;

		if ( ! $log['public_reply_sent'] ) {
			$reply = CFlow_Matcher::pick_reply( $automation['public_replies'], $log['from_username'] );
			if ( '' !== $reply ) {
				$result = $api->reply_to_comment( $log['comment_id'], $reply );
				if ( is_wp_error( $result ) ) {
					$error = $result;
				} else {
					CFlow_DB::update_log( $log_id, array( 'public_reply_sent' => 1 ) );
				}
			}
		}

		// Quem já concluiu a captura nesta automação recebe direto a entrega.
		$lead    = $lead_id ? CFlow_Leads::get( $lead_id ) : null;
		$capture = self::captures( $automation ) && ! ( $lead && 'done' === $lead['capture_step'] );
		$texts   = CFlow_Conversation::texts( $automation );
		$dm_text = $capture ? $texts['optin_text'] : $automation['dm_text'];

		if ( ! $log['dm_sent'] && '' !== trim( (string) $dm_text ) ) {
			$text = CFlow_Matcher::render( $dm_text, $log['from_username'] );
			if ( $capture ) {
				// Abre a conversa com um botão de resposta rápida: a resposta da
				// pessoa libera a janela de 24h para pedir e-mail/telefone.
				$result = $api->send_private_reply( $settings['ig_user_id'], $log['comment_id'], $text, '', '', $texts['optin_button'] );
				if ( is_wp_error( $result ) && ! self::is_retryable( $result ) ) {
					// Se a resposta rápida for recusada, tenta só com o texto.
					$result = $api->send_private_reply( $settings['ig_user_id'], $log['comment_id'], $text );
				}
			} else {
				$result = $api->send_private_reply(
					$settings['ig_user_id'],
					$log['comment_id'],
					$text,
					$automation['dm_button_label'],
					$automation['dm_button_url']
				);
			}

			if ( is_wp_error( $result ) ) {
				$error = $result;
			} else {
				CFlow_DB::update_log( $log_id, array( 'dm_sent' => 1 ) );
				// Já conversamos com a pessoa: a próxima DM dela não é "primeira mensagem".
				CFlow_Contacts::remember( ! empty( $result['recipient_id'] ) ? (string) $result['recipient_id'] : $log['from_id'] );
				if ( $lead_id ) {
					$lead_data = array(
						'dm_sent'      => 1,
						// O recipient_id é o ID que chega nas mensagens (webhook "messages").
						'ig_scoped_id' => ! empty( $result['recipient_id'] ) ? (string) $result['recipient_id'] : $log['from_id'],
					);
					if ( $capture ) {
						$lead_data['capture_step']     = 'awaiting_optin';
						$lead_data['capture_attempts'] = 0;
					}
					CFlow_Leads::update( $lead_id, $lead_data );
				}
			}
		}

		if ( ! $error ) {
			CFlow_DB::update_log( $log_id, array( 'status' => 'done', 'error' => null ) );
			return;
		}

		$attempts = (int) $log['attempts'];

		if ( self::is_retryable( $error ) && $attempts < self::MAX_ATTEMPTS ) {
			CFlow_DB::update_log( $log_id, array( 'status' => 'retry', 'error' => $error->get_error_message() ) );
			// Backoff exponencial: 2, 4, 8 minutos.
			CFlow_Queue::push( $log_id, MINUTE_IN_SECONDS * ( 2 ** $attempts ) );
			return;
		}

		CFlow_DB::update_log( $log_id, array( 'status' => 'failed', 'error' => $error->get_error_message() ) );
	}

	/**
	 * A automação precisa de conversa na DM (captura de dados ou "seguir
	 * para liberar")? Nesse caso a primeira DM é um convite.
	 */
	public static function captures( array $automation ) {
		return ! empty( $automation['capture_email'] ) || ! empty( $automation['capture_phone'] ) || ! empty( $automation['require_follow'] );
	}

	public static function is_retryable( WP_Error $error ) {
		$data = $error->get_error_data();
		return is_array( $data ) && ! empty( $data['retryable'] );
	}
}
