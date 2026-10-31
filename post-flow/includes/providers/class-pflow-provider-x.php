<?php
/**
 * X (Twitter): OAuth 2.0 com PKCE (cliente confidencial) e API v2.
 *
 * O token de acesso dura 2 horas; o refresh token (escopo offline.access)
 * é renovado antes de cada publicação e troca a cada uso.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Provider_X extends PFlow_Provider {

	const API = 'https://api.x.com/2/';

	public function id() {
		return 'x';
	}

	public function is_configured() {
		return '' !== self::setting( 'x_client_id' ) && '' !== self::setting( 'x_client_secret' );
	}

	public function uses_pkce() {
		return true;
	}

	public function scopes() {
		return array( 'tweet.read', 'tweet.write', 'users.read', 'media.write', 'offline.access' );
	}

	public function authorize_url( $state, $redirect_uri, $code_verifier ) {
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
		return add_query_arg(
			array(
				'response_type'         => 'code',
				'client_id'             => rawurlencode( self::setting( 'x_client_id' ) ),
				'redirect_uri'          => rawurlencode( $redirect_uri ),
				'scope'                 => rawurlencode( implode( ' ', $this->scopes() ) ),
				'state'                 => rawurlencode( $state ),
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
			),
			'https://x.com/i/oauth2/authorize'
		);
	}

	private function token_request( array $form ) {
		return PFlow_Http::request(
			'POST',
			self::API . 'oauth2/token',
			array(
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( self::setting( 'x_client_id' ) . ':' . self::setting( 'x_client_secret' ) ) ),
				'form'    => $form + array( 'client_id' => self::setting( 'x_client_id' ) ),
			)
		);
	}

	private static function tokens( array $body, $previous_refresh = '' ) {
		return array(
			'access_token'     => (string) $body['access_token'],
			'refresh_token'    => (string) ( $body['refresh_token'] ?? $previous_refresh ),
			'token_expires_at' => time() + (int) ( $body['expires_in'] ?? 7200 ),
		);
	}

	public function connect( $code, $redirect_uri, $code_verifier ) {
		$token = $this->token_request(
			array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => $redirect_uri,
				'code_verifier' => $code_verifier,
			)
		);
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$tokens = self::tokens( $token['body'] );

		$me = PFlow_Http::request(
			'GET',
			self::API . 'users/me',
			array(
				'query'   => array( 'user.fields' => 'profile_image_url' ),
				'headers' => array( 'Authorization' => 'Bearer ' . $tokens['access_token'] ),
			)
		);
		if ( is_wp_error( $me ) ) {
			return $me;
		}
		$user = (array) ( $me['body']['data'] ?? array() );

		return array(
			$tokens + array(
				'network'          => 'x',
				'external_id'      => (string) ( $user['id'] ?? '' ),
				'provider_user_id' => (string) ( $user['id'] ?? '' ),
				'name'             => (string) ( $user['name'] ?? '' ),
				'username'         => (string) ( $user['username'] ?? '' ),
				'avatar_url'       => (string) ( $user['profile_image_url'] ?? '' ),
				'meta'             => array(),
			),
		);
	}

	public function refresh( array $channel ) {
		if ( '' === $channel['refresh_token'] || $channel['token_expires_at'] > time() + 10 * MINUTE_IN_SECONDS ) {
			return null;
		}
		$token = $this->token_request(
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $channel['refresh_token'],
			)
		);
		return is_wp_error( $token ) ? $token : self::tokens( $token['body'], $channel['refresh_token'] );
	}

	public function publish( array $channel, array $post ) {
		$token = $channel['access_token'];
		$body  = array( 'text' => $post['text'] );

		$media_ids = array();
		foreach ( $post['media'] as $item ) {
			$id = $this->upload( $token, $item );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$media_ids[] = $id;
		}
		if ( $media_ids ) {
			$body['media'] = array( 'media_ids' => $media_ids );
		}

		$res = PFlow_Http::request( 'POST', self::API . 'tweets', array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ), 'json' => $body ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$id = (string) ( $res['body']['data']['id'] ?? '' );
		return array(
			'remote_id'  => $id,
			'remote_url' => '' !== $id ? 'https://x.com/' . rawurlencode( '' !== $channel['username'] ? $channel['username'] : 'i' ) . '/status/' . $id : '',
		);
	}

	/**
	 * Envia uma imagem (upload simples, multipart) e devolve o media_id.
	 *
	 * @return string|WP_Error
	 */
	private function upload( $token, array $item ) {
		$bytes = is_readable( $item['path'] ) ? file_get_contents( $item['path'] ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $bytes ) {
			return new WP_Error( 'pflow_x_upload', __( 'Imagem não encontrada no servidor.', 'post-flow' ) );
		}

		$boundary = 'pflow' . wp_generate_password( 24, false );
		$body     = self::multipart(
			$boundary,
			array(
				'media_category' => 'tweet_image',
				'media_type'     => $item['mime'],
			),
			'media',
			basename( $item['path'] ),
			$item['mime'],
			$bytes
		);

		$res = PFlow_Http::request(
			'POST',
			self::API . 'media/upload',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
				'timeout' => 60,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$id = (string) ( $res['body']['data']['id'] ?? $res['body']['media_id_string'] ?? '' );
		return '' !== $id ? $id : new WP_Error( 'pflow_x_upload', __( 'O X não devolveu o ID da imagem.', 'post-flow' ) );
	}

	public static function multipart( $boundary, array $fields, $file_field, $filename, $mime, $bytes ) {
		$eol  = "\r\n";
		$body = '';
		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . $eol . 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol . $value . $eol;
		}
		$body .= '--' . $boundary . $eol
			. 'Content-Disposition: form-data; name="' . $file_field . '"; filename="' . str_replace( '"', '', $filename ) . '"' . $eol
			. 'Content-Type: ' . $mime . $eol . $eol
			. $bytes . $eol
			. '--' . $boundary . '--' . $eol;
		return $body;
	}
}
