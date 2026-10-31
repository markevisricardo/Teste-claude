<?php
/**
 * Chamadas HTTP às APIs das redes. Devolve a resposta decodificada ou um
 * WP_Error com a mensagem da própria API.
 *
 * O WP_Error leva em "data": status (HTTP) e retry (true quando vale tentar
 * de novo mais tarde: falha de rede, 429 ou 5xx).
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Http {

	/**
	 * @param string $method GET, POST, PUT...
	 * @param string $url    Endereço.
	 * @param array  $args   query (array), form (array), json (array), body (string),
	 *                       headers (array), timeout (int).
	 * @return array|WP_Error array( 'code' => int, 'body' => array|string, 'headers' => array )
	 */
	public static function request( $method, $url, array $args = array() ) {
		if ( ! empty( $args['query'] ) ) {
			$url = add_query_arg( array_map( 'rawurlencode', $args['query'] ), $url );
		}

		$headers = isset( $args['headers'] ) ? $args['headers'] : array();
		$body    = null;
		if ( isset( $args['json'] ) ) {
			$headers['Content-Type'] = 'application/json';
			$body                    = wp_json_encode( $args['json'] );
		} elseif ( isset( $args['form'] ) ) {
			$body = $args['form'];
		} elseif ( isset( $args['body'] ) ) {
			$body = $args['body'];
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => $body,
				'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'pflow_http', $response->get_error_message(), array( 'status' => 0, 'retry' => true ) );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		$parsed  = is_array( $decoded ) ? $decoded : $raw;
		$out     = array(
			'code'    => $code,
			'body'    => $parsed,
			'headers' => wp_remote_retrieve_headers( $response ),
		);

		if ( $code >= 400 ) {
			return new WP_Error(
				'pflow_api',
				self::error_message( $parsed, $code ),
				array(
					'status' => $code,
					'retry'  => 429 === $code || $code >= 500,
				)
			);
		}
		return $out;
	}

	/**
	 * Extrai a mensagem de erro dos formatos das redes (Meta, LinkedIn, X).
	 */
	public static function error_message( $body, $code ) {
		if ( is_array( $body ) ) {
			if ( isset( $body['error']['error_user_msg'] ) ) {
				return (string) $body['error']['error_user_msg'];
			}
			if ( isset( $body['error']['message'] ) ) {
				return (string) $body['error']['message'];
			}
			if ( isset( $body['error_description'] ) ) {
				return (string) $body['error_description'];
			}
			if ( isset( $body['errors'][0]['message'] ) ) {
				return (string) $body['errors'][0]['message'];
			}
			if ( isset( $body['detail'] ) ) {
				return (string) $body['detail'];
			}
			if ( isset( $body['message'] ) ) {
				return (string) $body['message'];
			}
			if ( isset( $body['title'] ) ) {
				return (string) $body['title'];
			}
			if ( isset( $body['error'] ) && is_string( $body['error'] ) ) {
				return $body['error'];
			}
		}
		/* translators: %d: código HTTP */
		return sprintf( __( 'A rede social respondeu com erro HTTP %d.', 'post-flow' ), (int) $code );
	}

	public static function is_retryable( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}
		$data = $error->get_error_data();
		return is_array( $data ) && ! empty( $data['retry'] );
	}
}
