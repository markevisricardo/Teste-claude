<?php
/**
 * Imagens dos posts: enviadas pelo painel, guardadas na biblioteca de mídia
 * (autor = o usuário) e convertidas para JPG, o formato que todas as redes
 * aceitam (o Instagram só publica JPG pela API).
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Media {

	const MAX_BYTES = 8 * 1024 * 1024; // Limite do Instagram para imagens.
	const MIMES     = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

	/**
	 * Processa os arquivos de $_FILES[$field] (múltiplos).
	 *
	 * @return array array( 'ids' => int[], 'errors' => string[] )
	 */
	public static function handle_uploads( $field ) {
		$out = array( 'ids' => array(), 'errors' => array() );
		// phpcs:ignore WordPress.Security.NonceVerification -- verificado em PFlow_App::guard().
		if ( empty( $_FILES[ $field ]['name'] ) || ! is_array( $_FILES[ $field ]['name'] ) ) {
			return $out;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$files = $_FILES[ $field ]; // phpcs:ignore WordPress.Security
		foreach ( array_keys( $files['name'] ) as $i ) {
			if ( '' === (string) $files['name'][ $i ] || UPLOAD_ERR_NO_FILE === (int) $files['error'][ $i ] ) {
				continue;
			}
			$file = array(
				'name'     => sanitize_file_name( (string) $files['name'][ $i ] ),
				'type'     => (string) $files['type'][ $i ],
				'tmp_name' => (string) $files['tmp_name'][ $i ],
				'error'    => (int) $files['error'][ $i ],
				'size'     => (int) $files['size'][ $i ],
			);
			$id = self::store( $file );
			if ( is_wp_error( $id ) ) {
				$out['errors'][] = $file['name'] . ': ' . $id->get_error_message();
			} else {
				$out['ids'][] = $id;
			}
		}
		return $out;
	}

	/**
	 * @return int|WP_Error ID do anexo.
	 */
	private static function store( array $file ) {
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'pflow_upload', __( 'o envio falhou.', 'post-flow' ) );
		}
		if ( $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'pflow_upload', __( 'a imagem passa de 8 MB.', 'post-flow' ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $check['type'] ) || ! in_array( $check['type'], self::MIMES, true ) ) {
			return new WP_Error( 'pflow_upload', __( 'envie JPG, PNG, WebP ou GIF.', 'post-flow' ) );
		}

		$uploaded = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' ) ) );
		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'pflow_upload', $uploaded['error'] );
		}

		$path = $uploaded['file'];
		$url  = $uploaded['url'];
		$mime = $uploaded['type'];

		if ( 'image/jpeg' !== $mime ) {
			$jpeg = self::to_jpeg( $path );
			if ( is_wp_error( $jpeg ) ) {
				wp_delete_file( $path );
				return $jpeg;
			}
			wp_delete_file( $path );
			$url  = str_replace( wp_basename( $path ), wp_basename( $jpeg ), $url );
			$path = $jpeg;
			$mime = 'image/jpeg';
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => sanitize_text_field( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
				'post_status'    => 'inherit',
				'post_author'    => PFlow_Tenant::id(),
				'guid'           => $url,
				'meta_input'     => array( '_pflow_media' => 1 ),
			),
			$path,
			0,
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $path );
			return $id;
		}
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
		return (int) $id;
	}

	/**
	 * Salva uma cópia JPG ao lado do original.
	 *
	 * @return string|WP_Error Caminho do JPG.
	 */
	private static function to_jpeg( $path ) {
		$target = preg_replace( '/\.[^.]+$/', '', $path ) . '.jpg';
		$target = dirname( $target ) . '/' . wp_unique_filename( dirname( $target ), wp_basename( $target ) );

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return new WP_Error( 'pflow_upload', __( 'o servidor não conseguiu converter a imagem para JPG.', 'post-flow' ) );
		}
		$saved = $editor->save( $target, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return $saved['path'];
	}

	/**
	 * Mantém só imagens do usuário atual (impede usar anexos de outra pessoa).
	 */
	public static function owned( array $ids ) {
		return array_values(
			array_filter(
				PFlow_Posts::parse_ids( $ids ),
				static function ( $id ) {
					$post = get_post( $id );
					return $post && 'attachment' === $post->post_type && (int) $post->post_author === PFlow_Tenant::id() && wp_attachment_is_image( $id );
				}
			)
		);
	}

	/**
	 * Dados das imagens para o publicador.
	 */
	public static function for_publish( array $ids ) {
		$out = array();
		foreach ( PFlow_Posts::parse_ids( $ids ) as $id ) {
			$path = get_attached_file( $id );
			$url  = wp_get_attachment_url( $id );
			if ( $path && $url ) {
				$out[] = array(
					'url'  => set_url_scheme( $url, 'https' ),
					'path' => $path,
					'mime' => (string) get_post_mime_type( $id ),
				);
			}
		}
		return $out;
	}

	public static function thumb( $id ) {
		$src = wp_get_attachment_image_src( $id, 'medium' );
		return $src ? $src[0] : (string) wp_get_attachment_url( $id );
	}

	/**
	 * Apaga anexos que nenhum outro post usa.
	 */
	public static function delete_unused( array $ids ) {
		global $wpdb;
		foreach ( PFlow_Posts::parse_ids( $ids ) as $id ) {
			$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PFlow_DB::posts_table() . ' WHERE FIND_IN_SET(%d, media_ids)', $id ) ); // phpcs:ignore WordPress.DB
			if ( ! $used && get_post_meta( $id, '_pflow_media', true ) ) {
				wp_delete_attachment( $id, true );
			}
		}
	}
}
