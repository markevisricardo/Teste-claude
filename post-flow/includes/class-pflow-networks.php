<?php
/**
 * Redes suportadas: os tipos de canal (o que aparece para o usuário) e os
 * provedores de login (o app de cada empresa). O app da Meta conecta dois
 * tipos de canal: páginas do Facebook e perfis do Instagram ligados a elas.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Networks {

	/**
	 * Tipos de canal.
	 *
	 * limit:     máximo de caracteres do texto;
	 * max_media: máximo de imagens por post;
	 * needs_media: o post precisa de ao menos uma imagem.
	 */
	public static function types() {
		return array(
			'facebook'  => array(
				'label'       => 'Facebook',
				'provider'    => 'meta',
				'limit'       => 63206,
				'max_media'   => 10,
				'needs_media' => false,
				'color'       => '#1877F2',
				'glyph'       => 'f',
			),
			'instagram' => array(
				'label'       => 'Instagram',
				'provider'    => 'meta',
				'limit'       => 2200,
				'max_media'   => 10,
				'needs_media' => true,
				'color'       => '#E1306C',
				'glyph'       => 'IG',
			),
			'linkedin'  => array(
				'label'       => 'LinkedIn',
				'provider'    => 'linkedin',
				'limit'       => 3000,
				'max_media'   => 9,
				'needs_media' => false,
				'color'       => '#0A66C2',
				'glyph'       => 'in',
			),
			'x'         => array(
				'label'       => 'X',
				'provider'    => 'x',
				'limit'       => 280,
				'max_media'   => 4,
				'needs_media' => false,
				'color'       => '#000000',
				'glyph'       => 'X',
			),
			'threads'   => array(
				'label'       => 'Threads',
				'provider'    => 'threads',
				'limit'       => 500,
				'max_media'   => 10,
				'needs_media' => false,
				'color'       => '#101010',
				'glyph'       => '@',
			),
		);
	}

	public static function type( $network ) {
		$types = self::types();
		return isset( $types[ $network ] ) ? $types[ $network ] : null;
	}

	public static function label( $network ) {
		$type = self::type( $network );
		return $type ? $type['label'] : $network;
	}

	/**
	 * Provedores de login: o que o botão "Conectar" de cada um conecta.
	 */
	public static function providers() {
		return array(
			'meta'     => array(
				'label'       => __( 'Facebook e Instagram', 'post-flow' ),
				'description' => __( 'Páginas do Facebook e perfis profissionais do Instagram ligados a elas.', 'post-flow' ),
				'networks'    => array( 'facebook', 'instagram' ),
			),
			'linkedin' => array(
				'label'       => 'LinkedIn',
				'description' => __( 'O seu perfil pessoal do LinkedIn.', 'post-flow' ),
				'networks'    => array( 'linkedin' ),
			),
			'x'        => array(
				'label'       => 'X (Twitter)',
				'description' => __( 'O seu perfil do X.', 'post-flow' ),
				'networks'    => array( 'x' ),
			),
			'threads'  => array(
				'label'       => 'Threads',
				'description' => __( 'O seu perfil do Threads.', 'post-flow' ),
				'networks'    => array( 'threads' ),
			),
		);
	}

	/**
	 * Instância do provedor (ou null).
	 *
	 * @return PFlow_Provider|null
	 */
	public static function provider( $id ) {
		$classes = array(
			'meta'     => 'PFlow_Provider_Meta',
			'linkedin' => 'PFlow_Provider_LinkedIn',
			'x'        => 'PFlow_Provider_X',
			'threads'  => 'PFlow_Provider_Threads',
		);
		return isset( $classes[ $id ] ) ? new $classes[ $id ]() : null;
	}

	public static function provider_for( $network ) {
		$type = self::type( $network );
		return $type ? self::provider( $type['provider'] ) : null;
	}

	/**
	 * Tamanho do texto como as redes contam (aproximado: caracteres Unicode).
	 */
	public static function text_length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	/**
	 * Problemas de um post para um tipo de canal (lista de mensagens).
	 *
	 * @param string $network     Tipo de canal.
	 * @param string $text        Texto final (já com o link, se for o caso).
	 * @param int    $media_count Número de imagens.
	 */
	public static function validate( $network, $text, $media_count ) {
		$type   = self::type( $network );
		$errors = array();
		if ( ! $type ) {
			return array( __( 'Rede desconhecida.', 'post-flow' ) );
		}
		if ( self::text_length( $text ) > $type['limit'] ) {
			/* translators: 1: rede, 2: limite */
			$errors[] = sprintf( __( '%1$s aceita até %2$s caracteres.', 'post-flow' ), $type['label'], number_format_i18n( $type['limit'] ) );
		}
		if ( $type['needs_media'] && $media_count < 1 ) {
			/* translators: %s: rede */
			$errors[] = sprintf( __( '%s exige ao menos uma imagem.', 'post-flow' ), $type['label'] );
		}
		if ( $media_count > $type['max_media'] ) {
			/* translators: 1: rede, 2: máximo */
			$errors[] = sprintf( __( '%1$s aceita até %2$d imagens.', 'post-flow' ), $type['label'], $type['max_media'] );
		}
		if ( '' === trim( (string) $text ) && $media_count < 1 ) {
			$errors[] = __( 'Escreva um texto ou adicione uma imagem.', 'post-flow' );
		}
		return $errors;
	}

	/**
	 * Texto publicado numa rede: o link vai junto do texto, exceto no
	 * Facebook sem imagem, onde ele vira a prévia do link.
	 */
	public static function compose_text( $network, $content, $link, $media_count ) {
		$content = trim( (string) $content );
		$link    = trim( (string) $link );
		if ( '' === $link || ( 'facebook' === $network && 0 === $media_count ) ) {
			return $content;
		}
		if ( false !== strpos( $content, $link ) ) {
			return $content;
		}
		return '' === $content ? $link : $content . "\n\n" . $link;
	}
}
