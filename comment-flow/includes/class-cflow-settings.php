<?php
/**
 * Configurações da plataforma (feitas pelo administrador no wp-admin): o app
 * central da Meta e as opções de cadastro. Os dados de cada usuário ficam em
 * CFlow_Accounts.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Settings {

	const OPTION = 'cflow_settings';

	/** Campos guardados criptografados. */
	const SECRET_FIELDS = array( 'app_secret' );

	public static function defaults() {
		return array(
			'app_id'             => '',
			'app_secret'         => '',
			'verify_token'       => '',
			'allow_registration' => 1,
			'fullscreen'         => 1,
		);
	}

	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$data   = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		$data   = array_intersect_key( $data, self::defaults() );

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

	public static function has_app() {
		$s = self::all();
		return '' !== $s['app_id'] && '' !== $s['app_secret'];
	}

	public static function ensure_verify_token() {
		if ( '' === self::get( 'verify_token' ) ) {
			self::update( array( 'verify_token' => wp_generate_password( 32, false ) ) );
		}
	}

	public static function rest( $route ) {
		return rest_url( CFlow_Webhook::NAMESPACE_V1 . $route );
	}

	public static function webhook_url() {
		return self::rest( CFlow_Webhook::ROUTE );
	}

	public static function oauth_redirect_url() {
		return self::rest( CFlow_OAuth::ROUTE );
	}
}
