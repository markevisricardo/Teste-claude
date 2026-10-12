<?php
/**
 * Fila de processamento. Usa o Action Scheduler quando disponível (WooCommerce
 * ou o plugin standalone) e cai para o WP-Cron caso contrário.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Queue {

	const HOOK         = 'cflow_process_comment';
	const MESSAGE_HOOK = 'cflow_process_message';
	const GROUP        = 'comment-flow';

	public static function init() {
		add_action( self::HOOK, array( 'CFlow_Processor', 'process' ), 10, 1 );
		add_action( self::MESSAGE_HOOK, array( 'CFlow_Conversation', 'handle' ), 10, 4 );
	}

	public static function uses_action_scheduler() {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Agenda o processamento de um log de comentário.
	 */
	public static function push( $log_id, $delay = 0 ) {
		self::schedule( self::HOOK, array( (int) $log_id ), $delay );
	}

	/**
	 * Agenda o processamento de uma mensagem recebida na DM.
	 */
	public static function push_message( $sender_id, $text, $mid, $attempt = 0, $delay = 0 ) {
		self::schedule( self::MESSAGE_HOOK, array( (string) $sender_id, (string) $text, (string) $mid, (int) $attempt ), $delay );
	}

	private static function schedule( $hook, array $args, $delay ) {
		$timestamp = time() + max( 0, (int) $delay );

		if ( self::uses_action_scheduler() ) {
			as_schedule_single_action( $timestamp, $hook, $args, self::GROUP );
			return;
		}

		wp_schedule_single_event( $timestamp, $hook, $args );
		if ( 0 === (int) $delay ) {
			spawn_cron();
		}
	}
}
