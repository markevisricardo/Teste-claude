<?php
/**
 * Conexão da conta do Instagram via OAuth (Instagram Login).
 *
 * O callback é uma rota REST (/wp-json/comment-flow/v1/oauth) para que a
 * URL de redirecionamento cadastrada na Meta não tenha query string. Como
 * a rota não recebe o cookie de login autenticado, a segurança vem do
 * parâmetro "state": aleatório, de uso único e criado por um administrador.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_OAuth {

	const ROUTE = '/oauth';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_post_cflow_connect', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_cflow_disconnect', array( __CLASS__, 'disconnect' ) );
	}

	public static function register_routes() {
		register_rest_route(
			CFlow_Webhook::NAMESPACE_V1,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'callback' ),
				'permission_callback' => '__return_true', // Validado pelo "state".
			)
		);
	}

	public static function start() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'comment-flow' ), 403 );
		}
		check_admin_referer( 'cflow_connect' );

		$app_id = CFlow_Settings::get( 'app_id' );
		if ( '' === $app_id || '' === CFlow_Settings::get( 'app_secret' ) ) {
			self::back( 'error', __( 'Preencha o App ID e o App Secret antes de conectar.', 'comment-flow' ) );
		}

		$state = wp_generate_password( 40, false );
		set_transient( 'cflow_oauth_' . $state, get_current_user_id(), 15 * MINUTE_IN_SECONDS );

		wp_redirect( CFlow_Instagram_API::authorize_url( $app_id, CFlow_Settings::oauth_redirect_url(), $state ) ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	public static function callback( WP_REST_Request $request ) {
		$state   = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$user_id = '' !== $state ? get_transient( 'cflow_oauth_' . $state ) : false;
		delete_transient( 'cflow_oauth_' . $state );

		if ( ! $user_id || ! user_can( (int) $user_id, 'manage_options' ) ) {
			self::back( 'error', __( 'Sessão de conexão inválida ou expirada. Tente novamente.', 'comment-flow' ) );
		}

		if ( $request->get_param( 'error' ) ) {
			self::back( 'error', sanitize_text_field( (string) $request->get_param( 'error_description' ) ) );
		}

		// O Instagram às vezes anexa "#_" ao code.
		$code = preg_replace( '/#_$/', '', (string) $request->get_param( 'code' ) );
		if ( '' === $code ) {
			self::back( 'error', __( 'Código de autorização ausente.', 'comment-flow' ) );
		}

		$settings = CFlow_Settings::all();
		$token    = CFlow_Instagram_API::exchange_code(
			$settings['app_id'],
			$settings['app_secret'],
			CFlow_Settings::oauth_redirect_url(),
			$code
		);
		if ( is_wp_error( $token ) ) {
			self::back( 'error', $token->get_error_message() );
		}

		$api = new CFlow_Instagram_API( $token['access_token'] );
		$me  = $api->me();
		if ( is_wp_error( $me ) ) {
			self::back( 'error', $me->get_error_message() );
		}

		$ig_user_id = isset( $me['user_id'] ) ? (string) $me['user_id'] : $token['user_id'];

		CFlow_Settings::update(
			array(
				'access_token'     => $token['access_token'],
				'token_expires_at' => time() + (int) $token['expires_in'],
				'ig_user_id'       => $ig_user_id,
				'ig_username'      => isset( $me['username'] ) ? (string) $me['username'] : '',
			)
		);

		$sub = $api->subscribe_app( $ig_user_id );
		if ( is_wp_error( $sub ) ) {
			/* translators: %s: mensagem de erro da API */
			self::back( 'warning', sprintf( __( 'Conta conectada, mas a inscrição nos webhooks falhou: %s', 'comment-flow' ), $sub->get_error_message() ) );
		}

		self::back( 'success', __( 'Conta do Instagram conectada!', 'comment-flow' ) );
	}

	public static function disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'comment-flow' ), 403 );
		}
		check_admin_referer( 'cflow_disconnect' );

		CFlow_Settings::update(
			array(
				'access_token'     => '',
				'token_expires_at' => 0,
				'ig_user_id'       => '',
				'ig_username'      => '',
			)
		);

		self::back( 'success', __( 'Conta desconectada.', 'comment-flow' ) );
	}

	private static function back( $type, $message ) {
		set_transient( 'cflow_notice', array( 'type' => $type, 'message' => $message ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=cflow-settings' ) );
		exit;
	}
}
