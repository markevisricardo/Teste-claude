<?php
/**
 * Canais conectados (uma conta numa rede). Leitura e escrita sempre no
 * inquilino atual; os tokens ficam criptografados no banco.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Channels {

	const SECRET_COLUMNS = array( 'access_token', 'refresh_token' );

	private static function decode( $row ) {
		if ( ! $row ) {
			return null;
		}
		foreach ( self::SECRET_COLUMNS as $column ) {
			$row[ $column ] = PFlow_Crypto::decrypt( $row[ $column ] );
		}
		$row['id']               = (int) $row['id'];
		$row['user_id']          = (int) $row['user_id'];
		$row['token_expires_at'] = (int) $row['token_expires_at'];
		$meta                    = json_decode( (string) $row['meta'], true );
		$row['meta']             = is_array( $meta ) ? $meta : array();
		return $row;
	}

	/**
	 * Canais do usuário atual.
	 */
	public static function all( $only_active = false ) {
		global $wpdb;
		$sql  = 'SELECT * FROM ' . PFlow_DB::channels_table() . ' WHERE user_id = %d';
		$sql .= $only_active ? " AND status = 'active'" : '';
		$sql .= ' ORDER BY FIELD(network, \'facebook\', \'instagram\', \'linkedin\', \'x\', \'threads\'), name ASC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, PFlow_Tenant::id() ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PFlow_DB::channels_table() . ' WHERE id = %d AND user_id = %d', $id, PFlow_Tenant::id() ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return self::decode( $row );
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PFlow_DB::channels_table() . ' WHERE user_id = %d', PFlow_Tenant::id() ) ); // phpcs:ignore WordPress.DB
	}

	public static function exists( $network, $external_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . PFlow_DB::channels_table() . ' WHERE user_id = %d AND network = %s AND external_id = %s', PFlow_Tenant::id(), $network, $external_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Quantos canais novos o usuário ainda pode conectar (null = sem limite).
	 */
	public static function remaining_slots() {
		$max = (int) PFlow_Settings::get( 'max_channels' );
		return $max > 0 ? max( 0, $max - self::count() ) : null;
	}

	/**
	 * Cria ou atualiza (reconexão) um canal do usuário atual.
	 *
	 * @return int ID do canal.
	 */
	public static function upsert( array $account ) {
		global $wpdb;
		$table = PFlow_DB::channels_table();
		$now   = PFlow_DB::now();
		$data  = array(
			'provider_user_id' => (string) $account['provider_user_id'],
			'name'             => mb_substr( (string) $account['name'], 0, 191 ),
			'username'         => mb_substr( (string) $account['username'], 0, 191 ),
			'avatar_url'       => esc_url_raw( (string) $account['avatar_url'] ),
			'access_token'     => PFlow_Crypto::encrypt( (string) $account['access_token'] ),
			'refresh_token'    => PFlow_Crypto::encrypt( (string) $account['refresh_token'] ),
			'token_expires_at' => (int) $account['token_expires_at'],
			'meta'             => wp_json_encode( (array) $account['meta'] ),
			'status'           => 'active',
			'last_error'       => '',
			'updated_at'       => $now,
		);

		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND network = %s AND external_id = %s", PFlow_Tenant::id(), $account['network'], $account['external_id'] ) ); // phpcs:ignore WordPress.DB
		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
			return $id;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			$data + array(
				'user_id'     => PFlow_Tenant::id(),
				'network'     => (string) $account['network'],
				'external_id' => (string) $account['external_id'],
				'created_at'  => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Grava tokens renovados (por ID, sem inquilino: usado pelo publicador).
	 */
	public static function save_tokens( $id, array $tokens ) {
		global $wpdb;
		$data = array( 'updated_at' => PFlow_DB::now() );
		foreach ( array( 'access_token', 'refresh_token' ) as $column ) {
			if ( isset( $tokens[ $column ] ) ) {
				$data[ $column ] = PFlow_Crypto::encrypt( (string) $tokens[ $column ] );
			}
		}
		if ( isset( $tokens['token_expires_at'] ) ) {
			$data['token_expires_at'] = (int) $tokens['token_expires_at'];
		}
		$wpdb->update( PFlow_DB::channels_table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	public static function mark( $id, $status, $error = '' ) {
		global $wpdb;
		$wpdb->update( PFlow_DB::channels_table(), array( 'status' => $status, 'last_error' => (string) $error, 'updated_at' => PFlow_DB::now() ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Remove um canal do usuário atual e tira ele dos posts que ainda não saíram.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$channel = self::get( $id );
		if ( ! $channel ) {
			return false;
		}
		PFlow_Posts::drop_channel( $channel['id'] );
		$wpdb->delete( PFlow_DB::channels_table(), array( 'id' => $channel['id'], 'user_id' => PFlow_Tenant::id() ) ); // phpcs:ignore WordPress.DB
		return true;
	}

	/**
	 * A pessoa removeu o app na Meta (callback de desautorização/exclusão).
	 */
	public static function delete_by_provider_user( $networks, $provider_user_id ) {
		global $wpdb;
		$table = PFlow_DB::channels_table();
		$in    = implode( ',', array_fill( 0, count( $networks ), '%s' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id FROM {$table} WHERE provider_user_id = %s AND network IN ({$in})", array_merge( array( $provider_user_id ), $networks ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $row ) {
			PFlow_Tenant::run_as(
				(int) $row['user_id'],
				static function () use ( $row ) {
					self::delete( (int) $row['id'] );
				}
			);
		}
		return count( (array) $rows );
	}

	/**
	 * Nome para exibir: "@usuario" quando houver, senão o nome.
	 */
	public static function display_name( array $channel ) {
		return '' !== $channel['username'] ? '@' . $channel['username'] : $channel['name'];
	}

	/**
	 * Avatar redondo com a cor da rede (HTML escapado).
	 */
	public static function avatar( array $channel, $size = 'h-10 w-10' ) {
		$type  = PFlow_Networks::type( $channel['network'] );
		$color = $type ? $type['color'] : '#6d28d9';
		$glyph = $type ? $type['glyph'] : '?';
		$img   = '' !== $channel['avatar_url']
			? '<img src="' . esc_url( $channel['avatar_url'] ) . '" alt="" class="h-full w-full rounded-full object-cover" loading="lazy" referrerpolicy="no-referrer">'
			: '<span class="flex h-full w-full items-center justify-center rounded-full bg-gradient-to-br from-neutral-700 to-neutral-800 text-xs font-semibold text-white">' . esc_html( strtoupper( mb_substr( self::display_name( $channel ), '@' === mb_substr( self::display_name( $channel ), 0, 1 ) ? 1 : 0, 1 ) ) ) . '</span>';

		return '<span class="relative inline-flex shrink-0 ' . esc_attr( $size ) . '">' . $img
			. '<span class="absolute -bottom-0.5 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full px-0.5 text-[7px] font-bold leading-none text-white ring-2 ring-neutral-950" style="background:' . esc_attr( $color ) . '">' . esc_html( $glyph ) . '</span></span>';
	}
}
