<?php
/**
 * Chamadas à API do Instagram feitas pelo hub: OAuth (troca do code por um
 * token de longa duração) e inscrição da conta nos webhooks do app.
 * O hub não guarda tokens: ele os entrega ao site do cliente.
 */

defined( 'ABSPATH' ) || exit;

class CFHub_Instagram {

	const GRAPH_HOST = 'https://graph.instagram.com';
	const AUTH_HOST  = 'https://www.instagram.com';
	const TOKEN_HOST = 'https://api.instagram.com';

	const SCOPES = array(
		'instagram_business_basic',
		'instagram_business_manage_comments',
		'instagram_business_manage_messages',
	);

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
	 * code → token curto → token de longa duração (60 dias) + dados da conta.
	 *
	 * @return array|WP_Error { access_token, expires_in, ig_user_id, username }
	 */
	public static function connect( $app_id, $app_secret, $redirect_uri, $code ) {
		$short = self::parse(
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
		if ( isset( $short['data'][0] ) ) {
			$short = $short['data'][0];
		}
		if ( empty( $short['access_token'] ) ) {
			return new WP_Error( 'cfhub_oauth', __( 'A Meta não retornou um token de acesso.', 'comment-flow-hub' ) );
		}

		$long = self::parse(
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

		$token = $long['access_token'];
		$me    = self::parse(
			wp_remote_get(
				add_query_arg(
					array(
						'fields'       => 'user_id,username',
						'access_token' => rawurlencode( $token ),
					),
					self::GRAPH_HOST . '/' . CFHUB_GRAPH_VERSION . '/me'
				),
				array( 'timeout' => 20 )
			)
		);
		if ( is_wp_error( $me ) ) {
			return $me;
		}

		return array(
			'access_token' => $token,
			'expires_in'   => isset( $long['expires_in'] ) ? (int) $long['expires_in'] : 60 * DAY_IN_SECONDS,
			'ig_user_id'   => isset( $me['user_id'] ) ? (string) $me['user_id'] : ( isset( $short['user_id'] ) ? (string) $short['user_id'] : '' ),
			'username'     => isset( $me['username'] ) ? (string) $me['username'] : '',
		);
	}

	/**
	 * Inscreve a conta nos webhooks do app (comentários e mensagens).
	 */
	public static function subscribe( $ig_user_id, $access_token ) {
		return self::parse(
			wp_remote_post(
				self::GRAPH_HOST . '/' . CFHUB_GRAPH_VERSION . '/' . rawurlencode( $ig_user_id ) . '/subscribed_apps',
				array(
					'timeout' => 20,
					'body'    => array(
						'subscribed_fields' => 'comments,messages',
						'access_token'      => $access_token,
					),
				)
			)
		);
	}

	/**
	 * Lê o signed_request que a Meta envia nos callbacks de desautorização e
	 * de exclusão de dados. Retorna o payload ou null se a assinatura falhar.
	 */
	public static function parse_signed_request( $signed_request, $app_secret ) {
		$parts = explode( '.', (string) $signed_request, 2 );
		if ( 2 !== count( $parts ) || '' === (string) $app_secret ) {
			return null;
		}
		list( $encoded_sig, $encoded_payload ) = $parts;

		$sig      = self::base64url_decode( $encoded_sig );
		$expected = hash_hmac( 'sha256', $encoded_payload, $app_secret, true );
		if ( false === $sig || ! hash_equals( $expected, $sig ) ) {
			return null;
		}

		$payload = json_decode( (string) self::base64url_decode( $encoded_payload ), true );
		return is_array( $payload ) ? $payload : null;
	}

	private static function base64url_decode( $value ) {
		$value = strtr( (string) $value, '-_', '+/' );
		return base64_decode( $value . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true );
	}

	private static function parse( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 && is_array( $body ) && ! isset( $body['error'] ) ) {
			return $body;
		}
		$message = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message']
			: ( is_array( $body ) && isset( $body['error_message'] ) ? $body['error_message'] : sprintf( 'HTTP %d', $code ) );
		return new WP_Error( 'cfhub_api', $message, array( 'status' => $code ) );
	}
}
