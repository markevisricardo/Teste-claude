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
		add_action( self::HOOK, array( __CLASS__, 'run_comment' ), 10, 1 );
		add_action( self::MESSAGE_HOOK, array( __CLASS__, 'run_message' ), 10, 5 );
	}

	/**
	 * As tarefas trocam o inquilino; run_as devolve o anterior ao terminar,
	 * para a próxima tarefa do mesmo processo começar limpa.
	 */
	public static function run_comment( $log_id ) {
		return CFlow_Tenant::run_as(
			0,
			static function () use ( $log_id ) {
				return CFlow_Processor::process( $log_id );
			}
		);
	}

	public static function run_message( $user_id, $sender_id = '', $text = '', $mid = '', $attempt = 0 ) {
		return CFlow_Tenant::run_as(
			(int) $user_id,
			static function () use ( $user_id, $sender_id, $text, $mid, $attempt ) {
				return CFlow_Conversation::handle( $user_id, $sender_id, $text, $mid, $attempt );
			}
		);
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
	 * Agenda o processamento de uma mensagem recebida na DM do inquilino atual.
	 */
	public static function push_message( $sender_id, $text, $mid, $attempt = 0, $delay = 0 ) {
		self::schedule( self::MESSAGE_HOOK, array( CFlow_Tenant::id(), (string) $sender_id, (string) $text, (string) $mid, (int) $attempt ), $delay );
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
