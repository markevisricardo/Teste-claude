<?php
/**
 * Telas do painel: automações, logs e configurações.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		add_action( 'admin_post_cflow_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_cflow_save_automation', array( __CLASS__, 'save_automation' ) );
		add_action( 'admin_post_cflow_delete_automation', array( __CLASS__, 'delete_automation' ) );
		add_action( 'admin_post_cflow_toggle_automation', array( __CLASS__, 'toggle_automation' ) );
		add_action( 'admin_post_cflow_export_leads', array( __CLASS__, 'export_leads' ) );
		add_action( 'admin_post_cflow_delete_lead', array( __CLASS__, 'delete_lead' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Comment Flow', 'comment-flow' ),
			__( 'Comment Flow', 'comment-flow' ),
			self::CAP,
			'cflow',
			array( __CLASS__, 'page_automations' ),
			'dashicons-format-chat',
			58
		);
		add_submenu_page( 'cflow', __( 'Automações', 'comment-flow' ), __( 'Automações', 'comment-flow' ), self::CAP, 'cflow', array( __CLASS__, 'page_automations' ) );
		add_submenu_page( 'cflow', __( 'Nova automação', 'comment-flow' ), __( 'Nova automação', 'comment-flow' ), self::CAP, 'cflow-edit', array( __CLASS__, 'page_edit' ) );
		add_submenu_page( 'cflow', __( 'Leads', 'comment-flow' ), __( 'Leads', 'comment-flow' ), self::CAP, 'cflow-leads', array( __CLASS__, 'page_leads' ) );
		add_submenu_page( 'cflow', __( 'Logs', 'comment-flow' ), __( 'Logs', 'comment-flow' ), self::CAP, 'cflow-logs', array( __CLASS__, 'page_logs' ) );
		add_submenu_page( 'cflow', __( 'Configurações', 'comment-flow' ), __( 'Configurações', 'comment-flow' ), self::CAP, 'cflow-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'cflow' ) ) {
			return;
		}
		wp_enqueue_style( 'cflow-admin', CFLOW_URL . 'assets/admin.css', array(), CFLOW_VERSION );
	}

	public static function notices() {
		$notice = get_transient( 'cflow_notice' );
		if ( ! $notice ) {
			return;
		}
		delete_transient( 'cflow_notice' );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['message'] )
		);
	}

	/* ---------------------------------------------------------------------
	 * Páginas
	 * ------------------------------------------------------------------- */

	public static function page_automations() {
		$automations = CFlow_DB::get_automations();
		$stats       = CFlow_DB::automation_stats();
		include CFLOW_DIR . 'admin/views/automations.php';
	}

	public static function page_edit() {
		$id         = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$automation = $id ? CFlow_DB::get_automation( $id ) : null;
		if ( ! $automation ) {
			$id         = 0;
			$automation = array(
				'name'            => '',
				'status'          => 'active',
				'media_id'        => '',
				'keywords'        => '',
				'match_mode'      => 'word',
				'public_replies'  => "Te mandei no direct, {username}! 📩\nAcabei de te enviar no direct 😉\nConfere seu direct! 🚀",
				'dm_text'         => "Oi {username}! Aqui está o link que você pediu 👇",
				'dm_button_label' => '',
				'dm_button_url'   => '',
				'priority'        => 10,
				'capture_email'   => 0,
				'capture_phone'   => 0,
			);
			// Textos de captura vêm preenchidos com os padrões.
			$automation = array_merge( $automation, array_diff_key( CFlow_Conversation::defaults(), array_flip( array( 'invalid_email', 'invalid_phone' ) ) ) );
		}
		$media = self::recent_media();
		include CFLOW_DIR . 'admin/views/edit.php';
	}

	public static function page_leads() {
		// phpcs:disable WordPress.Security.NonceVerification
		$filters  = array(
			'automation_id' => isset( $_GET['automation_id'] ) ? absint( $_GET['automation_id'] ) : 0,
			'search'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		$per_page = 50;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$leads       = CFlow_Leads::query( $filters + array( 'limit' => $per_page, 'offset' => ( $paged - 1 ) * $per_page ) );
		$total       = CFlow_Leads::count( $filters );
		$automations = CFlow_DB::get_automations();
		$names       = wp_list_pluck( $automations, 'name', 'id' );
		include CFLOW_DIR . 'admin/views/leads.php';
	}

	public static function page_logs() {
		$per_page = 50;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$logs     = CFlow_DB::get_logs( $per_page, ( $paged - 1 ) * $per_page );
		$total    = CFlow_DB::count_logs();
		$names    = wp_list_pluck( CFlow_DB::get_automations(), 'name', 'id' );
		include CFLOW_DIR . 'admin/views/logs.php';
	}

	public static function page_settings() {
		CFlow_Settings::ensure_verify_token();
		$settings = CFlow_Settings::all();
		include CFLOW_DIR . 'admin/views/settings.php';
	}

	/**
	 * Posts recentes da conta conectada (cache de 10 minutos).
	 *
	 * @return array|WP_Error
	 */
	private static function recent_media() {
		if ( ! CFlow_Settings::is_connected() ) {
			return array();
		}
		$cached = get_transient( 'cflow_recent_media' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$api   = new CFlow_Instagram_API();
		$media = $api->recent_media( CFlow_Settings::get( 'ig_user_id' ), 30 );
		if ( ! is_wp_error( $media ) ) {
			set_transient( 'cflow_recent_media', $media, 10 * MINUTE_IN_SECONDS );
		}
		return $media;
	}

	/* ---------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------- */

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'comment-flow' ), 403 );
		}
		check_admin_referer( $action );
	}

	private static function redirect( $page, $type, $message, array $args = array() ) {
		set_transient( 'cflow_notice', array( 'type' => $type, 'message' => $message ), 60 );
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function save_settings() {
		self::guard( 'cflow_save_settings' );

		$values = array(
			'app_id'        => sanitize_text_field( wp_unslash( $_POST['app_id'] ?? '' ) ),
			'verify_token'  => sanitize_text_field( wp_unslash( $_POST['verify_token'] ?? '' ) ),
			'delay_seconds' => min( 300, absint( $_POST['delay_seconds'] ?? 5 ) ),
		);

		// Campo de segredo vazio = manter o valor atual.
		$secret = trim( (string) wp_unslash( $_POST['app_secret'] ?? '' ) );
		if ( '' !== $secret ) {
			$values['app_secret'] = $secret;
		}

		CFlow_Settings::update( $values );
		CFlow_Settings::ensure_verify_token();

		self::redirect( 'cflow-settings', 'success', __( 'Configurações salvas.', 'comment-flow' ) );
	}

	public static function save_automation() {
		self::guard( 'cflow_save_automation' );

		$id         = absint( $_POST['id'] ?? 0 );
		$media_id   = sanitize_text_field( wp_unslash( $_POST['media_id'] ?? '' ) );
		$manual     = sanitize_text_field( wp_unslash( $_POST['media_id_manual'] ?? '' ) );
		$match_mode = sanitize_key( $_POST['match_mode'] ?? 'word' );

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
		);

		if ( '' === $data['name'] ) {
			$data['name'] = __( 'Automação sem nome', 'comment-flow' );
		}
		if ( '' === trim( $data['public_replies'] ) && '' === trim( $data['dm_text'] ) ) {
			self::redirect( 'cflow-edit', 'error', __( 'Defina ao menos uma resposta pública ou uma mensagem de DM.', 'comment-flow' ), $id ? array( 'id' => $id ) : array() );
		}
		if ( ( '' === $data['dm_button_label'] ) !== ( '' === $data['dm_button_url'] ) ) {
			self::redirect( 'cflow-edit', 'error', __( 'O botão da DM precisa de texto e URL (ou deixe os dois vazios).', 'comment-flow' ), $id ? array( 'id' => $id ) : array() );
		}

		if ( ( $data['capture_email'] || $data['capture_phone'] ) && '' === trim( $data['dm_text'] ) ) {
			self::redirect( 'cflow-edit', 'error', __( 'Com a captura ligada, a mensagem no direct é a entrega final e não pode ficar vazia.', 'comment-flow' ), $id ? array( 'id' => $id ) : array() );
		}

		$id = CFlow_DB::save_automation( $data, $id );
		self::redirect( 'cflow-edit', 'success', __( 'Automação salva.', 'comment-flow' ), array( 'id' => $id ) );
	}

	public static function delete_automation() {
		self::guard( 'cflow_delete_automation' );
		CFlow_DB::delete_automation( absint( $_GET['id'] ?? 0 ) );
		self::redirect( 'cflow', 'success', __( 'Automação excluída.', 'comment-flow' ) );
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
		self::redirect( 'cflow', 'success', __( 'Status atualizado.', 'comment-flow' ) );
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
		self::redirect( 'cflow-leads', 'success', __( 'Lead excluído.', 'comment-flow' ) );
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
