<?php
/**
 * Cliente mínimo da Instagram API with Instagram Login (graph.instagram.com).
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Instagram_API {

	const GRAPH_HOST = 'https://graph.instagram.com';
	const AUTH_HOST  = 'https://www.instagram.com';
	const TOKEN_HOST = 'https://api.instagram.com';

	const SCOPES = array(
		'instagram_business_basic',
		'instagram_business_manage_comments',
		'instagram_business_manage_messages',
	);

	/**
	 * Códigos de erro da Graph API que valem nova tentativa (limites de taxa
	 * e erros temporários).
	 */
	const RETRYABLE_CODES = array( 1, 2, 4, 17, 32, 341, 613 );

	private $access_token;

	public function __construct( $access_token = null ) {
		$this->access_token = null === $access_token ? CFlow_Settings::get( 'access_token' ) : $access_token;
	}

	/* ---------------------------------------------------------------------
	 * OAuth
	 * ------------------------------------------------------------------- */

	public static function authorize_url( $app_id, $redirect_uri, $state ) {
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( $app_id ),
				'redirect_uri'  => rawurlencode( $redirect_uri ),
				'response_type' => 'code',
				'scope'         => rawurlencode( implode( ',', self::SCOPES ) ),
				'state'         => rawurlencode( $state ),
			),
			self::AUTH_HOST . '/oauth/authorize'
		);
	}

	/**
	 * Troca o code por um token curto e depois por um token de longa duração (60 dias).
	 *
	 * @return array|WP_Error { access_token, expires_in, user_id }
	 */
	public static function exchange_code( $app_id, $app_secret, $redirect_uri, $code ) {
		$short = self::parse_response(
			wp_remote_post(
				self::TOKEN_HOST . '/oauth/access_token',
				array(
					'timeout' => 20,
					'body'    => array(
						'client_id'     => $app_id,
						'client_secret' => $app_secret,
						'grant_type'    => 'authorization_code',
						'redirect_uri'  => $redirect_uri,
						'code'          => $code,
					),
				)
			)
		);
		if ( is_wp_error( $short ) ) {
			return $short;
		}
		// A resposta pode vir no formato antigo ou dentro de "data".
		if ( isset( $short['data'][0] ) ) {
			$short = $short['data'][0];
		}
		if ( empty( $short['access_token'] ) ) {
			return new WP_Error( 'cflow_oauth', __( 'A Meta não retornou um token de acesso.', 'comment-flow' ) );
		}

		$long = self::parse_response(
			wp_remote_get(
				add_query_arg(
					array(
						'grant_type'    => 'ig_exchange_token',
						'client_secret' => rawurlencode( $app_secret ),
						'access_token'  => rawurlencode( $short['access_token'] ),
					),
					self::GRAPH_HOST . '/access_token'
				),
				array( 'timeout' => 20 )
			)
		);
		if ( is_wp_error( $long ) ) {
			return $long;
		}

		return array(
			'access_token' => $long['access_token'],
			'expires_in'   => isset( $long['expires_in'] ) ? (int) $long['expires_in'] : 60 * DAY_IN_SECONDS,
			'user_id'      => isset( $short['user_id'] ) ? (string) $short['user_id'] : '',
		);
	}

	/**
	 * Renova um token de longa duração (precisa ter ao menos 24h de idade).
	 *
	 * @return array|WP_Error { access_token, expires_in }
	 */
	public function refresh_token() {
		return self::parse_response(
			wp_remote_get(
				add_query_arg(
					array(
						'grant_type'   => 'ig_refresh_token',
						'access_token' => rawurlencode( $this->access_token ),
					),
					self::GRAPH_HOST . '/refresh_access_token'
				),
				array( 'timeout' => 20 )
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Conta e mídia
	 * ------------------------------------------------------------------- */

	public function me() {
		return $this->get( 'me', array( 'fields' => 'user_id,username' ) );
	}

	public function recent_media( $ig_user_id, $limit = 25 ) {
		$res = $this->get(
			$ig_user_id . '/media',
			array(
				'fields' => 'id,caption,media_type,media_product_type,permalink,thumbnail_url,media_url,timestamp',
				'limit'  => (int) $limit,
			)
		);
		return is_wp_error( $res ) ? $res : ( isset( $res['data'] ) ? $res['data'] : array() );
	}

	/**
	 * Inscreve a conta conectada nos webhooks do app.
	 */
	public function subscribe_app( $ig_user_id ) {
		return $this->post( $ig_user_id . '/subscribed_apps', array( 'subscribed_fields' => 'comments,messages' ) );
	}

	/* ---------------------------------------------------------------------
	 * Ações de automação
	 * ------------------------------------------------------------------- */

	/**
	 * Responde publicamente a um comentário.
	 */
	public function reply_to_comment( $comment_id, $message ) {
		return $this->post( $comment_id . '/replies', array( 'message' => $message ) );
	}

	/**
	 * Envia uma Private Reply: DM para quem comentou, vinculada ao comentário.
	 * Só é permitida uma por comentário e em até 7 dias após o comentário.
	 */
	public function send_private_reply( $ig_user_id, $comment_id, $text, $button_label = '', $button_url = '', $quick_reply = '' ) {
		return $this->post_json(
			$ig_user_id . '/messages',
			array(
				'recipient' => array( 'comment_id' => $comment_id ),
				'message'   => self::build_message( $text, $button_label, $button_url, $quick_reply ),
			)
		);
	}

	/**
	 * Envia uma DM comum para um usuário (IGSID). Só funciona dentro da janela
	 * de 24h aberta pela última mensagem da pessoa.
	 */
	public function send_message( $ig_user_id, $recipient_id, $text, $button_label = '', $button_url = '' ) {
		return $this->post_json(
			$ig_user_id . '/messages',
			array(
				'recipient' => array( 'id' => $recipient_id ),
				'message'   => self::build_message( $text, $button_label, $button_url ),
			)
		);
	}

	/**
	 * Monta a mensagem: texto puro, texto com botão de link (button template)
	 * ou texto com um botão de resposta rápida (quick reply).
	 */
	public static function build_message( $text, $button_label = '', $button_url = '', $quick_reply = '' ) {
		if ( '' !== $button_label && '' !== $button_url ) {
			return array(
				'attachment' => array(
					'type'    => 'template',
					'payload' => array(
						'template_type' => 'button',
						'text'          => $text,
						'buttons'       => array(
							array(
								'type'  => 'web_url',
								'url'   => $button_url,
								'title' => $button_label,
							),
						),
					),
				),
			);
		}

		$message = array( 'text' => $text );
		if ( '' !== $quick_reply ) {
			$message['quick_replies'] = array(
				array(
					'content_type' => 'text',
					'title'        => $quick_reply,
					'payload'      => 'CFLOW_OPTIN',
				),
			);
		}
		return $message;
	}

	/* ---------------------------------------------------------------------
	 * HTTP
	 * ------------------------------------------------------------------- */

	private function url( $path ) {
		return self::GRAPH_HOST . '/' . CFLOW_GRAPH_VERSION . '/' . ltrim( $path, '/' );
	}

	private function get( $path, array $params = array() ) {
		$params['access_token'] = $this->access_token;
		return self::parse_response(
			wp_remote_get( add_query_arg( array_map( 'rawurlencode', $params ), $this->url( $path ) ), array( 'timeout' => 20 ) )
		);
	}

	private function post( $path, array $params = array() ) {
		$params['access_token'] = $this->access_token;
		return self::parse_response(
			wp_remote_post( $this->url( $path ), array( 'timeout' => 20, 'body' => $params ) )
		);
	}

	private function post_json( $path, array $payload ) {
		return self::parse_response(
			wp_remote_post(
				$this->url( $path ),
				array(
					'timeout' => 20,
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $this->access_token,
					),
					'body'    => wp_json_encode( $payload ),
				)
			)
		);
	}

	/**
	 * @return array|WP_Error Em erro, os dados incluem 'retryable' => bool.
	 */
	private static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$response->add_data( array( 'retryable' => true ) );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && is_array( $body ) && ! isset( $body['error'] ) ) {
			return $body;
		}

		$error   = is_array( $body ) && isset( $body['error'] ) ? $body['error'] : array();
		$err_num = isset( $error['code'] ) ? (int) $error['code'] : 0;
		$message = isset( $error['message'] ) ? $error['message'] : sprintf( 'HTTP %d', $code );

		return new WP_Error(
			'cflow_api',
			$message,
			array(
				'status'     => $code,
				'error_code' => $err_num,
				'subcode'    => isset( $error['error_subcode'] ) ? (int) $error['error_subcode'] : 0,
				'retryable'  => $code >= 500 || 429 === $code || in_array( $err_num, self::RETRYABLE_CODES, true ),
			)
		);
	}
}
