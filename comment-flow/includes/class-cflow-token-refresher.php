<?php
/**
 * Renova diariamente o token de longa duração (válido por 60 dias) e limpa
 * logs antigos.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Token_Refresher {

	const HOOK           = 'cflow_daily_maintenance';
	const REFRESH_WINDOW = 15 * DAY_IN_SECONDS;
	const LOG_RETENTION  = 90; // dias

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run() {
		CFlow_DB::purge_logs_older_than( self::LOG_RETENTION );

		// Renova o token de cada usuário que vence nos próximos dias.
		foreach ( CFlow_Accounts::expiring( self::REFRESH_WINDOW ) as $account ) {
			$api    = new CFlow_Instagram_API( $account['access_token'] );
			$result = $api->refresh_token();
			if ( is_wp_error( $result ) || empty( $result['access_token'] ) ) {
				continue; // Tenta de novo amanhã; o painel mostra a data de expiração.
			}

			CFlow_Accounts::update(
				$account['user_id'],
				array(
					'access_token'     => $result['access_token'],
					'token_expires_at' => time() + ( isset( $result['expires_in'] ) ? (int) $result['expires_in'] : 60 * DAY_IN_SECONDS ),
				)
			);
		}
	}
}
