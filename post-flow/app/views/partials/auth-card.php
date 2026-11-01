<?php
/**
 * Cartão de Entrar / Criar conta. Usado na tela do painel (visitante) e no
 * shortcode [post_flow_login], que pode aparecer em qualquer página, até
 * mais de uma vez: por isso os IDs levam o sufixo $pf_uid.
 *
 * @var string $tab           'login' ou 'register'.
 * @var string $email         E-mail digitado antes (após um erro).
 * @var string $error_message Mensagem de erro (ou '').
 * @var bool   $can_register  Cadastros abertos.
 * @var string $pf_uid        Sufixo único dos IDs.
 * @var string $return_url    Para onde voltar se der erro (a página do formulário).
 * @var string $redirect_url  Para onde ir depois de entrar ('' = painel).
 * @var string $card_title    Título opcional acima das abas.
 */

defined( 'ABSPATH' ) || exit;

$tab = $can_register ? $tab : 'login';
$uid = sanitize_key( $pf_uid );
$hidden = static function () use ( $return_url, $redirect_url ) {
	echo '<input type="hidden" name="pf_return" value="' . esc_url( $return_url ) . '">';
	if ( '' !== $redirect_url ) {
		echo '<input type="hidden" name="pf_redirect" value="' . esc_url( $redirect_url ) . '">';
	}
};
?>
<div class="<?php echo esc_attr( PFlow_App::CARD ); ?> p-8" data-pflow-auth="<?php echo esc_attr( $uid ); ?>">
	<?php if ( '' !== (string) $card_title ) : ?>
		<h2 class="m-0 mb-6 p-0 text-xl font-semibold text-white"><?php echo esc_html( $card_title ); ?></h2>
	<?php endif; ?>

	<?php if ( $can_register ) : ?>
		<div class="mb-8 grid grid-cols-2 gap-1 rounded-xl bg-black p-1" role="tablist">
			<button type="button" data-pflow-tab="login" class="cursor-pointer rounded-lg border-0 px-3 py-2 text-sm font-semibold transition-colors"><?php esc_html_e( 'Entrar', 'post-flow' ); ?></button>
			<button type="button" data-pflow-tab="register" class="cursor-pointer rounded-lg border-0 px-3 py-2 text-sm font-semibold transition-colors"><?php esc_html_e( 'Criar conta', 'post-flow' ); ?></button>
		</div>
	<?php elseif ( '' === (string) $card_title ) : ?>
		<h2 class="m-0 mb-6 p-0 text-xl font-semibold text-white"><?php esc_html_e( 'Entrar', 'post-flow' ); ?></h2>
	<?php endif; ?>

	<?php if ( '' !== $error_message ) : ?>
		<div role="alert" class="mb-6 rounded-xl border border-solid border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php echo esc_html( $error_message ); ?></div>
	<?php endif; ?>

	<!-- Entrar -->
	<form data-pflow-panel="login" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="space-y-5">
		<input type="hidden" name="action" value="pflow_login">
		<?php wp_nonce_field( 'pflow_login' ); ?>
		<?php $hidden(); ?>
		<div>
			<label for="pflow-login-email-<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'E-mail', 'post-flow' ); ?></label>
			<input type="email" id="pflow-login-email-<?php echo esc_attr( $uid ); ?>" name="email" required autocomplete="email" value="<?php echo esc_attr( $email ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
		</div>
		<div>
			<div class="flex items-center justify-between">
				<label for="pflow-login-password-<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Senha', 'post-flow' ); ?></label>
				<a href="<?php echo esc_url( wp_lostpassword_url( PFlow_App::url() ) ); ?>" class="text-xs <?php echo esc_attr( PFlow_App::LINK ); ?>"><?php esc_html_e( 'Esqueci a senha', 'post-flow' ); ?></a>
			</div>
			<input type="password" id="pflow-login-password-<?php echo esc_attr( $uid ); ?>" name="password" required autocomplete="current-password" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
		</div>
		<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> w-full"><?php esc_html_e( 'Entrar', 'post-flow' ); ?></button>
	</form>

	<?php if ( $can_register ) : ?>
		<!-- Criar conta -->
		<form data-pflow-panel="register" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="space-y-5">
			<input type="hidden" name="action" value="pflow_register">
			<?php wp_nonce_field( 'pflow_register' ); ?>
			<?php $hidden(); ?>
			<div class="absolute -left-[9999px]" aria-hidden="true">
				<label for="pflow-website-<?php echo esc_attr( $uid ); ?>">Website</label>
				<input type="text" id="pflow-website-<?php echo esc_attr( $uid ); ?>" name="website" tabindex="-1" autocomplete="off">
			</div>
			<div>
				<label for="pflow-name-<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Seu nome', 'post-flow' ); ?></label>
				<input type="text" id="pflow-name-<?php echo esc_attr( $uid ); ?>" name="name" required autocomplete="name" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
			</div>
			<div>
				<label for="pflow-email-<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'E-mail', 'post-flow' ); ?></label>
				<input type="email" id="pflow-email-<?php echo esc_attr( $uid ); ?>" name="email" required autocomplete="email" value="<?php echo esc_attr( $email ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
			</div>
			<div>
				<label for="pflow-password-<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Senha', 'post-flow' ); ?></label>
				<input type="password" id="pflow-password-<?php echo esc_attr( $uid ); ?>" name="password" required minlength="8" autocomplete="new-password" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
				<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>"><?php esc_html_e( 'Pelo menos 8 caracteres.', 'post-flow' ); ?></p>
			</div>
			<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> w-full"><?php esc_html_e( 'Criar conta grátis', 'post-flow' ); ?></button>
			<p class="text-center text-xs leading-5 text-neutral-500"><?php esc_html_e( 'Depois você conecta os seus canais em poucos cliques.', 'post-flow' ); ?></p>
		</form>
	<?php endif; ?>
</div>
<?php if ( $can_register ) : ?>
<script>
( function () {
	var root   = document.querySelector( '[data-pflow-auth="<?php echo esc_js( $uid ); ?>"]' );
	if ( ! root ) { return; }
	var tabs   = root.querySelectorAll( '[data-pflow-tab]' );
	var panels = root.querySelectorAll( '[data-pflow-panel]' );
	function show( name ) {
		panels.forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-pflow-panel' ) !== name;
		} );
		tabs.forEach( function ( tab ) {
			var on = tab.getAttribute( 'data-pflow-tab' ) === name;
			tab.className = tab.className.replace( / ?(bg-violet-600 text-white|bg-transparent text-neutral-400)/g, '' ) + ( on ? ' bg-violet-600 text-white' : ' bg-transparent text-neutral-400' );
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
	}
	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () { show( tab.getAttribute( 'data-pflow-tab' ) ); } );
	} );
	show( <?php echo wp_json_encode( $tab ); ?> );
} )();
</script>
<?php endif; ?>
