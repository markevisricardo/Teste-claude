<?php
/**
 * wp-admin → Post Flow → Configurações.
 *
 * @var array        $settings
 * @var string|false $notice
 * @var int          $page_id
 * @var bool         $is_front
 * @var int|false    $next_run
 */

defined( 'ABSPATH' ) || exit;

$app_url = PFlow_App::base_url();
$meta    = array(
	array( 'pflow-deauth', __( 'URL de cancelamento de autorização (Meta)', 'post-flow' ), PFlow_Settings::rest( '/meta/deauthorize' ) ),
	array( 'pflow-deletion', __( 'URL de solicitação de exclusão de dados (Meta)', 'post-flow' ), PFlow_Settings::rest( '/meta/data-deletion' ) ),
);
$copy    = static function ( $id, $value ) {
	?>
	<div class="mt-2 flex gap-2">
		<input type="text" id="<?php echo esc_attr( $id ); ?>" readonly class="<?php echo esc_attr( PFlow_App::INPUT ); ?> font-mono text-xs text-violet-200" value="<?php echo esc_attr( $value ); ?>" onclick="this.select()">
		<button type="button" class="<?php echo esc_attr( PFlow_App::BTN_SECONDARY ); ?> shrink-0" data-pflow-copy="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Copiar', 'post-flow' ); ?></button>
	</div>
	<?php
};
?>
<div class="wrap pflow-app"><div class="mx-auto max-w-4xl pb-16 pt-4">
	<div class="pflow-hero relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-violet-600 via-violet-700 to-indigo-900 px-6 py-7 shadow-2xl shadow-violet-900/40 sm:px-8"><div class="relative flex flex-wrap items-center justify-between gap-4">
		<div>
			<h1 class="m-0 p-0 text-3xl font-semibold tracking-tight text-white"><?php esc_html_e( 'Post Flow — Configurações', 'post-flow' ); ?></h1>
			<p class="mt-1 text-sm text-violet-100"><?php esc_html_e( 'Os apps de cada rede, usados por todos os usuários da plataforma.', 'post-flow' ); ?></p>
		</div>
		<a href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( PFlow_App::BTN_SECONDARY ); ?>"><?php esc_html_e( 'Abrir o painel ↗', 'post-flow' ); ?></a>
	</div></div>
	<hr class="wp-header-end m-0 border-0">

	<?php if ( $notice ) : ?>
		<div role="status" class="mb-6 rounded-xl border border-solid border-violet-500/30 bg-violet-500/10 px-4 py-3 text-sm text-violet-100"><?php echo esc_html( $notice ); ?></div>
	<?php endif; ?>
	<?php if ( 0 !== strpos( home_url(), 'https://' ) ) : ?>
		<div class="mb-6 rounded-xl border border-solid border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php esc_html_e( 'O site não usa HTTPS. As redes só aceitam URLs de retorno em HTTPS, e o Instagram/Threads só baixam imagens de endereços HTTPS.', 'post-flow' ); ?></div>
	<?php endif; ?>
	<?php if ( '' === get_option( 'permalink_structure' ) ) : ?>
		<div class="mb-6 rounded-xl border border-solid border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php esc_html_e( 'Os links permanentes estão em "Simples". Escolha outra opção (ex.: "Nome do post") para as URLs de retorno funcionarem.', 'post-flow' ); ?></div>
	<?php endif; ?>
	<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
		<div class="mb-6 rounded-xl border border-solid border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100"><?php esc_html_e( 'O WP-Cron está desligado (DISABLE_WP_CRON). Confirme que o servidor chama o wp-cron.php a cada minuto, senão os posts agendados não saem.', 'post-flow' ); ?></div>
	<?php endif; ?>

	<div class="space-y-6">
		<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
			<h2 class="m-0 mb-2 p-0 text-lg font-semibold text-white"><?php esc_html_e( '1. Página do painel', 'post-flow' ); ?></h2>
			<p class="text-sm leading-6 text-neutral-400"><?php esc_html_e( 'A página com o shortcode [post_flow] mostra "Entrar / Criar conta" para visitantes e o painel para quem está logado.', 'post-flow' ); ?></p>
			<div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-solid <?php echo $is_front ? 'border-violet-500/30 bg-violet-500/5' : 'border-amber-500/30 bg-amber-500/5'; ?> p-4">
				<div class="text-sm">
					<?php if ( $is_front ) : ?>
						<p class="font-semibold text-white"><?php esc_html_e( '✓ O painel é a página inicial do site.', 'post-flow' ); ?></p>
					<?php elseif ( $page_id ) : ?>
						<p class="font-semibold text-white"><?php esc_html_e( 'O painel está numa página, mas ela não é a página inicial.', 'post-flow' ); ?></p>
					<?php else : ?>
						<p class="font-semibold text-white"><?php esc_html_e( 'Ainda não há uma página com o painel.', 'post-flow' ); ?></p>
					<?php endif; ?>
					<p class="mt-1 text-xs text-neutral-400"><?php echo esc_html( sprintf( /* translators: %s: URL */ __( 'Endereço do painel: %s', 'post-flow' ), $app_url ) ); ?></p>
				</div>
				<?php if ( ! $is_front ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="pflow_setup_page">
						<?php wp_nonce_field( 'pflow_setup_page' ); ?>
						<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?>"><?php echo esc_html( $page_id ? __( 'Usar como página inicial', 'post-flow' ) : __( 'Criar página e usar como inicial', 'post-flow' ) ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</section>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="space-y-6">
			<input type="hidden" name="action" value="pflow_save_settings">
			<?php wp_nonce_field( 'pflow_save_settings' ); ?>

			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
				<h2 class="m-0 mb-2 p-0 text-lg font-semibold text-white"><?php esc_html_e( '2. Apps das redes sociais', 'post-flow' ); ?></h2>
				<p class="text-sm leading-6 text-neutral-400"><?php esc_html_e( 'Crie um app em cada rede (uma vez só, para toda a plataforma), cadastre nele a URL de retorno e cole aqui as credenciais. Redes sem credenciais aparecem como indisponíveis para os usuários.', 'post-flow' ); ?></p>

				<div class="mt-6 space-y-8">
					<?php foreach ( self::provider_fields() as $pflow_id => $pflow_field ) : ?>
						<?php $pflow_provider = PFlow_Networks::provider( $pflow_id ); ?>
						<div class="rounded-xl border border-solid border-neutral-800 p-5">
							<div class="flex flex-wrap items-center justify-between gap-3">
								<h3 class="m-0 p-0 text-base font-semibold text-white"><?php echo esc_html( $pflow_field['title'] ); ?></h3>
								<span class="flex items-center gap-3">
									<?php echo PFlow_App::badge( $pflow_provider->is_configured() ? 'active' : 'draft' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<a href="<?php echo esc_url( $pflow_field['console'] ); ?>" target="_blank" rel="noopener noreferrer" class="text-xs <?php echo esc_attr( PFlow_App::LINK ); ?>"><?php esc_html_e( 'Abrir console ↗', 'post-flow' ); ?></a>
								</span>
							</div>
							<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>"><?php echo esc_html( $pflow_field['help'] ); ?></p>

							<label for="pflow-redirect-<?php echo esc_attr( $pflow_id ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?> mt-4"><?php esc_html_e( 'URL de retorno (redirect / callback)', 'post-flow' ); ?></label>
							<?php $copy( 'pflow-redirect-' . $pflow_id, PFlow_Settings::redirect_url( $pflow_id ) ); ?>
							<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>">
								<?php
								/* translators: %s: escopos */
								echo esc_html( sprintf( __( 'Permissões pedidas: %s', 'post-flow' ), implode( ', ', $pflow_provider->scopes() ) ) );
								?>
							</p>

							<div class="mt-4 grid gap-4 md:grid-cols-2">
								<div>
									<label for="pflow-<?php echo esc_attr( $pflow_field['id'][0] ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php echo esc_html( $pflow_field['id'][1] ); ?></label>
									<input type="text" id="pflow-<?php echo esc_attr( $pflow_field['id'][0] ); ?>" name="<?php echo esc_attr( $pflow_field['id'][0] ); ?>" value="<?php echo esc_attr( $settings[ $pflow_field['id'][0] ] ); ?>" autocomplete="off" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
								</div>
								<div>
									<label for="pflow-<?php echo esc_attr( $pflow_field['secret'][0] ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php echo esc_html( $pflow_field['secret'][1] ); ?></label>
									<input type="password" id="pflow-<?php echo esc_attr( $pflow_field['secret'][0] ); ?>" name="<?php echo esc_attr( $pflow_field['secret'][0] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo $settings[ $pflow_field['secret'][0] ] ? esc_attr__( '•••••••• (salvo — deixe vazio para manter)', 'post-flow' ) : ''; ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
								</div>
							</div>

							<?php if ( 'meta' === $pflow_id ) : ?>
								<div class="mt-4 space-y-4 border-0 border-t border-solid border-neutral-900 pt-4">
									<?php foreach ( $meta as $pflow_url ) : ?>
										<div>
											<label for="<?php echo esc_attr( $pflow_url[0] ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php echo esc_html( $pflow_url[1] ); ?></label>
											<?php $copy( $pflow_url[0], $pflow_url[2] ); ?>
										</div>
									<?php endforeach; ?>
									<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>"><?php esc_html_e( 'Exigidas na análise do app (Configurações do app → Básico e Login do Facebook). Ao receber o aviso, o plugin remove os canais e tokens daquela pessoa.', 'post-flow' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
				<h2 class="m-0 mb-6 p-0 text-lg font-semibold text-white"><?php esc_html_e( '3. Regras da plataforma', 'post-flow' ); ?></h2>
				<div class="space-y-4">
					<label class="flex cursor-pointer items-center gap-3">
						<input type="checkbox" name="allow_registration" value="1" class="peer sr-only" <?php checked( 1, (int) $settings['allow_registration'] ); ?>>
						<span class="<?php echo esc_attr( PFlow_App::TOGGLE ); ?>"></span>
						<span class="text-sm text-white"><?php esc_html_e( 'Permitir novos cadastros', 'post-flow' ); ?></span>
					</label>
					<label class="flex cursor-pointer items-center gap-3">
						<input type="checkbox" name="fullscreen" value="1" class="peer sr-only" <?php checked( 1, (int) $settings['fullscreen'] ); ?>>
						<span class="<?php echo esc_attr( PFlow_App::TOGGLE ); ?>"></span>
						<span class="text-sm text-white"><?php esc_html_e( 'Exibir o painel em tela cheia (sem o tema do site)', 'post-flow' ); ?></span>
					</label>
				</div>
				<div class="mt-6 max-w-xs">
					<label for="pflow-max-channels" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Limite de canais por usuário', 'post-flow' ); ?></label>
					<input type="number" min="0" max="1000" id="pflow-max-channels" name="max_channels" value="<?php echo (int) $settings['max_channels']; ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
					<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>"><?php esc_html_e( '0 = sem limite. Use para montar planos (ex.: 3 canais no gratuito).', 'post-flow' ); ?></p>
				</div>
			</section>

			<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?>"><?php esc_html_e( 'Salvar configurações', 'post-flow' ); ?></button>
		</form>

		<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
			<h2 class="m-0 mb-2 p-0 text-lg font-semibold text-white"><?php esc_html_e( '4. Publicador', 'post-flow' ); ?></h2>
			<p class="text-sm leading-6 text-neutral-400">
				<?php
				echo $next_run
					/* translators: %s: data e hora */
					? esc_html( sprintf( __( 'Roda a cada minuto pelo WP-Cron. Próxima execução: %s.', 'post-flow' ), wp_date( 'd/m/Y H:i:s', $next_run ) ) )
					: esc_html__( 'O publicador não está agendado. Clique em "Rodar agora" para agendar de novo.', 'post-flow' );
				?>
			</p>
			<p class="mt-2 text-xs leading-5 text-neutral-500"><?php esc_html_e( 'O WP-Cron só roda quando o site recebe visitas. Para horários exatos, crie um cron no servidor: */1 * * * * curl -s https://SEU-SITE/wp-cron.php?doing_wp_cron > /dev/null', 'post-flow' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mt-4">
				<input type="hidden" name="action" value="pflow_run_now">
				<?php wp_nonce_field( 'pflow_run_now' ); ?>
				<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_SECONDARY ); ?>"><?php esc_html_e( 'Rodar agora', 'post-flow' ); ?></button>
			</form>
		</section>
	</div>
</div></div>
<script>
document.querySelectorAll('[data-pflow-copy]').forEach(function (button) {
	button.addEventListener('click', function () {
		var input = document.getElementById(button.getAttribute('data-pflow-copy'));
		input.select();
		(navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(input.value) : Promise.resolve(document.execCommand('copy'))).then(function () {
			var label = button.textContent;
			button.textContent = <?php echo wp_json_encode( __( 'Copiado!', 'post-flow' ) ); ?>;
			setTimeout(function () { button.textContent = label; }, 1500);
		});
	});
});
</script>
