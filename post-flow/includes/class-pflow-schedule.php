<?php
/**
 * Horários da fila (como os "posting times" de uma agenda de redes sociais):
 * cada usuário define dias da semana e horários; "Adicionar à fila" coloca o
 * post no próximo horário livre. Também guarda o fuso horário do usuário.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Schedule {

	const META_SLOTS = 'pflow_slots';
	const META_TZ    = 'pflow_timezone';

	public static function defaults() {
		return array(
			'days'  => array( 1, 2, 3, 4, 5 ), // 0 = domingo ... 6 = sábado.
			'times' => array( '09:00', '12:30', '18:00' ),
		);
	}

	public static function get( $user_id ) {
		$saved = get_user_meta( $user_id, self::META_SLOTS, true );
		if ( ! is_array( $saved ) || ! isset( $saved['days'], $saved['times'] ) ) {
			return self::defaults();
		}
		return self::sanitize( $saved['days'], $saved['times'] );
	}

	public static function save( $user_id, $days, $times ) {
		$clean = self::sanitize( $days, $times );
		update_user_meta( $user_id, self::META_SLOTS, $clean );
		return $clean;
	}

	public static function sanitize( $days, $times ) {
		$days = array_values( array_unique( array_filter( array_map( 'intval', (array) $days ), static function ( $d ) {
			return $d >= 0 && $d <= 6;
		} ) ) );
		sort( $days );

		$clean = array();
		foreach ( (array) $times as $time ) {
			if ( preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', trim( (string) $time ), $m ) ) {
				$clean[] = sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
			}
		}
		$clean = array_values( array_unique( $clean ) );
		sort( $clean );

		return array( 'days' => $days, 'times' => $clean );
	}

	/**
	 * Fuso horário do usuário (padrão: o do site).
	 */
	public static function timezone( $user_id ) {
		$tz = (string) get_user_meta( $user_id, self::META_TZ, true );
		if ( '' !== $tz ) {
			try {
				return new DateTimeZone( $tz );
			} catch ( Exception $e ) {
				unset( $e );
			}
		}
		return wp_timezone();
	}

	public static function save_timezone( $user_id, $tz ) {
		$tz = (string) $tz;
		if ( in_array( $tz, timezone_identifiers_list(), true ) ) {
			update_user_meta( $user_id, self::META_TZ, $tz );
			return true;
		}
		return false;
	}

	/**
	 * Próximo horário livre da fila (UTC 'Y-m-d H:i:s'), ou '' se não houver
	 * horários configurados.
	 *
	 * @param int[]        $days  Dias da semana (0 = domingo).
	 * @param string[]     $times Horários 'HH:MM' no fuso $tz.
	 * @param DateTimeZone $tz    Fuso do usuário.
	 * @param int          $now   Timestamp atual.
	 * @param string[]     $taken Horários ocupados (UTC 'Y-m-d H:i').
	 */
	public static function next_slot( array $days, array $times, DateTimeZone $tz, $now, array $taken = array() ) {
		if ( ! $days || ! $times ) {
			return '';
		}
		$taken = array_flip( $taken );
		$utc   = new DateTimeZone( 'UTC' );
		$day   = ( new DateTimeImmutable( '@' . (int) $now ) )->setTimezone( $tz )->setTime( 0, 0 );

		for ( $i = 0; $i < 400; $i++ ) {
			$date = $day->modify( '+' . $i . ' days' );
			if ( ! in_array( (int) $date->format( 'w' ), $days, true ) ) {
				continue;
			}
			foreach ( $times as $time ) {
				list( $h, $m ) = array_map( 'intval', explode( ':', $time ) );
				$slot          = $date->setTime( $h, $m );
				if ( $slot->getTimestamp() <= $now + 60 ) {
					continue;
				}
				$key = $slot->setTimezone( $utc )->format( 'Y-m-d H:i' );
				if ( ! isset( $taken[ $key ] ) ) {
					return $key . ':00';
				}
			}
		}
		return '';
	}

	/**
	 * Próximo horário livre do usuário atual.
	 */
	public static function next_for_current() {
		$user  = PFlow_Tenant::id();
		$slots = self::get( $user );
		return self::next_slot( $slots['days'], $slots['times'], self::timezone( $user ), time(), PFlow_Posts::taken_slots() );
	}

	/**
	 * Converte data e hora locais do usuário para UTC ('Y-m-d H:i:s'), ou ''.
	 */
	public static function local_to_utc( $date, $time, DateTimeZone $tz ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) || ! preg_match( '/^\d{2}:\d{2}$/', (string) $time ) ) {
			return '';
		}
		try {
			$local = new DateTimeImmutable( $date . ' ' . $time . ':00', $tz );
		} catch ( Exception $e ) {
			return '';
		}
		return $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Data UTC do banco no fuso do usuário.
	 */
	public static function to_local( $utc, DateTimeZone $tz ) {
		if ( empty( $utc ) ) {
			return null;
		}
		return ( new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz );
	}

	/**
	 * "Hoje", "Amanhã" ou "qua., 12 de nov." para agrupar a fila.
	 */
	public static function day_label( DateTimeImmutable $date, DateTimeZone $tz ) {
		$today = new DateTimeImmutable( 'today', $tz );
		$diff  = (int) $today->diff( $date->setTime( 0, 0 ) )->format( '%r%a' );
		if ( 0 === $diff ) {
			return __( 'Hoje', 'post-flow' );
		}
		if ( 1 === $diff ) {
			return __( 'Amanhã', 'post-flow' );
		}
		$label = wp_date( 'l, j \d\e F', $date->getTimestamp(), $tz );
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( mb_substr( $label, 0, 1 ) ) . mb_substr( $label, 1 ) : ucfirst( $label );
	}

	public static function weekday_names() {
		return array(
			0 => __( 'Dom', 'post-flow' ),
			1 => __( 'Seg', 'post-flow' ),
			2 => __( 'Ter', 'post-flow' ),
			3 => __( 'Qua', 'post-flow' ),
			4 => __( 'Qui', 'post-flow' ),
			5 => __( 'Sex', 'post-flow' ),
			6 => __( 'Sáb', 'post-flow' ),
		);
	}
}
