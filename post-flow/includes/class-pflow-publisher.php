<?php
/**
 * Publicador: a cada minuto (WP-Cron) pega os destinos vencidos e publica
 * cada um no seu canal. "Publicar agora" chama o mesmo código na hora.
 *
 * Falhas temporárias (rede, 429, 5xx) tentam de novo em 5 e 20 minutos;
 * as demais (texto recusado, token inválido) viram falha com a mensagem da rede.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Publisher {

	const HOOK        = 'pflow_publish_due';
	const DAILY_HOOK  = 'pflow_daily_maintenance';
	const MAX_RETRIES = 3;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( self::DAILY_HOOK, array( __CLASS__, 'daily' ) );
	}

	public static function cron_schedules( $schedules ) {
		$schedules['pflow_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'A cada minuto (Post Flow)', 'post-flow' ),
		);
		return $schedules;
	}

	public static function schedule() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'pflow_minute', self::HOOK );
		}
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::DAILY_HOOK );
	}

	/**
	 * Publica os destinos vencidos (até ~50 s por execução).
	 */
	public static function run() {
		$start = time();
		PFlow_Posts::release_stuck();
		foreach ( PFlow_Posts::due_targets( 25 ) as $target_id ) {
			if ( time() - $start > 50 ) {
				break;
			}
			self::publish_target( $target_id );
		}
	}

	/**
	 * Publica na hora os destinos vencidos de um post (botão "Publicar agora").
	 */
	public static function run_post( $post_id ) {
		global $wpdb;
		$ids = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . PFlow_DB::targets_table() . " WHERE post_id = %d AND status = 'pending' AND due_at <= %s", $post_id, PFlow_DB::now() ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			self::publish_target( (int) $id );
		}
	}

	public static function publish_target( $target_id ) {
		$target = PFlow_Posts::claim_target( $target_id );
		if ( ! $target ) {
			return; // Outro processo já pegou.
		}

		PFlow_Tenant::run_as(
			(int) $target['user_id'],
			static function () use ( $target ) {
				$result = self::attempt( $target );
				self::record( $target, $result );
				PFlow_Posts::refresh_status( (int) $target['post_id'] );
			}
		);
	}

	/**
	 * @return array|WP_Error
	 */
	private static function attempt( array $target ) {
		$post    = PFlow_Posts::raw( (int) $target['post_id'] );
		$channel = PFlow_Channels::get( (int) $target['channel_id'] );
		if ( ! $post || ! $channel ) {
			return new WP_Error( 'pflow_missing', __( 'O post ou o canal não existe mais.', 'post-flow' ) );
		}
		if ( 'active' !== $channel['status'] ) {
			return new WP_Error( 'pflow_channel', __( 'O canal está desconectado. Reconecte em Canais e tente de novo.', 'post-flow' ) );
		}

		$provider = PFlow_Networks::provider_for( $channel['network'] );
		if ( ! $provider || ! $provider->is_configured() ) {
			return new WP_Error( 'pflow_provider', __( 'Esta rede não está configurada na plataforma.', 'post-flow' ) );
		}

		$channel = self::fresh_tokens( $provider, $channel );
		if ( is_wp_error( $channel ) ) {
			return $channel;
		}

		$media = PFlow_Media::for_publish( $post['media_ids'] );
		$text  = PFlow_Networks::compose_text( $channel['network'], $post['content'], $post['link'], count( $media ) );

		$problems = PFlow_Networks::validate( $channel['network'], $text, count( $media ) );
		if ( $problems ) {
			return new WP_Error( 'pflow_invalid', implode( ' ', $problems ) );
		}

		return $provider->publish(
			$channel,
			array(
				'text'  => $text,
				'link'  => (string) $post['link'],
				'media' => $media,
			)
		);
	}

	/**
	 * Renova o token antes de publicar, se a rede exigir.
	 *
	 * @return array|WP_Error Canal com o token atual.
	 */
	private static function fresh_tokens( PFlow_Provider $provider, array $channel ) {
		$tokens = $provider->refresh( $channel );
		if ( is_wp_error( $tokens ) ) {
			if ( ! PFlow_Http::is_retryable( $tokens ) ) {
				PFlow_Channels::mark( $channel['id'], 'expired', $tokens->get_error_message() );
			}
			return $tokens;
		}
		if ( is_array( $tokens ) ) {
			PFlow_Channels::save_tokens( $channel['id'], $tokens );
			$channel = array_merge( $channel, $tokens );
		}
		if ( $channel['token_expires_at'] && $channel['token_expires_at'] < time() ) {
			PFlow_Channels::mark( $channel['id'], 'expired', __( 'A conexão venceu.', 'post-flow' ) );
			return new WP_Error( 'pflow_expired', __( 'A conexão com a rede venceu. Reconecte o canal e tente de novo.', 'post-flow' ) );
		}
		return $channel;
	}

	private static function record( array $target, $result ) {
		$now = PFlow_DB::now();
		if ( ! is_wp_error( $result ) ) {
			PFlow_Posts::update_target(
				$target['id'],
				array(
					'status'       => 'sent',
					'remote_id'    => mb_substr( (string) $result['remote_id'], 0, 191 ),
					'remote_url'   => esc_url_raw( (string) $result['remote_url'] ),
					'error'        => '',
					'published_at' => $now,
				)
			);
			return;
		}

		$attempts = (int) $target['attempts'];
		if ( PFlow_Http::is_retryable( $result ) && $attempts < self::MAX_RETRIES ) {
			$wait = 1 === $attempts ? 5 : 20;
			PFlow_Posts::update_target(
				$target['id'],
				array(
					'status' => 'pending',
					'due_at' => gmdate( 'Y-m-d H:i:s', time() + $wait * MINUTE_IN_SECONDS ),
					'error'  => $result->get_error_message(),
				)
			);
			return;
		}

		$data = $result->get_error_data();
		if ( is_array( $data ) && in_array( (int) ( $data['status'] ?? 0 ), array( 401 ), true ) ) {
			PFlow_Channels::mark( (int) $target['channel_id'], 'expired', $result->get_error_message() );
		}
		PFlow_Posts::update_target(
			$target['id'],
			array(
				'status'       => 'failed',
				'error'        => $result->get_error_message(),
				'published_at' => $now,
			)
		);
	}

	/**
	 * Manutenção diária: renova tokens que estão para vencer (Threads,
	 * Pinterest, LinkedIn com refresh token) mesmo sem posts agendados.
	 */
	public static function daily() {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, user_id FROM ' . PFlow_DB::channels_table() . " WHERE status = 'active' AND network IN ('threads','linkedin','pinterest') AND token_expires_at > 0 AND token_expires_at < %d", time() + 15 * DAY_IN_SECONDS ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $row ) {
			PFlow_Tenant::run_as(
				(int) $row['user_id'],
				static function () use ( $row ) {
					$channel  = PFlow_Channels::get( (int) $row['id'] );
					$provider = $channel ? PFlow_Networks::provider_for( $channel['network'] ) : null;
					if ( $provider && $provider->is_configured() ) {
						self::fresh_tokens( $provider, $channel );
					}
				}
			);
		}
	}
}
