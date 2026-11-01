<?php
/**
 * LinkedIn: perfil pessoal (OpenID Connect + w_member_social) e a API
 * REST de posts versionada.
 *
 * O token vale 60 dias. Só apps aprovados como parceiros recebem refresh
 * token; nos demais, a pessoa reconecta quando o token vence (o painel avisa).
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Provider_LinkedIn extends PFlow_Provider {

	const API = 'https://api.linkedin.com/rest/';

	public function id() {
		return 'linkedin';
	}

	public function is_configured() {
		return '' !== self::setting( 'linkedin_client_id' ) && '' !== self::setting( 'linkedin_client_secret' );
	}

	public function scopes() {
		return array( 'openid', 'profile', 'w_member_social' );
	}

	public function authorize_url( $state, $redirect_uri, $code_verifier ) {
		return add_query_arg(
			array(
				'response_type' => 'code',
				'client_id'     => rawurlencode( self::setting( 'linkedin_client_id' ) ),
				'redirect_uri'  => rawurlencode( $redirect_uri ),
				'state'         => rawurlencode( $state ),
				'scope'         => rawurlencode( implode( ' ', $this->scopes() ) ),
			),
			'https://www.linkedin.com/oauth/v2/authorization'
		);
	}

	private function token_request( array $form ) {
		return PFlow_Http::request(
			'POST',
			'https://www.linkedin.com/oauth/v2/accessToken',
			array(
				'form' => $form + array(
					'client_id'     => self::setting( 'linkedin_client_id' ),
					'client_secret' => self::setting( 'linkedin_client_secret' ),
				),
			)
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
		$access = (string) $token['body']['access_token'];

		$me = PFlow_Http::request( 'GET', 'https://api.linkedin.com/v2/userinfo', array( 'headers' => array( 'Authorization' => 'Bearer ' . $access ) ) );
		if ( is_wp_error( $me ) ) {
			return $me;
		}
		$sub = (string) ( $me['body']['sub'] ?? '' );
		if ( '' === $sub ) {
			return new WP_Error( 'pflow_linkedin', __( 'O LinkedIn não informou o perfil.', 'post-flow' ) );
		}

		return array(
			array(
				'network'          => 'linkedin',
				'external_id'      => $sub,
				'provider_user_id' => $sub,
				'name'             => (string) ( $me['body']['name'] ?? '' ),
				'username'         => '',
				'avatar_url'       => (string) ( $me['body']['picture'] ?? '' ),
				'access_token'     => $access,
				'refresh_token'    => (string) ( $token['body']['refresh_token'] ?? '' ),
				'token_expires_at' => time() + (int) ( $token['body']['expires_in'] ?? 0 ),
				'meta'             => array(),
			),
		);
	}

	public function refresh( array $channel ) {
		if ( '' === $channel['refresh_token'] || ( $channel['token_expires_at'] && $channel['token_expires_at'] > time() + 7 * DAY_IN_SECONDS ) ) {
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
			'token_expires_at' => time() + (int) ( $token['body']['expires_in'] ?? 0 ),
		);
	}

	private static function headers( $token ) {
		return array(
			'Authorization'             => 'Bearer ' . $token,
			'LinkedIn-Version'          => PFLOW_LINKEDIN_VERSION,
			'X-Restli-Protocol-Version' => '2.0.0',
		);
	}

	public function publish( array $channel, array $post ) {
		$token  = $channel['access_token'];
		$author = 'urn:li:person:' . $channel['external_id'];

		$video_urn = '';
		if ( self::video( $post ) ) {
			$video_urn = $this->upload_video( $token, $author, self::video( $post ) );
			if ( is_wp_error( $video_urn ) ) {
				return $video_urn;
			}
		}

		$images = array();
		foreach ( $post['media'] as $item ) {
			$image = $this->upload_image( $token, $author, $item );
			if ( is_wp_error( $image ) ) {
				return $image;
			}
			$images[] = $image;
		}

		$body = array(
			'author'                    => $author,
			'commentary'                => self::escape( $post['text'] ),
			'visibility'                => 'PUBLIC',
			'distribution'              => array(
				'feedDistribution'               => 'MAIN_FEED',
				'targetEntities'                 => array(),
				'thirdPartyDistributionChannels' => array(),
			),
			'lifecycleState'            => 'PUBLISHED',
			'isReshareDisabledByAuthor' => false,
		);
		if ( '' !== $video_urn ) {
			$body['content'] = array( 'media' => array( 'id' => $video_urn ) );
		} elseif ( 1 === count( $images ) ) {
			$body['content'] = array( 'media' => array( 'id' => $images[0] ) );
		} elseif ( count( $images ) > 1 ) {
			$body['content'] = array(
				'multiImage' => array(
					'images' => array_map(
						static function ( $id ) {
							return array( 'id' => $id );
						},
						$images
					),
				),
			);
		}

		$res = PFlow_Http::request( 'POST', self::API . 'posts', array( 'headers' => self::headers( $token ), 'json' => $body ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$urn = isset( $res['headers']['x-restli-id'] ) ? (string) $res['headers']['x-restli-id'] : '';
		return array(
			'remote_id'  => $urn,
			'remote_url' => '' !== $urn ? 'https://www.linkedin.com/feed/update/' . $urn . '/' : '',
		);
	}

	/**
	 * Envia uma imagem e devolve o URN (urn:li:image:...).
	 *
	 * @return string|WP_Error
	 */
	private function upload_image( $token, $author, array $item ) {
		$init = PFlow_Http::request(
			'POST',
			self::API . 'images?action=initializeUpload',
			array(
				'headers' => self::headers( $token ),
				'json'    => array( 'initializeUploadRequest' => array( 'owner' => $author ) ),
			)
		);
		if ( is_wp_error( $init ) ) {
			return $init;
		}
		$upload_url = (string) ( $init['body']['value']['uploadUrl'] ?? '' );
		$urn        = (string) ( $init['body']['value']['image'] ?? '' );
		$bytes      = is_readable( $item['path'] ) ? file_get_contents( $item['path'] ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( '' === $upload_url || '' === $urn || false === $bytes ) {
			return new WP_Error( 'pflow_linkedin_upload', __( 'Não foi possível enviar a imagem ao LinkedIn.', 'post-flow' ) );
		}

		$put = PFlow_Http::request(
			'PUT',
			$upload_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => $item['mime'],
				),
				'body'    => $bytes,
				'timeout' => 60,
			)
		);
		return is_wp_error( $put ) ? $put : $urn;
	}

	/**
	 * Envia um vídeo em partes (initializeUpload → PUT de cada parte →
	 * finalizeUpload) e espera o LinkedIn processar.
	 *
	 * @return string|WP_Error URN do vídeo (urn:li:video:...).
	 */
	private function upload_video( $token, $author, array $video ) {
		$size = $video['bytes'] ? (int) $video['bytes'] : ( is_readable( $video['path'] ) ? (int) filesize( $video['path'] ) : 0 );
		$init = PFlow_Http::request(
			'POST',
			self::API . 'videos?action=initializeUpload',
			array(
				'headers' => self::headers( $token ),
				'json'    => array(
					'initializeUploadRequest' => array(
						'owner'           => $author,
						'fileSizeBytes'   => $size,
						'uploadCaptions'  => false,
						'uploadThumbnail' => false,
					),
				),
			)
		);
		if ( is_wp_error( $init ) ) {
			return $init;
		}
		$value = (array) ( $init['body']['value'] ?? array() );
		$urn   = (string) ( $value['video'] ?? '' );
		if ( '' === $urn || empty( $value['uploadInstructions'] ) ) {
			return new WP_Error( 'pflow_linkedin_video', __( 'O LinkedIn não aceitou o envio do vídeo.', 'post-flow' ) );
		}

		$etags = array();
		foreach ( (array) $value['uploadInstructions'] as $part ) {
			$first = (int) $part['firstByte'];
			$bytes = self::read_chunk( $video['path'], $first, (int) $part['lastByte'] - $first + 1 );
			if ( false === $bytes ) {
				return new WP_Error( 'pflow_linkedin_video', __( 'Vídeo não encontrado no servidor.', 'post-flow' ) );
			}
			$put = PFlow_Http::request(
				'PUT',
				(string) $part['uploadUrl'],
				array(
					'headers' => array( 'Content-Type' => 'application/octet-stream' ),
					'body'    => $bytes,
					'timeout' => 300,
				)
			);
			if ( is_wp_error( $put ) ) {
				return $put;
			}
			$etags[] = trim( (string) ( $put['headers']['etag'] ?? '' ), '"' );
		}

		$final = PFlow_Http::request(
			'POST',
			self::API . 'videos?action=finalizeUpload',
			array(
				'headers' => self::headers( $token ),
				'json'    => array(
					'finalizeUploadRequest' => array(
						'video'           => $urn,
						'uploadToken'     => (string) ( $value['uploadToken'] ?? '' ),
						'uploadedPartIds' => $etags,
					),
				),
			)
		);
		if ( is_wp_error( $final ) ) {
			return $final;
		}

		$ready = self::wait_video(
			static function () use ( $token, $urn ) {
				$res = PFlow_Http::request( 'GET', self::API . 'videos/' . rawurlencode( $urn ), array( 'headers' => self::headers( $token ) ) );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$status = (string) ( $res['body']['status'] ?? '' );
				return 'AVAILABLE' === $status ? 'ready' : ( 'PROCESSING_FAILED' === $status ? 'error' : 'wait' );
			}
		);
		return is_wp_error( $ready ) ? $ready : $urn;
	}

	/**
	 * O texto do LinkedIn usa o formato "little text": caracteres reservados
	 * precisam de barra invertida, senão o post é cortado. Hashtags viram o
	 * modelo {hashtag|\#|tag} para continuarem clicáveis.
	 */
	public static function escape( $text ) {
		$escaped = preg_replace( '/([\\\\|{}@\[\]()<>#*_~])/u', '\\\\$1', (string) $text );
		return preg_replace( '/\\\\#([\p{L}\p{N}]+)/u', '{hashtag|\\\\#|$1}', $escaped );
	}
}
