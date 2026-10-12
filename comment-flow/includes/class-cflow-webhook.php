<?php
/**
 * Endpoint de webhook da Meta: /wp-json/comment-flow/v1/webhook
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Webhook {

	const NAMESPACE_V1 = 'comment-flow/v1';
	const ROUTE        = '/webhook';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'verify' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'receive' ),
					'permission_callback' => '__return_true', // Autenticado pela assinatura HMAC.
				),
			)
		);
	}

	/**
	 * Handshake de verificação: a Meta envia hub.mode, hub.verify_token e
	 * hub.challenge (o PHP converte os pontos em "_") e espera o challenge puro.
	 */
	public static function verify( WP_REST_Request $request ) {
		$token    = (string) $request->get_param( 'hub_verify_token' );
		$expected = (string) CFlow_Settings::get( 'verify_token' );

		if ( 'subscribe' !== $request->get_param( 'hub_mode' ) || '' === $expected || ! hash_equals( $expected, $token ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid verify token' ), 403 );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( (string) $request->get_param( 'hub_challenge' ) );
		exit;
	}

	public static function receive( WP_REST_Request $request ) {
		$body = $request->get_body();

		if ( ! self::valid_signature( $body, (string) $request->get_header( 'x_hub_signature_256' ) ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signature' ), 401 );
		}

		$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) || empty( $payload['entry'] ) || ! is_array( $payload['entry'] ) ) {
			return new WP_REST_Response( array( 'received' => 0 ), 200 );
		}

		$own_id   = (string) CFlow_Settings::get( 'ig_user_id' );
		$delay    = (int) CFlow_Settings::get( 'delay_seconds' );
		$received = 0;

		foreach ( self::extract_comments( $payload ) as $comment ) {
			// Ignora comentários da própria conta (inclusive as respostas do plugin).
			if ( $comment['from_id'] === $comment['account_id'] || ( '' !== $own_id && $comment['from_id'] === $own_id ) ) {
				continue;
			}

			$log_id = CFlow_DB::claim_comment( $comment );
			if ( $log_id ) {
				CFlow_Queue::push( $log_id, $delay );
				++$received;
			}
		}

		// Responde rápido; o trabalho pesado fica na fila.
		return new WP_REST_Response( array( 'received' => $received ), 200 );
	}

	public static function valid_signature( $body, $header ) {
		$secret = (string) CFlow_Settings::get( 'app_secret' );
		if ( '' === $secret || 0 !== strpos( $header, 'sha256=' ) ) {
			return false;
		}
		$expected = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		return hash_equals( $expected, $header );
	}

	/**
	 * Extrai os comentários de um payload de webhook do Instagram.
	 *
	 * @return array[] Cada item: id, text, media_id, from_id, from_username, account_id.
	 */
	public static function extract_comments( array $payload ) {
		$comments = array();

		foreach ( $payload['entry'] as $entry ) {
			if ( empty( $entry['changes'] ) || ! is_array( $entry['changes'] ) ) {
				continue;
			}
			foreach ( $entry['changes'] as $change ) {
				if ( ! isset( $change['field'], $change['value']['id'] ) || ! in_array( $change['field'], array( 'comments', 'live_comments' ), true ) ) {
					continue;
				}
				$value      = $change['value'];
				$comments[] = array(
					'id'            => (string) $value['id'],
					'text'          => isset( $value['text'] ) ? (string) $value['text'] : '',
					'media_id'      => isset( $value['media']['id'] ) ? (string) $value['media']['id'] : '',
					'from_id'       => isset( $value['from']['id'] ) ? (string) $value['from']['id'] : '',
					'from_username' => isset( $value['from']['username'] ) ? (string) $value['from']['username'] : '',
					'account_id'    => isset( $entry['id'] ) ? (string) $entry['id'] : '',
				);
			}
		}

		return $comments;
	}
}
