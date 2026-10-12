<?php
/**
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

$hub_active   = 'cfhub-settings';
$hub_title    = __( 'Configurações', 'comment-flow-hub' );
$hub_subtitle = __( 'O app central da Meta, usado por todos os clientes.', 'comment-flow-hub' );

$fields = array(
	array( 'cfhub-redirect', __( 'URL de redirecionamento OAuth', 'comment-flow-hub' ), CFHub_Settings::url( '/oauth' ), __( 'Em "Configurar o login da empresa no Instagram".', 'comment-flow-hub' ) ),
	array( 'cfhub-webhook', __( 'URL de callback do webhook', 'comment-flow-hub' ), CFHub_Settings::url( '/webhook' ), __( 'Em "Configurar webhooks". Assine os campos comments e messages.', 'comment-flow-hub' ) ),
	array( 'cfhub-verify', __( 'Token de verificação', 'comment-flow-hub' ), $settings['verify_token'], __( 'Em "Configurar webhooks", campo "Verificar token".', 'comment-flow-hub' ) ),
	array( 'cfhub-deauth', __( 'URL de retorno de chamada para cancelar autorização', 'comment-flow-hub' ), CFHub_Settings::url( '/deauthorize' ), __( 'Em "Configurações do login da empresa". Obrigatória para a análise do app.', 'comment-flow-hub' ) ),
	array( 'cfhub-deletion', __( 'URL de solicitação de exclusão de dados', 'comment-flow-hub' ), CFHub_Settings::url( '/data-deletion' ), __( 'Em "Configurações do login da empresa". Obrigatória para a análise do app.', 'comment-flow-hub' ) ),
);
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-4xl pb-16">
	<?php require CFHUB_DIR . 'admin/views/partials/header.php'; ?>

	<div class="space-y-6">
		<section class="<?php echo esc_attr( CFHub_Admin::CARD ); ?>">
			<h2 class="m-0 mb-6 p-0 text-lg font-semibold text-white"><?php esc_html_e( '1. Dados para cadastrar no app da Meta', 'comment-flow-hub' ); ?></h2>
			<div class="space-y-5">
				<?php foreach ( $fields as $field ) : ?>
					<div>
						<label for="<?php echo esc_attr( $field[0] ); ?>" class="<?php echo esc_attr( CFHub_Admin::LABEL ); ?>"><?php echo esc_html( $field[1] ); ?></label>
						<div class="mt-2 flex gap-2">
							<input type="text" id="<?php echo esc_attr( $field[0] ); ?>" readonly class="<?php echo esc_attr( CFHub_Admin::INPUT ); ?> font-mono text-xs text-blue-200" value="<?php echo esc_attr( $field[2] ); ?>" onclick="this.select()">
							<button type="button" class="<?php echo esc_attr( CFHub_Admin::BTN_SECONDARY ); ?> shrink-0" data-cfhub-copy="<?php echo esc_attr( $field[0] ); ?>"><?php esc_html_e( 'Copiar', 'comment-flow-hub' ); ?></button>
						</div>
						<p class="<?php echo esc_attr( CFHub_Admin::HELP ); ?>"><?php echo esc_html( $field[3] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( CFHub_Admin::CARD ); ?>">
			<h2 class="m-0 mb-6 p-0 text-lg font-semibold text-white"><?php esc_html_e( '2. Credenciais do app central', 'comment-flow-hub' ); ?></h2>
			<input type="hidden" name="action" value="cfhub_save_settings">
			<?php wp_nonce_field( 'cfhub_save_settings' ); ?>
			<div class="grid gap-6 md:grid-cols-2">
				<div>
					<label for="cfhub-app-id" class="<?php echo esc_attr( CFHub_Admin::LABEL ); ?>"><?php esc_html_e( 'Instagram App ID', 'comment-flow-hub' ); ?></label>
					<input type="text" id="cfhub-app-id" name="app_id" class="<?php echo esc_attr( CFHub_Admin::INPUT ); ?> mt-2" value="<?php echo esc_attr( $settings['app_id'] ); ?>" autocomplete="off">
				</div>
				<div>
					<label for="cfhub-app-secret" class="<?php echo esc_attr( CFHub_Admin::LABEL ); ?>"><?php esc_html_e( 'Instagram App Secret', 'comment-flow-hub' ); ?></label>
					<input type="password" id="cfhub-app-secret" name="app_secret" class="<?php echo esc_attr( CFHub_Admin::INPUT ); ?> mt-2" value="" autocomplete="new-password" placeholder="<?php echo $settings['app_secret'] ? esc_attr__( '•••••••• (salvo — deixe vazio para manter)', 'comment-flow-hub' ) : ''; ?>">
				</div>
			</div>
			<p class="<?php echo esc_attr( CFHub_Admin::HELP ); ?>"><?php esc_html_e( 'Crie um app NOVO só para o hub (não reaproveite o app de um site): um app tem uma única URL de webhook.', 'comment-flow-hub' ); ?></p>
			<div class="mt-6 border-0 border-t border-solid border-neutral-900 pt-6">
				<button type="submit" class="<?php echo esc_attr( CFHub_Admin::BTN_PRIMARY ); ?>"><?php esc_html_e( 'Salvar credenciais', 'comment-flow-hub' ); ?></button>
			</div>
		</form>

		<section class="rounded-2xl border border-solid border-neutral-800 p-6 text-sm leading-6 text-neutral-400">
			<h2 class="m-0 mb-3 p-0 text-base font-semibold text-white"><?php esc_html_e( '3. Para atender qualquer cliente: análise do app', 'comment-flow-hub' ); ?></h2>
			<p><?php esc_html_e( 'Enquanto o app não for aprovado, só funcionam contas adicionadas como "Testador do Instagram" no app. Para liberar para qualquer conta:', 'comment-flow-hub' ); ?></p>
			<ol class="mt-3 list-decimal space-y-1 pl-5">
				<li><?php esc_html_e( 'Verifique a empresa no Gerenciador de Negócios da Meta.', 'comment-flow-hub' ); ?></li>
				<li><?php esc_html_e( 'Preencha a política de privacidade, os termos e as duas URLs obrigatórias acima.', 'comment-flow-hub' ); ?></li>
				<li><?php esc_html_e( 'Peça Acesso Avançado para instagram_business_basic, instagram_business_manage_comments e instagram_business_manage_messages, com um vídeo mostrando o fluxo.', 'comment-flow-hub' ); ?></li>
			</ol>
		</section>
	</div>
</div></div>
<script>
document.querySelectorAll('[data-cfhub-copy]').forEach(function (button) {
	button.addEventListener('click', function () {
		var input = document.getElementById(button.getAttribute('data-cfhub-copy'));
		input.select();
		(navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(input.value) : Promise.resolve(document.execCommand('copy'))).then(function () {
			var label = button.textContent;
			button.textContent = <?php echo wp_json_encode( __( 'Copiado!', 'comment-flow-hub' ) ); ?>;
			setTimeout(function () { button.textContent = label; }, 1500);
		});
	});
});
</script>
