<?php
/**
 * Conexão da conta do Instagram de cada usuário (Instagram Login com o app
 * central da plataforma).
 *
 * O callback é uma rota REST (/wp-json/comment-flow/v1/oauth), sem query
 * string, como a Meta exige. A rota não recebe o cookie de login autenticado,
 * então o usuário vem do "state": aleatório, de uso único e criado por ele.
 *
 * Também atende os callbacks exigidos na análise do app: desautorização
 * (a pessoa removeu o app no Instagram) e exclusão de dados.
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
		$public = '__return_true'; // Validadas pelo "state" ou pelo signed_request da Meta.
		register_rest_route( CFlow_Webhook::NAMESPACE_V1, self::ROUTE, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'callback' ), 'permission_callback' => $public ) );
		register_rest_route( CFlow_Webhook::NAMESPACE_V1, '/deauthorize', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'deauthorize' ), 'permission_callback' => $public ) );
		register_rest_route( CFlow_Webhook::NAMESPACE_V1, '/data-deletion', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'data_deletion' ), 'permission_callback' => $public ) );
		register_rest_route( CFlow_Webhook::NAMESPACE_V1, '/data-deletion-status', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'data_deletion_status' ), 'permission_callback' => $public ) );
	}

	public static function start() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( CFlow_App::url() );
			exit;
		}
		check_admin_referer( 'cflow_connect' );

		if ( ! CFlow_Settings::has_app() ) {
			self::back( get_current_user_id(), 'error', __( 'A plataforma ainda não está configurada. Avise o administrador.', 'comment-flow' ) );
		}

		$state = wp_generate_password( 40, false );
		set_transient( 'cflow_oauth_' . $state, get_current_user_id(), 15 * MINUTE_IN_SECONDS );

		wp_redirect( CFlow_Instagram_API::authorize_url( CFlow_Settings::get( 'app_id' ), CFlow_Settings::oauth_redirect_url(), $state ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- login do Instagram.
		exit;
	}

	public static function callback( WP_REST_Request $request ) {
		$state   = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$user_id = '' !== $state ? (int) get_transient( 'cflow_oauth_' . $state ) : 0;
		delete_transient( 'cflow_oauth_' . $state );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'cf_error', 'oauth_state', CFlow_App::url( 'account' ) ) );
			exit;
		}

		if ( $request->get_param( 'error' ) ) {
			self::back( $user_id, 'error', sanitize_text_field( (string) $request->get_param( 'error_description' ) ) );
		}

		// O Instagram às vezes anexa "#_" ao code.
		$code = preg_replace( '/#_$/', '', (string) $request->get_param( 'code' ) );
		if ( '' === $code ) {
			self::back( $user_id, 'error', __( 'Código de autorização ausente.', 'comment-flow' ) );
		}

		$settings = CFlow_Settings::all();
		$token    = CFlow_Instagram_API::exchange_code( $settings['app_id'], $settings['app_secret'], CFlow_Settings::oauth_redirect_url(), $code );
		if ( is_wp_error( $token ) ) {
			self::back( $user_id, 'error', $token->get_error_message() );
		}

		$api = new CFlow_Instagram_API( $token['access_token'] );
		$me  = $api->me();
		if ( is_wp_error( $me ) ) {
			self::back( $user_id, 'error', $me->get_error_message() );
		}

		$ig_user_id = isset( $me['user_id'] ) ? (string) $me['user_id'] : $token['user_id'];
		$connected  = CFlow_Accounts::connect(
			$user_id,
			$ig_user_id,
			isset( $me['username'] ) ? (string) $me['username'] : '',
			$token['access_token'],
			(int) $token['expires_in']
		);
		if ( is_wp_error( $connected ) ) {
			self::back( $user_id, 'error', $connected->get_error_message() );
		}
		delete_transient( 'cflow_recent_media_' . $user_id );

		$sub = $api->subscribe_app( $ig_user_id );
		if ( is_wp_error( $sub ) ) {
			/* translators: %s: mensagem de erro da API */
			self::back( $user_id, 'warning', sprintf( __( 'Conta conectada, mas a inscrição nos webhooks falhou: %s', 'comment-flow' ), $sub->get_error_message() ) );
		}

		self::back( $user_id, 'success', __( 'Conta do Instagram conectada! Agora crie a sua primeira automação.', 'comment-flow' ), 'automations' );
	}

	public static function disconnect() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( CFlow_App::url() );
			exit;
		}
		check_admin_referer( 'cflow_disconnect' );
		CFlow_Accounts::disconnect( get_current_user_id() );
		self::back( get_current_user_id(), 'success', __( 'Conta do Instagram desconectada.', 'comment-flow' ) );
	}

	/* ---------------------------------------------------------------------
	 * Callbacks da Meta (análise do app)
	 * ------------------------------------------------------------------- */

	public static function deauthorize( WP_REST_Request $request ) {
		$data = self::signed_request( $request );
		if ( ! $data ) {
			return new WP_REST_Response( array( 'error' => 'invalid signed_request' ), 400 );
		}
		CFlow_Accounts::disconnect_ig_user( (string) $data['user_id'] );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Exclusão de dados: remove a conta conectada e o token. Os leads são do
	 * usuário da plataforma, que pode excluí-los no painel.
	 */
	public static function data_deletion( WP_REST_Request $request ) {
		$data = self::signed_request( $request );
		if ( ! $data ) {
			return new WP_REST_Response( array( 'error' => 'invalid signed_request' ), 400 );
		}
		CFlow_Accounts::disconnect_ig_user( (string) $data['user_id'] );

		$code = strtolower( wp_generate_password( 16, false ) );
		set_transient( 'cflow_deletion_' . $code, current_time( 'mysql', true ), 90 * DAY_IN_SECONDS );

		return new WP_REST_Response(
			array(
				'url'               => add_query_arg( 'code', $code, CFlow_Settings::rest( '/data-deletion-status' ) ),
				'confirmation_code' => $code,
			),
			200
		);
	}

	public static function data_deletion_status( WP_REST_Request $request ) {
		$code = sanitize_key( (string) $request->get_param( 'code' ) );
		$when = '' !== $code ? get_transient( 'cflow_deletion_' . $code ) : false;
		return new WP_REST_Response(
			array(
				'confirmation_code' => $code,
				'status'            => $when ? 'completed' : 'not_found',
				'completed_at'      => $when ? $when . ' UTC' : null,
			),
			$when ? 200 : 404
		);
	}

	/**
	 * Valida o signed_request da Meta (HMAC-SHA256 com a chave secreta do app).
	 */
	public static function parse_signed_request( $signed_request, $app_secret ) {
		$parts = explode( '.', (string) $signed_request, 2 );
		if ( 2 !== count( $parts ) || '' === (string) $app_secret ) {
			return null;
		}
		list( $encoded_sig, $encoded_payload ) = $parts;

		$decode   = static function ( $value ) {
			$value = strtr( (string) $value, '-_', '+/' );
			return base64_decode( $value . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true );
		};
		$sig      = $decode( $encoded_sig );
		$expected = hash_hmac( 'sha256', $encoded_payload, $app_secret, true );
		if ( false === $sig || ! hash_equals( $expected, $sig ) ) {
			return null;
		}

		$payload = json_decode( (string) $decode( $encoded_payload ), true );
		return is_array( $payload ) && ! empty( $payload['user_id'] ) ? $payload : null;
	}

	private static function signed_request( WP_REST_Request $request ) {
		return self::parse_signed_request( (string) $request->get_param( 'signed_request' ), (string) CFlow_Settings::get( 'app_secret' ) );
	}

	/**
	 * Volta ao painel do usuário com um aviso.
	 */
	private static function back( $user_id, $type, $message, $view = 'account' ) {
		CFlow_App::notice( $user_id, $type, $message );
		wp_safe_redirect( CFlow_App::url( $view ) );
		exit;
	}
}
