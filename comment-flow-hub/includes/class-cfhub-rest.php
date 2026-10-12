<?php
/**
 * Rotas REST do hub: /wp-json/cflow-hub/v1/...
 *
 * Da Meta:      webhook (GET verificação, POST eventos), oauth (retorno do
 *               login), deauthorize e data-deletion (exigidos na análise do app).
 * Dos clientes: activate, deactivate, connect, claim, disconnect, status.
 */

defined( 'ABSPATH' ) || exit;

class CFHub_Rest {

	const NAMESPACE_V1 = 'cflow-hub/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$public = '__return_true'; // Cada rota se autentica (assinatura da Meta ou do site).

		register_rest_route(
			self::NAMESPACE_V1,
			'/webhook',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'webhook_verify' ), 'permission_callback' => $public ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'webhook_receive' ), 'permission_callback' => $public ),
			)
		);
		register_rest_route( self::NAMESPACE_V1, '/oauth', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'oauth_callback' ), 'permission_callback' => $public ) );
		register_rest_route( self::NAMESPACE_V1, '/deauthorize', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'deauthorize' ), 'permission_callback' => $public ) );
		register_rest_route( self::NAMESPACE_V1, '/data-deletion', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'data_deletion' ), 'permission_callback' => $public ) );
		register_rest_route( self::NAMESPACE_V1, '/data-deletion-status', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'data_deletion_status' ), 'permission_callback' => $public ) );

		register_rest_route( self::NAMESPACE_V1, '/activate', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'activate' ), 'permission_callback' => $public ) );
		foreach ( array( 'deactivate', 'connect', 'claim', 'disconnect', 'status' ) as $route ) {
			register_rest_route( self::NAMESPACE_V1, '/' . $route, array( 'methods' => 'POST', 'callback' => array( __CLASS__, $route ), 'permission_callback' => $public ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Meta
	 * ------------------------------------------------------------------- */

	public static function webhook_verify( WP_REST_Request $request ) {
		$expected = (string) CFHub_Settings::get( 'verify_token' );
		$token    = (string) $request->get_param( 'hub_verify_token' );

		if ( 'subscribe' !== $request->get_param( 'hub_mode' ) || '' === $expected || ! hash_equals( $expected, $token ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid verify token' ), 403 );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( (string) $request->get_param( 'hub_challenge' ) );
		exit;
	}

	public static function webhook_receive( WP_REST_Request $request ) {
		$body   = $request->get_body();
		$secret = (string) CFHub_Settings::get( 'app_secret' );
		$header = (string) $request->get_header( 'x_hub_signature_256' );

		if ( '' === $secret || ! hash_equals( 'sha256=' . hash_hmac( 'sha256', $body, $secret ), $header ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signature' ), 401 );
		}

		$payload = json_decode( $body, true );
		$count   = is_array( $payload ) ? CFHub_Forwarder::dispatch( $payload ) : 0;
		return new WP_REST_Response( array( 'forwarded' => $count ), 200 );
	}

	public static function oauth_callback( WP_REST_Request $request ) {
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$flow  = '' !== $state ? get_transient( 'cfhub_state_' . $state ) : false;
		delete_transient( 'cfhub_state_' . $state );

		if ( ! is_array( $flow ) ) {
			wp_die( esc_html__( 'Sessão de conexão inválida ou expirada. Volte ao seu site e clique em "Conectar com o Instagram" de novo.', 'comment-flow-hub' ), '', array( 'response' => 400 ) );
		}

		$return = $flow['return_url'];
		$back   = static function ( array $args ) use ( $return ) {
			wp_redirect( add_query_arg( array_map( 'rawurlencode', $args ), $return ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- domínio validado na licença.
			exit;
		};

		if ( $request->get_param( 'error' ) ) {
			$back( array( 'cflow_hub_error' => sanitize_text_field( (string) $request->get_param( 'error_description' ) ) ) );
		}

		$code     = preg_replace( '/#_$/', '', (string) $request->get_param( 'code' ) );
		$settings = CFHub_Settings::all();
		$result   = '' === $code
			? new WP_Error( 'cfhub_oauth', __( 'Código de autorização ausente.', 'comment-flow-hub' ) )
			: CFHub_Instagram::connect( $settings['app_id'], $settings['app_secret'], CFHub_Settings::url( '/oauth' ), $code );

		if ( is_wp_error( $result ) ) {
			$back( array( 'cflow_hub_error' => $result->get_error_message() ) );
		}

		CFHub_Licenses::attach_account( $flow['license_id'], $result['ig_user_id'], $result['username'] );
		$subscribed = CFHub_Instagram::subscribe( $result['ig_user_id'], $result['access_token'] );

		// O token vai para o site por uma chamada servidor-a-servidor (claim),
		// nunca pela URL do navegador.
		$claim = wp_generate_password( 40, false );
		set_transient( 'cfhub_claim_' . $claim, array( 'license_id' => (int) $flow['license_id'] ) + $result, 10 * MINUTE_IN_SECONDS );

		$args = array( 'cflow_hub_code' => $claim );
		if ( is_wp_error( $subscribed ) ) {
			$args['cflow_hub_warning'] = $subscribed->get_error_message();
		}
		$back( $args );
	}

	/**
	 * A pessoa removeu o app nas configurações do Instagram.
	 */
	public static function deauthorize( WP_REST_Request $request ) {
		$data = CFHub_Instagram::parse_signed_request( (string) $request->get_param( 'signed_request' ), (string) CFHub_Settings::get( 'app_secret' ) );
		if ( ! $data || empty( $data['user_id'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signed_request' ), 400 );
		}
		CFHub_Licenses::detach_account( (string) $data['user_id'] );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Pedido de exclusão de dados. O hub só guarda o vínculo conta → licença;
	 * os leads ficam no site de cada cliente (que pode excluí-los no painel).
	 */
	public static function data_deletion( WP_REST_Request $request ) {
		$data = CFHub_Instagram::parse_signed_request( (string) $request->get_param( 'signed_request' ), (string) CFHub_Settings::get( 'app_secret' ) );
		if ( ! $data || empty( $data['user_id'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signed_request' ), 400 );
		}
		CFHub_Licenses::detach_account( (string) $data['user_id'] );

		$code = strtolower( wp_generate_password( 16, false ) );
		set_transient( 'cfhub_deletion_' . $code, CFHub_DB::now(), 90 * DAY_IN_SECONDS );

		return new WP_REST_Response(
			array(
				'url'               => add_query_arg( 'code', $code, CFHub_Settings::url( '/data-deletion-status' ) ),
				'confirmation_code' => $code,
			),
			200
		);
	}

	public static function data_deletion_status( WP_REST_Request $request ) {
		$code = sanitize_key( (string) $request->get_param( 'code' ) );
		$when = '' !== $code ? get_transient( 'cfhub_deletion_' . $code ) : false;
		return new WP_REST_Response(
			array(
				'confirmation_code' => $code,
				'status'            => $when ? 'completed' : 'not_found',
				'completed_at'      => $when ? $when . ' UTC' : null,
			),
			$when ? 200 : 404
		);
	}

	/* ---------------------------------------------------------------------
	 * Sites dos clientes
	 * ------------------------------------------------------------------- */

	public static function activate( WP_REST_Request $request ) {
		$data   = json_decode( $request->get_body(), true );
		$result = CFHub_Licenses::activate(
			isset( $data['license_key'] ) ? (string) $data['license_key'] : '',
			isset( $data['site_url'] ) ? (string) $data['site_url'] : '',
			isset( $data['callback_url'] ) ? (string) $data['callback_url'] : ''
		);
		return self::respond( $result );
	}

	public static function deactivate( WP_REST_Request $request ) {
		$auth = self::auth( $request );
		if ( is_wp_error( $auth ) ) {
			return self::respond( $auth );
		}
		CFHub_Licenses::release( $auth['license']['id'] );
		return self::respond( array( 'success' => true ) );
	}

	public static function connect( WP_REST_Request $request ) {
		$auth = self::auth( $request );
		if ( is_wp_error( $auth ) ) {
			return self::respond( $auth );
		}
		if ( ! CFHub_Settings::has_app() ) {
			return self::respond( new WP_Error( 'cfhub_setup', __( 'O hub ainda não tem o app da Meta configurado.', 'comment-flow-hub' ), array( 'status' => 503 ) ) );
		}

		$return_url = isset( $auth['data']['return_url'] ) ? esc_url_raw( (string) $auth['data']['return_url'] ) : '';
		if ( ! CFHub_Licenses::same_site( $return_url, $auth['license']['site_url'] ) ) {
			return self::respond( new WP_Error( 'cfhub_connect', __( 'Endereço de retorno não pertence ao site da licença.', 'comment-flow-hub' ), array( 'status' => 400 ) ) );
		}

		$state = wp_generate_password( 40, false );
		set_transient(
			'cfhub_state_' . $state,
			array(
				'license_id' => (int) $auth['license']['id'],
				'return_url' => $return_url,
			),
			15 * MINUTE_IN_SECONDS
		);

		return self::respond(
			array(
				'authorize_url' => CFHub_Instagram::authorize_url( CFHub_Settings::get( 'app_id' ), CFHub_Settings::url( '/oauth' ), $state ),
			)
		);
	}

	public static function claim( WP_REST_Request $request ) {
		$auth = self::auth( $request );
		if ( is_wp_error( $auth ) ) {
			return self::respond( $auth );
		}
		$code  = isset( $auth['data']['code'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $auth['data']['code'] ) : '';
		$claim = '' !== $code ? get_transient( 'cfhub_claim_' . $code ) : false;

		if ( ! is_array( $claim ) || (int) $claim['license_id'] !== (int) $auth['license']['id'] ) {
			return self::respond( new WP_Error( 'cfhub_claim', __( 'Código de conexão inválido ou expirado. Tente conectar de novo.', 'comment-flow-hub' ), array( 'status' => 404 ) ) );
		}
		delete_transient( 'cfhub_claim_' . $code );
		unset( $claim['license_id'] );

		return self::respond( $claim );
	}

	public static function disconnect( WP_REST_Request $request ) {
		$auth = self::auth( $request );
		if ( is_wp_error( $auth ) ) {
			return self::respond( $auth );
		}
		CFHub_Licenses::detach_license( $auth['license']['id'] );
		return self::respond( array( 'success' => true ) );
	}

	public static function status( WP_REST_Request $request ) {
		$auth = self::auth( $request );
		if ( is_wp_error( $auth ) ) {
			return self::respond( $auth );
		}
		return self::respond(
			array(
				'client_name' => $auth['license']['client_name'],
				'status'      => $auth['license']['status'],
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	private static function auth( WP_REST_Request $request ) {
		return CFHub_Licenses::authenticate( $request->get_body(), (string) $request->get_header( 'x_cflow_signature' ) );
	}

	private static function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400
			);
		}
		return new WP_REST_Response( $result, 200 );
	}
}
