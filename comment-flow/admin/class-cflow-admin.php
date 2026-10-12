<?php
/**
 * wp-admin (só administradores): configuração da plataforma e usuários.
 * O painel de cada usuário fica no front-end, na página do shortcode.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_post_cflow_save_settings', array( __CLASS__, 'save_settings' ) );
	}

	public static function menu() {
		add_menu_page( 'Comment Flow', 'Comment Flow', self::CAP, 'cflow', array( __CLASS__, 'page_settings' ), 'dashicons-format-chat', 58 );
		add_submenu_page( 'cflow', __( 'Configurações', 'comment-flow' ), __( 'Configurações', 'comment-flow' ), self::CAP, 'cflow', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'cflow', __( 'Usuários', 'comment-flow' ), __( 'Usuários', 'comment-flow' ), self::CAP, 'cflow-users', array( __CLASS__, 'page_users' ) );
	}

	private static function is_plugin_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return 0 === strpos( $page, 'cflow' );
	}

	public static function assets() {
		if ( self::is_plugin_screen() ) {
			wp_enqueue_style( 'cflow-admin', CFlow_Frontend::css_url(), array(), CFLOW_VERSION );
		}
	}

	public static function body_class( $classes ) {
		return self::is_plugin_screen() ? $classes . ' cflow-dark' : $classes;
	}

	public static function page_settings() {
		CFlow_Settings::ensure_verify_token();
		$settings = CFlow_Settings::all();
		$notice   = get_transient( 'cflow_admin_notice' );
		delete_transient( 'cflow_admin_notice' );
		include CFLOW_DIR . 'admin/views/settings.php';
	}

	public static function page_users() {
		global $wpdb;
		$accounts = CFlow_DB::accounts_table();
		$autos    = CFlow_DB::automations_table();
		$leads    = CFlow_DB::leads_table();

		$users = get_users(
			array(
				'role__in' => array( CFlow_Auth::ROLE ),
				'orderby'  => 'registered',
				'order'    => 'DESC',
				'number'   => 500,
			)
		);
		$ids   = wp_list_pluck( $users, 'ID' );
		$stats = array();
		if ( $ids ) {
			$in   = implode( ',', array_map( 'intval', $ids ) );
			$rows = $wpdb->get_results( "SELECT user_id, ig_username, access_token <> '' AS connected FROM {$accounts} WHERE user_id IN ({$in})", ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $rows as $row ) {
				$stats[ (int) $row['user_id'] ]['ig'] = $row['connected'] ? $row['ig_username'] : '';
			}
			foreach ( $wpdb->get_results( "SELECT user_id, COUNT(*) AS n FROM {$autos} WHERE user_id IN ({$in}) GROUP BY user_id", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB
				$stats[ (int) $row['user_id'] ]['automations'] = (int) $row['n'];
			}
			foreach ( $wpdb->get_results( "SELECT user_id, COUNT(*) AS n FROM {$leads} WHERE user_id IN ({$in}) GROUP BY user_id", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB
				$stats[ (int) $row['user_id'] ]['leads'] = (int) $row['n'];
			}
		}
		include CFLOW_DIR . 'admin/views/users.php';
	}

	public static function save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'comment-flow' ), 403 );
		}
		check_admin_referer( 'cflow_save_settings' );

		$values = array(
			'app_id'             => sanitize_text_field( wp_unslash( $_POST['app_id'] ?? '' ) ),
			'allow_registration' => isset( $_POST['allow_registration'] ) ? 1 : 0,
			'fullscreen'         => isset( $_POST['fullscreen'] ) ? 1 : 0,
		);
		// Campo de segredo vazio = manter o valor atual.
		$secret = trim( (string) wp_unslash( $_POST['app_secret'] ?? '' ) );
		if ( '' !== $secret ) {
			$values['app_secret'] = $secret;
		}
		CFlow_Settings::update( $values );

		set_transient( 'cflow_admin_notice', __( 'Configurações salvas.', 'comment-flow' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=cflow' ) );
		exit;
	}
}
