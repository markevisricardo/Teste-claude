<?php
/**
 * Google Meu Negócio (Google Business Profile): OAuth do Google com o
 * escopo business.manage e posts de novidades ("localPosts").
 *
 * Cada local (perfil da empresa) vira um canal. O token de acesso dura
 * 1 hora; o refresh token (access_type=offline) renova antes de publicar.
 * A API exige que o projeto do Google Cloud tenha o acesso aprovado pelo
 * Google (formulário "Business Profile API access").
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Provider_Google extends PFlow_Provider {

	public function id() {
		return 'google';
	}

	public function is_configured() {
		return '' !== self::setting( 'google_client_id' ) && '' !== self::setting( 'google_client_secret' );
	}

	public function scopes() {
		return array( 'https://www.googleapis.com/auth/business.manage' );
	}

	public function authorize_url( $state, $redirect_uri, $code_verifier ) {
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( self::setting( 'google_client_id' ) ),
				'redirect_uri'  => rawurlencode( $redirect_uri ),
				'response_type' => 'code',
				'scope'         => rawurlencode( implode( ' ', $this->scopes() ) ),
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => rawurlencode( $state ),
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	private function token_request( array $form ) {
		return PFlow_Http::request(
			'POST',
			'https://oauth2.googleapis.com/token',
			array(
				'form' => $form + array(
					'client_id'     => self::setting( 'google_client_id' ),
					'client_secret' => self::setting( 'google_client_secret' ),
				),
			)
		);
	}

	private static function auth( $token ) {
		return array( 'Authorization' => 'Bearer ' . $token );
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
		$access  = (string) $token['body']['access_token'];
		$refresh = (string) ( $token['body']['refresh_token'] ?? '' );
		$expires = time() + (int) ( $token['body']['expires_in'] ?? 3600 );

		$accounts = PFlow_Http::request( 'GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts', array( 'headers' => self::auth( $access ) ) );
		if ( is_wp_error( $accounts ) ) {
			return $accounts;
		}

		$out = array();
		foreach ( (array) ( $accounts['body']['accounts'] ?? array() ) as $account ) {
			$account_name = (string) ( $account['name'] ?? '' ); // accounts/123.
			if ( '' === $account_name ) {
				continue;
			}
			$locations = PFlow_Http::request(
				'GET',
				'https://mybusinessbusinessinformation.googleapis.com/v1/' . $account_name . '/locations',
				array(
					'headers' => self::auth( $access ),
					'query'   => array(
						'readMask' => 'name,title',
						'pageSize' => '100',
					),
				)
			);
			if ( is_wp_error( $locations ) ) {
				return $locations;
			}
			foreach ( (array) ( $locations['body']['locations'] ?? array() ) as $location ) {
				if ( empty( $location['name'] ) ) {
					continue;
				}
				$out[] = array(
					'network'          => 'google_business',
					'external_id'      => $account_name . '/' . $location['name'], // accounts/123/locations/456.
					'provider_user_id' => $account_name,
					'name'             => (string) ( $location['title'] ?? $location['name'] ),
					'username'         => '',
					'avatar_url'       => '',
					'access_token'     => $access,
					'refresh_token'    => $refresh,
					'token_expires_at' => $expires,
					'meta'             => array(),
				);
			}
		}

		if ( ! $out ) {
			return new WP_Error( 'pflow_google', __( 'Nenhum perfil de empresa foi encontrado nessa conta do Google.', 'post-flow' ) );
		}
		if ( '' === $refresh ) {
			return new WP_Error( 'pflow_google', __( 'O Google não liberou o acesso contínuo. Remova o app em myaccount.google.com/permissions e conecte de novo.', 'post-flow' ) );
		}
		return $out;
	}

	public function refresh( array $channel ) {
		if ( '' === $channel['refresh_token'] || $channel['token_expires_at'] > time() + 5 * MINUTE_IN_SECONDS ) {
			return null;
		}
		$token = $this->token_request(
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $channel['refresh_token'],
			)
		);
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		return array(
			'access_token'     => (string) $token['body']['access_token'],
			'refresh_token'    => (string) ( $token['body']['refresh_token'] ?? $channel['refresh_token'] ),
			'token_expires_at' => time() + (int) ( $token['body']['expires_in'] ?? 3600 ),
		);
	}

	public function publish( array $channel, array $post ) {
		$locale = function_exists( 'get_locale' ) ? str_replace( '_', '-', get_locale() ) : 'pt-BR';
		$body   = array(
			'languageCode' => $locale,
			'summary'      => $post['text'],
			'topicType'    => 'STANDARD',
		);
		if ( $post['media'] ) {
			$body['media'] = array(
				array(
					'mediaFormat' => 'PHOTO',
					'sourceUrl'   => $post['media'][0]['url'],
				),
			);
		}
		if ( '' !== $post['link'] ) {
			$body['callToAction'] = array(
				'actionType' => 'LEARN_MORE',
				'url'        => $post['link'],
			);
		}

		$res = PFlow_Http::request(
			'POST',
			'https://mybusiness.googleapis.com/v4/' . $channel['external_id'] . '/localPosts',
			array(
				'headers' => self::auth( $channel['access_token'] ),
				'json'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'remote_id'  => (string) ( $res['body']['name'] ?? '' ),
			'remote_url' => (string) ( $res['body']['searchUrl'] ?? '' ),
		);
	}
}
