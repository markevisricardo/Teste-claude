<?php
/**
 * Threads: API oficial (graph.threads.net). Token de longa duração (60 dias),
 * renovado sozinho quando faltam menos de 15 dias.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Provider_Threads extends PFlow_Provider {

	const API = 'https://graph.threads.net/';

	public function id() {
		return 'threads';
	}

	public function is_configured() {
		return '' !== self::setting( 'threads_app_id' ) && '' !== self::setting( 'threads_app_secret' );
	}

	public function scopes() {
		return array( 'threads_basic', 'threads_content_publish' );
	}

	public function authorize_url( $state, $redirect_uri, $code_verifier ) {
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( self::setting( 'threads_app_id' ) ),
				'redirect_uri'  => rawurlencode( $redirect_uri ),
				'scope'         => rawurlencode( implode( ',', $this->scopes() ) ),
				'response_type' => 'code',
				'state'         => rawurlencode( $state ),
			),
			'https://threads.net/oauth/authorize'
		);
	}

	public function connect( $code, $redirect_uri, $code_verifier ) {
		$short = PFlow_Http::request(
			'POST',
			self::API . 'oauth/access_token',
			array(
				'form' => array(
					'client_id'     => self::setting( 'threads_app_id' ),
					'client_secret' => self::setting( 'threads_app_secret' ),
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $redirect_uri,
					'code'          => $code,
				),
			)
		);
		if ( is_wp_error( $short ) ) {
			return $short;
		}

		$token   = (string) $short['body']['access_token'];
		$expires = time() + HOUR_IN_SECONDS;
		$long    = PFlow_Http::request(
			'GET',
			self::API . 'access_token',
			array(
				'query' => array(
					'grant_type'    => 'th_exchange_token',
					'client_secret' => self::setting( 'threads_app_secret' ),
					'access_token'  => $token,
				),
			)
		);
		if ( ! is_wp_error( $long ) ) {
			$token   = (string) $long['body']['access_token'];
			$expires = time() + (int) ( $long['body']['expires_in'] ?? 60 * DAY_IN_SECONDS );
		}

		$me = PFlow_Http::request( 'GET', self::API . 'v1.0/me', array( 'query' => array( 'fields' => 'id,username,name,threads_profile_picture_url', 'access_token' => $token ) ) );
		if ( is_wp_error( $me ) ) {
			return $me;
		}

		return array(
			array(
				'network'          => 'threads',
				'external_id'      => (string) $me['body']['id'],
				'provider_user_id' => (string) $me['body']['id'],
				'name'             => (string) ( $me['body']['name'] ?? $me['body']['username'] ?? '' ),
				'username'         => (string) ( $me['body']['username'] ?? '' ),
				'avatar_url'       => (string) ( $me['body']['threads_profile_picture_url'] ?? '' ),
				'access_token'     => $token,
				'refresh_token'    => '',
				'token_expires_at' => $expires,
				'meta'             => array(),
			),
		);
	}

	public function refresh( array $channel ) {
		if ( ! $channel['token_expires_at'] || $channel['token_expires_at'] > time() + 15 * DAY_IN_SECONDS ) {
			return null;
		}
		$res = PFlow_Http::request(
			'GET',
			self::API . 'refresh_access_token',
			array(
				'query' => array(
					'grant_type'   => 'th_refresh_token',
					'access_token' => $channel['access_token'],
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'access_token'     => (string) $res['body']['access_token'],
			'refresh_token'    => '',
			'token_expires_at' => time() + (int) ( $res['body']['expires_in'] ?? 60 * DAY_IN_SECONDS ),
		);
	}

	public function publish( array $channel, array $post ) {
		$user  = $channel['external_id'];
		$token = $channel['access_token'];
		$media = $post['media'];

		if ( ! $media ) {
			$container = $this->container( $user, $token, array( 'media_type' => 'TEXT', 'text' => $post['text'] ) );
		} elseif ( 1 === count( $media ) ) {
			$container = $this->container( $user, $token, array( 'media_type' => 'IMAGE', 'image_url' => $media[0]['url'], 'text' => $post['text'] ) );
		} else {
			$children = array();
			foreach ( $media as $item ) {
				$child = $this->container( $user, $token, array( 'media_type' => 'IMAGE', 'image_url' => $item['url'], 'is_carousel_item' => 'true' ) );
				if ( is_wp_error( $child ) ) {
					return $child;
				}
				$children[] = $child;
			}
			$container = $this->container(
				$user,
				$token,
				array(
					'media_type' => 'CAROUSEL',
					'children'   => implode( ',', $children ),
					'text'       => $post['text'],
				)
			);
		}
		if ( is_wp_error( $container ) ) {
			return $container;
		}

		if ( $media ) {
			$ready = self::wait_until_ready(
				static function () use ( $container, $token ) {
					$res = PFlow_Http::request( 'GET', self::API . 'v1.0/' . $container, array( 'query' => array( 'fields' => 'status', 'access_token' => $token ) ) );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					$status = (string) ( $res['body']['status'] ?? '' );
					return 'FINISHED' === $status ? 'ready' : ( in_array( $status, array( 'ERROR', 'EXPIRED' ), true ) ? 'error' : 'wait' );
				}
			);
			if ( is_wp_error( $ready ) ) {
				return $ready;
			}
		}

		$res = PFlow_Http::request( 'POST', self::API . 'v1.0/' . $user . '/threads_publish', array( 'form' => array( 'creation_id' => $container, 'access_token' => $token ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$id   = (string) $res['body']['id'];
		$link = PFlow_Http::request( 'GET', self::API . 'v1.0/' . $id, array( 'query' => array( 'fields' => 'permalink', 'access_token' => $token ) ) );

		return array(
			'remote_id'  => $id,
			'remote_url' => is_wp_error( $link ) ? '' : (string) ( $link['body']['permalink'] ?? '' ),
		);
	}

	/**
	 * @return string|WP_Error ID do contêiner.
	 */
	private function container( $user, $token, array $fields ) {
		$res = PFlow_Http::request( 'POST', self::API . 'v1.0/' . $user . '/threads', array( 'form' => $fields + array( 'access_token' => $token ) ) );
		return is_wp_error( $res ) ? $res : (string) $res['body']['id'];
	}
}
