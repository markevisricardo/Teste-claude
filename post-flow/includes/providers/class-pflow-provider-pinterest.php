<?php
/**
 * Pinterest: OAuth e API v5. Cada pasta (board) vira um canal; o post vira
 * um pin com imagem (ou várias), descrição e link de destino.
 *
 * O token de acesso dura 30 dias e o refresh token, 1 ano; renovamos
 * quando faltam menos de 5 dias. Enquanto o app estiver com acesso "Trial",
 * os pins criados ficam visíveis só para o dono da conta.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Provider_Pinterest extends PFlow_Provider {

	const API = 'https://api.pinterest.com/v5/';

	public function id() {
		return 'pinterest';
	}

	public function is_configured() {
		return '' !== self::setting( 'pinterest_app_id' ) && '' !== self::setting( 'pinterest_app_secret' );
	}

	public function scopes() {
		return array( 'boards:read', 'pins:read', 'pins:write', 'user_accounts:read' );
	}

	public function authorize_url( $state, $redirect_uri, $code_verifier ) {
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( self::setting( 'pinterest_app_id' ) ),
				'redirect_uri'  => rawurlencode( $redirect_uri ),
				'response_type' => 'code',
				'scope'         => rawurlencode( implode( ',', $this->scopes() ) ),
				'state'         => rawurlencode( $state ),
			),
			'https://www.pinterest.com/oauth/'
		);
	}

	private function token_request( array $form ) {
		return PFlow_Http::request(
			'POST',
			self::API . 'oauth/token',
			array(
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( self::setting( 'pinterest_app_id' ) . ':' . self::setting( 'pinterest_app_secret' ) ) ),
				'form'    => $form,
			)
		);
	}

	private static function tokens( array $body, $previous_refresh = '' ) {
		return array(
			'access_token'     => (string) $body['access_token'],
			'refresh_token'    => (string) ( $body['refresh_token'] ?? $previous_refresh ),
			'token_expires_at' => time() + (int) ( $body['expires_in'] ?? 30 * DAY_IN_SECONDS ),
		);
	}

	public function connect( $code, $redirect_uri, $code_verifier ) {
		$token = $this->token_request(
			array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => $redirect_uri,
			)
		);
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$tokens  = self::tokens( $token['body'] );
		$headers = array( 'Authorization' => 'Bearer ' . $tokens['access_token'] );

		$me = PFlow_Http::request( 'GET', self::API . 'user_account', array( 'headers' => $headers ) );
		if ( is_wp_error( $me ) ) {
			return $me;
		}
		$username = (string) ( $me['body']['username'] ?? '' );
		$avatar   = (string) ( $me['body']['profile_image'] ?? '' );

		$boards = PFlow_Http::request( 'GET', self::API . 'boards', array( 'headers' => $headers, 'query' => array( 'page_size' => '100' ) ) );
		if ( is_wp_error( $boards ) ) {
			return $boards;
		}

		$out = array();
		foreach ( (array) ( $boards['body']['items'] ?? array() ) as $board ) {
			if ( empty( $board['id'] ) ) {
				continue;
			}
			$out[] = $tokens + array(
				'network'          => 'pinterest',
				'external_id'      => (string) $board['id'],
				'provider_user_id' => $username,
				'name'             => (string) ( $board['name'] ?? '' ) . ( '' !== $username ? ' · @' . $username : '' ),
				'username'         => '',
				'avatar_url'       => $avatar,
				'meta'             => array( 'username' => $username ),
			);
		}
		if ( ! $out ) {
			return new WP_Error( 'pflow_pinterest', __( 'Crie ao menos uma pasta no Pinterest e conecte de novo.', 'post-flow' ) );
		}
		return $out;
	}

	public function refresh( array $channel ) {
		if ( '' === $channel['refresh_token'] || $channel['token_expires_at'] > time() + 5 * DAY_IN_SECONDS ) {
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
		if ( ! $post['media'] ) {
			return new WP_Error( 'pflow_pinterest_media', __( 'O Pinterest exige ao menos uma imagem.', 'post-flow' ) );
		}

		if ( 1 === count( $post['media'] ) ) {
			$source = array(
				'source_type' => 'image_url',
				'url'         => $post['media'][0]['url'],
			);
		} else {
			$source = array(
				'source_type' => 'multiple_image_urls',
				'items'       => array_map(
					static function ( $item ) {
						return array( 'url' => $item['url'] );
					},
					$post['media']
				),
			);
		}

		$body = array(
			'board_id'     => $channel['external_id'],
			'title'        => self::title( $post['text'] ),
			'description'  => $post['text'],
			'media_source' => $source,
		);
		if ( '' !== $post['link'] ) {
			$body['link'] = $post['link'];
		}

		$res = PFlow_Http::request( 'POST', self::API . 'pins', array( 'headers' => array( 'Authorization' => 'Bearer ' . $channel['access_token'] ), 'json' => $body ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$id = (string) ( $res['body']['id'] ?? '' );
		return array(
			'remote_id'  => $id,
			'remote_url' => '' !== $id ? 'https://www.pinterest.com/pin/' . rawurlencode( $id ) . '/' : '',
		);
	}

	/**
	 * Título do pin: a primeira linha do texto, com até 100 caracteres.
	 */
	public static function title( $text ) {
		$first = trim( (string) strtok( trim( (string) $text ), "\n" ) );
		return mb_strlen( $first ) > 100 ? rtrim( mb_substr( $first, 0, 99 ) ) . '…' : $first;
	}
}
