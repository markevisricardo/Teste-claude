<?php
/**
 * Conexão do WhatsApp por QR Code (Evolution API) no painel do usuário.
 *
 * 1. "Gerar QR Code" cria a instância do usuário (se não houver) e abre a
 *    tela do QR;
 * 2. a tela consulta o estado a cada poucos segundos (admin-ajax) e troca o
 *    QR quando ele expira;
 * 3. quando o número conecta, os destinos (Status e grupos) vão para a tela
 *    de escolha de canais.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_WhatsApp_Web {

	const META_INSTANCE = 'pflow_wa_instance';

	public static function init() {
		add_action( 'admin_post_pflow_wa_start', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_pflow_wa_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'wp_ajax_pflow_wa_state', array( __CLASS__, 'ajax_state' ) );
	}

	public static function provider() {
		return new PFlow_Provider_WhatsApp_Web();
	}

	/**
	 * Instância do usuário ('' se ainda não criou).
	 */
	public static function instance( $user_id ) {
		return (string) get_user_meta( $user_id, self::META_INSTANCE, true );
	}

	private static function guard( $action ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( PFlow_App::url() );
			exit;
		}
		check_admin_referer( $action );
		PFlow_Tenant::set( get_current_user_id() );
	}

	public static function start() {
		self::guard( 'pflow_wa_start' );
		$provider = self::provider();
		if ( ! $provider->is_configured() ) {
			PFlow_OAuth::back( 'error', __( 'O WhatsApp por QR Code ainda não foi configurado na plataforma. Avise o administrador.', 'post-flow' ) );
		}

		$user     = get_current_user_id();
		$instance = self::instance( $user );
		$state    = '' !== $instance ? $provider->state( $instance ) : 'missing';
		if ( is_wp_error( $state ) ) {
			PFlow_OAuth::back( 'error', $state->get_error_message() );
		}

		if ( 'missing' === $state ) {
			if ( 0 === PFlow_Channels::remaining_slots() ) {
				PFlow_OAuth::back( 'error', __( 'Você atingiu o limite de canais do seu plano. Remova um canal para conectar outro.', 'post-flow' ) );
			}
			$instance = 'pflow-' . $user . '-' . strtolower( wp_generate_password( 8, false ) );
			$created  = $provider->create_instance( $instance );
			if ( is_wp_error( $created ) ) {
				/* translators: %s: erro */
				PFlow_OAuth::back( 'error', sprintf( __( 'Não foi possível criar a conexão no servidor do WhatsApp: %s', 'post-flow' ), $created->get_error_message() ) );
			}
			update_user_meta( $user, self::META_INSTANCE, $instance );
		} elseif ( 'open' === $state ) {
			self::connected( $instance );
		}

		wp_safe_redirect( PFlow_App::url( 'channels', array( 'wa' => 'qr' ) ) );
		exit;
	}

	/**
	 * Número conectado: reativa os canais existentes e oferece os destinos.
	 */
	private static function connected( $instance ) {
		$accounts = self::provider()->destinations( $instance );
		if ( is_wp_error( $accounts ) ) {
			PFlow_OAuth::back( 'error', $accounts->get_error_message() );
		}
		PFlow_OAuth::receive_accounts( $accounts, __( 'WhatsApp conectado de novo.', 'post-flow' ) );
	}

	/**
	 * Estado da conexão para a tela do QR (JSON).
	 */
	public static function ajax_state() {
		check_ajax_referer( 'pflow_wa_state' );
		$user     = get_current_user_id();
		$instance = self::instance( $user );
		if ( ! $user || '' === $instance ) {
			wp_send_json_error( array( 'message' => __( 'Gere o QR Code de novo.', 'post-flow' ) ) );
		}
		$provider = self::provider();
		$state    = $provider->state( $instance );
		if ( is_wp_error( $state ) ) {
			wp_send_json_error( array( 'message' => $state->get_error_message() ) );
		}
		if ( 'open' === $state ) {
			// O próximo passo (escolher Status e grupos) acontece em start().
			// URL crua (wp_nonce_url() escapa o & para HTML, o que quebraria o redirecionamento no JS).
			$next = add_query_arg(
				array(
					'action'   => 'pflow_wa_start',
					'_wpnonce' => wp_create_nonce( 'pflow_wa_start' ),
				),
				admin_url( 'admin-post.php' )
			);
			wp_send_json_success( array( 'state' => 'open', 'next' => $next ) );
		}
		$qr = $provider->qr( $instance );
		wp_send_json_success(
			array(
				'state' => $state,
				'qr'    => is_wp_error( $qr ) ? '' : $qr,
			)
		);
	}

	/**
	 * Desconecta o número: apaga a instância e todos os canais dela.
	 */
	public static function disconnect() {
		self::guard( 'pflow_wa_disconnect' );
		$user = get_current_user_id();
		foreach ( PFlow_Channels::all() as $channel ) {
			if ( 'whatsapp_web' === $channel['network'] ) {
				PFlow_Channels::delete( $channel['id'] );
			}
		}
		self::forget( $user );
		PFlow_OAuth::back( 'success', __( 'WhatsApp desconectado e removido do servidor.', 'post-flow' ) );
	}

	/**
	 * Apaga a instância do usuário no servidor (se houver).
	 */
	public static function forget( $user_id ) {
		$instance = self::instance( $user_id );
		if ( '' !== $instance && self::provider()->is_configured() ) {
			self::provider()->delete_instance( $instance );
		}
		delete_user_meta( $user_id, self::META_INSTANCE );
	}

	/**
	 * Depois de remover um canal: se era o último do WhatsApp por QR,
	 * desconecta o número também.
	 */
	public static function after_channel_removed( $user_id ) {
		foreach ( PFlow_Channels::all() as $channel ) {
			if ( 'whatsapp_web' === $channel['network'] ) {
				return;
			}
		}
		self::forget( $user_id );
	}
}
