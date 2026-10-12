<?php
/**
 * Credenciais do app central da Meta.
 */

defined( 'ABSPATH' ) || exit;

class CFHub_Settings {

	const OPTION        = 'cfhub_settings';
	const SECRET_FIELDS = array( 'app_secret' );

	public static function defaults() {
		return array(
			'app_id'       => '',
			'app_secret'   => '',
			'verify_token' => '',
		);
	}

	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$data   = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		foreach ( self::SECRET_FIELDS as $field ) {
			$data[ $field ] = CFHub_Crypto::decrypt( $data[ $field ] );
		}
		return $data;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		$data = array_merge( self::all(), array_intersect_key( $values, self::defaults() ) );
		foreach ( self::SECRET_FIELDS as $field ) {
			$data[ $field ] = CFHub_Crypto::encrypt( $data[ $field ] );
		}
		update_option( self::OPTION, $data, false );
	}

	public static function ensure_verify_token() {
		if ( '' === self::get( 'verify_token' ) ) {
			self::update( array( 'verify_token' => wp_generate_password( 32, false ) ) );
		}
	}

	public static function has_app() {
		$s = self::all();
		return '' !== $s['app_id'] && '' !== $s['app_secret'];
	}

	public static function url( $route ) {
		return rest_url( CFHub_Rest::NAMESPACE_V1 . $route );
	}
}
