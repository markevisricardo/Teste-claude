<?php
/**
 * Inquilino atual: o usuário dono dos dados que estão sendo lidos ou
 * gravados. Toda consulta às tabelas do plugin filtra por ele.
 *
 * É definido em cada ponto de entrada:
 * - telas do painel: o usuário logado;
 * - webhook: o dono da conta do Instagram que recebeu o evento;
 * - fila: o user_id gravado na tarefa.
 * Sem inquilino (0), as consultas não retornam nada.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Tenant {

	private static $user_id = 0;

	public static function set( $user_id ) {
		self::$user_id = max( 0, (int) $user_id );
	}

	public static function id() {
		return self::$user_id;
	}

	/**
	 * Executa $callback com outro inquilino e restaura o anterior.
	 */
	public static function run_as( $user_id, $callback ) {
		$previous = self::$user_id;
		self::set( $user_id );
		try {
			return call_user_func( $callback );
		} finally {
			self::$user_id = $previous;
		}
	}
}
