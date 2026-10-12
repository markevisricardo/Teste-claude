<?php
/**
 * Conversas pela DM: captura de e-mail/telefone, "seguir para liberar" e
 * entrega da mensagem final.
 *
 * Uma conversa começa de dois jeitos:
 * - por comentário: Private Reply com botão "Sim, quero!"; a resposta da
 *   pessoa abre a janela de 24h (etapa awaiting_optin);
 * - por DM: a própria mensagem da pessoa (palavra-chave ou primeira DM)
 *   já abre a janela, então o fluxo começa direto.
 *
 * Depois: pede e-mail → pede telefone → confere se segue a conta → envia a
 * mensagem final (dm_text + botão). Cada resposta chega pelo webhook "messages".
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
			'follow_prompt'  => __( 'Falta só um passo: me segue aqui no Instagram 💙 Depois toca no botão abaixo que eu libero pra você 👇', 'comment-flow' ),
			'follow_button'  => __( 'Já segui ✅', 'comment-flow' ),
			'follow_retry'   => __( 'Ainda não apareceu aqui que você me segue 😅 Me segue e toca em "Já segui" de novo.', 'comment-flow' ),
			'invalid_email'  => __( 'Hmm, esse e-mail não parece válido 🤔 Pode conferir e mandar de novo?', 'comment-flow' ),
			'invalid_phone'  => __( 'Não consegui entender esse número 🤔 Manda com DDD, ex.: 11 91234-5678 (ou responda PULAR).', 'comment-flow' ),
		);
	}

	/**
	 * Processa uma mensagem recebida (executado pela fila).
	 */
	public static function handle( $user_id, $sender_id, $text, $mid, $attempt = 0 ) {
		CFlow_Tenant::set( (int) $user_id );
		$settings = CFlow_Accounts::current();
		if ( '' === $settings['access_token'] || '' === $settings['ig_user_id'] ) {
			return;
		}

		$sender_id = (string) $sender_id;
		$api       = new CFlow_Instagram_API( $settings['access_token'] );

		$lead = CFlow_Leads::find_open_conversation( $sender_id );
		if ( ! $lead ) {
			// Sem conversa em andamento: talvez a mensagem dispare uma automação de DM.
			$lead = self::start_from_dm( $sender_id, (string) $text, $api, $settings['ig_user_id'] );
			if ( ! $lead ) {
				return;
			}
		} else {
			CFlow_Contacts::remember( $sender_id );
		}

		if ( '' !== $mid && $lead['last_mid'] === $mid ) {
			return; // Mensagem repetida.
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

		$recipient = '' !== $lead['ig_scoped_id'] ? $lead['ig_scoped_id'] : $sender_id;
		$error     = null;

		$plan = self::next( $lead, $automation, (string) $text );
		if ( ! empty( $plan['check_follow'] ) ) {
			$follows = $api->user_follows( $recipient );
			if ( is_wp_error( $follows ) ) {
				if ( CFlow_Processor::is_retryable( $follows ) && $attempt < self::MAX_ATTEMPTS ) {
					CFlow_Leads::unlock( $lead['id'] );
					CFlow_Queue::push_message( $sender_id, $text, $mid, $attempt + 1, MINUTE_IN_SECONDS * ( 2 ** $attempt ) );
					return;
				}
				// Sem como conferir: entrega mesmo assim para não travar a pessoa.
				$error   = $follows;
				$follows = true;
			}
			$plan = self::next( $lead, $automation, (string) $text, $follows );
		}

		foreach ( $plan['send'] as $message ) {
			$result = $api->send_message(
				$settings['ig_user_id'],
				$recipient,
				CFlow_Matcher::render( $message['text'], $lead['username'] ),
				$message['button_label'],
				$message['button_url'],
				$message['quick_reply']
			);
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
	 * Procura uma automação de DM (palavra-chave ou boas-vindas) para a
	 * mensagem e, se houver, cria o lead já pronto para seguir o fluxo.
	 *
	 * @return array|null O lead, ou null se nenhuma automação se aplica.
	 */
	private static function start_from_dm( $sender_id, $text, CFlow_Instagram_API $api, $ig_user_id ) {
		$automations = CFlow_DB::get_automations( true, 'dm' );
		if ( empty( $automations ) ) {
			CFlow_Contacts::remember( $sender_id );
			return null;
		}

		$is_first   = CFlow_Contacts::is_first_conversation( $sender_id, $api, $ig_user_id );
		$automation = CFlow_Matcher::find_dm( $text, $automations, $is_first );
		if ( ! $automation ) {
			return null;
		}

		$profile  = $api->user_profile( $sender_id );
		$username = ! is_wp_error( $profile ) && isset( $profile['username'] ) ? (string) $profile['username'] : '';

		$lead_id = CFlow_Leads::upsert( (int) $automation['id'], $sender_id, $username, $text );
		if ( ! $lead_id ) {
			return null;
		}

		// A mensagem da pessoa já abriu a janela de 24h: começa como se ela
		// tivesse aceitado o convite. Quem já passou por esta automação antes
		// recebe a entrega de novo (e-mail/telefone já salvos não são pedidos).
		CFlow_Leads::update(
			$lead_id,
			array(
				'ig_scoped_id'     => $sender_id,
				'capture_step'     => 'awaiting_optin',
				'capture_attempts' => 0,
				'last_mid'         => '',
				'last_error'       => null,
			)
		);

		return CFlow_Leads::get( $lead_id );
	}

	/**
	 * Decide o próximo passo da conversa, sem chamar a API.
	 *
	 * @param bool|null $follows Se a pessoa segue a conta (null = ainda não conferido).
	 * @return array { update: colunas do lead, send: mensagens, check_follow?: true }
	 *               Com check_follow, quem chama deve conferir se a pessoa
	 *               segue a conta e chamar de novo informando $follows.
	 */
	public static function next( array $lead, array $automation, $text, $follows = null ) {
		$texts = self::texts( $automation );
		$now   = current_time( 'mysql', true );

		switch ( $lead['capture_step'] ) {
			case 'awaiting_optin':
				return self::ask_next( $lead, $automation, array(), $follows );

			case 'awaiting_email':
				$email = CFlow_Leads::extract_email( $text );
				if ( '' !== $email ) {
					$lead['email'] = $email;
					return self::ask_next( $lead, $automation, self::with_consent( $lead, array( 'email' => $email ), $now ), $follows );
				}
				return self::invalid( $lead, $automation, 'email', $texts['invalid_email'], $follows );

			case 'awaiting_phone':
				if ( in_array( CFlow_Matcher::normalize( $text ), self::SKIP_KEYWORDS, true ) ) {
					return self::ask_next( $lead, $automation, array(), $follows, array( 'phone' ) );
				}
				$phone = CFlow_Leads::normalize_phone( $text );
				if ( '' !== $phone ) {
					$lead['phone'] = $phone;
					return self::ask_next( $lead, $automation, self::with_consent( $lead, array( 'phone' => $phone ), $now ), $follows );
				}
				return self::invalid( $lead, $automation, 'phone', $texts['invalid_phone'], $follows );

			case 'awaiting_follow':
				if ( null === $follows ) {
					return self::check_follow( array() );
				}
				if ( $follows ) {
					return self::finish( $automation, array() );
				}
				return array(
					'update' => array( 'capture_attempts' => (int) $lead['capture_attempts'] + 1 ),
					'send'   => array( self::message( $texts['follow_retry'], '', '', $texts['follow_button'] ) ),
				);
		}

		return array( 'update' => array(), 'send' => array() );
	}

	/**
	 * Próximo passo que falta: e-mail → telefone → seguir → entrega.
	 *
	 * @param string[] $skip Dados que não devem mais ser pedidos ('email', 'phone').
	 */
	private static function ask_next( array $lead, array $automation, array $update, $follows, array $skip = array() ) {
		$texts = self::texts( $automation );

		if ( ! empty( $automation['capture_email'] ) && '' === $lead['email'] && ! in_array( 'email', $skip, true ) ) {
			return self::ask( 'awaiting_email', $texts['email_question'], $update );
		}
		if ( ! empty( $automation['capture_phone'] ) && '' === $lead['phone'] && ! in_array( 'phone', $skip, true ) ) {
			return self::ask( 'awaiting_phone', $texts['phone_question'], $update );
		}
		if ( ! empty( $automation['require_follow'] ) ) {
			if ( null === $follows ) {
				return self::check_follow( $update );
			}
			if ( ! $follows ) {
				return array(
					'update' => array_merge( $update, array( 'capture_step' => 'awaiting_follow', 'capture_attempts' => 0 ) ),
					'send'   => array( self::message( $texts['follow_prompt'], '', '', $texts['follow_button'] ) ),
				);
			}
		}
		return self::finish( $automation, $update );
	}

	private static function check_follow( array $update ) {
		return array(
			'update'       => $update,
			'send'         => array(),
			'check_follow' => true,
		);
	}

	private static function ask( $step, $question, array $update ) {
		return array(
			'update' => array_merge( $update, array( 'capture_step' => $step, 'capture_attempts' => 0 ) ),
			'send'   => array( self::message( $question ) ),
		);
	}

	private static function invalid( array $lead, array $automation, $field, $retry_text, $follows ) {
		$attempts = (int) $lead['capture_attempts'] + 1;
		if ( $attempts >= self::MAX_INVALID ) {
			// Desiste deste dado e segue o fluxo para não travar a entrega.
			return self::ask_next( $lead, $automation, array(), $follows, 'email' === $field ? array( 'email' ) : array( 'email', 'phone' ) );
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

	private static function message( $text, $button_label = '', $button_url = '', $quick_reply = '' ) {
		return array(
			'text'         => (string) $text,
			'button_label' => (string) $button_label,
			'button_url'   => (string) $button_url,
			'quick_reply'  => (string) $quick_reply,
		);
	}

	/**
	 * Textos da automação, com os padrões no lugar dos campos vazios.
	 */
	public static function texts( array $automation ) {
		$texts = self::defaults();
		foreach ( array( 'optin_text', 'optin_button', 'email_question', 'phone_question', 'follow_prompt', 'follow_button', 'follow_retry' ) as $key ) {
			if ( isset( $automation[ $key ] ) && '' !== trim( (string) $automation[ $key ] ) ) {
				$texts[ $key ] = $automation[ $key ];
			}
		}
		return $texts;
	}
}
