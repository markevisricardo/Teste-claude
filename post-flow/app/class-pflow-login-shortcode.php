<?php
/**
 * Shortcode [post_flow_login]: só o formulário de Entrar / Criar conta, para
 * colocar em qualquer página (landing page, Elementor, blocos).
 *
 * Atributos (todos opcionais):
 *   aba="cadastro"         abre na aba Criar conta (padrão: entrar);
 *   redirecionar="URL"     para onde ir depois de entrar (padrão: o painel;
 *                          depois do cadastro, a tela Canais do painel);
 *   titulo="Texto"         título acima do formulário;
 *   botao="Texto"          texto do botão para quem já está logado.
 *
 * Quem já está logado vê um cartão com o nome e o botão "Ir para o painel".
 * Os erros (senha errada, e-mail já cadastrado...) voltam para a mesma página.
 */

defined( 'ABSPATH' ) || exit;

class PFlow_Login_Shortcode {

	const TAG = 'post_flow_login';

	private static $count = 0;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Carrega o CSS no <head> quando a página tem o shortcode.
	 */
	public static function assets() {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'pflow-app', PFlow_Frontend::css_url(), array(), PFLOW_VERSION );
		}
	}

	/**
	 * Endereço da página atual (para voltar a ela em caso de erro).
	 */
	private static function current_url() {
		$id  = get_queried_object_id();
		$url = $id ? get_permalink( $id ) : '';
		if ( ! $url ) {
			$url = home_url( '/' );
		}
		return remove_query_arg( array( 'pf_error', 'pf_tab', 'pf_email' ), $url );
	}

	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'aba'          => 'entrar',
				'redirecionar' => '',
				'titulo'       => '',
				'botao'        => __( 'Ir para o painel', 'post-flow' ),
			),
			$atts,
			self::TAG
		);

		// Garante o CSS mesmo quando a página não foi detectada (construtores de página).
		wp_enqueue_style( 'pflow-app', PFlow_Frontend::css_url(), array(), PFLOW_VERSION );

		++self::$count;
		$redirect = '' !== $atts['redirecionar'] ? (string) wp_validate_redirect( esc_url_raw( $atts['redirecionar'] ), '' ) : '';
		$anchor   = 1 === self::$count ? ' id="pflow-entrar"' : '';

		ob_start();
		echo '<div class="pflow-app pflow-login-shortcode font-sans"' . $anchor . '><div class="mx-auto w-full max-w-md">'; // phpcs:ignore WordPress.Security.EscapeOutput -- $anchor é fixo.

		if ( is_user_logged_in() ) {
			self::logged_in( $atts, $redirect );
		} else {
			// phpcs:disable WordPress.Security.NonceVerification
			$error = isset( $_GET['pf_error'] ) ? sanitize_key( wp_unslash( $_GET['pf_error'] ) ) : '';
			$tab   = isset( $_GET['pf_tab'] ) ? sanitize_key( wp_unslash( $_GET['pf_tab'] ) ) : '';
			$email = isset( $_GET['pf_email'] ) ? sanitize_email( wp_unslash( $_GET['pf_email'] ) ) : '';
			// phpcs:enable
			if ( '' === $tab ) {
				$tab = in_array( strtolower( $atts['aba'] ), array( 'cadastro', 'cadastrar', 'register', 'criar' ), true ) ? 'register' : 'login';
			}
			$messages      = PFlow_Auth::messages();
			$error_message = isset( $messages[ $error ] ) ? $messages[ $error ] : '';
			$can_register  = (bool) PFlow_Settings::get( 'allow_registration' );
			$tab           = 'register' === $tab ? 'register' : 'login';
			$pf_uid        = 'sc' . self::$count;
			$return_url    = self::current_url();
			$redirect_url  = $redirect;
			$card_title    = sanitize_text_field( $atts['titulo'] );
			include PFLOW_DIR . 'app/views/partials/auth-card.php';
		}

		echo '</div></div>';
		return (string) ob_get_clean();
	}

	/**
	 * Cartão para quem já entrou.
	 */
	private static function logged_in( array $atts, $redirect ) {
		$user  = wp_get_current_user();
		$panel = '' !== $redirect ? $redirect : PFlow_App::url();
		?>
		<div class="<?php echo esc_attr( PFlow_App::CARD ); ?> p-8 text-center">
			<span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-700 text-lg font-semibold text-white"><?php echo esc_html( strtoupper( mb_substr( (string) $user->display_name, 0, 1 ) ) ); ?></span>
			<p class="m-0 mt-4 text-base font-semibold text-white">
				<?php
				/* translators: %s: nome */
				echo esc_html( sprintf( __( 'Olá, %s!', 'post-flow' ), $user->display_name ) );
				?>
			</p>
			<p class="m-0 mt-1 text-sm text-neutral-400"><?php esc_html_e( 'Você já está conectado.', 'post-flow' ); ?></p>
			<a href="<?php echo esc_url( $panel ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> mt-6 w-full"><?php echo esc_html( $atts['botao'] ); ?></a>
			<a href="<?php echo esc_url( wp_logout_url( self::current_url() ) ); ?>" class="mt-4 inline-block text-sm <?php echo esc_attr( PFlow_App::LINK ); ?>"><?php esc_html_e( 'Sair', 'post-flow' ); ?></a>
		</div>
		<?php
	}
}
