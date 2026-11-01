<?php
/**
 * Criptografia simétrica (AES-256-GCM) para guardar tokens no banco.
 *
 * A chave vem de PFLOW_ENCRYPTION_KEY (wp-config.php) ou, sem ela, do salt
 * "auth" do WordPress. Trocar a chave invalida os tokens: os canais precisam
 * ser reconectados.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Crypto {

	const CIPHER = 'aes-256-gcm';
	const PREFIX = 'pf1:';

	private static function key() {
		$secret = defined( 'PFLOW_ENCRYPTION_KEY' ) ? PFLOW_ENCRYPTION_KEY : wp_salt( 'auth' );
		return hash( 'sha256', $secret, true );
	}

	public static function encrypt( $plain ) {
		if ( '' === (string) $plain ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return 'b64:' . base64_encode( $plain );
		}

		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag );

		return self::PREFIX . base64_encode( $iv . $tag . $cipher );
	}

	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored ) {
			return '';
		}
		if ( 0 === strpos( $stored, 'b64:' ) ) {
			return (string) base64_decode( substr( $stored, 4 ) );
		}
		if ( 0 !== strpos( $stored, self::PREFIX ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}

		$plain = openssl_decrypt( substr( $raw, 28 ), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $plain ? '' : $plain;
	}
}
