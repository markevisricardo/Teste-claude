<?php
/**
 * Posts e os seus destinos (um por canal).
 *
 * Status do post (calculado a partir dos destinos):
 *   draft     – rascunho, sem destinos agendados;
 *   scheduled – algum destino ainda vai sair (pending/publishing);
 *   sent      – saiu em todos os canais;
 *   partial   – saiu em alguns e falhou em outros;
 *   failed    – falhou em todos.
 * Status do destino: pending, publishing, sent, failed.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Posts {

	private static function decode_post( $row ) {
		if ( ! $row ) {
			return null;
		}
		$row['id']        = (int) $row['id'];
		$row['user_id']   = (int) $row['user_id'];
		$row['in_queue']  = (int) $row['in_queue'];
		$row['media_ids'] = self::parse_ids( $row['media_ids'] );
		return $row;
	}

	public static function parse_ids( $value ) {
		$ids = is_array( $value ) ? $value : explode( ',', (string) $value );
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Post do usuário atual, com os destinos.
	 */
	public static function get( $id ) {
		global $wpdb;
		$post = self::decode_post( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PFlow_DB::posts_table() . ' WHERE id = %d AND user_id = %d', $id, PFlow_Tenant::id() ), ARRAY_A ) ); // phpcs:ignore WordPress.DB
		if ( $post ) {
			$post['targets'] = self::targets( $post['id'] );
		}
		return $post;
	}

	/**
	 * Destinos de um post (com dados do canal para exibir).
	 */
	public static function targets( $post_id ) {
		global $wpdb;
		$t    = PFlow_DB::targets_table();
		$c    = PFlow_DB::channels_table();
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT t.*, c.network, c.name, c.username, c.avatar_url FROM {$t} t LEFT JOIN {$c} c ON c.id = t.channel_id WHERE t.post_id = %d AND t.user_id = %d ORDER BY t.id ASC", // phpcs:ignore WordPress.DB
				$post_id,
				PFlow_Tenant::id()
			),
			ARRAY_A
		);
		return array_map(
			static function ( $row ) {
				$row['id']         = (int) $row['id'];
				$row['channel_id'] = (int) $row['channel_id'];
				foreach ( array( 'network', 'name', 'username', 'avatar_url' ) as $key ) {
					$row[ $key ] = (string) $row[ $key ];
				}
				return $row;
			},
			(array) $rows
		);
	}

	/**
	 * Lista posts do usuário atual.
	 *
	 * @param array $args statuses (array), from/to (UTC 'Y-m-d H:i:s', sobre
	 *                    scheduled_at ou sent_at), order (ASC|DESC), limit, offset.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'statuses' => array(),
				'from'     => '',
				'to'       => '',
				'order'    => 'ASC',
				'order_by' => 'scheduled_at',
				'limit'    => 100,
				'offset'   => 0,
			)
		);
		$where = array( $wpdb->prepare( 'user_id = %d', PFlow_Tenant::id() ) );
		if ( $args['statuses'] ) {
			$where[] = "status IN ('" . implode( "','", array_map( 'esc_sql', (array) $args['statuses'] ) ) . "')";
		}
		$when = 'COALESCE(sent_at, scheduled_at, updated_at)';
		if ( '' !== $args['from'] ) {
			$where[] = $wpdb->prepare( "{$when} >= %s", $args['from'] ); // phpcs:ignore WordPress.DB
		}
		if ( '' !== $args['to'] ) {
			$where[] = $wpdb->prepare( "{$when} < %s", $args['to'] ); // phpcs:ignore WordPress.DB
		}
		$order_by = in_array( $args['order_by'], array( 'scheduled_at', 'sent_at', 'updated_at' ), true ) ? $args['order_by'] : 'scheduled_at';
		$order    = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$sql      = 'SELECT * FROM ' . PFlow_DB::posts_table() . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY {$order_by} {$order}, id {$order}";
		$sql     .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );

		$posts = array_map( array( __CLASS__, 'decode_post' ), (array) $wpdb->get_results( $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB
		foreach ( $posts as &$post ) {
			$post['targets'] = self::targets( $post['id'] );
		}
		return $posts;
	}

	public static function count( array $statuses ) {
		global $wpdb;
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . PFlow_DB::posts_table() . " WHERE user_id = %d AND status IN ('" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "')", PFlow_Tenant::id() ) // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Cria ou atualiza um post e refaz os destinos que ainda não saíram.
	 *
	 * @param array    $data        content, link, media_ids (array), in_queue.
	 * @param int[]    $channel_ids Canais do usuário atual.
	 * @param string   $due         UTC 'Y-m-d H:i:s' para agendar, ou '' (rascunho).
	 * @param int      $id          Post existente (do usuário atual) ou 0.
	 * @return int ID do post.
	 */
	public static function save( array $data, array $channel_ids, $due, $id = 0 ) {
		global $wpdb;
		$posts = PFlow_DB::posts_table();
		$now   = PFlow_DB::now();
		$row   = array(
			'content'      => (string) $data['content'],
			'link'         => (string) $data['link'],
			'media_ids'    => implode( ',', self::parse_ids( $data['media_ids'] ) ),
			'in_queue'     => empty( $data['in_queue'] ) ? 0 : 1,
			'scheduled_at' => '' !== $due ? $due : null,
			// Rascunho: guarda a escolha de canais para quando for agendado.
			'channel_ids'  => '' !== $due ? '' : implode( ',', self::parse_ids( $channel_ids ) ),
			'updated_at'   => $now,
		);

		if ( $id ) {
			$wpdb->update( $posts, $row, array( 'id' => (int) $id, 'user_id' => PFlow_Tenant::id() ) ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( $posts, $row + array( 'user_id' => PFlow_Tenant::id(), 'status' => 'draft', 'created_at' => $now ) ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;
		}

		// Destinos que ainda não saíram são refeitos; os já publicados ficam.
		$targets = PFlow_DB::targets_table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$targets} WHERE post_id = %d AND status IN ('pending','failed')", $id ) ); // phpcs:ignore WordPress.DB
		$sent = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT channel_id FROM {$targets} WHERE post_id = %d", $id ) ) ); // phpcs:ignore WordPress.DB

		if ( '' !== $due ) {
			foreach ( array_unique( array_map( 'intval', $channel_ids ) ) as $channel_id ) {
				if ( in_array( $channel_id, $sent, true ) ) {
					continue;
				}
				$wpdb->insert( // phpcs:ignore WordPress.DB
					$targets,
					array(
						'post_id'    => $id,
						'user_id'    => PFlow_Tenant::id(),
						'channel_id' => $channel_id,
						'status'     => 'pending',
						'due_at'     => $due,
					)
				);
			}
		}

		self::refresh_status( $id );
		return $id;
	}

	/**
	 * Canais escolhidos (destinos ou, num rascunho, a escolha guardada).
	 */
	public static function channel_ids( array $post ) {
		if ( 'draft' === $post['status'] ) {
			return self::parse_ids( $post['channel_ids'] );
		}
		return array_map( 'intval', wp_list_pluck( $post['targets'], 'channel_id' ) );
	}

	/**
	 * Recalcula o status do post a partir dos destinos.
	 */
	public static function refresh_status( $post_id ) {
		global $wpdb;
		$statuses = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT status FROM ' . PFlow_DB::targets_table() . ' WHERE post_id = %d', $post_id ) ); // phpcs:ignore WordPress.DB
		$status   = self::status_from_targets( $statuses );
		$data     = array( 'status' => $status );
		if ( in_array( $status, array( 'sent', 'partial', 'failed' ), true ) ) {
			$data['sent_at'] = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(COALESCE(published_at, locked_at, due_at)) FROM ' . PFlow_DB::targets_table() . ' WHERE post_id = %d', $post_id ) ); // phpcs:ignore WordPress.DB
		} else {
			$data['sent_at'] = null;
		}
		$wpdb->update( PFlow_DB::posts_table(), $data, array( 'id' => (int) $post_id ) ); // phpcs:ignore WordPress.DB
		return $status;
	}

	public static function status_from_targets( array $statuses ) {
		if ( ! $statuses ) {
			return 'draft';
		}
		if ( array_intersect( $statuses, array( 'pending', 'publishing' ) ) ) {
			return 'scheduled';
		}
		$sent   = in_array( 'sent', $statuses, true );
		$failed = in_array( 'failed', $statuses, true );
		if ( $sent && $failed ) {
			return 'partial';
		}
		return $sent ? 'sent' : 'failed';
	}

	/**
	 * O post pode ser editado (nada saindo neste instante e nem tudo publicado)?
	 */
	public static function is_editable( array $post ) {
		if ( 'sent' === $post['status'] ) {
			return false;
		}
		foreach ( $post['targets'] as $target ) {
			if ( 'publishing' === $target['status'] ) {
				return false;
			}
		}
		return true;
	}

	public static function delete( $id ) {
		global $wpdb;
		$post = self::get( $id );
		// Posts publicados podem sair do histórico; os que estão saindo agora, não.
		if ( ! $post || ( ! self::is_editable( $post ) && 'sent' !== $post['status'] ) ) {
			return false;
		}
		$wpdb->delete( PFlow_DB::targets_table(), array( 'post_id' => $post['id'] ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( PFlow_DB::posts_table(), array( 'id' => $post['id'], 'user_id' => PFlow_Tenant::id() ) ); // phpcs:ignore WordPress.DB
		PFlow_Media::delete_unused( $post['media_ids'] );
		return true;
	}

	/**
	 * Faz os destinos com falha tentarem de novo agora.
	 */
	public static function retry( $id ) {
		global $wpdb;
		$post = self::get( $id );
		if ( ! $post ) {
			return false;
		}
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . PFlow_DB::targets_table() . " SET status = 'pending', due_at = %s, attempts = 0, error = '' WHERE post_id = %d AND status = 'failed'", PFlow_DB::now(), $post['id'] ) ); // phpcs:ignore WordPress.DB
		self::refresh_status( $post['id'] );
		return true;
	}

	/**
	 * Tira um canal removido dos posts que ainda não saíram.
	 */
	public static function drop_channel( $channel_id ) {
		global $wpdb;
		$targets  = PFlow_DB::targets_table();
		$post_ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$targets} WHERE channel_id = %d AND status IN ('pending','failed')", $channel_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$targets} WHERE channel_id = %d AND status IN ('pending','failed')", $channel_id ) ); // phpcs:ignore WordPress.DB
		foreach ( $post_ids as $post_id ) {
			self::refresh_status( (int) $post_id );
		}
	}

	/**
	 * Horários da fila já ocupados pelo usuário atual (UTC 'Y-m-d H:i').
	 */
	public static function taken_slots() {
		global $wpdb;
		$rows = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT scheduled_at FROM ' . PFlow_DB::posts_table() . " WHERE user_id = %d AND in_queue = 1 AND status = 'scheduled' AND scheduled_at >= %s", PFlow_Tenant::id(), PFlow_DB::now() ) ); // phpcs:ignore WordPress.DB
		return array_map(
			static function ( $value ) {
				return substr( (string) $value, 0, 16 );
			},
			$rows
		);
	}

	/* ---------------------------------------------------------------------
	 * Publicador (sem inquilino: atua em todos os usuários)
	 * ------------------------------------------------------------------- */

	/**
	 * IDs dos destinos vencidos.
	 */
	public static function due_targets( $limit = 20 ) {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . PFlow_DB::targets_table() . " WHERE status = 'pending' AND due_at <= %s ORDER BY due_at ASC, id ASC LIMIT %d", PFlow_DB::now(), $limit ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Reserva um destino (só um processo publica cada um).
	 *
	 * @return array|null O destino reservado.
	 */
	public static function claim_target( $id ) {
		global $wpdb;
		$table   = PFlow_DB::targets_table();
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'publishing', locked_at = %s, attempts = attempts + 1 WHERE id = %d AND status = 'pending' AND due_at <= %s", PFlow_DB::now(), $id, PFlow_DB::now() ) ); // phpcs:ignore WordPress.DB
		if ( 1 !== (int) $claimed ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public static function update_target( $id, array $data ) {
		global $wpdb;
		$wpdb->update( PFlow_DB::targets_table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Destinos presos em "publishing" (processo interrompido): viram falha,
	 * para não arriscar publicar duas vezes. O usuário pode tentar de novo.
	 */
	public static function release_stuck( $minutes = 15 ) {
		global $wpdb;
		$table = PFlow_DB::targets_table();
		$limit = gmdate( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS );
		$ids   = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$table} WHERE status = 'publishing' AND locked_at < %s", $limit ) ); // phpcs:ignore WordPress.DB
		if ( ! $ids ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'failed', error = %s WHERE status = 'publishing' AND locked_at < %s", __( 'A publicação foi interrompida. Confira na rede antes de tentar de novo.', 'post-flow' ), $limit ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $post_id ) {
			self::refresh_status( (int) $post_id );
		}
	}

	/**
	 * Post cru por ID (sem inquilino).
	 */
	public static function raw( $id ) {
		global $wpdb;
		return self::decode_post( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PFlow_DB::posts_table() . ' WHERE id = %d', $id ), ARRAY_A ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Remove tudo de um usuário (exclusão da conta).
	 */
	public static function delete_user_data( $user_id ) {
		global $wpdb;
		if ( class_exists( 'PFlow_WhatsApp_Web' ) ) {
			PFlow_WhatsApp_Web::forget( (int) $user_id ); // Desconecta o número do servidor.
		}
		$media = array();
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT media_ids FROM ' . PFlow_DB::posts_table() . ' WHERE user_id = %d', $user_id ) ) as $ids ) { // phpcs:ignore WordPress.DB
			$media = array_merge( $media, self::parse_ids( $ids ) );
		}
		$wpdb->delete( PFlow_DB::targets_table(), array( 'user_id' => (int) $user_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( PFlow_DB::posts_table(), array( 'user_id' => (int) $user_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( PFlow_DB::channels_table(), array( 'user_id' => (int) $user_id ) ); // phpcs:ignore WordPress.DB
		foreach ( array_unique( $media ) as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}
