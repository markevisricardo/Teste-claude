<?php
/**
 * Contrato de um provedor (o app de uma rede social).
 *
 * Contas devolvidas por connect():
 *   network, external_id, provider_user_id, name, username, avatar_url,
 *   access_token, refresh_token, token_expires_at (timestamp ou 0), meta (array).
 *
 * Post recebido por publish():
 *   text  (texto final daquela rede), link, media (lista de imagens:
 *   array( 'url' => URL pública, 'path' => arquivo local, 'mime' => tipo )),
 *   video (o vídeo do post, no mesmo formato e com 'bytes', ou null).
 *   Um post tem imagens ou um vídeo, nunca os dois.
 */

defined( 'ABSPATH' ) || exit;

abstract class PFlow_Provider {

	/** @return string meta, linkedin, x, threads, google, pinterest ou whatsapp. */
	abstract public function id();

	/** O administrador já cadastrou o app desta rede? */
	abstract public function is_configured();

	/** Endereço da tela de autorização da rede. */
	abstract public function authorize_url( $state, $redirect_uri, $code_verifier );

	/**
	 * Troca o código de autorização pelas contas que o usuário pode conectar.
	 *
	 * @return array|WP_Error Lista de contas.
	 */
	abstract public function connect( $code, $redirect_uri, $code_verifier );

	/**
	 * Publica um post num canal.
	 *
	 * @param array $channel Canal com tokens já descriptografados.
	 * @param array $post    Ver o topo do arquivo.
	 * @return array|WP_Error array( 'remote_id' => string, 'remote_url' => string )
	 */
	abstract public function publish( array $channel, array $post );

	/**
	 * Renova o token, se for preciso.
	 *
	 * @return array|WP_Error|null Campos novos (access_token, refresh_token,
	 *                             token_expires_at), null se não precisou.
	 */
	public function refresh( array $channel ) {
		return null;
	}

	/**
	 * Como o usuário conecta: 'oauth' (botão que leva ao site da rede) ou
	 * 'form' (o usuário cola as próprias credenciais, como no WhatsApp).
	 */
	public function connect_mode() {
		return 'oauth';
	}

	/** A rede usa PKCE no OAuth? */
	public function uses_pkce() {
		return false;
	}

	/**
	 * Escopos pedidos (exibidos no wp-admin para o administrador conferir o app).
	 */
	public function scopes() {
		return array();
	}

	protected static function setting( $key ) {
		return (string) PFlow_Settings::get( $key );
	}

	/** Vídeo do post (ou null). */
	protected static function video( array $post ) {
		return empty( $post['video'] ) ? null : $post['video'];
	}

	/**
	 * Espera o processamento de um vídeo: até 10 minutos, consultando a cada 10 s.
	 */
	protected static function wait_video( $check ) {
		return self::wait_until_ready( $check, (int) apply_filters( 'pflow_video_wait_tries', 60 ), (int) apply_filters( 'pflow_video_wait_seconds', 10 ) );
	}

	/**
	 * Lê um pedaço de um arquivo (para envios em partes, sem carregar o
	 * vídeo inteiro na memória).
	 *
	 * @return string|false
	 */
	protected static function read_chunk( $path, $offset, $length ) {
		$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return false;
		}
		fseek( $fh, $offset );
		$data = fread( $fh, $length ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $data;
	}

	/**
	 * Espera um contêiner de mídia (Instagram/Threads) ficar pronto.
	 *
	 * @param callable $check Devolve 'ready', 'error' ou 'wait'.
	 * @return true|WP_Error
	 */
	protected static function wait_until_ready( $check, $tries = 10, $seconds = 3 ) {
		for ( $i = 0; $i < $tries; $i++ ) {
			$state = call_user_func( $check );
			if ( is_wp_error( $state ) ) {
				return $state;
			}
			if ( 'ready' === $state ) {
				return true;
			}
			if ( 'error' === $state ) {
				return new WP_Error( 'pflow_media', __( 'A rede não conseguiu processar a mídia. Confira o formato (JPG para imagens, MP4 H.264 para vídeos), a proporção e a duração.', 'post-flow' ) );
			}
			sleep( $seconds );
		}
		return new WP_Error( 'pflow_media', __( 'A rede demorou demais para processar a mídia. Vamos tentar de novo.', 'post-flow' ), array( 'retry' => true ) );
	}
}
