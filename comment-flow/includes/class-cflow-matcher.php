<?php
/**
 * Decide qual automação (se alguma) se aplica a um comentário.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Matcher {

	/**
	 * @param array $comment     ['text' => ..., 'media_id' => ...]
	 * @param array $automations Linhas da tabela, já ordenadas por prioridade.
	 * @return array|null A primeira automação que casa.
	 */
	public static function find( array $comment, array $automations ) {
		foreach ( $automations as $automation ) {
			if ( self::matches( $comment, $automation ) ) {
				return $automation;
			}
		}
		return null;
	}

	public static function matches( array $comment, array $automation ) {
		if ( 'active' !== $automation['status'] ) {
			return false;
		}

		if ( '' !== $automation['media_id'] && (string) $automation['media_id'] !== (string) $comment['media_id'] ) {
			return false;
		}

		$keywords = self::split_lines( $automation['keywords'], ',' );
		if ( empty( $keywords ) ) {
			return true; // Sem palavras-chave: qualquer comentário serve.
		}

		$text = self::normalize( $comment['text'] );
		foreach ( $keywords as $keyword ) {
			$keyword = self::normalize( $keyword );
			if ( '' === $keyword ) {
				continue;
			}

			switch ( $automation['match_mode'] ) {
				case 'exact':
					if ( $text === $keyword ) {
						return true;
					}
					break;
				case 'word':
					if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $keyword, '/' ) . '(?![\p{L}\p{N}])/u', $text ) ) {
						return true;
					}
					break;
				default: // contains
					if ( false !== strpos( $text, $keyword ) ) {
						return true;
					}
			}
		}

		return false;
	}

	/**
	 * Minúsculas, sem acentos e com espaços colapsados ("Quéro  " => "quero").
	 */
	public static function normalize( $text ) {
		$text = remove_accents( (string) $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Divide um texto por quebras de linha (e opcionalmente outro separador),
	 * removendo itens vazios.
	 */
	public static function split_lines( $text, $extra_separator = '' ) {
		$pattern = '' === $extra_separator ? '/\R/u' : '/\R|' . preg_quote( $extra_separator, '/' ) . '/u';
		$items   = array_map( 'trim', preg_split( $pattern, (string) $text ) );
		return array_values( array_filter( $items, 'strlen' ) );
	}

	/**
	 * Escolhe uma variação de resposta e substitui {username}.
	 */
	public static function pick_reply( $replies, $username ) {
		$options = self::split_lines( $replies );
		if ( empty( $options ) ) {
			return '';
		}
		return self::render( $options[ array_rand( $options ) ], $username );
	}

	public static function render( $template, $username ) {
		return str_replace( '{username}', '@' . ltrim( (string) $username, '@' ), (string) $template );
	}
}
