<?php
/**
 * WhatsApp por QR Code (gratuito, NÃO oficial), via Evolution API.
 *
 * A Evolution API é um servidor de código aberto que você hospeda (Docker)
 * e que conecta números como o WhatsApp Web faz. Não há cobrança por
 * mensagem, mas a conexão não é oficial: vai contra os termos do WhatsApp e
 * o número pode ser bloqueado, principalmente se enviar muito ou receber
 * denúncias. Use um número dedicado.
 *
 * Cada usuário tem uma instância (um número) criada pelo plugin com a chave
 * global do servidor. Cada destino vira um canal:
 *   - "status@broadcast": o Status do número;
 *   - "...@g.us": um grupo em que o número participa.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Provider_WhatsApp_Web extends PFlow_Provider {

	const STATUS_JID = 'status@broadcast';

	public function id() {
		return 'whatsapp_web';
	}

	public function is_configured() {
		return '' !== self::setting( 'evolution_url' ) && '' !== self::setting( 'evolution_api_key' );
	}

	public function connect_mode() {
		return 'qr';
	}

	public function authorize_url( $state, $redirect_uri, $code_verifier ) {
		return '';
	}

	public function connect( $code, $redirect_uri, $code_verifier ) {
		return new WP_Error( 'pflow_wa_web', __( 'O WhatsApp por QR Code é conectado pelo painel.', 'post-flow' ) );
	}

	/**
	 * Chamada à Evolution API.
	 *
	 * @return array|WP_Error
	 */
	private function api( $method, $path, array $json = null, array $query = array() ) {
		$args = array(
			'headers' => array( 'apikey' => self::setting( 'evolution_api_key' ) ),
			'query'   => $query,
			'timeout' => 45,
		);
		if ( null !== $json ) {
			$args['json'] = $json;
		}
		return PFlow_Http::request( $method, rtrim( self::setting( 'evolution_url' ), '/' ) . '/' . ltrim( $path, '/' ), $args );
	}

	/* ---------------------------------------------------------------------
	 * Instância (o número do usuário)
	 * ------------------------------------------------------------------- */

	/**
	 * Cria a instância. Devolve o QR Code (data URI) se vier junto.
	 *
	 * @return array|WP_Error array( 'qr' => string )
	 */
	public function create_instance( $name ) {
		$res = $this->api(
			'POST',
			'instance/create',
			array(
				'instanceName' => $name,
				'qrcode'       => true,
				'integration'  => 'WHATSAPP-BAILEYS',
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'qr' => (string) ( $res['body']['qrcode']['base64'] ?? '' ) );
	}

	/**
	 * Estado da conexão: 'open' (conectado), 'connecting', 'close' ou
	 * 'missing' (a instância não existe no servidor).
	 *
	 * @return string|WP_Error
	 */
	public function state( $instance ) {
		$res = $this->api( 'GET', 'instance/connectionState/' . rawurlencode( $instance ) );
		if ( is_wp_error( $res ) ) {
			$data = $res->get_error_data();
			return is_array( $data ) && 404 === (int) ( $data['status'] ?? 0 ) ? 'missing' : $res;
		}
		return (string) ( $res['body']['instance']['state'] ?? $res['body']['state'] ?? 'close' );
	}

	/**
	 * QR Code atual (data URI) para conectar o número, ou '' se já conectou.
	 *
	 * @return string|WP_Error
	 */
	public function qr( $instance ) {
		$res = $this->api( 'GET', 'instance/connect/' . rawurlencode( $instance ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$qr = (string) ( $res['body']['base64'] ?? '' );
		if ( '' !== $qr && 0 !== strpos( $qr, 'data:image' ) ) {
			$qr = 'data:image/png;base64,' . $qr;
		}
		return $qr;
	}

	/**
	 * Desconecta o número e apaga a instância do servidor.
	 */
	public function delete_instance( $instance ) {
		$this->api( 'DELETE', 'instance/logout/' . rawurlencode( $instance ) );
		return $this->api( 'DELETE', 'instance/delete/' . rawurlencode( $instance ) );
	}

	/**
	 * Destinos do número conectado: o Status e os grupos.
	 *
	 * @return array|WP_Error Contas para PFlow_Channels::upsert().
	 */
	public function destinations( $instance ) {
		$base = array(
			'network'          => 'whatsapp_web',
			'provider_user_id' => $instance,
			'username'         => '',
			'avatar_url'       => '',
			'access_token'     => $instance, // Não há token por usuário: a chave é global.
			'refresh_token'    => '',
			'token_expires_at' => 0,
			'meta'             => array( 'instance' => $instance ),
		);
		$out  = array(
			array(
				'external_id' => self::STATUS_JID,
				'name'        => __( 'Meu Status', 'post-flow' ),
			) + $base,
		);

		$groups = $this->api( 'GET', 'group/fetchAllGroups/' . rawurlencode( $instance ), null, array( 'getParticipants' => 'false' ) );
		if ( is_wp_error( $groups ) ) {
			return $groups;
		}
		$list = is_array( $groups['body'] ) ? $groups['body'] : array();
		usort(
			$list,
			static function ( $a, $b ) {
				return strcasecmp( (string) ( $a['subject'] ?? '' ), (string) ( $b['subject'] ?? '' ) );
			}
		);
		foreach ( $list as $group ) {
			if ( empty( $group['id'] ) || false === strpos( (string) $group['id'], '@g.us' ) ) {
				continue;
			}
			$out[] = array(
				'external_id' => (string) $group['id'],
				'name'        => (string) ( $group['subject'] ?? $group['id'] ),
				'avatar_url'  => (string) ( $group['pictureUrl'] ?? '' ),
			) + $base;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Publicação
	 * ------------------------------------------------------------------- */

	public function publish( array $channel, array $post ) {
		$instance = (string) ( $channel['meta']['instance'] ?? '' );
		$state    = $this->state( $instance );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( 'open' !== $state ) {
			// 401 faz o publicador marcar o canal para reconectar.
			return new WP_Error( 'pflow_wa_web', __( 'O WhatsApp está desconectado. Em Canais, clique em "Gerar QR Code" e leia com o celular.', 'post-flow' ), array( 'status' => 401 ) );
		}

		$video = self::video( $post );
		$image = $video ? $video : ( $post['media'] ? $post['media'][0] : null ); // A mídia do post (vídeo ou 1ª imagem).
		$kind  = $video ? 'video' : 'image';
		$to    = $channel['external_id'];
		$path  = rawurlencode( $instance );

		if ( self::STATUS_JID === $to ) {
			$body = $image
				? array(
					'type'        => $kind,
					'content'     => $image['url'],
					'caption'     => $post['text'],
					'allContacts' => true,
				)
				: array(
					'type'            => 'text',
					'content'         => $post['text'],
					'backgroundColor' => '#6d28d9',
					'font'            => 1,
					'allContacts'     => true,
				);
			$res  = $this->api( 'POST', 'message/sendStatus/' . $path, $body );
		} elseif ( $image ) {
			$res = $this->api(
				'POST',
				'message/sendMedia/' . $path,
				array(
					'number'    => $to,
					'mediatype' => $kind,
					'mimetype'  => $image['mime'],
					'caption'   => $post['text'],
					'media'     => $image['url'],
					'fileName'  => basename( $image['path'] ? $image['path'] : ( $video ? 'video.mp4' : 'imagem.jpg' ) ),
				)
			);
		} else {
			$res = $this->api( 'POST', 'message/sendText/' . $path, array( 'number' => $to, 'text' => $post['text'] ) );
		}

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'remote_id'  => (string) ( $res['body']['key']['id'] ?? '' ),
			'remote_url' => '',
		);
	}
}
