<?php
/**
 * Meta (Facebook Login): conecta páginas do Facebook e os perfis
 * profissionais do Instagram ligados a elas, e publica nos dois.
 *
 * Os tokens de página obtidos a partir de um token de usuário de longa
 * duração não expiram; o Instagram usa o token da página dona do perfil.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Provider_Meta extends PFlow_Provider {

	public function id() {
		return 'meta';
	}

	public function is_configured() {
		return '' !== self::setting( 'meta_app_id' ) && '' !== self::setting( 'meta_app_secret' );
	}

	public function scopes() {
		return array( 'pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'business_management', 'instagram_basic', 'instagram_content_publish' );
	}

	private static function graph( $path ) {
		return 'https://graph.facebook.com/' . PFLOW_GRAPH_VERSION . '/' . ltrim( $path, '/' );
	}

	public function authorize_url( $state, $redirect_uri, $code_verifier ) {
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( self::setting( 'meta_app_id' ) ),
				'redirect_uri'  => rawurlencode( $redirect_uri ),
				'state'         => rawurlencode( $state ),
				'response_type' => 'code',
				'scope'         => rawurlencode( implode( ',', $this->scopes() ) ),
			),
			'https://www.facebook.com/' . PFLOW_GRAPH_VERSION . '/dialog/oauth'
		);
	}

	public function connect( $code, $redirect_uri, $code_verifier ) {
		$short = PFlow_Http::request(
			'GET',
			self::graph( 'oauth/access_token' ),
			array(
				'query' => array(
					'client_id'     => self::setting( 'meta_app_id' ),
					'client_secret' => self::setting( 'meta_app_secret' ),
					'redirect_uri'  => $redirect_uri,
					'code'          => $code,
				),
			)
		);
		if ( is_wp_error( $short ) ) {
			return $short;
		}

		$long = PFlow_Http::request(
			'GET',
			self::graph( 'oauth/access_token' ),
			array(
				'query' => array(
					'grant_type'        => 'fb_exchange_token',
					'client_id'         => self::setting( 'meta_app_id' ),
					'client_secret'     => self::setting( 'meta_app_secret' ),
					'fb_exchange_token' => (string) $short['body']['access_token'],
				),
			)
		);
		$user_token = is_wp_error( $long ) ? (string) $short['body']['access_token'] : (string) $long['body']['access_token'];

		$me = PFlow_Http::request( 'GET', self::graph( 'me' ), array( 'query' => array( 'fields' => 'id,name', 'access_token' => $user_token ) ) );
		if ( is_wp_error( $me ) ) {
			return $me;
		}
		$fb_user = (string) $me['body']['id'];

		$pages = PFlow_Http::request(
			'GET',
			self::graph( 'me/accounts' ),
			array(
				'query' => array(
					'fields'       => 'id,name,access_token,picture{url},instagram_business_account{id,username,name,profile_picture_url}',
					'limit'        => '100',
					'access_token' => $user_token,
				),
			)
		);
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}

		$accounts = array();
		foreach ( (array) ( $pages['body']['data'] ?? array() ) as $page ) {
			if ( empty( $page['id'] ) || empty( $page['access_token'] ) ) {
				continue;
			}
			$accounts[] = array(
				'network'          => 'facebook',
				'external_id'      => (string) $page['id'],
				'provider_user_id' => $fb_user,
				'name'             => (string) ( $page['name'] ?? '' ),
				'username'         => '',
				'avatar_url'       => (string) ( $page['picture']['data']['url'] ?? '' ),
				'access_token'     => (string) $page['access_token'],
				'refresh_token'    => '',
				'token_expires_at' => 0,
				'meta'             => array(),
			);
			if ( ! empty( $page['instagram_business_account']['id'] ) ) {
				$ig         = $page['instagram_business_account'];
				$accounts[] = array(
					'network'          => 'instagram',
					'external_id'      => (string) $ig['id'],
					'provider_user_id' => $fb_user,
					'name'             => (string) ( $ig['name'] ?? $ig['username'] ?? '' ),
					'username'         => (string) ( $ig['username'] ?? '' ),
					'avatar_url'       => (string) ( $ig['profile_picture_url'] ?? '' ),
					'access_token'     => (string) $page['access_token'],
					'refresh_token'    => '',
					'token_expires_at' => 0,
					'meta'             => array( 'page_id' => (string) $page['id'] ),
				);
			}
		}

		if ( ! $accounts ) {
			return new WP_Error( 'pflow_no_pages', __( 'Nenhuma página do Facebook foi liberada. Conecte de novo e marque as páginas (e os perfis do Instagram) que quer usar.', 'post-flow' ) );
		}
		return $accounts;
	}

	public function publish( array $channel, array $post ) {
		return 'instagram' === $channel['network'] ? $this->publish_instagram( $channel, $post ) : $this->publish_facebook( $channel, $post );
	}

	/* ---------------------------------------------------------------------
	 * Facebook
	 * ------------------------------------------------------------------- */

	private function publish_facebook( array $channel, array $post ) {
		$page  = $channel['external_id'];
		$token = $channel['access_token'];
		$media = $post['media'];

		if ( ! $media ) {
			$form = array( 'message' => $post['text'], 'access_token' => $token );
			if ( '' !== $post['link'] ) {
				$form['link'] = $post['link'];
			}
			$res = PFlow_Http::request( 'POST', self::graph( $page . '/feed' ), array( 'form' => $form ) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return self::fb_result( (string) $res['body']['id'] );
		}

		if ( 1 === count( $media ) ) {
			$res = PFlow_Http::request(
				'POST',
				self::graph( $page . '/photos' ),
				array( 'form' => array( 'url' => $media[0]['url'], 'caption' => $post['text'], 'access_token' => $token ) )
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return self::fb_result( (string) ( $res['body']['post_id'] ?? $res['body']['id'] ) );
		}

		// Várias fotos: envia sem publicar e anexa todas a um único post.
		$form = array( 'message' => $post['text'], 'access_token' => $token );
		foreach ( $media as $i => $item ) {
			$photo = PFlow_Http::request(
				'POST',
				self::graph( $page . '/photos' ),
				array( 'form' => array( 'url' => $item['url'], 'published' => 'false', 'access_token' => $token ) )
			);
			if ( is_wp_error( $photo ) ) {
				return $photo;
			}
			$form[ 'attached_media[' . $i . ']' ] = wp_json_encode( array( 'media_fbid' => (string) $photo['body']['id'] ) );
		}
		$res = PFlow_Http::request( 'POST', self::graph( $page . '/feed' ), array( 'form' => $form ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::fb_result( (string) $res['body']['id'] );
	}

	private static function fb_result( $id ) {
		return array(
			'remote_id'  => $id,
			'remote_url' => 'https://www.facebook.com/' . rawurlencode( $id ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Instagram
	 * ------------------------------------------------------------------- */

	private function publish_instagram( array $channel, array $post ) {
		$ig    = $channel['external_id'];
		$token = $channel['access_token'];
		$media = $post['media'];

		if ( ! $media ) {
			return new WP_Error( 'pflow_ig_media', __( 'O Instagram exige ao menos uma imagem.', 'post-flow' ) );
		}

		if ( 1 === count( $media ) ) {
			$container = $this->ig_container( $ig, $token, array( 'image_url' => $media[0]['url'], 'caption' => $post['text'] ) );
		} else {
			$children = array();
			foreach ( $media as $item ) {
				$child = $this->ig_container( $ig, $token, array( 'image_url' => $item['url'], 'is_carousel_item' => 'true' ) );
				if ( is_wp_error( $child ) ) {
					return $child;
				}
				$children[] = $child;
			}
			$container = $this->ig_container(
				$ig,
				$token,
				array(
					'media_type' => 'CAROUSEL',
					'children'   => implode( ',', $children ),
					'caption'    => $post['text'],
				)
			);
		}
		if ( is_wp_error( $container ) ) {
			return $container;
		}

		$ready = self::wait_until_ready(
			static function () use ( $container, $token ) {
				$res = PFlow_Http::request( 'GET', self::graph( $container ), array( 'query' => array( 'fields' => 'status_code', 'access_token' => $token ) ) );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$code = (string) ( $res['body']['status_code'] ?? '' );
				return 'FINISHED' === $code ? 'ready' : ( in_array( $code, array( 'ERROR', 'EXPIRED' ), true ) ? 'error' : 'wait' );
			}
		);
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$res = PFlow_Http::request( 'POST', self::graph( $ig . '/media_publish' ), array( 'form' => array( 'creation_id' => $container, 'access_token' => $token ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$media_id = (string) $res['body']['id'];

		$link = PFlow_Http::request( 'GET', self::graph( $media_id ), array( 'query' => array( 'fields' => 'permalink', 'access_token' => $token ) ) );
		return array(
			'remote_id'  => $media_id,
			'remote_url' => is_wp_error( $link ) ? '' : (string) ( $link['body']['permalink'] ?? '' ),
		);
	}

	/**
	 * Cria um contêiner de mídia e devolve o ID.
	 *
	 * @return string|WP_Error
	 */
	private function ig_container( $ig, $token, array $fields ) {
		$res = PFlow_Http::request( 'POST', self::graph( $ig . '/media' ), array( 'form' => $fields + array( 'access_token' => $token ) ) );
		return is_wp_error( $res ) ? $res : (string) $res['body']['id'];
	}
}
