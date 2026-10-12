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

		$settings = CFlow_Settings::all();
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
				CFlow_DB::get_automations( true )
			);
		}

		if ( ! $automation ) {
			CFlow_DB::update_log( $log_id, array( 'status' => 'no_match' ) );
			return;
		}

		CFlow_DB::update_log( $log_id, array( 'automation_id' => (int) $automation['id'] ) );

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

		if ( ! $log['dm_sent'] && '' !== trim( $automation['dm_text'] ) ) {
			$result = $api->send_private_reply(
				$settings['ig_user_id'],
				$log['comment_id'],
				CFlow_Matcher::render( $automation['dm_text'], $log['from_username'] ),
				$automation['dm_button_label'],
				$automation['dm_button_url']
			);
			if ( is_wp_error( $result ) ) {
				$error = $result;
			} else {
				CFlow_DB::update_log( $log_id, array( 'dm_sent' => 1 ) );
			}
		}

		if ( ! $error ) {
			CFlow_DB::update_log( $log_id, array( 'status' => 'done', 'error' => null ) );
			return;
		}

		$data      = $error->get_error_data();
		$retryable = is_array( $data ) && ! empty( $data['retryable'] );
		$attempts  = (int) $log['attempts'];

		if ( $retryable && $attempts < self::MAX_ATTEMPTS ) {
			CFlow_DB::update_log( $log_id, array( 'status' => 'retry', 'error' => $error->get_error_message() ) );
			// Backoff exponencial: 2, 4, 8 minutos.
			CFlow_Queue::push( $log_id, MINUTE_IN_SECONDS * ( 2 ** $attempts ) );
			return;
		}

		CFlow_DB::update_log( $log_id, array( 'status' => 'failed', 'error' => $error->get_error_message() ) );
	}
}
