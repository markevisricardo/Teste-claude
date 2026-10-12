<?php
/**
 * Painel do usuário (front-end): exibido pelo shortcode [comment_flow].
 *
 * Telas: automations (padrão), edit, leads, logs, account; escolhidas pelo
 * parâmetro ?cf= na página do shortcode. As ações (salvar, excluir, exportar)
 * passam pelo admin-post.php e sempre agem sobre os dados do usuário logado.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_App {

	const VIEWS = array( 'automations', 'edit', 'leads', 'logs', 'account' );

	/*
	 * Classes Tailwind reutilizadas pelas telas (ficam aqui para o Tailwind
	 * encontrá-las ao gerar o CSS).
	 */
	const CARD          = 'rounded-2xl border border-solid border-neutral-800 bg-neutral-950 p-6 shadow-xl shadow-black/40';
	const INPUT         = 'block w-full rounded-lg border border-solid border-neutral-800 bg-black px-3 py-2 text-sm leading-6 text-white transition-colors hover:border-neutral-700';
	const LABEL         = 'block text-sm font-medium text-white';
	const HELP          = 'mt-2 text-xs leading-5 text-neutral-500';
	const LINK          = 'font-medium text-blue-400 transition-colors hover:text-blue-300';
	const BTN_PRIMARY   = 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border-0 bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-500 hover:text-white disabled:cursor-not-allowed disabled:bg-neutral-800 disabled:text-neutral-500 disabled:shadow-none';
	const BTN_SECONDARY = 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-solid border-neutral-700 bg-transparent px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:border-blue-500 hover:text-blue-300';
	const TOGGLE        = "relative h-6 w-11 shrink-0 rounded-full bg-neutral-800 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-transform after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-blue-400 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-black";
	const TH            = 'whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500';
	const TD            = 'px-4 py-4 align-top text-sm text-neutral-300';

	public static function init() {
		foreach ( array( 'save_preferences', 'save_automation', 'delete_automation', 'toggle_automation', 'export_leads', 'delete_lead' ) as $action ) {
			add_action( 'admin_post_cflow_' . $action, array( __CLASS__, $action ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Rotas e avisos
	 * ------------------------------------------------------------------- */

	/**
	 * Endereço da página do painel (a que contém o shortcode).
	 */
	public static function base_url() {
		$url = (string) get_option( 'cflow_app_url', '' );
		return '' !== $url ? $url : home_url( '/' );
	}

	public static function url( $view = 'automations', array $args = array() ) {
		if ( 'automations' !== $view ) {
			$args = array( 'cf' => $view ) + $args;
		}
		return $args ? add_query_arg( array_map( 'rawurlencode', $args ), self::base_url() ) : self::base_url();
	}

	public static function current_view() {
		$view = isset( $_GET['cf'] ) ? sanitize_key( wp_unslash( $_GET['cf'] ) ) : 'automations'; // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $view, self::VIEWS, true ) ? $view : 'automations';
	}

	public static function notice( $user_id, $type, $message ) {
		set_transient( 'cflow_notice_' . (int) $user_id, array( 'type' => $type, 'message' => $message ), 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Aviso pendente do usuário logado, consumido uma única vez.
	 */
	public static function flash() {
		$key    = 'cflow_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
		}
		return is_array( $notice ) ? $notice : null;
	}

	/* ---------------------------------------------------------------------
	 * Renderização
	 * ------------------------------------------------------------------- */

	/**
	 * HTML do painel (ou da tela de entrar/criar conta).
	 */
	public static function render() {
		ob_start();
		echo '<div class="cflow-app"><div class="cflow-bg min-h-screen px-4 py-6 font-sans sm:px-8">';

		if ( ! is_user_logged_in() ) {
			CFlow_Auth::render_forms();
		} else {
			CFlow_Tenant::set( get_current_user_id() );
			$method = 'page_' . self::current_view();
			self::$method();
		}

		echo '</div></div>';
		return (string) ob_get_clean();
	}

	private static function view( $name, array $vars = array() ) {
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		include CFLOW_DIR . 'app/views/' . $name . '.php';
	}

	private static function page_automations() {
		self::view(
			'automations',
			array(
				'automations' => CFlow_DB::get_automations(),
				'stats'       => CFlow_DB::automation_stats(),
			)
		);
	}

	private static function page_edit() {
		$id         = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$automation = $id ? CFlow_DB::get_automation( $id ) : null;
		if ( ! $automation ) {
			$id         = 0;
			$automation = array(
				'name'            => '',
				'status'          => 'active',
				'trigger_type'    => self::trigger_from_request(),
				'media_id'        => '',
				'keywords'        => '',
				'match_mode'      => 'word',
				'public_replies'  => "Te mandei no direct, {username}! 📩\nAcabei de te enviar no direct 😉\nConfere seu direct! 🚀",
				'dm_text'         => 'Oi {username}! Aqui está o link que você pediu 👇',
				'dm_button_label' => '',
				'dm_button_url'   => '',
				'priority'        => 10,
				'capture_email'   => 0,
				'capture_phone'   => 0,
				'require_follow'  => 0,
			);
			// Textos de captura vêm preenchidos com os padrões.
			$automation = array_merge( $automation, array_diff_key( CFlow_Conversation::defaults(), array_flip( array( 'invalid_email', 'invalid_phone' ) ) ) );
		}
		self::view(
			'edit',
			array(
				'id'         => $id,
				'automation' => $automation,
				'media'      => self::recent_media(),
			)
		);
	}

	private static function page_leads() {
		// phpcs:disable WordPress.Security.NonceVerification
		$filters = array(
			'automation_id' => isset( $_GET['automation_id'] ) ? absint( $_GET['automation_id'] ) : 0,
			'search'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$automations = CFlow_DB::get_automations();
		self::view(
			'leads',
			array(
				'filters'     => $filters,
				'per_page'    => 50,
				'paged'       => $paged,
				'leads'       => CFlow_Leads::query( $filters + array( 'limit' => 50, 'offset' => ( $paged - 1 ) * 50 ) ),
				'total'       => CFlow_Leads::count( $filters ),
				'automations' => $automations,
				'names'       => wp_list_pluck( $automations, 'name', 'id' ),
			)
		);
	}

	private static function page_logs() {
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		self::view(
			'logs',
			array(
				'per_page' => 50,
				'paged'    => $paged,
				'logs'     => CFlow_DB::get_logs( 50, ( $paged - 1 ) * 50 ),
				'total'    => CFlow_DB::count_logs(),
				'names'    => wp_list_pluck( CFlow_DB::get_automations(), 'name', 'id' ),
			)
		);
	}

	private static function page_account() {
		self::view(
			'account',
			array(
				'account' => CFlow_Accounts::current(),
				'user'    => wp_get_current_user(),
			)
		);
	}

	/**
	 * Tipos de gatilho de uma automação.
	 */
	public static function triggers() {
		return array(
			'comment'    => __( 'Comentário em post', 'comment-flow' ),
			'dm_keyword' => __( 'Palavra-chave na DM', 'comment-flow' ),
			'dm_welcome' => __( 'Boas-vindas (primeira DM)', 'comment-flow' ),
		);
	}

	private static function trigger_from_request() {
		$trigger = isset( $_GET['trigger'] ) ? sanitize_key( wp_unslash( $_GET['trigger'] ) ) : 'comment'; // phpcs:ignore WordPress.Security.NonceVerification
		return array_key_exists( $trigger, self::triggers() ) ? $trigger : 'comment';
	}

	/**
	 * Posts recentes da conta do usuário (cache de 10 minutos).
	 *
	 * @return array|WP_Error
	 */
	private static function recent_media() {
		$account = CFlow_Accounts::current();
		if ( '' === $account['access_token'] || '' === $account['ig_user_id'] ) {
			return array();
		}
		$key    = 'cflow_recent_media_' . CFlow_Tenant::id();
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$api   = new CFlow_Instagram_API( $account['access_token'] );
		$media = $api->recent_media( $account['ig_user_id'], 30 );
		if ( ! is_wp_error( $media ) ) {
			set_transient( $key, $media, 10 * MINUTE_IN_SECONDS );
		}
		return $media;
	}

	/* ---------------------------------------------------------------------
	 * Ações (admin-post.php)
	 * ------------------------------------------------------------------- */

	/**
	 * Exige usuário logado e nonce válido; a partir daqui tudo é dele.
	 */
	private static function guard( $action ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		check_admin_referer( $action );
		CFlow_Tenant::set( get_current_user_id() );
	}

	private static function redirect( $view, $type, $message, array $args = array() ) {
		self::notice( get_current_user_id(), $type, $message );
		wp_safe_redirect( self::url( $view, $args ) );
		exit;
	}

	public static function save_preferences() {
		self::guard( 'cflow_save_preferences' );
		CFlow_Accounts::save_preferences( get_current_user_id(), min( 300, absint( $_POST['delay_seconds'] ?? 5 ) ) );
		self::redirect( 'account', 'success', __( 'Preferências salvas.', 'comment-flow' ) );
	}

	public static function save_automation() {
		self::guard( 'cflow_save_automation' );

		$id         = absint( $_POST['id'] ?? 0 );
		$media_id   = sanitize_text_field( wp_unslash( $_POST['media_id'] ?? '' ) );
		$manual     = sanitize_text_field( wp_unslash( $_POST['media_id_manual'] ?? '' ) );
		$match_mode = sanitize_key( $_POST['match_mode'] ?? 'word' );
		$trigger    = sanitize_key( $_POST['trigger_type'] ?? 'comment' );
		$trigger    = array_key_exists( $trigger, self::triggers() ) ? $trigger : 'comment';
		$back       = $id ? array( 'id' => $id ) : array( 'trigger' => $trigger );

		// Só edita automações do próprio usuário.
		if ( $id && ! CFlow_DB::get_automation( $id ) ) {
			self::redirect( 'automations', 'error', __( 'Automação não encontrada.', 'comment-flow' ) );
		}

		$data = array(
			'name'            => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'status'          => isset( $_POST['status'] ) ? 'active' : 'paused',
			'media_id'        => 'manual' === $media_id ? preg_replace( '/[^0-9_]/', '', $manual ) : preg_replace( '/[^0-9_]/', '', $media_id ),
			'keywords'        => sanitize_textarea_field( wp_unslash( $_POST['keywords'] ?? '' ) ),
			'match_mode'      => in_array( $match_mode, array( 'contains', 'word', 'exact' ), true ) ? $match_mode : 'word',
			'public_replies'  => sanitize_textarea_field( wp_unslash( $_POST['public_replies'] ?? '' ) ),
			'dm_text'         => sanitize_textarea_field( wp_unslash( $_POST['dm_text'] ?? '' ) ),
			'dm_button_label' => mb_substr( sanitize_text_field( wp_unslash( $_POST['dm_button_label'] ?? '' ) ), 0, 20 ),
			'dm_button_url'   => esc_url_raw( wp_unslash( $_POST['dm_button_url'] ?? '' ), array( 'https', 'http' ) ),
			'priority'        => (int) ( $_POST['priority'] ?? 10 ),
			'capture_email'   => isset( $_POST['capture_email'] ) ? 1 : 0,
			'capture_phone'   => isset( $_POST['capture_phone'] ) ? 1 : 0,
			'optin_text'      => sanitize_textarea_field( wp_unslash( $_POST['optin_text'] ?? '' ) ),
			'optin_button'    => mb_substr( sanitize_text_field( wp_unslash( $_POST['optin_button'] ?? '' ) ), 0, 20 ),
			'email_question'  => sanitize_textarea_field( wp_unslash( $_POST['email_question'] ?? '' ) ),
			'phone_question'  => sanitize_textarea_field( wp_unslash( $_POST['phone_question'] ?? '' ) ),
			'trigger_type'    => $trigger,
			'require_follow'  => isset( $_POST['require_follow'] ) ? 1 : 0,
			'follow_prompt'   => sanitize_textarea_field( wp_unslash( $_POST['follow_prompt'] ?? '' ) ),
			'follow_button'   => mb_substr( sanitize_text_field( wp_unslash( $_POST['follow_button'] ?? '' ) ), 0, 20 ),
			'follow_retry'    => sanitize_textarea_field( wp_unslash( $_POST['follow_retry'] ?? '' ) ),
		);

		// Gatilhos de DM não têm post nem resposta pública.
		if ( 'comment' !== $trigger ) {
			$data['media_id']       = '';
			$data['public_replies'] = '';
		}
		if ( 'dm_welcome' === $trigger ) {
			$data['keywords'] = '';
		}

		if ( '' === $data['name'] ) {
			$data['name'] = __( 'Automação sem nome', 'comment-flow' );
		}
		if ( '' === trim( $data['public_replies'] ) && '' === trim( $data['dm_text'] ) ) {
			self::redirect( 'edit', 'error', __( 'Defina ao menos uma resposta pública ou uma mensagem de DM.', 'comment-flow' ), $back );
		}
		if ( ( '' === $data['dm_button_label'] ) !== ( '' === $data['dm_button_url'] ) ) {
			self::redirect( 'edit', 'error', __( 'O botão da DM precisa de texto e URL (ou deixe os dois vazios).', 'comment-flow' ), $back );
		}
		if ( ( 'comment' !== $trigger || CFlow_Processor::captures( $data ) ) && '' === trim( $data['dm_text'] ) ) {
			self::redirect( 'edit', 'error', __( 'A mensagem no direct (entrega final) não pode ficar vazia.', 'comment-flow' ), $back );
		}
		if ( 'dm_keyword' === $trigger && '' === trim( $data['keywords'] ) ) {
			self::redirect( 'edit', 'error', __( 'Informe ao menos uma palavra-chave para o gatilho de DM.', 'comment-flow' ), $back );
		}

		$id = CFlow_DB::save_automation( $data, $id );
		self::redirect( 'edit', 'success', __( 'Automação salva.', 'comment-flow' ), array( 'id' => $id ) );
	}

	public static function delete_automation() {
		self::guard( 'cflow_delete_automation' );
		CFlow_DB::delete_automation( absint( $_GET['id'] ?? 0 ) );
		self::redirect( 'automations', 'success', __( 'Automação excluída.', 'comment-flow' ) );
	}

	public static function toggle_automation() {
		self::guard( 'cflow_toggle_automation' );
		$automation = CFlow_DB::get_automation( absint( $_GET['id'] ?? 0 ) );
		if ( $automation ) {
			CFlow_DB::save_automation(
				array( 'status' => 'active' === $automation['status'] ? 'paused' : 'active' ),
				$automation['id']
			);
		}
		self::redirect( 'automations', 'success', __( 'Status atualizado.', 'comment-flow' ) );
	}

	public static function export_leads() {
		self::guard( 'cflow_export_leads' );
		CFlow_Leads::stream_csv(
			absint( $_GET['automation_id'] ?? 0 ),
			sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) )
		);
		exit;
	}

	public static function delete_lead() {
		self::guard( 'cflow_delete_lead' );
		CFlow_Leads::delete( absint( $_GET['id'] ?? 0 ) );
		self::redirect( 'leads', 'success', __( 'Lead excluído.', 'comment-flow' ) );
	}

	/* ---------------------------------------------------------------------
	 * Helpers de view
	 * ------------------------------------------------------------------- */

	public static function action_url( $action, $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . '&id=' . (int) $id ), $action );
	}

	public static function export_url( $automation_id = 0, $search = '' ) {
		$args = array( 'action' => 'cflow_export_leads' );
		if ( $automation_id ) {
			$args['automation_id'] = (int) $automation_id;
		}
		if ( '' !== $search ) {
			$args['s'] = rawurlencode( $search );
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'cflow_export_leads' );
	}

	/**
	 * Selo de status (Tailwind).
	 */
	public static function badge( $status, $label ) {
		$tones = array(
			'active'          => 'bg-blue-500/10 text-blue-300 ring-blue-500/30',
			'done'            => 'bg-blue-500/10 text-blue-300 ring-blue-500/30',
			'failed'          => 'bg-red-500/10 text-red-300 ring-red-500/30',
			'queued'          => 'bg-white/5 text-white ring-white/15',
			'processing'      => 'bg-white/5 text-white ring-white/15',
			'retry'           => 'bg-white/5 text-white ring-white/15',
			'awaiting_optin'  => 'bg-white/5 text-white ring-white/15',
			'awaiting_email'  => 'bg-white/5 text-white ring-white/15',
			'awaiting_phone'  => 'bg-white/5 text-white ring-white/15',
			'awaiting_follow' => 'bg-white/5 text-white ring-white/15',
		);
		$tone  = isset( $tones[ $status ] ) ? $tones[ $status ] : 'bg-neutral-800/60 text-neutral-400 ring-neutral-700';

		return sprintf(
			'<span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset %1$s"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>%2$s</span>',
			esc_attr( $tone ),
			esc_html( $label )
		);
	}

	public static function status_label( $status ) {
		$labels = array(
			'queued'     => __( 'Na fila', 'comment-flow' ),
			'processing' => __( 'Processando', 'comment-flow' ),
			'retry'      => __( 'Nova tentativa', 'comment-flow' ),
			'done'       => __( 'Concluído', 'comment-flow' ),
			'no_match'   => __( 'Sem automação', 'comment-flow' ),
			'failed'     => __( 'Falhou', 'comment-flow' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
