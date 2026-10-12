<?php
/**
 * Modo licença: o site usa o app central (hub) em vez de um app próprio da
 * Meta. O hub faz o login do Instagram, entrega o token a este site e repassa
 * os webhooks da conta para /wp-json/comment-flow/v1/hub.
 *
 * Requisições site → hub vão assinadas (X-CFlow-Signature) com o segredo que
 * o hub devolve na ativação; as do hub → site chegam com X-CFlow-Hub-Signature.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Hub {

	const DEFAULT_URL    = 'https://commentflow.aceleradordeclinicas.com.br';
	const ROUTE          = '/hub';
	const MAX_CLOCK_SKEW = 300;

	/**
	 * Endereço do hub (pode ser trocado com CFLOW_HUB_URL no wp-config.php).
	 */
	public static function url() {
		return untrailingslashit( defined( 'CFLOW_HUB_URL' ) ? CFLOW_HUB_URL : self::DEFAULT_URL );
	}

	public static function endpoint( $path ) {
		return self::url() . '/wp-json/cflow-hub/v1/' . ltrim( $path, '/' );
	}

	public static function callback_url() {
		return rest_url( CFlow_Webhook::NAMESPACE_V1 . self::ROUTE );
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			CFlow_Webhook::NAMESPACE_V1,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'receive' ),
				'permission_callback' => '__return_true', // Autenticado pela assinatura do hub.
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Assinaturas (funções puras)
	 * ------------------------------------------------------------------- */

	public static function sign( $body, $secret ) {
		return 'sha256=' . hash_hmac( 'sha256', (string) $body, (string) $secret );
	}

	/**
	 * Valida um evento repassado pelo hub. Retorna o payload da Meta ou null.
	 */
	public static function open_delivery( $body, $signature, $secret, $now = null ) {
		if ( '' === (string) $secret || ! hash_equals( self::sign( $body, $secret ), (string) $signature ) ) {
			return null;
		}
		$data = json_decode( (string) $body, true );
		$now  = null === $now ? time() : $now;
		if ( ! is_array( $data ) || empty( $data['ts'] ) || abs( $now - (int) $data['ts'] ) > self::MAX_CLOCK_SKEW || ! isset( $data['payload'] ) || ! is_array( $data['payload'] ) ) {
			return null;
		}
		return $data['payload'];
	}

	/* ---------------------------------------------------------------------
	 * Chamadas ao hub
	 * ------------------------------------------------------------------- */

	/**
	 * Ativa a chave neste site.
	 *
	 * @return array|WP_Error { site_secret, client_name }
	 */
	public static function activate( $license_key ) {
		return self::request(
			'activate',
			wp_json_encode(
				array(
					'license_key'  => $license_key,
					'site_url'     => home_url( '/' ),
					'callback_url' => self::callback_url(),
				)
			)
		);
	}

	/**
	 * Chamada assinada com o segredo do site.
	 */
	public static function signed( $path, array $data = array() ) {
		$settings = CFlow_Settings::all();
		if ( '' === $settings['license_key'] || '' === $settings['site_secret'] ) {
			return new WP_Error( 'cflow_hub', __( 'Ative a licença primeiro.', 'comment-flow' ) );
		}
		$body = wp_json_encode(
			array_merge(
				$data,
				array(
					'license_key' => $settings['license_key'],
					'ts'          => time(),
				)
			)
		);
		return self::request( $path, $body, array( 'X-CFlow-Signature' => self::sign( $body, $settings['site_secret'] ) ) );
	}

	/**
	 * URL do login do Instagram (feito pelo app central).
	 */
	public static function connect_url( $return_url ) {
		$result = self::signed( 'connect', array( 'return_url' => $return_url ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return ! empty( $result['authorize_url'] ) ? $result['authorize_url'] : new WP_Error( 'cflow_hub', __( 'O hub não devolveu o link de conexão.', 'comment-flow' ) );
	}

	/**
	 * Troca o código de retorno pelo token da conta conectada.
	 *
	 * @return array|WP_Error { access_token, expires_in, ig_user_id, username }
	 */
	public static function claim( $code ) {
		$result = self::signed( 'claim', array( 'code' => $code ) );
		if ( ! is_wp_error( $result ) && empty( $result['access_token'] ) ) {
			return new WP_Error( 'cflow_hub', __( 'O hub não devolveu o token da conta.', 'comment-flow' ) );
		}
		return $result;
	}

	private static function request( $path, $body, array $headers = array() ) {
		$response = wp_remote_post(
			self::endpoint( $path ),
			array(
				'timeout' => 20,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			/* translators: %s: erro */
			return new WP_Error( 'cflow_hub', sprintf( __( 'Não foi possível falar com o hub: %s', 'comment-flow' ), $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
			return $data;
		}
		$message = is_array( $data ) && ! empty( $data['error'] ) ? $data['error'] : sprintf( 'HTTP %d', $code );
		return new WP_Error( 'cflow_hub', $message, array( 'status' => $code ) );
	}

	/* ---------------------------------------------------------------------
	 * Eventos repassados pelo hub
	 * ------------------------------------------------------------------- */

	public static function receive( WP_REST_Request $request ) {
		$payload = self::open_delivery(
			$request->get_body(),
			(string) $request->get_header( 'x_cflow_hub_signature' ),
			(string) CFlow_Settings::get( 'site_secret' )
		);
		if ( null === $payload ) {
			return new WP_REST_Response( array( 'error' => 'invalid signature' ), 401 );
		}
		return new WP_REST_Response( array( 'received' => CFlow_Webhook::process_payload( $payload ) ), 200 );
	}
}
