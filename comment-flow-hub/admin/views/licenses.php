<?php
/**
 * @var array        $licenses
 * @var string|false $new_key
 */

defined( 'ABSPATH' ) || exit;

$hub_active   = 'cfhub';
$hub_title    = __( 'Licenças', 'comment-flow-hub' );
$hub_subtitle = __( 'Uma licença por site de cliente. O cliente cola a chave no plugin Comment Flow e conecta o Instagram dele.', 'comment-flow-hub' );
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-7xl pb-16">
	<?php require CFHUB_DIR . 'admin/views/partials/header.php'; ?>

	<?php if ( $new_key ) : ?>
		<div class="mb-6 rounded-2xl border border-solid border-blue-500/40 bg-blue-500/10 p-5">
			<p class="text-sm font-semibold text-white"><?php esc_html_e( 'Chave da nova licença', 'comment-flow-hub' ); ?></p>
			<div class="mt-3 flex flex-wrap gap-2">
				<div class="w-full max-w-sm">
					<input type="text" id="cfhub-new-key" readonly value="<?php echo esc_attr( $new_key ); ?>" class="<?php echo esc_attr( CFHub_Admin::INPUT ); ?> font-mono text-base tracking-wider text-blue-200" onclick="this.select()">
				</div>
				<button type="button" class="<?php echo esc_attr( CFHub_Admin::BTN_PRIMARY ); ?>" data-cfhub-copy="cfhub-new-key"><?php esc_html_e( 'Copiar', 'comment-flow-hub' ); ?></button>
			</div>
			<p class="<?php echo esc_attr( CFHub_Admin::HELP ); ?>"><?php esc_html_e( 'Envie ao cliente. Ele cola em Comment Flow → Configurações → Licença.', 'comment-flow-hub' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( CFHub_Admin::CARD ); ?> mb-8 flex flex-wrap items-end gap-3">
		<input type="hidden" name="action" value="cfhub_create_license">
		<?php wp_nonce_field( 'cfhub_create_license' ); ?>
		<div class="min-w-[260px] flex-1">
			<label for="cfhub-client" class="<?php echo esc_attr( CFHub_Admin::LABEL ); ?>"><?php esc_html_e( 'Nova licença', 'comment-flow-hub' ); ?></label>
			<input type="text" id="cfhub-client" name="client_name" class="<?php echo esc_attr( CFHub_Admin::INPUT ); ?> mt-2" placeholder="<?php esc_attr_e( 'Nome do cliente (ex.: Clínica Sorriso)', 'comment-flow-hub' ); ?>" required>
		</div>
		<button type="submit" class="<?php echo esc_attr( CFHub_Admin::BTN_PRIMARY ); ?>"><span class="text-base leading-none">+</span><?php esc_html_e( 'Gerar chave', 'comment-flow-hub' ); ?></button>
	</form>

	<div class="overflow-hidden rounded-2xl border border-solid border-neutral-800 bg-neutral-950">
		<div class="overflow-x-auto">
			<table class="w-full border-collapse">
				<thead class="border-0 border-b border-solid border-neutral-800 bg-black/40">
					<tr>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Cliente', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Chave', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Site', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Instagram', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Último evento', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Status', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?> text-right"><?php esc_html_e( 'Ações', 'comment-flow-hub' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $licenses ) ) : ?>
					<tr><td colspan="7" class="px-4 py-16 text-center text-sm text-neutral-500"><?php esc_html_e( 'Nenhuma licença ainda. Gere a primeira acima.', 'comment-flow-hub' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $licenses as $l ) : ?>
					<tr class="border-0 border-b border-solid border-neutral-900 last:border-b-0 hover:bg-white/[0.02]">
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?> font-semibold text-white"><?php echo esc_html( $l['client_name'] ); ?></td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?>"><code><?php echo esc_html( $l['license_key'] ); ?></code></td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?>">
							<?php if ( '' !== $l['site_url'] ) : ?>
								<a class="<?php echo esc_attr( CFHub_Admin::LINK ); ?>" href="<?php echo esc_url( $l['site_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( CFHub_Licenses::host( $l['site_url'] ) ); ?></a>
							<?php else : ?>
								<span class="text-neutral-600"><?php esc_html_e( 'não ativada', 'comment-flow-hub' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?>"><?php echo $l['ig_username'] ? '<span class="text-white">@' . esc_html( $l['ig_username'] ) . '</span>' : '<span class="text-neutral-600">—</span>'; ?></td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?> whitespace-nowrap text-xs text-neutral-500"><?php echo $l['last_delivery_at'] ? esc_html( $l['last_delivery_at'] . ' UTC' ) : '—'; ?></td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?>">
							<?php
							if ( 'active' !== $l['status'] ) {
								echo CFHub_Admin::badge( 'red', __( 'Desativada', 'comment-flow-hub' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							} elseif ( '' === $l['site_url'] ) {
								echo CFHub_Admin::badge( 'gray', __( 'Aguardando ativação', 'comment-flow-hub' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							} elseif ( ! $l['ig_username'] ) {
								echo CFHub_Admin::badge( 'white', __( 'Sem Instagram', 'comment-flow-hub' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							} else {
								echo CFHub_Admin::badge( 'blue', __( 'Ativa', 'comment-flow-hub' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
							?>
						</td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?> whitespace-nowrap text-right">
							<div class="inline-flex items-center gap-3 text-xs">
								<?php if ( 'active' === $l['status'] ) : ?>
									<a class="font-medium text-neutral-300 hover:text-white" href="<?php echo esc_url( CFHub_Admin::action_url( $l['id'], 'revoke' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Desativar a licença? O site do cliente para de receber comentários e mensagens.', 'comment-flow-hub' ) ); ?>');"><?php esc_html_e( 'Desativar', 'comment-flow-hub' ); ?></a>
								<?php else : ?>
									<a class="<?php echo esc_attr( CFHub_Admin::LINK ); ?>" href="<?php echo esc_url( CFHub_Admin::action_url( $l['id'], 'restore' ) ); ?>"><?php esc_html_e( 'Reativar', 'comment-flow-hub' ); ?></a>
								<?php endif; ?>
								<?php if ( '' !== $l['site_url'] ) : ?>
									<a class="font-medium text-neutral-300 hover:text-white" href="<?php echo esc_url( CFHub_Admin::action_url( $l['id'], 'release' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Liberar o site? A chave poderá ser ativada em outro endereço e o site atual perde a conexão.', 'comment-flow-hub' ) ); ?>');"><?php esc_html_e( 'Liberar site', 'comment-flow-hub' ); ?></a>
								<?php endif; ?>
								<a class="font-medium text-red-400 hover:text-red-300" href="<?php echo esc_url( CFHub_Admin::action_url( $l['id'], 'delete' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Excluir esta licença definitivamente?', 'comment-flow-hub' ) ); ?>');"><?php esc_html_e( 'Excluir', 'comment-flow-hub' ); ?></a>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
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
