<?php
/**
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

$connected   = CFlow_Settings::is_connected();
$days_left   = $settings['token_expires_at'] ? (int) floor( ( $settings['token_expires_at'] - time() ) / DAY_IN_SECONDS ) : 0;
$has_creds   = '' !== $settings['app_id'] && '' !== $settings['app_secret'];
$meta_fields = array(
	array( 'cflow-redirect', __( 'URL de redirecionamento OAuth', 'comment-flow' ), CFlow_Settings::oauth_redirect_url(), __( 'Em "Configurar o login da empresa no Instagram".', 'comment-flow' ) ),
	array( 'cflow-webhook', __( 'URL de callback do webhook', 'comment-flow' ), CFlow_Settings::webhook_url(), __( 'Em "Configurar webhooks". Assine os campos comments e messages.', 'comment-flow' ) ),
	array( 'cflow-verify', __( 'Token de verificação', 'comment-flow' ), $settings['verify_token'], __( 'Em "Configurar webhooks", campo "Verificar token".', 'comment-flow' ) ),
);

$cflow_active   = 'cflow-settings';
$cflow_title    = __( 'Configurações', 'comment-flow' );
$cflow_subtitle = __( 'Conecte o app da Meta e a sua conta do Instagram.', 'comment-flow' );
$cflow_actions  = '';

$step = static function ( $number, $title, $done ) {
	?>
	<div class="mb-6 flex items-center gap-4">
		<span class="<?php echo $done ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30' : 'border border-solid border-neutral-700 text-neutral-400'; ?> flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
			<?php if ( $done ) : ?>
				<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
			<?php else : ?>
				<?php echo (int) $number; ?>
			<?php endif; ?>
		</span>
		<h2 class="m-0 p-0 text-lg font-semibold text-white"><?php echo esc_html( $title ); ?></h2>
	</div>
	<?php
};
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-4xl pb-16">
	<?php require CFLOW_DIR . 'admin/views/partials/header.php'; ?>

	<?php if ( ! is_ssl() && 0 !== strpos( home_url(), 'https://' ) ) : ?>
		<div class="mb-6 rounded-xl border border-solid border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php esc_html_e( 'Seu site não usa HTTPS. A Meta só entrega webhooks e aceita redirecionamentos em HTTPS.', 'comment-flow' ); ?></div>
	<?php endif; ?>
	<?php if ( '' === get_option( 'permalink_structure' ) ) : ?>
		<div class="mb-6 rounded-xl border border-solid border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php esc_html_e( 'Os links permanentes estão no modo "Simples". Ative um modo amigável (ex.: "Nome do post") para que as URLs abaixo não tenham "?rest_route=".', 'comment-flow' ); ?></div>
	<?php endif; ?>

	<div class="space-y-6">
		<!-- 1. Dados para a Meta -->
		<section class="<?php echo esc_attr( CFlow_Admin::CARD ); ?>">
			<?php $step( 1, __( 'Dados para cadastrar no app da Meta', 'comment-flow' ), true ); ?>
			<div class="space-y-5">
				<?php foreach ( $meta_fields as $field ) : ?>
					<div>
						<label for="<?php echo esc_attr( $field[0] ); ?>" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php echo esc_html( $field[1] ); ?></label>
						<div class="mt-2 flex gap-2">
							<input type="text" id="<?php echo esc_attr( $field[0] ); ?>" readonly class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> font-mono text-xs text-blue-200" value="<?php echo esc_attr( $field[2] ); ?>" onclick="this.select()">
							<button type="button" class="<?php echo esc_attr( CFlow_Admin::BTN_SECONDARY ); ?> shrink-0" data-cflow-copy="<?php echo esc_attr( $field[0] ); ?>"><?php esc_html_e( 'Copiar', 'comment-flow' ); ?></button>
						</div>
						<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php echo esc_html( $field[3] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- 2. Credenciais -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( CFlow_Admin::CARD ); ?>">
			<?php $step( 2, __( 'Credenciais do app', 'comment-flow' ), $has_creds ); ?>
			<input type="hidden" name="action" value="cflow_save_settings">
			<input type="hidden" name="verify_token" value="<?php echo esc_attr( $settings['verify_token'] ); ?>">
			<?php wp_nonce_field( 'cflow_save_settings' ); ?>

			<div class="grid gap-6 md:grid-cols-2">
				<div>
					<label for="cflow-app-id" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Instagram App ID', 'comment-flow' ); ?></label>
					<input type="text" id="cflow-app-id" name="app_id" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" value="<?php echo esc_attr( $settings['app_id'] ); ?>" autocomplete="off">
				</div>
				<div>
					<label for="cflow-app-secret" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Instagram App Secret', 'comment-flow' ); ?></label>
					<input type="password" id="cflow-app-secret" name="app_secret" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" value="" autocomplete="new-password" placeholder="<?php echo $settings['app_secret'] ? esc_attr__( '•••••••• (salvo — deixe vazio para manter)', 'comment-flow' ) : ''; ?>">
				</div>
			</div>
			<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Use o ID e a chave secreta do app do Instagram (em "Configuração da API com login do Instagram"), não os do app do Facebook.', 'comment-flow' ); ?></p>

			<div class="mt-6 max-w-[200px]">
				<label for="cflow-delay" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Atraso antes de responder', 'comment-flow' ); ?></label>
				<div class="mt-2 flex items-center gap-2">
					<input type="number" id="cflow-delay" name="delay_seconds" min="0" max="300" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?>" value="<?php echo (int) $settings['delay_seconds']; ?>">
					<span class="text-sm text-neutral-400"><?php esc_html_e( 'segundos', 'comment-flow' ); ?></span>
				</div>
			</div>
			<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Um pequeno atraso deixa as respostas mais naturais.', 'comment-flow' ); ?></p>

			<div class="mt-6 border-0 border-t border-solid border-neutral-900 pt-6">
				<button type="submit" class="<?php echo esc_attr( CFlow_Admin::BTN_PRIMARY ); ?>"><?php esc_html_e( 'Salvar credenciais', 'comment-flow' ); ?></button>
			</div>
		</form>

		<!-- 3. Conta -->
		<section class="<?php echo esc_attr( CFlow_Admin::CARD ); ?>">
			<?php $step( 3, __( 'Conta do Instagram', 'comment-flow' ), $connected ); ?>
			<?php if ( $connected ) : ?>
				<div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-solid border-blue-500/30 bg-blue-500/5 p-4">
					<div class="flex items-center gap-3">
						<span class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-base font-semibold text-white"><?php echo esc_html( strtoupper( mb_substr( (string) $settings['ig_username'], 0, 1 ) ) ); ?></span>
						<div>
							<p class="text-sm font-semibold text-white">@<?php echo esc_html( $settings['ig_username'] ); ?></p>
							<p class="mt-0.5 text-xs text-neutral-400">
								<?php
								/* translators: 1: ID 2: dias */
								echo esc_html( sprintf( __( 'ID %1$s · token renova sozinho (expira em %2$d dias)', 'comment-flow' ), $settings['ig_user_id'], $days_left ) );
								?>
							</p>
						</div>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cflow_disconnect">
						<?php wp_nonce_field( 'cflow_disconnect' ); ?>
						<button type="submit" class="<?php echo esc_attr( CFlow_Admin::BTN_SECONDARY ); ?>"><?php esc_html_e( 'Desconectar', 'comment-flow' ); ?></button>
					</form>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cflow_connect">
					<?php wp_nonce_field( 'cflow_connect' ); ?>
					<button type="submit" class="<?php echo esc_attr( CFlow_Admin::BTN_PRIMARY ); ?>" <?php disabled( ! $has_creds ); ?>>
						<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
						<?php esc_html_e( 'Conectar com o Instagram', 'comment-flow' ); ?>
					</button>
				</form>
				<?php if ( ! $has_creds ) : ?>
					<p class="mt-3 text-xs text-red-400"><?php esc_html_e( 'Preencha e salve o Instagram App ID e o Instagram App Secret (passo 2) para liberar este botão.', 'comment-flow' ); ?></p>
				<?php endif; ?>
				<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'A conta precisa ser Profissional (Empresa ou Criador de conteúdo).', 'comment-flow' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
</div></div>
<script>
document.querySelectorAll('[data-cflow-copy]').forEach(function (button) {
	button.addEventListener('click', function () {
		var input = document.getElementById(button.getAttribute('data-cflow-copy'));
		var label = button.textContent;
		var done  = function () {
			button.textContent = <?php echo wp_json_encode( __( 'Copiado!', 'comment-flow' ) ); ?>;
			setTimeout(function () { button.textContent = label; }, 1500);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(input.value).then(done);
		} else {
			input.select();
			document.execCommand('copy');
			done();
		}
	});
});
</script>
