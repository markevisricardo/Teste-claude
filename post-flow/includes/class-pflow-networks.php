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
	 * limit:        máximo de caracteres do texto;
	 * max_media:    máximo de imagens por post;
	 * needs_media:  o post precisa de ao menos uma imagem (ou um vídeo);
	 * video:        a rede aceita vídeo pela API;
	 * max_video_mb: tamanho máximo do vídeo.
	 */
	public static function types() {
		return array(
			'facebook'  => array(
				'label'       => 'Facebook',
				'video'       => true,
				'max_video_mb' => 1024,
				'provider'    => 'meta',
				'limit'       => 63206,
				'max_media'   => 10,
				'needs_media' => false,
				'color'       => '#1877F2',
				'glyph'       => 'f',
			),
			'instagram' => array(
				'label'       => 'Instagram',
				'video'       => true,
				'max_video_mb' => 300,
				'provider'    => 'meta',
				'limit'       => 2200,
				'max_media'   => 10,
				'needs_media' => true,
				'color'       => '#E1306C',
				'glyph'       => 'IG',
			),
			'linkedin'  => array(
				'label'       => 'LinkedIn',
				'video'       => true,
				'max_video_mb' => 5000,
				'provider'    => 'linkedin',
				'limit'       => 3000,
				'max_media'   => 9,
				'needs_media' => false,
				'color'       => '#0A66C2',
				'glyph'       => 'in',
			),
			'x'         => array(
				'label'       => 'X',
				'video'       => true,
				'max_video_mb' => 512,
				'provider'    => 'x',
				'limit'       => 280,
				'max_media'   => 4,
				'needs_media' => false,
				'color'       => '#000000',
				'glyph'       => 'X',
			),
			'threads'   => array(
				'label'       => 'Threads',
				'video'       => true,
				'max_video_mb' => 1000,
				'provider'    => 'threads',
				'limit'       => 500,
				'max_media'   => 10,
				'needs_media' => false,
				'color'       => '#101010',
				'glyph'       => '@',
			),
			'google_business' => array(
				'label'       => 'Google Meu Negócio',
				'video'       => false,
				'max_video_mb' => 0,
				'provider'    => 'google',
				'limit'       => 1500,
				'max_media'   => 1,
				'needs_media' => false,
				'needs_text'  => true,
				'link_field'  => true,
				'color'       => '#4285F4',
				'glyph'       => 'G',
			),
			'pinterest' => array(
				'label'       => 'Pinterest',
				'video'       => true,
				'max_video_mb' => 2000,
				'provider'    => 'pinterest',
				'limit'       => 500,
				'max_media'   => 5,
				'needs_media' => true,
				'link_field'  => true,
				'color'       => '#E60023',
				'glyph'       => 'P',
			),
			'whatsapp_web' => array(
				'label'       => 'WhatsApp',
				'video'       => true,
				'max_video_mb' => 64,
				'provider'    => 'whatsapp_web',
				'limit'       => 4096,
				'max_media'   => 1,
				'needs_media' => false,
				'color'       => '#25D366',
				'glyph'       => 'W',
			),
			'whatsapp'  => array(
				'label'       => 'WhatsApp (API oficial)',
				'video'       => true,
				'max_video_mb' => 16,
				'provider'    => 'whatsapp',
				'limit'       => 1000,
				'max_media'   => 1,
				'needs_media' => false,
				'needs_text'  => true,
				'color'       => '#25D366',
				'glyph'       => 'W',
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
			'google'   => array(
				'label'       => 'Google Meu Negócio',
				'description' => __( 'Posts de novidades nos perfis da sua empresa no Google (Busca e Maps).', 'post-flow' ),
				'networks'    => array( 'google_business' ),
			),
			'pinterest' => array(
				'label'       => 'Pinterest',
				'description' => __( 'Pins nas suas pastas do Pinterest. Cada pasta vira um canal.', 'post-flow' ),
				'networks'    => array( 'pinterest' ),
			),
			'whatsapp_web' => array(
				'label'       => 'WhatsApp (QR Code)',
				'description' => __( 'Gratuito: leia o QR Code com o celular e publique no seu Status e nos seus grupos. Conexão não oficial.', 'post-flow' ),
				'networks'    => array( 'whatsapp_web' ),
			),
			'whatsapp' => array(
				'label'       => 'WhatsApp (API oficial)',
				'description' => __( 'Pago por mensagem: envia o post para a sua lista de contatos (que autorizaram receber) pela API oficial da Meta.', 'post-flow' ),
				'networks'    => array( 'whatsapp' ),
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
			'google'   => 'PFlow_Provider_Google',
			'pinterest' => 'PFlow_Provider_Pinterest',
			'whatsapp' => 'PFlow_Provider_WhatsApp',
			'whatsapp_web' => 'PFlow_Provider_WhatsApp_Web',
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
	 * @param string     $network     Tipo de canal.
	 * @param string     $text        Texto final (já com o link, se for o caso).
	 * @param int        $media_count Número de imagens.
	 * @param array|null $video       array( 'bytes' => int ) se o post tem vídeo.
	 */
	public static function validate( $network, $text, $media_count, $video = null ) {
		$type   = self::type( $network );
		$errors = array();
		if ( ! $type ) {
			return array( __( 'Rede desconhecida.', 'post-flow' ) );
		}
		$has_media = $media_count > 0 || $video;
		if ( self::text_length( $text ) > $type['limit'] ) {
			/* translators: 1: rede, 2: limite */
			$errors[] = sprintf( __( '%1$s aceita até %2$s caracteres.', 'post-flow' ), $type['label'], number_format_i18n( $type['limit'] ) );
		}
		if ( $video ) {
			if ( empty( $type['video'] ) ) {
				/* translators: %s: rede */
				$errors[] = sprintf( __( '%s não aceita vídeo pela API.', 'post-flow' ), $type['label'] );
			} elseif ( ! empty( $video['bytes'] ) && $video['bytes'] > $type['max_video_mb'] * 1024 * 1024 ) {
				/* translators: 1: rede, 2: tamanho em MB */
				$errors[] = sprintf( __( '%1$s aceita vídeo de até %2$s MB.', 'post-flow' ), $type['label'], number_format_i18n( $type['max_video_mb'] ) );
			}
		}
		if ( $type['needs_media'] && ! $has_media ) {
			/* translators: %s: rede */
			$errors[] = sprintf( __( '%s exige ao menos uma imagem ou um vídeo.', 'post-flow' ), $type['label'] );
		}
		if ( $media_count > $type['max_media'] ) {
			/* translators: 1: rede, 2: máximo */
			$errors[] = sprintf( _n( '%1$s aceita só %2$d imagem.', '%1$s aceita até %2$d imagens.', $type['max_media'], 'post-flow' ), $type['label'], $type['max_media'] );
		}
		if ( ! empty( $type['needs_text'] ) && '' === trim( (string) $text ) ) {
			/* translators: %s: rede */
			$errors[] = sprintf( __( '%s exige um texto.', 'post-flow' ), $type['label'] );
		} elseif ( '' === trim( (string) $text ) && ! $has_media ) {
			$errors[] = __( 'Escreva um texto ou adicione uma imagem ou um vídeo.', 'post-flow' );
		}
		return $errors;
	}

	/**
	 * O link vai num campo próprio da rede (e não no texto)? Vale para o
	 * Google (botão "Saiba mais"), o Pinterest (destino do pin) e o Facebook
	 * sem imagem (prévia do link).
	 */
	public static function link_is_separate( $network, $media_count ) {
		$type = self::type( $network );
		return ( $type && ! empty( $type['link_field'] ) ) || ( 'facebook' === $network && 0 === (int) $media_count );
	}

	/**
	 * Texto publicado numa rede: o link vai no fim do texto, exceto nas redes
	 * em que ele tem campo próprio.
	 */
	public static function compose_text( $network, $content, $link, $media_count ) {
		$content = trim( (string) $content );
		$link    = trim( (string) $link );
		if ( '' === $link || self::link_is_separate( $network, $media_count ) ) {
			return $content;
		}
		if ( false !== strpos( $content, $link ) ) {
			return $content;
		}
		return '' === $content ? $link : $content . "\n\n" . $link;
	}
}
