<?php
/**
 * Repasse dos webhooks da Meta para os sites dos clientes.
 *
 * Cada entrada do webhook vai para o site da licença dona da conta. A entrega
 * é tentada na hora; se o site não responder 2xx, entra na fila e é tentada
 * de novo depois de 1, 5, 15, 60 e 180 minutos.
 */

defined( 'ABSPATH' ) || exit;

class CFHub_Forwarder {

	const CRON_HOOK    = 'cfhub_retry_deliveries';
	const CLEANUP_HOOK = 'cfhub_cleanup_deliveries';
	const BACKOFF      = array( 1, 5, 15, 60, 180 ); // minutos

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( self::CRON_HOOK, array( __CLASS__, 'retry_due' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'cfhub_minute', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function schedules( $schedules ) {
		$schedules['cfhub_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'Comment Flow Hub: a cada minuto',
		);
		return $schedules;
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/**
	 * Separa as entradas do webhook por licença.
	 *
	 * @param callable $resolve fn( string $ig_user_id ): ?array licença.
	 * @return array[] license_id => { license, payload }
	 */
	public static function route( array $payload, $resolve ) {
		$routes = array();
		$object = isset( $payload['object'] ) ? $payload['object'] : 'instagram';

		foreach ( isset( $payload['entry'] ) && is_array( $payload['entry'] ) ? $payload['entry'] : array() as $entry ) {
			if ( empty( $entry['id'] ) ) {
				continue;
			}
			$license = call_user_func( $resolve, (string) $entry['id'] );
			if ( ! $license ) {
				continue; // Conta sem licença ativa: descarta.
			}
			$id = (int) $license['id'];
			if ( ! isset( $routes[ $id ] ) ) {
				$routes[ $id ] = array(
					'license' => $license,
					'payload' => array(
						'object' => $object,
						'entry'  => array(),
					),
				);
			}
			$routes[ $id ]['payload']['entry'][] = $entry;
		}

		return $routes;
	}

	/**
	 * Recebe o payload da Meta, enfileira e tenta entregar na hora.
	 */
	public static function dispatch( array $payload ) {
		global $wpdb;
		$count = 0;

		foreach ( self::route( $payload, array( 'CFHub_Licenses', 'license_for_account' ) ) as $route ) {
			$wpdb->insert(
				CFHub_DB::deliveries_table(),
				array(
					'license_id'      => (int) $route['license']['id'],
					'payload'         => wp_json_encode( $route['payload'] ),
					'status'          => 'pending',
					'next_attempt_at' => CFHub_DB::now(),
					'created_at'      => CFHub_DB::now(),
				)
			);
			self::attempt( (int) $wpdb->insert_id, $route['license'], $route['payload'] );
			++$count;
		}

		return $count;
	}

	/**
	 * Monta o corpo e os cabeçalhos assinados de uma entrega.
	 */
	public static function build_request( array $payload, $site_secret, $ts = null ) {
		$body = wp_json_encode(
			array(
				'ts'      => null === $ts ? time() : (int) $ts,
				'payload' => $payload,
			)
		);
		return array(
			'body'    => $body,
			'headers' => array(
				'Content-Type'          => 'application/json',
				'X-CFlow-Hub-Signature' => CFHub_Licenses::sign( $body, $site_secret ),
			),
		);
	}

	private static function attempt( $delivery_id, array $license, array $payload ) {
		global $wpdb;
		$table   = CFHub_DB::deliveries_table();
		$request = self::build_request( $payload, $license['site_secret'] );

		$response = wp_remote_post(
			$license['callback_url'],
			array(
				'timeout' => 8,
				'headers' => $request['headers'],
				'body'    => $request['body'],
			)
		);
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			$wpdb->update( $table, array( 'status' => 'delivered', 'last_error' => null ), array( 'id' => $delivery_id ) );
			CFHub_Licenses::update( $license['id'], array( 'last_delivery_at' => CFHub_DB::now() ) );
			return true;
		}

		$attempts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT attempts FROM {$table} WHERE id = %d", $delivery_id ) ) + 1;
		$error    = is_wp_error( $response ) ? $response->get_error_message() : sprintf( 'HTTP %d', $code );
		$final    = $attempts > count( self::BACKOFF );

		$wpdb->update(
			$table,
			array(
				'status'          => $final ? 'failed' : 'retry',
				'attempts'        => $attempts,
				'last_error'      => $error,
				'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + MINUTE_IN_SECONDS * ( $final ? 0 : self::BACKOFF[ $attempts - 1 ] ) ),
			),
			array( 'id' => $delivery_id )
		);
		return false;
	}

	public static function retry_due() {
		global $wpdb;
		$table = CFHub_DB::deliveries_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'retry' AND next_attempt_at <= %s ORDER BY id ASC LIMIT 50", CFHub_DB::now() ),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$license = CFHub_Licenses::get( $row['license_id'] );
			if ( ! $license || 'active' !== $license['status'] || '' === $license['callback_url'] ) {
				$wpdb->update( $table, array( 'status' => 'failed', 'last_error' => 'licença inativa' ), array( 'id' => $row['id'] ) );
				continue;
			}
			$payload = json_decode( $row['payload'], true );
			self::attempt( (int) $row['id'], $license, is_array( $payload ) ? $payload : array() );
		}
	}

	public static function cleanup() {
		global $wpdb;
		$table = CFHub_DB::deliveries_table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = 'delivered' AND created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ) ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS ) ) );
	}

	public static function recent( $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT d.id, d.license_id, d.status, d.attempts, d.last_error, d.created_at, d.next_attempt_at, l.client_name FROM ' . CFHub_DB::deliveries_table() . ' d LEFT JOIN ' . CFHub_DB::licenses_table() . ' l ON l.id = d.license_id ORDER BY d.id DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);
	}

	public static function counts() {
		global $wpdb;
		$rows   = $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . CFHub_DB::deliveries_table() . ' GROUP BY status', ARRAY_A );
		$counts = array( 'delivered' => 0, 'retry' => 0, 'failed' => 0, 'pending' => 0 );
		foreach ( $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['n'];
		}
		return $counts;
	}
}
