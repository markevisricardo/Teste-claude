<?php
/**
 * Acesso às configurações do plugin (credenciais do app e da conta conectada).
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Settings {

	const OPTION = 'cflow_settings';

	/** Campos guardados criptografados. */
	const SECRET_FIELDS = array( 'app_secret', 'access_token', 'site_secret' );

	public static function defaults() {
		return array(
			'app_id'           => '',
			'app_secret'       => '',
			'verify_token'     => '',
			'access_token'     => '',
			'token_expires_at' => 0,
			'ig_user_id'       => '',
			'ig_username'      => '',
			'delay_seconds'    => 5,
			// Modo de conexão: 'hub' (licença do app central) ou 'own' (app próprio).
			'mode'             => '',
			'license_key'      => '',
			'site_secret'      => '',
			'hub_client'       => '',
		);
	}

	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$data   = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );

		foreach ( self::SECRET_FIELDS as $field ) {
			$data[ $field ] = CFlow_Crypto::decrypt( $data[ $field ] );
		}

		return $data;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Mescla e salva valores (em texto puro; os segredos são criptografados aqui).
	 */
	public static function update( array $values ) {
		$data = array_merge( self::all(), array_intersect_key( $values, self::defaults() ) );

		foreach ( self::SECRET_FIELDS as $field ) {
			$data[ $field ] = CFlow_Crypto::encrypt( $data[ $field ] );
		}

		update_option( self::OPTION, $data, false );
	}

	/**
	 * Modo de conexão. Instalações antigas com app próprio continuam nele.
	 */
	public static function mode() {
		$s = self::all();
		if ( in_array( $s['mode'], array( 'hub', 'own' ), true ) ) {
			return $s['mode'];
		}
		return '' !== $s['app_id'] ? 'own' : 'hub';
	}

	public static function has_license() {
		$s = self::all();
		return '' !== $s['license_key'] && '' !== $s['site_secret'];
	}

	public static function is_connected() {
		$s = self::all();
		return '' !== $s['access_token'] && '' !== $s['ig_user_id'];
	}

	public static function ensure_verify_token() {
		if ( '' === self::get( 'verify_token' ) ) {
			self::update( array( 'verify_token' => wp_generate_password( 32, false ) ) );
		}
	}

	public static function webhook_url() {
		return rest_url( CFlow_Webhook::NAMESPACE_V1 . CFlow_Webhook::ROUTE );
	}

	public static function oauth_redirect_url() {
		return rest_url( CFlow_Webhook::NAMESPACE_V1 . CFlow_OAuth::ROUTE );
	}
}
