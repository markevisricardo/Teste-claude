<?php
/**
 * WhatsApp Business (Cloud API da Meta).
 *
 * O WhatsApp não tem API para postar em Status nem em Canais. O caminho
 * oficial é enviar mensagem a contatos que autorizaram receber (opt-in),
 * usando um modelo de mensagem aprovado pela Meta, porque mensagens fora
 * da janela de 24 horas de conversa só podem ser modelos.
 *
 * Cada usuário conecta o próprio número (WhatsApp Business Account): cola o
 * ID do número, um token permanente (usuário do sistema), o nome do modelo
 * e a lista de contatos. O texto do post entra na variável {{1}} do modelo.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Provider_WhatsApp extends PFlow_Provider {

	const MAX_RECIPIENTS = 250;

	public function id() {
		return 'whatsapp';
	}

	/** As credenciais são de cada usuário: não depende do administrador. */
	public function is_configured() {
		return true;
	}

	public function connect_mode() {
		return 'form';
	}

	public function scopes() {
		return array( 'whatsapp_business_messaging' );
	}

	public function authorize_url( $state, $redirect_uri, $code_verifier ) {
		return '';
	}

	public function connect( $code, $redirect_uri, $code_verifier ) {
		return new WP_Error( 'pflow_whatsapp', __( 'O WhatsApp é conectado pelo formulário.', 'post-flow' ) );
	}

	private static function graph( $path ) {
		return 'https://graph.facebook.com/' . PFLOW_GRAPH_VERSION . '/' . ltrim( $path, '/' );
	}

	/**
	 * Lista de contatos: um número por linha (ou separados por vírgula), só
	 * dígitos com DDI, sem repetidos.
	 *
	 * @return string[]
	 */
	public static function parse_recipients( $raw ) {
		$out = array();
		foreach ( preg_split( '/[\r\n,;]+/', (string) $raw ) as $item ) {
			$digits = preg_replace( '/\D/', '', $item );
			if ( strlen( $digits ) >= 10 && strlen( $digits ) <= 15 ) {
				$out[ $digits ] = $digits;
			}
		}
		return array_values( $out );
	}

	/**
	 * Valida os dados do formulário e confere o número na API.
	 *
	 * @param array $fields phone_number_id, access_token, template, language,
	 *                      header_image (bool), recipients (texto).
	 * @return array|WP_Error Conta pronta para PFlow_Channels::upsert().
	 */
	public function connect_form( array $fields ) {
		$phone_id   = preg_replace( '/\D/', '', (string) ( $fields['phone_number_id'] ?? '' ) );
		$token      = trim( (string) ( $fields['access_token'] ?? '' ) );
		$template   = strtolower( preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $fields['template'] ?? '' ) ) );
		$language   = preg_replace( '/[^A-Za-z_]/', '', (string) ( $fields['language'] ?? 'pt_BR' ) );
		$recipients = self::parse_recipients( $fields['recipients'] ?? '' );

		if ( '' === $phone_id || '' === $token || '' === $template ) {
			return new WP_Error( 'pflow_whatsapp', __( 'Preencha o ID do número, o token e o nome do modelo.', 'post-flow' ) );
		}
		if ( ! $recipients ) {
			return new WP_Error( 'pflow_whatsapp', __( 'Informe ao menos um número de contato (com DDI, ex.: 5511999998888).', 'post-flow' ) );
		}
		if ( count( $recipients ) > self::MAX_RECIPIENTS ) {
			/* translators: %d: máximo */
			return new WP_Error( 'pflow_whatsapp', sprintf( __( 'A lista aceita até %d contatos por canal.', 'post-flow' ), self::MAX_RECIPIENTS ) );
		}

		$info = PFlow_Http::request( 'GET', self::graph( $phone_id ), array( 'query' => array( 'fields' => 'display_phone_number,verified_name', 'access_token' => $token ) ) );
		if ( is_wp_error( $info ) ) {
			/* translators: %s: erro da API */
			return new WP_Error( 'pflow_whatsapp', sprintf( __( 'Não foi possível validar o número: %s', 'post-flow' ), $info->get_error_message() ) );
		}
		$name  = (string) ( $info['body']['verified_name'] ?? 'WhatsApp' );
		$phone = (string) ( $info['body']['display_phone_number'] ?? '' );

		return array(
			'network'          => 'whatsapp',
			'external_id'      => $phone_id,
			'provider_user_id' => $phone_id,
			'name'             => '' !== $phone ? $name . ' · ' . $phone : $name,
			'username'         => '',
			'avatar_url'       => '',
			'access_token'     => $token,
			'refresh_token'    => '',
			'token_expires_at' => 0,
			'meta'             => array(
				'template'     => $template,
				'language'     => '' !== $language ? $language : 'pt_BR',
				'header_image' => ! empty( $fields['header_image'] ),
				'recipients'   => $recipients,
			),
		);
	}

	/**
	 * Variáveis de modelo não aceitam quebra de linha, tabulação nem mais de
	 * 4 espaços seguidos.
	 */
	public static function template_text( $text ) {
		$text = preg_replace( '/\s*[\r\n\t]+\s*/u', ' · ', trim( (string) $text ) );
		return preg_replace( '/ {4,}/', '   ', $text );
	}

	public function publish( array $channel, array $post ) {
		$meta       = $channel['meta'];
		$recipients = (array) ( $meta['recipients'] ?? array() );
		if ( ! $recipients || empty( $meta['template'] ) ) {
			return new WP_Error( 'pflow_whatsapp', __( 'Configure o modelo e a lista de contatos do WhatsApp.', 'post-flow' ) );
		}

		$components = array();
		if ( ! empty( $meta['header_image'] ) ) {
			if ( ! $post['media'] ) {
				return new WP_Error( 'pflow_whatsapp', __( 'O modelo do WhatsApp tem imagem no cabeçalho: adicione uma imagem ao post.', 'post-flow' ) );
			}
			$components[] = array(
				'type'       => 'header',
				'parameters' => array( array( 'type' => 'image', 'image' => array( 'link' => $post['media'][0]['url'] ) ) ),
			);
		}
		$components[] = array(
			'type'       => 'body',
			'parameters' => array( array( 'type' => 'text', 'text' => self::template_text( $post['text'] ) ) ),
		);

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$sent  = 0;
		$error = null;
		foreach ( $recipients as $to ) {
			$res = PFlow_Http::request(
				'POST',
				self::graph( $channel['external_id'] . '/messages' ),
				array(
					'headers' => array( 'Authorization' => 'Bearer ' . $channel['access_token'] ),
					'json'    => array(
						'messaging_product' => 'whatsapp',
						'to'                => $to,
						'type'              => 'template',
						'template'          => array(
							'name'       => $meta['template'],
							'language'   => array( 'code' => $meta['language'] ),
							'components' => $components,
						),
					),
				)
			);
			if ( is_wp_error( $res ) ) {
				$error = $error ? $error : $res;
				$data  = $res->get_error_data();
				// Token inválido ou modelo inexistente: não adianta seguir a lista.
				if ( is_array( $data ) && in_array( (int) ( $data['status'] ?? 0 ), array( 401, 403 ), true ) ) {
					break;
				}
				continue;
			}
			++$sent;
		}

		if ( ! $sent ) {
			return $error ? $error : new WP_Error( 'pflow_whatsapp', __( 'Nenhuma mensagem foi enviada.', 'post-flow' ) );
		}
		return array(
			/* translators: 1: enviadas, 2: total */
			'remote_id'  => sprintf( __( '%1$d de %2$d contatos', 'post-flow' ), $sent, count( $recipients ) ),
			'remote_url' => '',
		);
	}
}
