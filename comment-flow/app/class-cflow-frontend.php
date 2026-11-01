<?php
/**
 * Shortcode [comment_flow]: coloca a plataforma numa página (ex.: a home).
 *
 * Com "tela cheia" ligada (padrão), a página do shortcode é exibida com um
 * modelo próprio, sem o tema: o visual fica igual em qualquer site.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Frontend {

	const SHORTCODE   = 'comment_flow';
	const PAGE_OPTION = 'cflow_app_page_id';
	const SETUP_FLAG  = 'cflow_setup_page';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_setup_page' ), 20 );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function shortcode() {
		self::remember_url();
		// Garante o CSS mesmo quando a página não foi detectada antes (ex.: Elementor).
		wp_enqueue_style( 'cflow-app', self::css_url(), array(), CFLOW_VERSION );
		return CFlow_App::render();
	}

	public static function is_app_page() {
		$post = get_queried_object();
		if ( ! is_singular() || ! $post instanceof WP_Post ) {
			return false;
		}
		// Construtores de página (Elementor etc.) guardam o conteúdo fora do
		// post_content; por isso vale também a página registrada.
		return (int) $post->ID === self::page_id() || has_shortcode( (string) $post->post_content, self::SHORTCODE );
	}

	/**
	 * ID da página do painel (publicada), ou 0.
	 */
	public static function page_id() {
		$id = (int) get_option( self::PAGE_OPTION, 0 );
		return $id && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	public static function is_front_page( $page_id ) {
		return $page_id && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $page_id;
	}

	/**
	 * Pede a configuração da página no próximo carregamento (ativação/atualização).
	 */
	public static function request_setup() {
		update_option( self::SETUP_FLAG, 1, false );
	}

	public static function maybe_setup_page() {
		if ( get_option( self::SETUP_FLAG ) ) {
			delete_option( self::SETUP_FLAG );
			self::setup_page( false );
		}
	}

	/**
	 * Encontra ou cria a página com o shortcode. Ela vira a página inicial
	 * quando o site ainda não tem uma (mostra os posts) ou quando $make_front.
	 */
	public static function setup_page( $make_front ) {
		$id = self::page_id();
		if ( ! $id ) {
			$id = self::find_page();
		}
		if ( ! $id ) {
			$id = (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => 'Comment Flow',
					'post_content' => '<!-- wp:shortcode -->[' . self::SHORTCODE . ']<!-- /wp:shortcode -->',
				)
			);
		}
		if ( ! $id ) {
			return 0;
		}
		update_option( self::PAGE_OPTION, $id, false );

		if ( $make_front || 'page' !== get_option( 'show_on_front' ) || ! get_option( 'page_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $id );
		}
		update_option( 'cflow_app_url', self::is_front_page( $id ) ? home_url( '/' ) : get_permalink( $id ), false );
		return $id;
	}

	private static function find_page() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				's'              => '[' . self::SHORTCODE,
				'posts_per_page' => 10,
			)
		);
		foreach ( $pages as $page ) {
			if ( has_shortcode( $page->post_content, self::SHORTCODE ) ) {
				return (int) $page->ID;
			}
		}
		return 0;
	}

	public static function template( $template ) {
		if ( self::is_app_page() && CFlow_Settings::get( 'fullscreen' ) ) {
			nocache_headers();
			return CFLOW_DIR . 'templates/app.php';
		}
		return $template;
	}

	public static function assets() {
		if ( self::is_app_page() ) {
			wp_enqueue_style( 'cflow-app', self::css_url(), array(), CFLOW_VERSION );
		}
	}

	public static function css_url() {
		return CFLOW_URL . 'assets/admin.css';
	}

	/**
	 * Guarda o endereço da página do painel (usado nos redirecionamentos).
	 */
	private static function remember_url() {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$url = self::is_front_page( $post->ID ) ? home_url( '/' ) : get_permalink( $post );
		if ( $url && get_option( 'cflow_app_url' ) !== $url ) {
			update_option( 'cflow_app_url', $url, false );
		}
		if ( (int) get_option( self::PAGE_OPTION ) !== (int) $post->ID ) {
			update_option( self::PAGE_OPTION, (int) $post->ID, false );
		}
	}

	/**
	 * Usado pelo modelo de tela cheia.
	 */
	public static function render_page() {
		self::remember_url();
		return CFlow_App::render();
	}
}
