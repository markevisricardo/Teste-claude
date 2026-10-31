<?php
/**
 * Entrar / Criar conta (visitante).
 *
 * @var string $tab           'login' ou 'register'.
 * @var string $email         E-mail digitado antes (após um erro).
 * @var string $error_message Mensagem de erro (ou '').
 * @var bool   $can_register  Cadastros abertos.
 */

defined( 'ABSPATH' ) || exit;

$tab = $can_register ? $tab : 'login';
?>
<div class="mx-auto flex min-h-[85vh] max-w-6xl items-center">
	<div class="grid w-full items-center gap-12 lg:grid-cols-2">
		<!-- Apresentação -->
		<div class="hidden lg:block">
			<span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-600 shadow-lg shadow-violet-600/30">
				<svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
			</span>
			<h1 class="m-0 mt-8 p-0 text-4xl font-semibold leading-tight tracking-tight text-white"><?php esc_html_e( 'Planeje uma vez. Publique em todas as redes.', 'post-flow' ); ?></h1>
			<p class="mt-4 max-w-md text-base leading-7 text-neutral-400"><?php esc_html_e( 'Escreva o post, escolha os canais e deixe a fila publicar no melhor horário — pelas APIs oficiais de cada rede.', 'post-flow' ); ?></p>
			<ul class="mt-8 space-y-3 text-sm text-neutral-300">
				<?php
				$features = array(
					__( 'Facebook, Instagram, LinkedIn, X e Threads num só lugar', 'post-flow' ),
					__( 'Fila com os seus horários de publicação', 'post-flow' ),
					__( 'Agendamento por data e calendário da semana', 'post-flow' ),
					__( 'Histórico com o link de cada post publicado', 'post-flow' ),
				);
				foreach ( $features as $feature ) :
					?>
					<li class="flex items-center gap-3">
						<span class="flex h-5 w-5 items-center justify-center rounded-full bg-violet-600/20 text-violet-400"><svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg></span>
						<?php echo esc_html( $feature ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<!-- Formulários -->
		<div class="mx-auto w-full max-w-md">
			<div class="mb-8 flex items-center gap-3 lg:hidden">
				<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-600"><svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>
				<span class="text-lg font-semibold text-white">Post Flow</span>
			</div>

			<div class="<?php echo esc_attr( PFlow_App::CARD ); ?> p-8">
				<?php if ( $can_register ) : ?>
					<div class="mb-8 grid grid-cols-2 gap-1 rounded-xl bg-black p-1" role="tablist">
						<button type="button" data-pflow-tab="login" class="cursor-pointer rounded-lg border-0 px-3 py-2 text-sm font-semibold transition-colors"><?php esc_html_e( 'Entrar', 'post-flow' ); ?></button>
						<button type="button" data-pflow-tab="register" class="cursor-pointer rounded-lg border-0 px-3 py-2 text-sm font-semibold transition-colors"><?php esc_html_e( 'Criar conta', 'post-flow' ); ?></button>
					</div>
				<?php else : ?>
					<h2 class="m-0 mb-6 p-0 text-xl font-semibold text-white"><?php esc_html_e( 'Entrar', 'post-flow' ); ?></h2>
				<?php endif; ?>

				<?php if ( '' !== $error_message ) : ?>
					<div role="alert" class="mb-6 rounded-xl border border-solid border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php echo esc_html( $error_message ); ?></div>
				<?php endif; ?>

				<!-- Entrar -->
				<form data-pflow-panel="login" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="space-y-5">
					<input type="hidden" name="action" value="pflow_login">
					<?php wp_nonce_field( 'pflow_login' ); ?>
					<div>
						<label for="pflow-login-email" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'E-mail', 'post-flow' ); ?></label>
						<input type="email" id="pflow-login-email" name="email" required autocomplete="email" value="<?php echo esc_attr( $email ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
					</div>
					<div>
						<div class="flex items-center justify-between">
							<label for="pflow-login-password" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Senha', 'post-flow' ); ?></label>
							<a href="<?php echo esc_url( wp_lostpassword_url( PFlow_App::url() ) ); ?>" class="text-xs <?php echo esc_attr( PFlow_App::LINK ); ?>"><?php esc_html_e( 'Esqueci a senha', 'post-flow' ); ?></a>
						</div>
						<input type="password" id="pflow-login-password" name="password" required autocomplete="current-password" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
					</div>
					<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> w-full"><?php esc_html_e( 'Entrar', 'post-flow' ); ?></button>
				</form>

				<?php if ( $can_register ) : ?>
					<!-- Criar conta -->
					<form data-pflow-panel="register" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="space-y-5">
						<input type="hidden" name="action" value="pflow_register">
						<?php wp_nonce_field( 'pflow_register' ); ?>
						<div class="absolute -left-[9999px]" aria-hidden="true">
							<label for="pflow-website">Website</label>
							<input type="text" id="pflow-website" name="website" tabindex="-1" autocomplete="off">
						</div>
						<div>
							<label for="pflow-name" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Seu nome', 'post-flow' ); ?></label>
							<input type="text" id="pflow-name" name="name" required autocomplete="name" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
						</div>
						<div>
							<label for="pflow-email" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'E-mail', 'post-flow' ); ?></label>
							<input type="email" id="pflow-email" name="email" required autocomplete="email" value="<?php echo esc_attr( $email ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
						</div>
						<div>
							<label for="pflow-password" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Senha', 'post-flow' ); ?></label>
							<input type="password" id="pflow-password" name="password" required minlength="8" autocomplete="new-password" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
							<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>"><?php esc_html_e( 'Pelo menos 8 caracteres.', 'post-flow' ); ?></p>
						</div>
						<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> w-full"><?php esc_html_e( 'Criar conta grátis', 'post-flow' ); ?></button>
						<p class="text-center text-xs leading-5 text-neutral-500"><?php esc_html_e( 'Depois você conecta os seus canais em poucos cliques.', 'post-flow' ); ?></p>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<?php if ( $can_register ) : ?>
<script>
( function () {
	var tabs   = document.querySelectorAll( '[data-pflow-tab]' );
	var panels = document.querySelectorAll( '[data-pflow-panel]' );
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
