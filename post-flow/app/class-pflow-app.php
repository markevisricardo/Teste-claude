<?php
/**
 * Painel do usuário (front-end), exibido pelo shortcode [post_flow].
 *
 * Telas (?pf=): queue (padrão), compose, calendar, sent, channels, schedule,
 * account. As ações passam pelo admin-post.php e sempre agem sobre os dados
 * do usuário logado.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_App {

	const VIEWS = array( 'queue', 'compose', 'calendar', 'sent', 'channels', 'schedule', 'account' );

	/*
	 * Classes Tailwind reutilizadas pelas telas (ficam aqui para o Tailwind
	 * encontrá-las ao gerar o CSS).
	 */
	const CARD          = 'rounded-2xl border border-solid border-neutral-800 bg-neutral-950 p-6 shadow-xl shadow-black/40';
	const INPUT         = 'block w-full rounded-lg border border-solid border-neutral-800 bg-black px-3 py-2 text-sm leading-6 text-white transition-colors hover:border-neutral-700';
	const LABEL         = 'block text-sm font-medium text-white';
	const HELP          = 'mt-2 text-xs leading-5 text-neutral-500';
	const LINK          = 'font-medium text-violet-400 transition-colors hover:text-violet-300';
	const BTN_PRIMARY   = 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border-0 bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-600/25 transition-colors hover:bg-violet-500 hover:text-white disabled:cursor-not-allowed disabled:bg-neutral-800 disabled:text-neutral-500 disabled:shadow-none';
	const BTN_SECONDARY = 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-solid border-neutral-700 bg-transparent px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:border-violet-500 hover:text-violet-300';
	const BTN_DANGER    = 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-solid border-red-500/40 bg-transparent px-4 py-2.5 text-sm font-semibold text-red-300 transition-colors hover:border-red-400 hover:bg-red-500/10';
	const TOGGLE        = "relative h-6 w-11 shrink-0 rounded-full bg-neutral-800 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-transform after:content-[''] peer-checked:bg-violet-600 peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-violet-400 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-black";
	const TH            = 'whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500';
	const TD            = 'px-4 py-4 align-top text-sm text-neutral-300';
	const BTN_SMALL     = 'inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-solid border-neutral-800 bg-transparent px-2.5 py-1.5 text-xs font-medium text-neutral-300 transition-colors hover:border-violet-500 hover:text-white';

	public static function init() {
		$actions = array( 'save_post', 'delete_post', 'retry_post', 'publish_now', 'duplicate_post', 'save_schedule', 'save_account', 'delete_account' );
		foreach ( $actions as $action ) {
			add_action( 'admin_post_pflow_' . $action, array( __CLASS__, $action ) );
		}
		// Usuário excluído (pelo painel ou pelo wp-admin): apaga canais, posts e imagens.
		add_action( 'delete_user', array( 'PFlow_Posts', 'delete_user_data' ) );
	}

	/* ---------------------------------------------------------------------
	 * Rotas e avisos
	 * ------------------------------------------------------------------- */

	public static function base_url() {
		$url = (string) get_option( 'pflow_app_url', '' );
		return '' !== $url ? $url : home_url( '/' );
	}

	public static function url( $view = 'queue', array $args = array() ) {
		if ( 'queue' !== $view ) {
			$args = array( 'pf' => $view ) + $args;
		}
		return $args ? add_query_arg( array_map( 'rawurlencode', $args ), self::base_url() ) : self::base_url();
	}

	public static function current_view() {
		$view = isset( $_GET['pf'] ) ? sanitize_key( wp_unslash( $_GET['pf'] ) ) : 'queue'; // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $view, self::VIEWS, true ) ? $view : 'queue';
	}

	public static function notice( $user_id, $type, $message ) {
		set_transient( 'pflow_notice_' . (int) $user_id, array( 'type' => $type, 'message' => $message ), 5 * MINUTE_IN_SECONDS );
	}

	public static function flash() {
		$key    = 'pflow_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
		}
		return is_array( $notice ) ? $notice : null;
	}

	public static function action_url( $action, array $args = array() ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'pflow_' . $action ) + $args, admin_url( 'admin-post.php' ) ), 'pflow_' . $action );
	}

	/* ---------------------------------------------------------------------
	 * Renderização
	 * ------------------------------------------------------------------- */

	public static function render() {
		ob_start();
		echo '<div class="pflow-app"><div class="pflow-bg min-h-screen px-4 py-6 font-sans sm:px-8">';

		if ( ! is_user_logged_in() ) {
			PFlow_Auth::render_forms();
		} else {
			PFlow_Tenant::set( get_current_user_id() );
			$method = 'page_' . self::current_view();
			self::$method();
		}

		echo '</div></div>';
		return (string) ob_get_clean();
	}

	private static function view( $name, array $vars = array() ) {
		$tz = PFlow_Schedule::timezone( PFlow_Tenant::id() );
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		include PFLOW_DIR . 'app/views/' . $name . '.php';
	}

	private static function channels_by_id() {
		$out = array();
		foreach ( PFlow_Channels::all() as $channel ) {
			$out[ $channel['id'] ] = $channel;
		}
		return $out;
	}

	private static function page_queue() {
		$filter = isset( $_GET['channel'] ) ? absint( $_GET['channel'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$posts  = PFlow_Posts::query( array( 'statuses' => array( 'scheduled' ), 'limit' => 200 ) );
		$drafts = PFlow_Posts::query( array( 'statuses' => array( 'draft' ), 'order_by' => 'updated_at', 'order' => 'DESC', 'limit' => 50 ) );

		$counts = array();
		foreach ( $posts as $post ) {
			foreach ( $post['targets'] as $target ) {
				$counts[ $target['channel_id'] ] = ( $counts[ $target['channel_id'] ] ?? 0 ) + 1;
			}
		}
		$filtered = $posts;
		if ( $filter ) {
			$filtered = array_values(
				array_filter(
					$posts,
					static function ( $post ) use ( $filter ) {
						return in_array( $filter, array_map( 'intval', wp_list_pluck( $post['targets'], 'channel_id' ) ), true );
					}
				)
			);
		}

		self::view(
			'queue',
			array(
				'total'     => count( $posts ),
				'posts'     => $filtered,
				'drafts'    => $filter ? array() : $drafts,
				'channels'  => self::channels_by_id(),
				'counts'    => $counts,
				'filter'    => $filter,
				'next_slot' => PFlow_Schedule::next_for_current(),
			)
		);
	}

	private static function page_compose() {
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$post = $id ? PFlow_Posts::get( $id ) : null;
		if ( $post && ! PFlow_Posts::is_editable( $post ) ) {
			$post = null;
			$id   = 0;
		}
		$channels = self::channels_by_id();
		if ( ! $post ) {
			$id   = 0;
			$post = array(
				'id'           => 0,
				'content'      => '',
				'link'         => '',
				'media_ids'    => array(),
				'status'       => 'draft',
				'in_queue'     => 0,
				'scheduled_at' => null,
				'targets'      => array(),
				'channel_ids'  => '',
			);
			// Post novo: já vem com todos os canais ativos marcados (ou o do filtro).
			$preset   = isset( $_GET['channel'] ) ? absint( $_GET['channel'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			$selected = $preset && isset( $channels[ $preset ] ) ? array( $preset ) : array_keys(
				array_filter(
					$channels,
					static function ( $c ) {
						return 'active' === $c['status'];
					}
				)
			);
		} else {
			$selected = PFlow_Posts::channel_ids( $post );
		}

		self::view(
			'compose',
			array(
				'id'        => $id,
				'post'      => $post,
				'channels'  => $channels,
				'selected'  => $selected,
				'next_slot' => PFlow_Schedule::next_for_current(),
			)
		);
	}

	private static function page_calendar() {
		$tz    = PFlow_Schedule::timezone( PFlow_Tenant::id() );
		$week  = isset( $_GET['week'] ) ? sanitize_text_field( wp_unslash( $_GET['week'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$start = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $week ) ? new DateTimeImmutable( $week, $tz ) : new DateTimeImmutable( 'today', $tz );
		// Semana de segunda a domingo.
		$start = $start->modify( '-' . ( ( (int) $start->format( 'N' ) ) - 1 ) . ' days' )->setTime( 0, 0 );
		$end   = $start->modify( '+7 days' );
		$utc   = new DateTimeZone( 'UTC' );

		$posts = PFlow_Posts::query(
			array(
				'statuses' => array( 'scheduled', 'sent', 'partial', 'failed' ),
				'from'     => $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
				'to'       => $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
				'limit'    => 500,
			)
		);

		$days = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$days[ $start->modify( '+' . $i . ' days' )->format( 'Y-m-d' ) ] = array();
		}
		foreach ( $posts as $post ) {
			$when = PFlow_Schedule::to_local( $post['sent_at'] ? $post['sent_at'] : $post['scheduled_at'], $tz );
			if ( $when && isset( $days[ $when->format( 'Y-m-d' ) ] ) ) {
				$post['local']                            = $when;
				$days[ $when->format( 'Y-m-d' ) ][]       = $post;
			}
		}
		foreach ( $days as &$list ) {
			usort(
				$list,
				static function ( $a, $b ) {
					return $a['local'] <=> $b['local'];
				}
			);
		}
		unset( $list );

		self::view(
			'calendar',
			array(
				'start'    => $start,
				'days'     => $days,
				'channels' => self::channels_by_id(),
			)
		);
	}

	private static function page_sent() {
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$args  = array(
			'statuses' => array( 'sent', 'partial', 'failed' ),
			'order_by' => 'sent_at',
			'order'    => 'DESC',
			'limit'    => 30,
			'offset'   => ( $paged - 1 ) * 30,
		);
		self::view(
			'sent',
			array(
				'posts'    => PFlow_Posts::query( $args ),
				'total'    => PFlow_Posts::count( $args['statuses'] ),
				'paged'    => $paged,
				'per_page' => 30,
				'channels' => self::channels_by_id(),
			)
		);
	}

	private static function page_channels() {
		$picking = isset( $_GET['pick'] ) ? PFlow_OAuth::pending_accounts() : array(); // phpcs:ignore WordPress.Security.NonceVerification
		self::view(
			'channels',
			array(
				'channels' => PFlow_Channels::all(),
				'pending'  => $picking,
				'left'     => PFlow_Channels::remaining_slots(),
			)
		);
	}

	private static function page_schedule() {
		$user  = PFlow_Tenant::id();
		$slots = PFlow_Schedule::get( $user );
		$tz    = PFlow_Schedule::timezone( $user );

		// Prévia dos próximos horários livres.
		$preview = array();
		$taken   = PFlow_Posts::taken_slots();
		for ( $i = 0; $i < 5; $i++ ) {
			$next = PFlow_Schedule::next_slot( $slots['days'], $slots['times'], $tz, time(), $taken );
			if ( '' === $next ) {
				break;
			}
			$preview[] = $next;
			$taken[]   = substr( $next, 0, 16 );
		}

		self::view(
			'schedule',
			array(
				'slots'   => $slots,
				'preview' => $preview,
			)
		);
	}

	private static function page_account() {
		self::view( 'account', array( 'user' => wp_get_current_user() ) );
	}

	/* ---------------------------------------------------------------------
	 * Ações (admin-post.php)
	 * ------------------------------------------------------------------- */

	private static function guard( $action ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		check_admin_referer( $action );
		PFlow_Tenant::set( get_current_user_id() );
	}

	private static function redirect( $view, $type, $message, array $args = array() ) {
		self::notice( get_current_user_id(), $type, $message );
		wp_safe_redirect( self::url( $view, $args ) );
		exit;
	}

	public static function save_post() {
		self::guard( 'pflow_save_post' );

		$id       = absint( $_POST['id'] ?? 0 );
		$existing = $id ? PFlow_Posts::get( $id ) : null;
		if ( $id && ( ! $existing || ! PFlow_Posts::is_editable( $existing ) ) ) {
			self::redirect( 'queue', 'error', __( 'Este post não pode mais ser editado.', 'post-flow' ) );
		}

		$when = ! empty( $_POST['as_draft'] ) ? 'draft' : sanitize_key( $_POST['when'] ?? 'queue' );
		$when = in_array( $when, array( 'now', 'queue', 'schedule', 'draft' ), true ) ? $when : 'queue';

		// Canais: só os do próprio usuário.
		$mine     = self::channels_by_id();
		$channels = array_values( array_intersect( array_map( 'absint', (array) ( $_POST['channels'] ?? array() ) ), array_keys( $mine ) ) );

		// Imagens: as que ficaram + as novas.
		$keep   = PFlow_Media::owned( array_map( 'absint', (array) ( $_POST['keep_media'] ?? array() ) ) );
		$upload = PFlow_Media::handle_uploads( 'media' );
		$media  = array_slice( array_merge( $keep, $upload['ids'] ), 0, 10 );

		$data = array(
			'content'   => sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) ),
			'link'      => esc_url_raw( trim( (string) wp_unslash( $_POST['link'] ?? '' ) ), array( 'http', 'https' ) ),
			'media_ids' => $media,
			'in_queue'  => 'queue' === $when ? 1 : 0,
		);

		$errors = $upload['errors'];
		$due    = '';
		$tz     = PFlow_Schedule::timezone( PFlow_Tenant::id() );

		if ( 'now' === $when ) {
			$due = PFlow_DB::now();
		} elseif ( 'queue' === $when ) {
			// Mantém o horário ao editar um post que já estava na fila.
			$due = $existing && $existing['in_queue'] && 'scheduled' === $existing['status'] && $existing['scheduled_at'] > PFlow_DB::now() ? (string) $existing['scheduled_at'] : PFlow_Schedule::next_for_current();
			if ( '' === $due ) {
				$errors[] = __( 'Configure os horários da fila (em Horários) ou escolha uma data.', 'post-flow' );
			}
		} elseif ( 'schedule' === $when ) {
			$due = PFlow_Schedule::local_to_utc( sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) ), sanitize_text_field( wp_unslash( $_POST['time'] ?? '' ) ), $tz );
			if ( '' === $due ) {
				$errors[] = __( 'Escolha a data e o horário.', 'post-flow' );
			} elseif ( strtotime( $due . ' UTC' ) < time() - MINUTE_IN_SECONDS ) {
				$errors[] = __( 'Esse horário já passou. Escolha um horário no futuro.', 'post-flow' );
			}
		}

		if ( 'draft' !== $when ) {
			if ( ! $channels ) {
				$errors[] = __( 'Escolha ao menos um canal.', 'post-flow' );
			}
			foreach ( $channels as $channel_id ) {
				$network = $mine[ $channel_id ]['network'];
				$text    = PFlow_Networks::compose_text( $network, $data['content'], $data['link'], count( $media ) );
				$errors  = array_merge( $errors, PFlow_Networks::validate( $network, $text, count( $media ) ) );
			}
		} elseif ( '' === trim( $data['content'] ) && ! $media ) {
			$errors[] = __( 'O rascunho está vazio.', 'post-flow' );
		}

		$errors = array_values( array_unique( $errors ) );
		if ( $errors ) {
			// Nada se perde: guarda como rascunho e volta ao editor.
			if ( '' !== trim( $data['content'] ) || $media ) {
				$data['in_queue'] = 0;
				$id               = PFlow_Posts::save( $data, $channels, '', $id );
				/* translators: %s: lista de problemas */
				self::redirect( 'compose', 'error', sprintf( __( 'Guardamos como rascunho. Falta ajustar: %s', 'post-flow' ), implode( ' ', $errors ) ), array( 'id' => $id ) );
			}
			self::redirect( 'compose', 'error', implode( ' ', $errors ), $id ? array( 'id' => $id ) : array() );
		}

		$id = PFlow_Posts::save( $data, $channels, $due, $id );

		if ( 'draft' === $when ) {
			self::redirect( 'queue', 'success', __( 'Rascunho salvo.', 'post-flow' ) );
		}
		if ( 'now' === $when ) {
			PFlow_Publisher::run_post( $id );
			self::after_publish( $id );
		}
		$local = PFlow_Schedule::to_local( $due, $tz );
		/* translators: %s: data e hora */
		self::redirect( 'queue', 'success', sprintf( __( 'Post agendado para %s.', 'post-flow' ), wp_date( 'd/m \à\s H:i', $local->getTimestamp(), $tz ) ) );
	}

	/**
	 * Mensagem depois de publicar na hora.
	 */
	private static function after_publish( $id ) {
		$post   = PFlow_Posts::get( $id );
		$status = $post ? $post['status'] : 'failed';
		if ( 'sent' === $status ) {
			self::redirect( 'sent', 'success', __( 'Publicado em todos os canais!', 'post-flow' ) );
		}
		if ( 'scheduled' === $status ) {
			self::redirect( 'queue', 'warning', __( 'Alguns canais não responderam. Vamos tentar de novo em alguns minutos.', 'post-flow' ) );
		}
		self::redirect( 'sent', 'error', 'partial' === $status ? __( 'Publicado em parte. Veja abaixo os canais que falharam.', 'post-flow' ) : __( 'Não foi possível publicar. Veja o motivo abaixo.', 'post-flow' ) );
	}

	public static function publish_now() {
		self::guard( 'pflow_publish_now' );
		global $wpdb;
		$post = PFlow_Posts::get( absint( $_GET['id'] ?? 0 ) );
		if ( ! $post || 'scheduled' !== $post['status'] ) {
			self::redirect( 'queue', 'error', __( 'Post não encontrado.', 'post-flow' ) );
		}
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . PFlow_DB::targets_table() . " SET due_at = %s WHERE post_id = %d AND status = 'pending'", PFlow_DB::now(), $post['id'] ) ); // phpcs:ignore WordPress.DB
		$wpdb->update( PFlow_DB::posts_table(), array( 'scheduled_at' => PFlow_DB::now(), 'in_queue' => 0 ), array( 'id' => $post['id'] ) ); // phpcs:ignore WordPress.DB
		PFlow_Publisher::run_post( $post['id'] );
		self::after_publish( $post['id'] );
	}

	public static function delete_post() {
		self::guard( 'pflow_delete_post' );
		$ok   = PFlow_Posts::delete( absint( $_GET['id'] ?? 0 ) );
		$back = sanitize_key( $_GET['back'] ?? 'queue' );
		self::redirect( in_array( $back, self::VIEWS, true ) ? $back : 'queue', $ok ? 'success' : 'error', $ok ? __( 'Post excluído.', 'post-flow' ) : __( 'Este post está sendo publicado agora e não pode ser excluído.', 'post-flow' ) );
	}

	public static function retry_post() {
		self::guard( 'pflow_retry_post' );
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! PFlow_Posts::retry( $id ) ) {
			self::redirect( 'sent', 'error', __( 'Post não encontrado.', 'post-flow' ) );
		}
		PFlow_Publisher::run_post( $id );
		self::after_publish( $id );
	}

	/**
	 * Copia um post (inclusive publicado) para um rascunho novo.
	 */
	public static function duplicate_post() {
		self::guard( 'pflow_duplicate_post' );
		$post = PFlow_Posts::get( absint( $_GET['id'] ?? 0 ) );
		if ( ! $post ) {
			self::redirect( 'queue', 'error', __( 'Post não encontrado.', 'post-flow' ) );
		}
		$id = PFlow_Posts::save(
			array(
				'content'   => $post['content'],
				'link'      => $post['link'],
				'media_ids' => $post['media_ids'],
				'in_queue'  => 0,
			),
			PFlow_Posts::channel_ids( $post ),
			''
		);
		self::redirect( 'compose', 'success', __( 'Cópia criada como rascunho. Ajuste e agende.', 'post-flow' ), array( 'id' => $id ) );
	}

	public static function save_schedule() {
		self::guard( 'pflow_save_schedule' );
		$user = get_current_user_id();
		PFlow_Schedule::save( $user, (array) ( $_POST['days'] ?? array() ), array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['times'] ?? array() ) ) );
		PFlow_Schedule::save_timezone( $user, sanitize_text_field( wp_unslash( $_POST['timezone'] ?? '' ) ) );
		self::redirect( 'schedule', 'success', __( 'Horários salvos. Os próximos posts da fila usam os novos horários.', 'post-flow' ) );
	}

	public static function save_account() {
		self::guard( 'pflow_save_account' );
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		if ( '' !== $name ) {
			wp_update_user(
				array(
					'ID'           => get_current_user_id(),
					'display_name' => $name,
					'first_name'   => $name,
				)
			);
		}
		self::redirect( 'account', 'success', __( 'Dados salvos.', 'post-flow' ) );
	}

	/**
	 * Exclui a conta e todos os dados (canais, tokens, posts, imagens).
	 */
	public static function delete_account() {
		self::guard( 'pflow_delete_account' );
		$user = wp_get_current_user();
		if ( 'EXCLUIR' !== strtoupper( trim( (string) wp_unslash( $_POST['confirm'] ?? '' ) ) ) ) {
			self::redirect( 'account', 'error', __( 'Digite EXCLUIR para confirmar.', 'post-flow' ) );
		}
		if ( ! PFlow_Auth::is_member( $user ) ) {
			self::redirect( 'account', 'error', __( 'Administradores não podem se excluir por aqui.', 'post-flow' ) );
		}

		delete_transient( 'pflow_pick_' . $user->ID );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_logout();
		wp_delete_user( $user->ID );
		wp_safe_redirect( self::url() );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Helpers de view
	 * ------------------------------------------------------------------- */

	public static function badge( $status ) {
		$map  = array(
			'pending'    => array( __( 'Agendado', 'post-flow' ), 'bg-white/5 text-white ring-white/15' ),
			'scheduled'  => array( __( 'Agendado', 'post-flow' ), 'bg-white/5 text-white ring-white/15' ),
			'publishing' => array( __( 'Publicando', 'post-flow' ), 'bg-violet-500/10 text-violet-200 ring-violet-500/30' ),
			'sent'       => array( __( 'Publicado', 'post-flow' ), 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30' ),
			'partial'    => array( __( 'Em parte', 'post-flow' ), 'bg-amber-500/10 text-amber-200 ring-amber-500/30' ),
			'failed'     => array( __( 'Falhou', 'post-flow' ), 'bg-red-500/10 text-red-300 ring-red-500/30' ),
			'draft'      => array( __( 'Rascunho', 'post-flow' ), 'bg-neutral-800/60 text-neutral-400 ring-neutral-700' ),
			'active'     => array( __( 'Conectado', 'post-flow' ), 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30' ),
			'expired'    => array( __( 'Desconectado', 'post-flow' ), 'bg-red-500/10 text-red-300 ring-red-500/30' ),
		);
		$item = isset( $map[ $status ] ) ? $map[ $status ] : array( $status, 'bg-neutral-800/60 text-neutral-400 ring-neutral-700' );

		return sprintf(
			'<span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset %1$s"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>%2$s</span>',
			esc_attr( $item[1] ),
			esc_html( $item[0] )
		);
	}

	/**
	 * Canal (ou um resumo) a partir de um destino.
	 */
	public static function target_channel( array $target, array $channels ) {
		if ( isset( $channels[ $target['channel_id'] ] ) ) {
			return $channels[ $target['channel_id'] ];
		}
		return array(
			'network'    => '' !== $target['network'] ? $target['network'] : 'x',
			'name'       => '' !== $target['name'] ? $target['name'] : __( 'Canal removido', 'post-flow' ),
			'username'   => $target['username'],
			'avatar_url' => $target['avatar_url'],
		);
	}

	public static function excerpt( $text, $length = 220 ) {
		$text = trim( (string) $text );
		return mb_strlen( $text ) > $length ? rtrim( mb_substr( $text, 0, $length ) ) . '…' : $text;
	}
}
