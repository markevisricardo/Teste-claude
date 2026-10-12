<?php
/**
 * Captura de e-mail e telefone pela DM.
 *
 * Fluxo: Private Reply com botão "Sim, quero!" → a pessoa responde (abre a
 * janela de 24h) → pede e-mail → pede telefone → envia a mensagem final
 * (dm_text + botão). Cada resposta chega pelo webhook "messages".
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Conversation {

	const MAX_INVALID   = 3;
	const MAX_ATTEMPTS  = 3;
	const SKIP_KEYWORDS = array( 'pular', 'pula', 'nao', 'nao quero', 'prefiro nao', 'skip' );

	public static function defaults() {
		return array(
			'optin_text'     => __( 'Oi {username}! 😊 Posso te mandar o material aqui no direct? Toca no botão abaixo 👇', 'comment-flow' ),
			'optin_button'   => __( 'Sim, quero!', 'comment-flow' ),
			'email_question' => __( 'Perfeito! Qual é o seu melhor e-mail? 📧 (Ao enviar, você concorda em receber nossos conteúdos por e-mail.)', 'comment-flow' ),
			'phone_question' => __( 'E qual é o seu WhatsApp com DDD? 📱 Se preferir não informar, responda PULAR.', 'comment-flow' ),
			'invalid_email'  => __( 'Hmm, esse e-mail não parece válido 🤔 Pode conferir e mandar de novo?', 'comment-flow' ),
			'invalid_phone'  => __( 'Não consegui entender esse número 🤔 Manda com DDD, ex.: 11 91234-5678 (ou responda PULAR).', 'comment-flow' ),
		);
	}

	/**
	 * Processa uma mensagem recebida (executado pela fila).
	 */
	public static function handle( $sender_id, $text, $mid, $attempt = 0 ) {
		$settings = CFlow_Settings::all();
		if ( '' === $settings['access_token'] || '' === $settings['ig_user_id'] ) {
			return;
		}

		$lead = CFlow_Leads::find_open_conversation( (string) $sender_id );
		if ( ! $lead || ( '' !== $mid && $lead['last_mid'] === $mid ) ) {
			return; // Sem conversa em andamento ou mensagem repetida.
		}

		if ( ! CFlow_Leads::lock( $lead['id'] ) ) {
			// Outra mensagem da mesma pessoa está sendo processada.
			CFlow_Queue::push_message( $sender_id, $text, $mid, $attempt, 20 );
			return;
		}

		$automation = CFlow_DB::get_automation( $lead['automation_id'] );
		if ( ! $automation ) {
			CFlow_Leads::update( $lead['id'], array( 'capture_step' => 'none', 'locked_until' => null ) );
			return;
		}

		$plan      = self::next( $lead, $automation, (string) $text );
		$recipient = '' !== $lead['ig_scoped_id'] ? $lead['ig_scoped_id'] : (string) $sender_id;
		$api       = new CFlow_Instagram_API( $settings['access_token'] );
		$error     = null;

		foreach ( $plan['send'] as $message ) {
			$result = $api->send_message( $settings['ig_user_id'], $recipient, CFlow_Matcher::render( $message['text'], $lead['username'] ), $message['button_label'], $message['button_url'] );
			if ( is_wp_error( $result ) ) {
				$error = $result;
				break;
			}
		}

		if ( $error && CFlow_Processor::is_retryable( $error ) && $attempt < self::MAX_ATTEMPTS ) {
			// Não avança a etapa: a mesma mensagem será reprocessada.
			CFlow_Leads::unlock( $lead['id'] );
			CFlow_Queue::push_message( $sender_id, $text, $mid, $attempt + 1, MINUTE_IN_SECONDS * ( 2 ** $attempt ) );
			return;
		}

		// Os dados capturados são salvos mesmo se o envio falhar de vez.
		CFlow_Leads::update(
			$lead['id'],
			array_merge(
				$plan['update'],
				array(
					'last_mid'     => (string) $mid,
					'last_error'   => $error ? $error->get_error_message() : null,
					'locked_until' => null,
				)
			)
		);
	}

	/**
	 * Decide o próximo passo da conversa, sem chamar a API.
	 *
	 * @return array { update: array de colunas do lead, send: lista de mensagens }
	 */
	public static function next( array $lead, array $automation, $text ) {
		$texts = self::texts( $automation );
		$now   = current_time( 'mysql', true );

		switch ( $lead['capture_step'] ) {
			case 'awaiting_optin':
				return self::ask_next( $lead, $automation, array() );

			case 'awaiting_email':
				$email = CFlow_Leads::extract_email( $text );
				if ( '' !== $email ) {
					$lead['email'] = $email;
					return self::ask_next( $lead, $automation, self::with_consent( $lead, array( 'email' => $email ), $now ) );
				}
				return self::invalid( $lead, $automation, 'email', $texts['invalid_email'] );

			case 'awaiting_phone':
				if ( in_array( CFlow_Matcher::normalize( $text ), self::SKIP_KEYWORDS, true ) ) {
					return self::finish( $automation, array() );
				}
				$phone = CFlow_Leads::normalize_phone( $text );
				if ( '' !== $phone ) {
					return self::finish( $automation, self::with_consent( $lead, array( 'phone' => $phone ), $now ) );
				}
				return self::invalid( $lead, $automation, 'phone', $texts['invalid_phone'] );
		}

		return array( 'update' => array(), 'send' => array() );
	}

	/**
	 * Pergunta o próximo dado que falta, ou encerra.
	 */
	private static function ask_next( array $lead, array $automation, array $update, array $skip = array() ) {
		$texts = self::texts( $automation );

		if ( ! empty( $automation['capture_email'] ) && '' === $lead['email'] && ! in_array( 'email', $skip, true ) ) {
			return self::ask( 'awaiting_email', $texts['email_question'], $update );
		}
		if ( ! empty( $automation['capture_phone'] ) && '' === $lead['phone'] ) {
			return self::ask( 'awaiting_phone', $texts['phone_question'], $update );
		}
		return self::finish( $automation, $update );
	}

	private static function ask( $step, $question, array $update ) {
		return array(
			'update' => array_merge( $update, array( 'capture_step' => $step, 'capture_attempts' => 0 ) ),
			'send'   => array( self::message( $question ) ),
		);
	}

	private static function invalid( array $lead, array $automation, $field, $retry_text ) {
		$attempts = (int) $lead['capture_attempts'] + 1;
		if ( $attempts >= self::MAX_INVALID ) {
			// Desiste deste dado e segue o fluxo para não travar a entrega.
			return 'email' === $field
				? self::ask_next( $lead, $automation, array(), array( 'email' ) )
				: self::finish( $automation, array() );
		}
		return array(
			'update' => array( 'capture_attempts' => $attempts ),
			'send'   => array( self::message( $retry_text ) ),
		);
	}

	private static function finish( array $automation, array $update ) {
		$send = array();
		if ( '' !== trim( (string) $automation['dm_text'] ) ) {
			$send[] = self::message( $automation['dm_text'], $automation['dm_button_label'], $automation['dm_button_url'] );
		}
		return array(
			'update' => array_merge( $update, array( 'capture_step' => 'done', 'capture_attempts' => 0 ) ),
			'send'   => $send,
		);
	}

	private static function with_consent( array $lead, array $update, $now ) {
		if ( empty( $lead['consent_at'] ) ) {
			$update['consent_at'] = $now;
		}
		return $update;
	}

	private static function message( $text, $button_label = '', $button_url = '' ) {
		return array(
			'text'         => (string) $text,
			'button_label' => (string) $button_label,
			'button_url'   => (string) $button_url,
		);
	}

	/**
	 * Textos da automação, com os padrões no lugar dos campos vazios.
	 */
	public static function texts( array $automation ) {
		$texts = self::defaults();
		foreach ( array( 'optin_text', 'optin_button', 'email_question', 'phone_question' ) as $key ) {
			if ( isset( $automation[ $key ] ) && '' !== trim( (string) $automation[ $key ] ) ) {
				$texts[ $key ] = $automation[ $key ];
			}
		}
		return $texts;
	}
}
