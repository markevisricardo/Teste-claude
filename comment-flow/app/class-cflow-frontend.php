<?php
/**
 * Shortcode [comment_flow]: coloca a plataforma numa página (ex.: a home).
 *
 * Com "tela cheia" ligada (padrão), a página do shortcode é exibida com um
 * modelo próprio, sem o tema: o visual fica igual em qualquer site.
 */

defined( 'ABSPATH' ) || exit;

class CFlow_Frontend {

	const SHORTCODE = 'comment_flow';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function shortcode() {
		self::remember_url();
		return CFlow_App::render();
	}

	public static function is_app_page() {
		$post = get_queried_object();
		return is_singular() && $post instanceof WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE );
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
		$url = (int) get_option( 'page_on_front' ) === (int) $post->ID ? home_url( '/' ) : get_permalink( $post );
		if ( $url && get_option( 'cflow_app_url' ) !== $url ) {
			update_option( 'cflow_app_url', $url, false );
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
