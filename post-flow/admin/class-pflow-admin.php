<?php
/**
 * wp-admin (só administradores): apps das redes, regras do SaaS e usuários.
 * O painel de cada usuário fica no front-end, na página do shortcode.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_post_pflow_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_pflow_setup_page', array( __CLASS__, 'setup_page' ) );
		add_action( 'admin_post_pflow_run_now', array( __CLASS__, 'run_now' ) );
	}

	public static function menu() {
		add_menu_page( 'Post Flow', 'Post Flow', self::CAP, 'pflow', array( __CLASS__, 'page_settings' ), 'dashicons-share', 59 );
		add_submenu_page( 'pflow', __( 'Configurações', 'post-flow' ), __( 'Configurações', 'post-flow' ), self::CAP, 'pflow', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'pflow', __( 'Usuários', 'post-flow' ), __( 'Usuários', 'post-flow' ), self::CAP, 'pflow-users', array( __CLASS__, 'page_users' ) );
	}

	private static function is_plugin_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return 0 === strpos( $page, 'pflow' );
	}

	public static function assets() {
		if ( self::is_plugin_screen() ) {
			wp_enqueue_style( 'pflow-admin', PFlow_Frontend::css_url(), array(), PFLOW_VERSION );
		}
	}

	public static function body_class( $classes ) {
		return self::is_plugin_screen() ? $classes . ' pflow-dark' : $classes;
	}

	/**
	 * Campos de cada provedor no formulário (id do campo, rótulo, segredo?).
	 */
	public static function provider_fields() {
		return array(
			'meta'     => array(
				'title'   => __( 'Meta — Facebook e Instagram', 'post-flow' ),
				'console' => 'https://developers.facebook.com/apps/',
				'id'      => array( 'meta_app_id', __( 'App ID', 'post-flow' ) ),
				'secret'  => array( 'meta_app_secret', __( 'App Secret', 'post-flow' ) ),
				'help'    => __( 'App do tipo Empresa com o produto "Login do Facebook para Empresas". Cadastre a URL de retorno em "URIs de redirecionamento do OAuth válidos".', 'post-flow' ),
			),
			'linkedin' => array(
				'title'   => 'LinkedIn',
				'console' => 'https://www.linkedin.com/developers/apps',
				'id'      => array( 'linkedin_client_id', __( 'Client ID', 'post-flow' ) ),
				'secret'  => array( 'linkedin_client_secret', __( 'Client Secret', 'post-flow' ) ),
				'help'    => __( 'Adicione os produtos "Sign In with LinkedIn using OpenID Connect" e "Share on LinkedIn". Cadastre a URL de retorno em Auth → Authorized redirect URLs.', 'post-flow' ),
			),
			'x'        => array(
				'title'   => 'X (Twitter)',
				'console' => 'https://developer.x.com/en/portal/dashboard',
				'id'      => array( 'x_client_id', __( 'OAuth 2.0 Client ID', 'post-flow' ) ),
				'secret'  => array( 'x_client_secret', __( 'OAuth 2.0 Client Secret', 'post-flow' ) ),
				'help'    => __( 'Em User authentication settings: permissões "Read and write", tipo "Web App (confidential client)" e a URL de retorno em Callback URI. Publicar exige um plano da API do X que permita escrita.', 'post-flow' ),
			),
			'threads'  => array(
				'title'   => 'Threads',
				'console' => 'https://developers.facebook.com/apps/',
				'id'      => array( 'threads_app_id', __( 'Threads App ID', 'post-flow' ) ),
				'secret'  => array( 'threads_app_secret', __( 'Threads App Secret', 'post-flow' ) ),
				'help'    => __( 'App da Meta com o caso de uso "Acessar a API do Threads". Use o ID e a chave do Threads (diferentes dos do app) e cadastre a URL de retorno em "URLs de callback de redirecionamento".', 'post-flow' ),
			),
		);
	}

	public static function page_settings() {
		$settings = PFlow_Settings::all();
		$page_id  = PFlow_Frontend::page_id();
		$is_front = PFlow_Frontend::is_front_page( $page_id );
		$notice   = get_transient( 'pflow_admin_notice' );
		delete_transient( 'pflow_admin_notice' );
		$next_run = wp_next_scheduled( PFlow_Publisher::HOOK );
		include PFLOW_DIR . 'admin/views/settings.php';
	}

	public static function page_users() {
		global $wpdb;
		$users = get_users(
			array(
				'role__in' => array( PFlow_Auth::ROLE ),
				'orderby'  => 'registered',
				'order'    => 'DESC',
				'number'   => 500,
			)
		);
		$ids   = wp_list_pluck( $users, 'ID' );
		$stats = array();
		if ( $ids ) {
			$in = implode( ',', array_map( 'intval', $ids ) );
			foreach ( $wpdb->get_results( 'SELECT user_id, network, COUNT(*) AS n FROM ' . PFlow_DB::channels_table() . " WHERE user_id IN ({$in}) GROUP BY user_id, network", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB
				$stats[ (int) $row['user_id'] ]['networks'][ $row['network'] ] = (int) $row['n'];
			}
			foreach ( $wpdb->get_results( 'SELECT user_id, status, COUNT(*) AS n FROM ' . PFlow_DB::posts_table() . " WHERE user_id IN ({$in}) GROUP BY user_id, status", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB
				$stats[ (int) $row['user_id'] ]['posts'][ $row['status'] ] = (int) $row['n'];
			}
		}
		include PFLOW_DIR . 'admin/views/users.php';
	}

	private static function require_admin( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'post-flow' ), 403 );
		}
		check_admin_referer( $action );
	}

	private static function back( $message ) {
		set_transient( 'pflow_admin_notice', $message, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=pflow' ) );
		exit;
	}

	public static function save_settings() {
		self::require_admin( 'pflow_save_settings' );

		$values = array(
			'allow_registration' => isset( $_POST['allow_registration'] ) ? 1 : 0,
			'fullscreen'         => isset( $_POST['fullscreen'] ) ? 1 : 0,
			'max_channels'       => min( 1000, absint( $_POST['max_channels'] ?? 0 ) ),
		);
		foreach ( self::provider_fields() as $provider ) {
			$values[ $provider['id'][0] ] = sanitize_text_field( wp_unslash( $_POST[ $provider['id'][0] ] ?? '' ) );
			// Campo de segredo vazio = manter o valor atual.
			$secret = trim( (string) wp_unslash( $_POST[ $provider['secret'][0] ] ?? '' ) );
			if ( '' !== $secret ) {
				$values[ $provider['secret'][0] ] = $secret;
			}
		}
		PFlow_Settings::update( $values );
		self::back( __( 'Configurações salvas.', 'post-flow' ) );
	}

	public static function setup_page() {
		self::require_admin( 'pflow_setup_page' );
		$id = PFlow_Frontend::setup_page( true );
		self::back( $id ? __( 'Pronto: o painel agora é a página inicial do site.', 'post-flow' ) : __( 'Não foi possível criar a página.', 'post-flow' ) );
	}

	/**
	 * Roda o publicador na hora (útil para conferir sem esperar o cron).
	 */
	public static function run_now() {
		self::require_admin( 'pflow_run_now' );
		PFlow_Publisher::schedule();
		PFlow_Publisher::run();
		self::back( __( 'Publicador executado: os posts vencidos foram processados.', 'post-flow' ) );
	}
}
