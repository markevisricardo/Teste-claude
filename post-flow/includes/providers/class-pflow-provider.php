<?php
/**
 * Contrato de um provedor (o app de uma rede social).
 *
 * Contas devolvidas por connect():
 *   network, external_id, provider_user_id, name, username, avatar_url,
 *   access_token, refresh_token, token_expires_at (timestamp ou 0), meta (array).
 *
 * Post recebido por publish():
 *   text  (texto final daquela rede), link, media (lista de
 *   array( 'url' => URL pública, 'path' => arquivo local, 'mime' => tipo )).
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
				return new WP_Error( 'pflow_media', __( 'A rede não conseguiu processar a imagem. Confira o formato (JPG) e a proporção.', 'post-flow' ) );
			}
			sleep( $seconds );
		}
		return new WP_Error( 'pflow_media', __( 'A rede demorou demais para processar a mídia. Vamos tentar de novo.', 'post-flow' ), array( 'retry' => true ) );
	}
}
