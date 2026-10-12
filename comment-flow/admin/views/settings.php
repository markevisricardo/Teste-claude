<?php
/**
 * wp-admin → Comment Flow → Configurações.
 *
 * @var array        $settings
 * @var string|false $notice
 * @var int          $page_id
 * @var bool         $is_front
 */

defined( 'ABSPATH' ) || exit;

$app_url = CFlow_App::base_url();
$fields  = array(
	array( 'cflow-redirect', __( 'URL de redirecionamento OAuth', 'comment-flow' ), CFlow_Settings::oauth_redirect_url(), __( 'Em "Configurar o login da empresa no Instagram".', 'comment-flow' ) ),
	array( 'cflow-webhook', __( 'URL de callback do webhook', 'comment-flow' ), CFlow_Settings::webhook_url(), __( 'Em "Configurar webhooks". Assine os campos comments e messages.', 'comment-flow' ) ),
	array( 'cflow-verify', __( 'Token de verificação', 'comment-flow' ), $settings['verify_token'], __( 'Em "Configurar webhooks", campo "Verificar token".', 'comment-flow' ) ),
	array( 'cflow-deauth', __( 'URL para cancelar autorização', 'comment-flow' ), CFlow_Settings::rest( '/deauthorize' ), __( 'Em "Configurações do login da empresa". Exigida na análise do app.', 'comment-flow' ) ),
	array( 'cflow-deletion', __( 'URL de exclusão de dados', 'comment-flow' ), CFlow_Settings::rest( '/data-deletion' ), __( 'Em "Configurações do login da empresa". Exigida na análise do app.', 'comment-flow' ) ),
);
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-4xl pb-16 pt-4">
	<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
		<div>
			<h1 class="m-0 p-0 text-2xl font-semibold tracking-tight text-white"><?php esc_html_e( 'Comment Flow — Configurações', 'comment-flow' ); ?></h1>
			<p class="mt-1 text-sm text-neutral-400"><?php esc_html_e( 'O app da Meta da plataforma, usado por todos os usuários.', 'comment-flow' ); ?></p>
		</div>
		<a href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( CFlow_App::BTN_SECONDARY ); ?>"><?php esc_html_e( 'Abrir o painel ↗', 'comment-flow' ); ?></a>
	</div>
	<hr class="wp-header-end m-0 border-0">

	<?php if ( $notice ) : ?>
		<div role="status" class="mb-6 rounded-xl border border-solid border-blue-500/30 bg-blue-500/10 px-4 py-3 text-sm text-blue-100"><?php echo esc_html( $notice ); ?></div>
	<?php endif; ?>
	<?php if ( ! is_ssl() && 0 !== strpos( home_url(), 'https://' ) ) : ?>
		<div class="mb-6 rounded-xl border border-solid border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php esc_html_e( 'O site não usa HTTPS. A Meta só entrega webhooks e aceita redirecionamentos em HTTPS.', 'comment-flow' ); ?></div>
	<?php endif; ?>
	<?php if ( '' === get_option( 'permalink_structure' ) ) : ?>
		<div class="mb-6 rounded-xl border border-solid border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php esc_html_e( 'Os links permanentes estão em "Simples". Escolha outra opção (ex.: "Nome do post") para as URLs abaixo funcionarem na Meta.', 'comment-flow' ); ?></div>
	<?php endif; ?>

	<div class="space-y-6">
		<section class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
			<h2 class="m-0 mb-2 p-0 text-lg font-semibold text-white"><?php esc_html_e( '1. Página do painel', 'comment-flow' ); ?></h2>
			<p class="text-sm leading-6 text-neutral-400">
				<?php esc_html_e( 'A página com o shortcode [comment_flow] mostra "Entrar / Criar conta" para visitantes e o painel para quem está logado.', 'comment-flow' ); ?>
			</p>
			<div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-solid <?php echo $is_front ? 'border-blue-500/30 bg-blue-500/5' : 'border-red-500/30 bg-red-500/10'; ?> p-4">
				<div class="text-sm">
					<?php if ( $is_front ) : ?>
						<p class="font-semibold text-white"><?php esc_html_e( '✓ O painel é a página inicial do site.', 'comment-flow' ); ?></p>
					<?php elseif ( $page_id ) : ?>
						<p class="font-semibold text-white"><?php esc_html_e( 'O painel está numa página, mas ela não é a página inicial.', 'comment-flow' ); ?></p>
					<?php else : ?>
						<p class="font-semibold text-white"><?php esc_html_e( 'Ainda não há uma página com o painel.', 'comment-flow' ); ?></p>
					<?php endif; ?>
					<p class="mt-1 text-xs text-neutral-400"><?php echo esc_html( sprintf( /* translators: %s: URL */ __( 'Endereço do painel: %s', 'comment-flow' ), $app_url ) ); ?></p>
				</div>
				<?php if ( ! $is_front ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cflow_setup_page">
						<?php wp_nonce_field( 'cflow_setup_page' ); ?>
						<button type="submit" class="<?php echo esc_attr( CFlow_App::BTN_PRIMARY ); ?>"><?php echo esc_html( $page_id ? __( 'Usar como página inicial', 'comment-flow' ) : __( 'Criar página e usar como inicial', 'comment-flow' ) ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<p class="mt-4 text-xs leading-5 text-neutral-500">
				<?php esc_html_e( 'Logado como administrador, você vê o painel, e não o cadastro. Para conferir a tela de cadastro, abra o site numa janela anônima. Se usar plugin de cache, exclua essa página do cache.', 'comment-flow' ); ?>
			</p>
		</section>

		<section class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
			<h2 class="m-0 mb-6 p-0 text-lg font-semibold text-white"><?php esc_html_e( '2. Dados para cadastrar no app da Meta', 'comment-flow' ); ?></h2>
			<div class="space-y-5">
				<?php foreach ( $fields as $field ) : ?>
					<div>
						<label for="<?php echo esc_attr( $field[0] ); ?>" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php echo esc_html( $field[1] ); ?></label>
						<div class="mt-2 flex gap-2">
							<input type="text" id="<?php echo esc_attr( $field[0] ); ?>" readonly class="<?php echo esc_attr( CFlow_App::INPUT ); ?> font-mono text-xs text-blue-200" value="<?php echo esc_attr( $field[2] ); ?>" onclick="this.select()">
							<button type="button" class="<?php echo esc_attr( CFlow_App::BTN_SECONDARY ); ?> shrink-0" data-cflow-copy="<?php echo esc_attr( $field[0] ); ?>"><?php esc_html_e( 'Copiar', 'comment-flow' ); ?></button>
						</div>
						<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php echo esc_html( $field[3] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
			<h2 class="m-0 mb-6 p-0 text-lg font-semibold text-white"><?php esc_html_e( '3. Credenciais e opções', 'comment-flow' ); ?></h2>
			<input type="hidden" name="action" value="cflow_save_settings">
			<?php wp_nonce_field( 'cflow_save_settings' ); ?>
			<div class="grid gap-6 md:grid-cols-2">
				<div>
					<label for="cflow-app-id" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Instagram App ID', 'comment-flow' ); ?></label>
					<input type="text" id="cflow-app-id" name="app_id" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" value="<?php echo esc_attr( $settings['app_id'] ); ?>" autocomplete="off">
				</div>
				<div>
					<label for="cflow-app-secret" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Instagram App Secret', 'comment-flow' ); ?></label>
					<input type="password" id="cflow-app-secret" name="app_secret" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" value="" autocomplete="new-password" placeholder="<?php echo $settings['app_secret'] ? esc_attr__( '•••••••• (salvo — deixe vazio para manter)', 'comment-flow' ) : ''; ?>">
				</div>
			</div>
			<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Use o ID e a chave secreta do app do Instagram (em "Configuração da API com login do Instagram"), não os do app do Facebook.', 'comment-flow' ); ?></p>

			<div class="mt-6 space-y-4 border-0 border-t border-solid border-neutral-900 pt-6">
				<label class="flex cursor-pointer items-center gap-3">
					<input type="checkbox" name="allow_registration" value="1" class="peer sr-only" <?php checked( 1, (int) $settings['allow_registration'] ); ?>>
					<span class="<?php echo esc_attr( CFlow_App::TOGGLE ); ?>"></span>
					<span class="text-sm text-white"><?php esc_html_e( 'Permitir novos cadastros', 'comment-flow' ); ?></span>
				</label>
				<label class="flex cursor-pointer items-center gap-3">
					<input type="checkbox" name="fullscreen" value="1" class="peer sr-only" <?php checked( 1, (int) $settings['fullscreen'] ); ?>>
					<span class="<?php echo esc_attr( CFlow_App::TOGGLE ); ?>"></span>
					<span class="text-sm text-white"><?php esc_html_e( 'Exibir o painel em tela cheia (sem o tema do site)', 'comment-flow' ); ?></span>
				</label>
			</div>

			<div class="mt-6 border-0 border-t border-solid border-neutral-900 pt-6">
				<button type="submit" class="<?php echo esc_attr( CFlow_App::BTN_PRIMARY ); ?>"><?php esc_html_e( 'Salvar', 'comment-flow' ); ?></button>
			</div>
		</form>

		<section class="rounded-2xl border border-solid border-neutral-800 p-6 text-sm leading-6 text-neutral-400">
			<h2 class="m-0 mb-3 p-0 text-base font-semibold text-white"><?php esc_html_e( 'Para liberar qualquer conta do Instagram', 'comment-flow' ); ?></h2>
			<p><?php esc_html_e( 'Até a Meta aprovar o app, só funcionam contas adicionadas em Funções do app → Testador do Instagram (cada usuário aceita o convite no Instagram). Depois:', 'comment-flow' ); ?></p>
			<ol class="mt-3 list-decimal space-y-1 pl-5">
				<li><?php esc_html_e( 'Verifique a empresa no Gerenciador de Negócios da Meta.', 'comment-flow' ); ?></li>
				<li><?php esc_html_e( 'Preencha a política de privacidade, os termos e as duas URLs exigidas acima.', 'comment-flow' ); ?></li>
				<li><?php esc_html_e( 'Peça Acesso Avançado para instagram_business_basic, instagram_business_manage_comments e instagram_business_manage_messages, com um vídeo do fluxo.', 'comment-flow' ); ?></li>
			</ol>
		</section>
	</div>
</div></div>
<script>
document.querySelectorAll('[data-cflow-copy]').forEach(function (button) {
	button.addEventListener('click', function () {
		var input = document.getElementById(button.getAttribute('data-cflow-copy'));
		input.select();
		(navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(input.value) : Promise.resolve(document.execCommand('copy'))).then(function () {
			var label = button.textContent;
			button.textContent = <?php echo wp_json_encode( __( 'Copiado!', 'comment-flow' ) ); ?>;
			setTimeout(function () { button.textContent = label; }, 1500);
		});
	});
});
</script>
