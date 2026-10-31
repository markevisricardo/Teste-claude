<?php
/**
 * Conexão dos canais de cada usuário com os apps da plataforma.
 *
 * O retorno do OAuth é uma rota REST sem query string
 * (/wp-json/post-flow/v1/oauth/{provedor}), como a Meta exige. A rota não
 * recebe o cookie de login autenticado, então o usuário vem do "state":
 * aleatório, de uso único e criado por ele (junto com o code_verifier do PKCE).
 *
 * Quando a rede devolve mais de uma conta (várias páginas do Facebook, por
 * exemplo), o usuário escolhe quais quer conectar.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_OAuth {

	const STATE_TTL = 15 * MINUTE_IN_SECONDS;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_post_pflow_connect', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_pflow_pick_channels', array( __CLASS__, 'pick' ) );
		add_action( 'admin_post_pflow_disconnect', array( __CLASS__, 'disconnect' ) );
	}

	public static function register_routes() {
		$public = '__return_true'; // Validadas pelo "state" ou pelo signed_request da Meta.
		register_rest_route( PFlow_Settings::REST_NAMESPACE, '/oauth/(?P<provider>meta|linkedin|x|threads)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'callback' ), 'permission_callback' => $public ) );
		register_rest_route( PFlow_Settings::REST_NAMESPACE, '/meta/deauthorize', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'deauthorize' ), 'permission_callback' => $public ) );
		register_rest_route( PFlow_Settings::REST_NAMESPACE, '/meta/data-deletion', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'data_deletion' ), 'permission_callback' => $public ) );
		register_rest_route( PFlow_Settings::REST_NAMESPACE, '/meta/data-deletion-status', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'data_deletion_status' ), 'permission_callback' => $public ) );
	}

	/* ---------------------------------------------------------------------
	 * Início e retorno
	 * ------------------------------------------------------------------- */

	public static function start() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( PFlow_App::url() );
			exit;
		}
		check_admin_referer( 'pflow_connect' );
		PFlow_Tenant::set( get_current_user_id() );

		$id       = sanitize_key( wp_unslash( $_GET['provider'] ?? '' ) );
		$provider = PFlow_Networks::provider( $id );
		if ( ! $provider ) {
			self::back( 'error', __( 'Rede desconhecida.', 'post-flow' ) );
		}
		if ( ! $provider->is_configured() ) {
			self::back( 'error', __( 'Esta rede ainda não foi configurada na plataforma. Avise o administrador.', 'post-flow' ) );
		}
		if ( 0 === PFlow_Channels::remaining_slots() ) {
			self::back( 'error', __( 'Você atingiu o limite de canais do seu plano. Remova um canal para conectar outro.', 'post-flow' ) );
		}

		$state    = wp_generate_password( 40, false );
		$verifier = $provider->uses_pkce() ? wp_generate_password( 64, false ) : '';
		set_transient(
			'pflow_oauth_' . $state,
			array(
				'user_id'  => get_current_user_id(),
				'provider' => $id,
				'verifier' => $verifier,
			),
			self::STATE_TTL
		);

		wp_redirect( $provider->authorize_url( $state, PFlow_Settings::redirect_url( $id ), $verifier ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- tela de login da rede.
		exit;
	}

	public static function callback( WP_REST_Request $request ) {
		$id    = sanitize_key( (string) $request->get_param( 'provider' ) );
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$saved = '' !== $state ? get_transient( 'pflow_oauth_' . $state ) : false;
		delete_transient( 'pflow_oauth_' . $state );

		if ( ! is_array( $saved ) || $saved['provider'] !== $id || ! get_userdata( (int) $saved['user_id'] ) ) {
			wp_safe_redirect( add_query_arg( 'pf_error', 'oauth_state', PFlow_App::url( 'channels' ) ) );
			exit;
		}
		PFlow_Tenant::set( (int) $saved['user_id'] );

		if ( $request->get_param( 'error' ) ) {
			$reason = (string) ( $request->get_param( 'error_description' ) ? $request->get_param( 'error_description' ) : $request->get_param( 'error' ) );
			/* translators: %s: motivo informado pela rede */
			self::back( 'error', sprintf( __( 'A conexão foi cancelada: %s', 'post-flow' ), sanitize_text_field( $reason ) ) );
		}

		$code = preg_replace( '/#_$/', '', (string) $request->get_param( 'code' ) );
		if ( '' === $code ) {
			self::back( 'error', __( 'A rede não devolveu o código de autorização.', 'post-flow' ) );
		}

		$provider = PFlow_Networks::provider( $id );
		$accounts = $provider->connect( $code, PFlow_Settings::redirect_url( $id ), (string) $saved['verifier'] );
		if ( is_wp_error( $accounts ) ) {
			self::back( 'error', $accounts->get_error_message() );
		}
		$accounts = array_values(
			array_filter(
				(array) $accounts,
				static function ( $account ) {
					return '' !== (string) $account['external_id'] && '' !== (string) $account['access_token'];
				}
			)
		);
		if ( ! $accounts ) {
			self::back( 'error', __( 'Nenhuma conta foi encontrada nessa conexão.', 'post-flow' ) );
		}

		// Reconexão: contas já conectadas recebem o token novo na hora.
		$new = array();
		foreach ( $accounts as $account ) {
			if ( PFlow_Channels::exists( $account['network'], $account['external_id'] ) ) {
				PFlow_Channels::upsert( $account );
			} else {
				$new[] = $account;
			}
		}

		if ( ! $new ) {
			self::back( 'success', __( 'Conexão renovada.', 'post-flow' ) );
		}
		if ( 1 === count( $new ) && count( $accounts ) === 1 ) {
			$saved_count = self::save_accounts( $new );
			self::back( $saved_count ? 'success' : 'error', $saved_count ? __( 'Canal conectado! Agora é só criar o seu primeiro post.', 'post-flow' ) : __( 'Você atingiu o limite de canais do seu plano.', 'post-flow' ) );
		}

		// Várias contas novas: o usuário escolhe.
		set_transient( 'pflow_pick_' . PFlow_Tenant::id(), PFlow_Crypto::encrypt( wp_json_encode( $new ) ), self::STATE_TTL );
		wp_safe_redirect( PFlow_App::url( 'channels', array( 'pick' => '1' ) ) );
		exit;
	}

	/**
	 * Contas aguardando escolha do usuário atual.
	 */
	public static function pending_accounts() {
		$stored = get_transient( 'pflow_pick_' . PFlow_Tenant::id() );
		if ( ! $stored ) {
			return array();
		}
		$accounts = json_decode( PFlow_Crypto::decrypt( $stored ), true );
		return is_array( $accounts ) ? $accounts : array();
	}

	public static function pick() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( PFlow_App::url() );
			exit;
		}
		check_admin_referer( 'pflow_pick_channels' );
		PFlow_Tenant::set( get_current_user_id() );

		$accounts = self::pending_accounts();
		delete_transient( 'pflow_pick_' . PFlow_Tenant::id() );
		$chosen = array_map( 'absint', (array) wp_unslash( $_POST['accounts'] ?? array() ) );

		$selected = array();
		foreach ( $chosen as $index ) {
			if ( isset( $accounts[ $index ] ) ) {
				$selected[] = $accounts[ $index ];
			}
		}
		if ( ! $selected ) {
			self::back( 'warning', __( 'Nenhum canal foi escolhido.', 'post-flow' ) );
		}

		$count = self::save_accounts( $selected );
		if ( $count < count( $selected ) ) {
			/* translators: %d: canais conectados */
			self::back( 'warning', sprintf( __( '%d canal(is) conectado(s). Os demais passaram do limite do seu plano.', 'post-flow' ), $count ) );
		}
		/* translators: %d: canais conectados */
		self::back( 'success', sprintf( _n( '%d canal conectado!', '%d canais conectados!', $count, 'post-flow' ), $count ) );
	}

	/**
	 * Salva contas novas respeitando o limite de canais. Devolve quantas entraram.
	 */
	private static function save_accounts( array $accounts ) {
		$count = 0;
		foreach ( $accounts as $account ) {
			$left = PFlow_Channels::remaining_slots();
			if ( null !== $left && $left < 1 ) {
				break;
			}
			PFlow_Channels::upsert( $account );
			++$count;
		}
		return $count;
	}

	public static function disconnect() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( PFlow_App::url() );
			exit;
		}
		check_admin_referer( 'pflow_disconnect' );
		PFlow_Tenant::set( get_current_user_id() );
		$ok = PFlow_Channels::delete( absint( $_GET['id'] ?? 0 ) );
		self::back( $ok ? 'success' : 'error', $ok ? __( 'Canal removido. Os posts que iam para ele foram ajustados.', 'post-flow' ) : __( 'Canal não encontrado.', 'post-flow' ) );
	}

	/* ---------------------------------------------------------------------
	 * Callbacks exigidos pela Meta na análise do app
	 * ------------------------------------------------------------------- */

	public static function deauthorize( WP_REST_Request $request ) {
		$data = self::signed_request( $request );
		if ( ! $data ) {
			return new WP_REST_Response( array( 'error' => 'invalid signed_request' ), 400 );
		}
		PFlow_Channels::delete_by_provider_user( array( 'facebook', 'instagram' ), (string) $data['user_id'] );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public static function data_deletion( WP_REST_Request $request ) {
		$data = self::signed_request( $request );
		if ( ! $data ) {
			return new WP_REST_Response( array( 'error' => 'invalid signed_request' ), 400 );
		}
		PFlow_Channels::delete_by_provider_user( array( 'facebook', 'instagram' ), (string) $data['user_id'] );

		$code = strtolower( wp_generate_password( 16, false ) );
		set_transient( 'pflow_deletion_' . $code, current_time( 'mysql', true ), 90 * DAY_IN_SECONDS );

		return new WP_REST_Response(
			array(
				'url'               => add_query_arg( 'code', $code, PFlow_Settings::rest( '/meta/data-deletion-status' ) ),
				'confirmation_code' => $code,
			),
			200
		);
	}

	public static function data_deletion_status( WP_REST_Request $request ) {
		$code = sanitize_key( (string) $request->get_param( 'code' ) );
		$when = '' !== $code ? get_transient( 'pflow_deletion_' . $code ) : false;
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
		return self::parse_signed_request( (string) $request->get_param( 'signed_request' ), (string) PFlow_Settings::get( 'meta_app_secret' ) );
	}

	private static function back( $type, $message ) {
		PFlow_App::notice( PFlow_Tenant::id(), $type, $message );
		wp_safe_redirect( PFlow_App::url( 'channels' ) );
		exit;
	}
}
