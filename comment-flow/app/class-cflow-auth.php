<?php
/**
 * Cadastro e login dos usuários da plataforma (e-mail + senha).
 *
 * Os usuários criados aqui recebem a função "cflow_member": só usam o painel
 * da página do shortcode, sem acesso ao wp-admin nem à barra do WordPress.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Auth {

	const ROLE = 'cflow_member';

	public static function init() {
		add_action( 'admin_post_nopriv_cflow_register', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_nopriv_cflow_login', array( __CLASS__, 'login' ) );
		// Logado tentando entrar/cadastrar de novo: só volta ao painel.
		add_action( 'admin_post_cflow_register', array( __CLASS__, 'to_app' ) );
		add_action( 'admin_post_cflow_login', array( __CLASS__, 'to_app' ) );

		add_action( 'admin_init', array( __CLASS__, 'keep_members_out_of_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	public static function add_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Membro Comment Flow', 'comment-flow' ), array( 'read' => true ) );
		}
	}

	public static function is_member( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return $user && $user->exists() && in_array( self::ROLE, (array) $user->roles, true );
	}

	/* ---------------------------------------------------------------------
	 * Telas
	 * ------------------------------------------------------------------- */

	public static function messages() {
		return array(
			'login'        => __( 'E-mail ou senha incorretos.', 'comment-flow' ),
			'fields'       => __( 'Preencha todos os campos.', 'comment-flow' ),
			'email'        => __( 'Informe um e-mail válido.', 'comment-flow' ),
			'email_exists' => __( 'Já existe uma conta com este e-mail. Entre ou recupere a senha.', 'comment-flow' ),
			'password'     => __( 'A senha precisa ter pelo menos 8 caracteres.', 'comment-flow' ),
			'closed'       => __( 'Os cadastros estão fechados no momento.', 'comment-flow' ),
			'register'     => __( 'Não foi possível criar a conta. Tente de novo.', 'comment-flow' ),
			'oauth_state'  => __( 'A conexão com o Instagram expirou. Entre e tente de novo.', 'comment-flow' ),
		);
	}

	public static function render_forms() {
		// phpcs:disable WordPress.Security.NonceVerification
		$error = isset( $_GET['cf_error'] ) ? sanitize_key( wp_unslash( $_GET['cf_error'] ) ) : '';
		$tab   = isset( $_GET['cf_tab'] ) && 'register' === $_GET['cf_tab'] ? 'register' : 'login';
		$email = isset( $_GET['cf_email'] ) ? sanitize_email( wp_unslash( $_GET['cf_email'] ) ) : '';
		// phpcs:enable
		$messages      = self::messages();
		$error_message = isset( $messages[ $error ] ) ? $messages[ $error ] : '';
		$can_register  = (bool) CFlow_Settings::get( 'allow_registration' );
		include CFLOW_DIR . 'app/views/auth.php';
	}

	/* ---------------------------------------------------------------------
	 * Ações
	 * ------------------------------------------------------------------- */

	public static function register() {
		check_admin_referer( 'cflow_register' );
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput
		$name     = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$password = (string) wp_unslash( $_POST['password'] ?? '' );
		$trap     = (string) ( $_POST['website'] ?? '' ); // Campo invisível: robôs preenchem.
		// phpcs:enable

		$fail = static function ( $code ) use ( $email ) {
			wp_safe_redirect( add_query_arg( array( 'cf_tab' => 'register', 'cf_error' => $code, 'cf_email' => rawurlencode( $email ) ), CFlow_App::url() ) );
			exit;
		};

		if ( '' !== $trap ) {
			$fail( 'register' );
		}
		if ( ! CFlow_Settings::get( 'allow_registration' ) ) {
			$fail( 'closed' );
		}
		$error = self::validate_registration( $name, $email, $password, email_exists( $email ) );
		if ( $error ) {
			$fail( $error );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => self::unique_login( $email ),
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => self::ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			$fail( 'register' );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		CFlow_App::notice( $user_id, 'success', __( 'Conta criada! Agora conecte o seu Instagram.', 'comment-flow' ) );
		wp_safe_redirect( CFlow_App::url( 'account' ) );
		exit;
	}

	/**
	 * Valida o cadastro. Retorna o código do erro ou '' se estiver tudo certo.
	 */
	public static function validate_registration( $name, $email, $password, $email_taken ) {
		if ( '' === trim( (string) $name ) || '' === (string) $email || '' === (string) $password ) {
			return 'fields';
		}
		if ( ! is_email( $email ) ) {
			return 'email';
		}
		if ( $email_taken ) {
			return 'email_exists';
		}
		if ( strlen( (string) $password ) < 8 ) {
			return 'password';
		}
		return '';
	}

	/**
	 * Nome de usuário a partir do e-mail ("ana.souza", "ana.souza2", ...).
	 */
	private static function unique_login( $email ) {
		$base  = sanitize_user( strstr( $email, '@', true ), true );
		$base  = '' !== $base ? substr( $base, 0, 50 ) : 'usuario';
		$login = $base;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $base . $i;
		}
		return $login;
	}

	public static function login() {
		check_admin_referer( 'cflow_login' );
		$email = sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ); // E-mail ou nome de usuário.
		$user  = wp_signon(
			array(
				'user_login'    => $email,
				'user_password' => (string) wp_unslash( $_POST['password'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				'remember'      => true,
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( array( 'cf_error' => 'login', 'cf_email' => rawurlencode( $email ) ), CFlow_App::url() ) );
			exit;
		}
		wp_safe_redirect( CFlow_App::url() );
		exit;
	}

	public static function to_app() {
		wp_safe_redirect( CFlow_App::url() );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Membros fora do wp-admin
	 * ------------------------------------------------------------------- */

	public static function keep_members_out_of_admin() {
		if ( wp_doing_ajax() || ! self::is_member() ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( 'admin-post.php' === $script ) {
			return; // As ações do painel passam por aqui.
		}
		wp_safe_redirect( CFlow_App::url() );
		exit;
	}

	public static function admin_bar( $show ) {
		return self::is_member() ? false : $show;
	}

	public static function login_redirect( $redirect_to, $requested, $user ) {
		return $user instanceof WP_User && self::is_member( $user ) ? CFlow_App::url() : $redirect_to;
	}
}
