<?php
/**
 * Configurações da plataforma (feitas pelo administrador no wp-admin): os
 * apps de cada rede social, usados por todos os usuários, e as regras do
 * SaaS (cadastro aberto, limite de canais).
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Settings {

	const OPTION = 'pflow_settings';

	/** Campos guardados criptografados. */
	const SECRET_FIELDS = array( 'meta_app_secret', 'linkedin_client_secret', 'x_client_secret', 'threads_app_secret', 'google_client_secret', 'pinterest_app_secret', 'evolution_api_key' );

	const REST_NAMESPACE = 'post-flow/v1';

	public static function defaults() {
		return array(
			'meta_app_id'            => '',
			'meta_app_secret'        => '',
			'linkedin_client_id'     => '',
			'linkedin_client_secret' => '',
			'x_client_id'            => '',
			'x_client_secret'        => '',
			'threads_app_id'         => '',
			'threads_app_secret'     => '',
			'google_client_id'       => '',
			'google_client_secret'   => '',
			'pinterest_app_id'       => '',
			'pinterest_app_secret'   => '',
			'evolution_url'          => '',
			'evolution_api_key'      => '',
			'allow_registration'     => 1,
			'fullscreen'             => 1,
			'max_channels'           => 0,
		);
	}

	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$data   = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		$data   = array_intersect_key( $data, self::defaults() );

		foreach ( self::SECRET_FIELDS as $field ) {
			$data[ $field ] = PFlow_Crypto::decrypt( $data[ $field ] );
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
			$data[ $field ] = PFlow_Crypto::encrypt( $data[ $field ] );
		}
		update_option( self::OPTION, $data, false );
	}

	public static function rest( $route ) {
		return rest_url( self::REST_NAMESPACE . $route );
	}

	/**
	 * URL de retorno do OAuth de um provedor (meta, linkedin, x, threads, google, pinterest).
	 */
	public static function redirect_url( $provider ) {
		return self::rest( '/oauth/' . $provider );
	}
}
