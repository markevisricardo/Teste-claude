<?php
/**
 * Painel do hub: licenças, entregas e configurações do app central.
 */

defined( 'ABSPATH' ) || exit;

class CFHub_Admin {

	const CAP = 'manage_options';

	/* Classes Tailwind (as mesmas do plugin do cliente). */
	const CARD          = 'rounded-2xl border border-solid border-neutral-800 bg-neutral-950 p-6 shadow-xl shadow-black/40';
	const INPUT         = 'block w-full rounded-lg border border-solid border-neutral-800 bg-black px-3 py-2 text-sm leading-6 text-white transition-colors hover:border-neutral-700';
	const LABEL         = 'block text-sm font-medium text-white';
	const HELP          = 'mt-2 text-xs leading-5 text-neutral-500';
	const LINK          = 'font-medium text-blue-400 transition-colors hover:text-blue-300';
	const BTN_PRIMARY   = 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border-0 bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-500 hover:text-white disabled:cursor-not-allowed disabled:bg-neutral-800 disabled:text-neutral-500 disabled:shadow-none';
	const BTN_SECONDARY = 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-solid border-neutral-700 bg-transparent px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:border-blue-500 hover:text-blue-300';
	const TH            = 'whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500';
	const TD            = 'px-4 py-4 align-top text-sm text-neutral-300';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );

		foreach ( array( 'create_license', 'license_action', 'save_settings' ) as $action ) {
			add_action( 'admin_post_cfhub_' . $action, array( __CLASS__, $action ) );
		}
	}

	public static function menu() {
		add_menu_page( 'Comment Flow Hub', 'Comment Flow Hub', self::CAP, 'cfhub', array( __CLASS__, 'page_licenses' ), 'dashicons-networking', 58 );
		add_submenu_page( 'cfhub', __( 'Licenças', 'comment-flow-hub' ), __( 'Licenças', 'comment-flow-hub' ), self::CAP, 'cfhub', array( __CLASS__, 'page_licenses' ) );
		add_submenu_page( 'cfhub', __( 'Entregas', 'comment-flow-hub' ), __( 'Entregas', 'comment-flow-hub' ), self::CAP, 'cfhub-deliveries', array( __CLASS__, 'page_deliveries' ) );
		add_submenu_page( 'cfhub', __( 'Configurações', 'comment-flow-hub' ), __( 'Configurações', 'comment-flow-hub' ), self::CAP, 'cfhub-settings', array( __CLASS__, 'page_settings' ) );
	}

	private static function is_hub_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return 0 === strpos( $page, 'cfhub' );
	}

	public static function assets() {
		if ( self::is_hub_screen() ) {
			wp_enqueue_style( 'cfhub-admin', CFHUB_URL . 'assets/admin.css', array(), CFHUB_VERSION );
		}
	}

	public static function body_class( $classes ) {
		return self::is_hub_screen() ? $classes . ' cflow-dark' : $classes;
	}

	/* ---------------------------------------------------------------------
	 * Páginas
	 * ------------------------------------------------------------------- */

	public static function page_licenses() {
		$licenses = CFHub_Licenses::all();
		$new_key  = get_transient( 'cfhub_new_key' );
		delete_transient( 'cfhub_new_key' );
		include CFHUB_DIR . 'admin/views/licenses.php';
	}

	public static function page_deliveries() {
		$deliveries = CFHub_Forwarder::recent( 100 );
		$counts     = CFHub_Forwarder::counts();
		include CFHUB_DIR . 'admin/views/deliveries.php';
	}

	public static function page_settings() {
		CFHub_Settings::ensure_verify_token();
		$settings = CFHub_Settings::all();
		include CFHUB_DIR . 'admin/views/settings.php';
	}

	/* ---------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------- */

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'comment-flow-hub' ), 403 );
		}
		check_admin_referer( $action );
	}

	private static function redirect( $page, $type, $message ) {
		set_transient( 'cfhub_notice', array( 'type' => $type, 'message' => $message ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}

	public static function flash() {
		$notice = get_transient( 'cfhub_notice' );
		if ( $notice ) {
			delete_transient( 'cfhub_notice' );
		}
		return is_array( $notice ) ? $notice : null;
	}

	public static function create_license() {
		self::guard( 'cfhub_create_license' );
		$name = sanitize_text_field( wp_unslash( $_POST['client_name'] ?? '' ) );
		if ( '' === $name ) {
			self::redirect( 'cfhub', 'error', __( 'Informe o nome do cliente.', 'comment-flow-hub' ) );
		}
		$license = CFHub_Licenses::get( CFHub_Licenses::create( $name ) );
		set_transient( 'cfhub_new_key', $license['license_key'], 60 );
		/* translators: %s: cliente */
		self::redirect( 'cfhub', 'success', sprintf( __( 'Licença criada para %s.', 'comment-flow-hub' ), $name ) );
	}

	public static function license_action() {
		self::guard( 'cfhub_license_action' );
		$id      = absint( $_GET['id'] ?? 0 );
		$do      = sanitize_key( $_GET['do'] ?? '' );
		$license = CFHub_Licenses::get( $id );
		if ( ! $license ) {
			self::redirect( 'cfhub', 'error', __( 'Licença não encontrada.', 'comment-flow-hub' ) );
		}

		switch ( $do ) {
			case 'revoke':
				CFHub_Licenses::update( $id, array( 'status' => 'revoked' ) );
				self::redirect( 'cfhub', 'success', __( 'Licença desativada: o site deixa de receber eventos.', 'comment-flow-hub' ) );
				break;
			case 'restore':
				CFHub_Licenses::update( $id, array( 'status' => 'active' ) );
				self::redirect( 'cfhub', 'success', __( 'Licença reativada.', 'comment-flow-hub' ) );
				break;
			case 'release':
				CFHub_Licenses::release( $id );
				self::redirect( 'cfhub', 'success', __( 'Site liberado: a chave pode ser ativada em outro endereço.', 'comment-flow-hub' ) );
				break;
			case 'delete':
				CFHub_Licenses::delete( $id );
				self::redirect( 'cfhub', 'success', __( 'Licença excluída.', 'comment-flow-hub' ) );
				break;
		}
		self::redirect( 'cfhub', 'error', __( 'Ação inválida.', 'comment-flow-hub' ) );
	}

	public static function save_settings() {
		self::guard( 'cfhub_save_settings' );
		$values = array( 'app_id' => sanitize_text_field( wp_unslash( $_POST['app_id'] ?? '' ) ) );
		$secret = trim( (string) wp_unslash( $_POST['app_secret'] ?? '' ) );
		if ( '' !== $secret ) {
			$values['app_secret'] = $secret;
		}
		CFHub_Settings::update( $values );
		self::redirect( 'cfhub-settings', 'success', __( 'Configurações salvas.', 'comment-flow-hub' ) );
	}

	/* ---------------------------------------------------------------------
	 * Helpers de view
	 * ------------------------------------------------------------------- */

	public static function action_url( $id, $do ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cfhub_license_action&id=' . (int) $id . '&do=' . $do ), 'cfhub_license_action' );
	}

	public static function badge( $tone, $label ) {
		$tones = array(
			'blue'  => 'bg-blue-500/10 text-blue-300 ring-blue-500/30',
			'red'   => 'bg-red-500/10 text-red-300 ring-red-500/30',
			'white' => 'bg-white/5 text-white ring-white/15',
			'gray'  => 'bg-neutral-800/60 text-neutral-400 ring-neutral-700',
		);
		return sprintf(
			'<span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset %1$s"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>%2$s</span>',
			esc_attr( isset( $tones[ $tone ] ) ? $tones[ $tone ] : $tones['gray'] ),
			esc_html( $label )
		);
	}
}
