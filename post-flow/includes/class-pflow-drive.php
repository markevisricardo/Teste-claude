<?php
/**
 * Vídeos por link do Google Drive.
 *
 * O usuário cola o link de compartilhamento ("Qualquer pessoa com o link").
 * Ao salvar, o plugin confere se o arquivo é público e é um vídeo; antes de
 * publicar, baixa o arquivo para a biblioteca de mídia, porque algumas redes
 * pedem uma URL pública direta (Instagram, Facebook, Threads) e outras pedem
 * o próprio arquivo (LinkedIn, X, Pinterest). O download começa em segundo
 * plano logo depois de agendar, para o vídeo já estar pronto na hora.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Drive {

	const PREFETCH_HOOK = 'pflow_prefetch_video';

	public static function init() {
		add_action( self::PREFETCH_HOOK, array( __CLASS__, 'prefetch' ) );
	}

	/**
	 * ID do arquivo num link do Drive (ou '' se não for um link do Drive).
	 *
	 * Aceita: /file/d/ID/view, /open?id=ID, /uc?id=ID e drive.usercontent.
	 */
	public static function file_id( $url ) {
		$url  = trim( (string) $url );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! in_array( $host, array( 'drive.google.com', 'docs.google.com', 'drive.usercontent.google.com' ), true ) ) {
			return '';
		}
		if ( preg_match( '#/file/d/([A-Za-z0-9_-]{10,})#', $url, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#[?&]id=([A-Za-z0-9_-]{10,})#', $url, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Endereço de download direto (pula o aviso de "não foi possível verificar
	 * vírus" dos arquivos grandes).
	 */
	public static function download_url( $id ) {
		return 'https://drive.usercontent.google.com/download?id=' . rawurlencode( $id ) . '&export=download&confirm=t';
	}

	/**
	 * Confere o link sem baixar o vídeo todo (só os primeiros bytes).
	 *
	 * @return array|WP_Error array( 'bytes' => int, 'mime' => string )
	 */
	public static function probe( $url ) {
		$id = self::file_id( $url );
		if ( '' === $id ) {
			return new WP_Error( 'pflow_drive', __( 'Cole o link de compartilhamento do Google Drive (drive.google.com/file/d/…).', 'post-flow' ) );
		}
		$res = wp_remote_get(
			self::download_url( $id ),
			array(
				'timeout'     => 20,
				'redirection' => 5,
				'headers'     => array( 'Range' => 'bytes=0-1023' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			/* translators: %s: erro */
			return new WP_Error( 'pflow_drive', sprintf( __( 'Não foi possível acessar o Google Drive: %s', 'post-flow' ), $res->get_error_message() ), array( 'retry' => true ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$type = strtolower( (string) wp_remote_retrieve_header( $res, 'content-type' ) );
		$body = (string) wp_remote_retrieve_body( $res );

		if ( $code >= 400 || false !== strpos( $type, 'text/html' ) || self::looks_like_html( $body ) ) {
			return new WP_Error( 'pflow_drive', __( 'O vídeo do Google Drive não está público. No Drive, clique em Compartilhar › Acesso geral › "Qualquer pessoa com o link" e tente de novo.', 'post-flow' ) );
		}
		if ( 0 !== strpos( $type, 'video/' ) && ! self::looks_like_video( $body ) ) {
			return new WP_Error( 'pflow_drive', __( 'O arquivo do link não é um vídeo MP4 ou MOV.', 'post-flow' ) );
		}

		$bytes = 0;
		$range = (string) wp_remote_retrieve_header( $res, 'content-range' ); // bytes 0-1023/123456.
		if ( preg_match( '#/(\d+)$#', $range, $m ) ) {
			$bytes = (int) $m[1];
		} elseif ( 200 === $code ) {
			$bytes = (int) wp_remote_retrieve_header( $res, 'content-length' );
		}
		if ( $bytes > PFlow_Media::max_video_bytes() ) {
			/* translators: %d: MB */
			return new WP_Error( 'pflow_drive', sprintf( __( 'O vídeo passa de %d MB.', 'post-flow' ), PFLOW_MAX_VIDEO_MB ) );
		}
		return array(
			'bytes' => $bytes,
			'mime'  => 0 === strpos( $type, 'video/' ) ? $type : 'video/mp4',
		);
	}

	private static function looks_like_html( $body ) {
		$start = strtolower( ltrim( substr( (string) $body, 0, 200 ) ) );
		return 0 === strpos( $start, '<!doctype html' ) || 0 === strpos( $start, '<html' );
	}

	/**
	 * MP4 e MOV têm a "caixa" ftyp (ou moov/mdat/wide) logo no começo.
	 */
	private static function looks_like_video( $body ) {
		$head = substr( (string) $body, 4, 4 );
		return in_array( $head, array( 'ftyp', 'moov', 'mdat', 'wide', 'free' ), true );
	}

	/**
	 * Garante que o vídeo do Drive de um post já está na biblioteca de mídia.
	 * Roda no inquilino dono do post.
	 *
	 * @param array $post Post (PFlow_Posts::raw()).
	 * @return int|WP_Error ID do anexo (0 se o post não tem vídeo do Drive).
	 */
	public static function ensure( array $post ) {
		if ( '' === (string) ( $post['video_url'] ?? '' ) ) {
			return 0;
		}
		foreach ( $post['media_ids'] as $id ) {
			if ( PFlow_Media::is_video( $id ) ) {
				return (int) $id; // Já baixado.
			}
		}

		$lock = 'pflow_dl_' . (int) $post['id'];
		if ( get_transient( $lock ) ) {
			return new WP_Error( 'pflow_drive', __( 'O vídeo ainda está sendo baixado do Google Drive.', 'post-flow' ), array( 'retry' => true ) );
		}
		set_transient( $lock, 1, 30 * MINUTE_IN_SECONDS );

		try {
			$id = self::import( $post['video_url'] );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			global $wpdb;
			$wpdb->update( PFlow_DB::posts_table(), array( 'media_ids' => (string) $id ), array( 'id' => (int) $post['id'] ) ); // phpcs:ignore WordPress.DB
			return $id;
		} finally {
			delete_transient( $lock );
		}
	}

	/**
	 * Baixa o vídeo para a pasta de uploads e cria o anexo.
	 *
	 * @return int|WP_Error
	 */
	public static function import( $url ) {
		$file_id = self::file_id( $url );
		if ( '' === $file_id ) {
			return new WP_Error( 'pflow_drive', __( 'Link do Google Drive inválido.', 'post-flow' ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'pflow_drive', $uploads['error'] );
		}
		$name = wp_unique_filename( $uploads['path'], 'drive-' . strtolower( substr( $file_id, 0, 12 ) ) . '.mp4' );
		$path = trailingslashit( $uploads['path'] ) . $name;

		$res = wp_remote_get(
			self::download_url( $file_id ),
			array(
				'timeout'             => 600,
				'redirection'         => 5,
				'stream'              => true,
				'filename'            => $path,
				'limit_response_size' => PFlow_Media::max_video_bytes() + 1,
			)
		);
		$fail = static function ( $message, $retry = false ) use ( $path ) {
			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
			return new WP_Error( 'pflow_drive', $message, array( 'retry' => $retry ) );
		};

		if ( is_wp_error( $res ) ) {
			/* translators: %s: erro */
			return $fail( sprintf( __( 'O download do Google Drive falhou: %s', 'post-flow' ), $res->get_error_message() ), true );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$head = file_exists( $path ) ? (string) file_get_contents( $path, false, null, 0, 512 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $code >= 400 || self::looks_like_html( $head ) ) {
			return $fail( __( 'O vídeo do Google Drive não está público ("Qualquer pessoa com o link") ou foi removido.', 'post-flow' ) );
		}
		if ( ! self::looks_like_video( $head ) ) {
			return $fail( __( 'O arquivo baixado do Google Drive não é um vídeo MP4 ou MOV.', 'post-flow' ) );
		}
		if ( filesize( $path ) > PFlow_Media::max_video_bytes() ) {
			/* translators: %d: MB */
			return $fail( sprintf( __( 'O vídeo passa de %d MB.', 'post-flow' ), PFLOW_MAX_VIDEO_MB ) );
		}

		$mime = 'qt  ' === substr( $head, 8, 4 ) ? 'video/quicktime' : 'video/mp4';
		$url  = trailingslashit( $uploads['url'] ) . $name;
		$id   = PFlow_Media::insert( $path, $url, $mime, __( 'Vídeo do Google Drive', 'post-flow' ) );
		return is_wp_error( $id ) ? $fail( $id->get_error_message() ) : $id;
	}

	/**
	 * Agenda o download em segundo plano (logo depois de agendar o post).
	 */
	public static function schedule_prefetch( $post_id ) {
		if ( ! wp_next_scheduled( self::PREFETCH_HOOK, array( (int) $post_id ) ) ) {
			wp_schedule_single_event( time(), self::PREFETCH_HOOK, array( (int) $post_id ) );
		}
	}

	public static function prefetch( $post_id ) {
		$post = PFlow_Posts::raw( (int) $post_id );
		if ( $post && 'scheduled' === $post['status'] ) {
			PFlow_Tenant::run_as(
				$post['user_id'],
				static function () use ( $post ) {
					self::ensure( $post );
				}
			);
		}
	}
}
