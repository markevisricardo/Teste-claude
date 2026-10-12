<?php
/**
 * Licenças dos clientes, contas do Instagram conectadas e autenticação das
 * requisições entre o hub e os sites.
 *
 * Protocolo:
 * - ativação: o site envia a chave da licença; o hub vincula o site e devolve
 *   um segredo exclusivo (site_secret);
 * - site → hub: corpo JSON com license_key e ts, cabeçalho
 *   X-CFlow-Signature: sha256=HMAC(corpo, site_secret);
 * - hub → site: corpo JSON com ts, cabeçalho
 *   X-CFlow-Hub-Signature: sha256=HMAC(corpo, site_secret).
 * O ts (unix) precisa estar a no máximo 5 minutos do relógio de quem recebe.
 */

defined( 'ABSPATH' ) || exit;

class CFHub_Licenses {

	const MAX_CLOCK_SKEW = 300;

	/* ---------------------------------------------------------------------
	 * Assinaturas (funções puras)
	 * ------------------------------------------------------------------- */

	public static function sign( $body, $secret ) {
		return 'sha256=' . hash_hmac( 'sha256', (string) $body, (string) $secret );
	}

	public static function valid_signature( $body, $header, $secret ) {
		return '' !== (string) $secret && hash_equals( self::sign( $body, $secret ), (string) $header );
	}

	public static function fresh( $ts, $now = null ) {
		$now = null === $now ? time() : $now;
		return abs( $now - (int) $ts ) <= self::MAX_CLOCK_SKEW;
	}

	/**
	 * Chave no formato CF-XXXX-XXXX-XXXX-XXXX (sem caracteres ambíguos).
	 */
	public static function generate_key() {
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$groups   = array();
		for ( $g = 0; $g < 4; $g++ ) {
			$chunk = '';
			for ( $i = 0; $i < 4; $i++ ) {
				$chunk .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
			$groups[] = $chunk;
		}
		return 'CF-' . implode( '-', $groups );
	}

	public static function normalize_key( $key ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', (string) $key ) );
	}

	/**
	 * Domínio de uma URL, sem "www." (para comparar sites).
	 */
	public static function host( $url ) {
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		return preg_replace( '/^www\./', '', $host );
	}

	public static function same_site( $url_a, $url_b ) {
		$a = self::host( $url_a );
		return '' !== $a && self::host( $url_b ) === $a;
	}

	/* ---------------------------------------------------------------------
	 * Licenças
	 * ------------------------------------------------------------------- */

	public static function create( $client_name ) {
		global $wpdb;
		do {
			$key = self::generate_key();
		} while ( self::find_by_key( $key ) );

		$wpdb->insert(
			CFHub_DB::licenses_table(),
			array(
				'license_key' => $key,
				'client_name' => $client_name,
				'status'      => 'active',
				'created_at'  => CFHub_DB::now(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . CFHub_DB::licenses_table() . ' WHERE id = %d', $id ), ARRAY_A );
		return $row ? self::decode( $row ) : null;
	}

	public static function find_by_key( $key ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . CFHub_DB::licenses_table() . ' WHERE license_key = %s', self::normalize_key( $key ) ),
			ARRAY_A
		);
		return $row ? self::decode( $row ) : null;
	}

	public static function all() {
		global $wpdb;
		$licenses = $wpdb->get_results(
			'SELECT l.*, a.username AS ig_username, a.ig_user_id FROM ' . CFHub_DB::licenses_table() . ' l LEFT JOIN ' . CFHub_DB::accounts_table() . ' a ON a.license_id = l.id ORDER BY l.id DESC',
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'decode' ), $licenses );
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		if ( array_key_exists( 'site_secret', $data ) ) {
			$data['site_secret'] = CFHub_Crypto::encrypt( (string) $data['site_secret'] );
		}
		$wpdb->update( CFHub_DB::licenses_table(), $data, array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( CFHub_DB::licenses_table(), array( 'id' => (int) $id ) );
		$wpdb->delete( CFHub_DB::accounts_table(), array( 'license_id' => (int) $id ) );
		$wpdb->delete( CFHub_DB::deliveries_table(), array( 'license_id' => (int) $id ) );
	}

	/**
	 * Desvincula o site (o cliente pode ativar a chave em outro endereço).
	 */
	public static function release( $id ) {
		global $wpdb;
		self::update( $id, array( 'site_url' => '', 'callback_url' => '', 'site_secret' => '', 'activated_at' => null ) );
		$wpdb->delete( CFHub_DB::accounts_table(), array( 'license_id' => (int) $id ) );
	}

	private static function decode( array $row ) {
		$row['site_secret'] = CFHub_Crypto::decrypt( (string) $row['site_secret'] );
		return $row;
	}

	/**
	 * Ativa a licença num site. Retorna o segredo do site ou WP_Error.
	 */
	public static function activate( $key, $site_url, $callback_url ) {
		$license = self::find_by_key( $key );
		if ( ! $license ) {
			return new WP_Error( 'cfhub_license', __( 'Chave de licença não encontrada.', 'comment-flow-hub' ), array( 'status' => 404 ) );
		}
		if ( 'active' !== $license['status'] ) {
			return new WP_Error( 'cfhub_license', __( 'Esta licença está desativada.', 'comment-flow-hub' ), array( 'status' => 403 ) );
		}
		if ( ! self::same_site( $site_url, $callback_url ) || 0 !== strpos( (string) $callback_url, 'https://' ) ) {
			return new WP_Error( 'cfhub_license', __( 'O site precisa usar HTTPS.', 'comment-flow-hub' ), array( 'status' => 400 ) );
		}
		if ( '' !== $license['site_url'] && ! self::same_site( $license['site_url'], $site_url ) ) {
			return new WP_Error(
				'cfhub_license',
				/* translators: %s: domínio */
				sprintf( __( 'Esta licença já está em uso em %s. Peça para liberá-la.', 'comment-flow-hub' ), self::host( $license['site_url'] ) ),
				array( 'status' => 409 )
			);
		}

		$secret = wp_generate_password( 64, false );
		self::update(
			$license['id'],
			array(
				'site_url'     => esc_url_raw( $site_url ),
				'callback_url' => esc_url_raw( $callback_url ),
				'site_secret'  => $secret,
				'activated_at' => CFHub_DB::now(),
			)
		);

		return array(
			'site_secret' => $secret,
			'client_name' => $license['client_name'],
		);
	}

	/**
	 * Autentica uma requisição assinada vinda de um site.
	 *
	 * @return array|WP_Error { license, data }
	 */
	public static function authenticate( $body, $signature ) {
		$data = json_decode( (string) $body, true );
		if ( ! is_array( $data ) || empty( $data['license_key'] ) || empty( $data['ts'] ) ) {
			return new WP_Error( 'cfhub_auth', 'invalid request', array( 'status' => 400 ) );
		}

		$license = self::find_by_key( $data['license_key'] );
		if ( ! $license || '' === $license['site_secret'] || ! self::valid_signature( $body, $signature, $license['site_secret'] ) ) {
			return new WP_Error( 'cfhub_auth', __( 'Assinatura inválida. Ative a licença novamente.', 'comment-flow-hub' ), array( 'status' => 401 ) );
		}
		if ( ! self::fresh( $data['ts'] ) ) {
			return new WP_Error( 'cfhub_auth', __( 'Requisição expirada. Confira o relógio do servidor.', 'comment-flow-hub' ), array( 'status' => 401 ) );
		}
		if ( 'active' !== $license['status'] ) {
			return new WP_Error( 'cfhub_license', __( 'Esta licença está desativada.', 'comment-flow-hub' ), array( 'status' => 403 ) );
		}

		return array( 'license' => $license, 'data' => $data );
	}

	/* ---------------------------------------------------------------------
	 * Contas do Instagram
	 * ------------------------------------------------------------------- */

	/**
	 * Liga a conta do Instagram à licença. Uma conta só recebe webhooks num
	 * site: se ela estava em outra licença, passa para esta.
	 */
	public static function attach_account( $license_id, $ig_user_id, $username ) {
		global $wpdb;
		$table = CFHub_DB::accounts_table();
		$wpdb->delete( $table, array( 'license_id' => (int) $license_id ) );
		$wpdb->replace(
			$table,
			array(
				'ig_user_id'   => (string) $ig_user_id,
				'license_id'   => (int) $license_id,
				'username'     => (string) $username,
				'connected_at' => CFHub_DB::now(),
			)
		);
	}

	public static function detach_license( $license_id ) {
		global $wpdb;
		$wpdb->delete( CFHub_DB::accounts_table(), array( 'license_id' => (int) $license_id ) );
	}

	public static function detach_account( $ig_user_id ) {
		global $wpdb;
		$wpdb->delete( CFHub_DB::accounts_table(), array( 'ig_user_id' => (string) $ig_user_id ) );
	}

	/**
	 * Licença ativa e vinculada que recebe os webhooks da conta, ou null.
	 */
	public static function license_for_account( $ig_user_id ) {
		global $wpdb;
		$license_id = $wpdb->get_var(
			$wpdb->prepare( 'SELECT license_id FROM ' . CFHub_DB::accounts_table() . ' WHERE ig_user_id = %s', (string) $ig_user_id )
		);
		$license    = $license_id ? self::get( $license_id ) : null;
		return $license && 'active' === $license['status'] && '' !== $license['callback_url'] ? $license : null;
	}
}
