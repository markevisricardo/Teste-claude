<?php
/**
 * Fila de processamento. Usa o Action Scheduler quando disponível (WooCommerce
 * ou o plugin standalone) e cai para o WP-Cron caso contrário.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Queue {

	const HOOK  = 'cflow_process_comment';
	const GROUP = 'comment-flow';

	public static function init() {
		add_action( self::HOOK, array( 'CFlow_Processor', 'process' ), 10, 1 );
	}

	public static function uses_action_scheduler() {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Agenda o processamento de um log de comentário.
	 */
	public static function push( $log_id, $delay = 0 ) {
		$timestamp = time() + max( 0, (int) $delay );
		$args      = array( (int) $log_id );

		if ( self::uses_action_scheduler() ) {
			as_schedule_single_action( $timestamp, self::HOOK, $args, self::GROUP );
			return;
		}

		wp_schedule_single_event( $timestamp, self::HOOK, $args );
		if ( 0 === (int) $delay ) {
			spawn_cron();
		}
	}
}
