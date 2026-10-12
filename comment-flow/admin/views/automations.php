<?php
/**
 * @var array $automations
 * @var array $stats
 */

defined( 'ABSPATH' ) || exit;

$totals = array( 'matched' => 0, 'dms' => 0, 'leads' => 0, 'emails' => 0 );
foreach ( $stats as $row ) {
	foreach ( $totals as $key => $value ) {
		$totals[ $key ] += isset( $row[ $key ] ) ? (int) $row[ $key ] : 0;
	}
}

$cflow_active   = 'cflow';
$cflow_title    = __( 'Automações', 'comment-flow' );
$cflow_subtitle = __( 'Responda comentários e envie DMs automaticamente por palavra-chave.', 'comment-flow' );
$cflow_actions  = sprintf(
	'<a href="%1$s" class="%2$s"><span class="text-base leading-none">+</span>%3$s</a>',
	esc_url( admin_url( 'admin.php?page=cflow-edit' ) ),
	esc_attr( CFlow_Admin::BTN_PRIMARY ),
	esc_html__( 'Nova automação', 'comment-flow' )
);
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-7xl pb-16">
	<?php require CFLOW_DIR . 'admin/views/partials/header.php'; ?>

	<?php if ( ! CFlow_Settings::is_connected() ) : ?>
		<div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-solid border-blue-500/30 bg-blue-500/10 px-4 py-3 text-sm text-blue-100">
			<span><?php esc_html_e( 'Nenhuma conta do Instagram conectada.', 'comment-flow' ); ?></span>
			<a class="<?php echo esc_attr( CFlow_Admin::LINK ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-settings' ) ); ?>"><?php esc_html_e( 'Conectar agora →', 'comment-flow' ); ?></a>
		</div>
	<?php endif; ?>

	<div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
		<?php
		$cards = array(
			array( __( 'Comentários respondidos', 'comment-flow' ), $totals['matched'] ),
			array( __( 'DMs enviadas', 'comment-flow' ), $totals['dms'] ),
			array( __( 'Leads', 'comment-flow' ), $totals['leads'] ),
			array( __( 'E-mails capturados', 'comment-flow' ), $totals['emails'] ),
		);
		foreach ( $cards as $i => $card ) :
			?>
			<div class="rounded-2xl border border-solid border-neutral-800 bg-neutral-950 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-neutral-500"><?php echo esc_html( $card[0] ); ?></p>
				<p class="<?php echo 0 === $i ? 'text-blue-400' : 'text-white'; ?> mt-2 text-3xl font-semibold tabular-nums tracking-tight"><?php echo esc_html( number_format_i18n( $card[1] ) ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( empty( $automations ) ) : ?>
		<div class="flex flex-col items-center rounded-2xl border border-dashed border-neutral-800 px-6 py-16 text-center">
			<span class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600/15 text-blue-400">
				<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
			</span>
			<p class="text-base font-semibold text-white"><?php esc_html_e( 'Nenhuma automação ainda', 'comment-flow' ); ?></p>
			<p class="mt-1 max-w-sm text-sm text-neutral-400"><?php esc_html_e( 'Crie a primeira: escolha um post, as palavras-chave e a mensagem que vai no direct.', 'comment-flow' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-edit' ) ); ?>" class="<?php echo esc_attr( CFlow_Admin::BTN_PRIMARY ); ?> mt-6"><?php esc_html_e( 'Criar automação', 'comment-flow' ); ?></a>
		</div>
	<?php else : ?>
		<div class="overflow-hidden rounded-2xl border border-solid border-neutral-800 bg-neutral-950">
			<div class="overflow-x-auto">
				<table class="w-full border-collapse">
					<thead class="border-0 border-b border-solid border-neutral-800 bg-black/40">
						<tr>
							<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Automação', 'comment-flow' ); ?></th>
							<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Palavras-chave', 'comment-flow' ); ?></th>
							<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?> text-right"><?php esc_html_e( 'Comentários', 'comment-flow' ); ?></th>
							<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?> text-right"><?php esc_html_e( 'DMs', 'comment-flow' ); ?></th>
							<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?> text-right"><?php esc_html_e( 'Leads', 'comment-flow' ); ?></th>
							<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?> text-right"><?php esc_html_e( 'E-mails', 'comment-flow' ); ?></th>
							<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Status', 'comment-flow' ); ?></th>
							<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?> text-right"><?php esc_html_e( 'Ações', 'comment-flow' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $automations as $a ) : ?>
						<?php
						$s         = isset( $stats[ (int) $a['id'] ] ) ? $stats[ (int) $a['id'] ] : array();
						$s        += array( 'matched' => 0, 'dms' => 0, 'leads' => 0, 'emails' => 0 );
						$edit_url  = admin_url( 'admin.php?page=cflow-edit&id=' . (int) $a['id'] );
						$leads_url = add_query_arg( array( 'page' => 'cflow-leads', 'automation_id' => (int) $a['id'] ), admin_url( 'admin.php' ) );
						$keywords  = CFlow_Matcher::split_lines( $a['keywords'], ',' );
						?>
						<tr class="border-0 border-b border-solid border-neutral-900 transition-colors last:border-b-0 hover:bg-white/[0.02]">
							<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?>">
								<a href="<?php echo esc_url( $edit_url ); ?>" class="font-semibold text-white hover:text-blue-300"><?php echo esc_html( $a['name'] ); ?></a>
								<p class="mt-1 text-xs text-neutral-500">
									<?php
									echo '' === $a['media_id']
										? esc_html__( 'Qualquer post ou reel', 'comment-flow' )
										: esc_html( sprintf( /* translators: %s: ID da mídia */ __( 'Post %s', 'comment-flow' ), $a['media_id'] ) );
									?>
									· <?php echo esc_html( sprintf( /* translators: %d: prioridade */ __( 'prioridade %d', 'comment-flow' ), (int) $a['priority'] ) ); ?>
								</p>
							</td>
							<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?>">
								<?php if ( empty( $keywords ) ) : ?>
									<span class="text-xs italic text-neutral-500"><?php esc_html_e( 'qualquer comentário', 'comment-flow' ); ?></span>
								<?php else : ?>
									<div class="flex max-w-xs flex-wrap gap-1.5">
										<?php foreach ( $keywords as $keyword ) : ?>
											<span class="rounded-md bg-neutral-900 px-2 py-0.5 text-xs text-neutral-200 ring-1 ring-inset ring-neutral-800"><?php echo esc_html( $keyword ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</td>
							<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?> text-right tabular-nums"><?php echo esc_html( number_format_i18n( $s['matched'] ) ); ?></td>
							<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?> text-right tabular-nums"><?php echo esc_html( number_format_i18n( $s['dms'] ) ); ?></td>
							<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?> text-right tabular-nums"><a class="<?php echo esc_attr( CFlow_Admin::LINK ); ?>" href="<?php echo esc_url( $leads_url ); ?>"><?php echo esc_html( number_format_i18n( $s['leads'] ) ); ?></a></td>
							<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?> text-right tabular-nums"><?php echo esc_html( number_format_i18n( $s['emails'] ) ); ?></td>
							<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?>">
								<?php echo CFlow_Admin::badge( $a['status'], 'active' === $a['status'] ? __( 'Ativa', 'comment-flow' ) : __( 'Pausada', 'comment-flow' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</td>
							<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?> whitespace-nowrap text-right">
								<div class="inline-flex items-center gap-3 text-xs">
									<a class="<?php echo esc_attr( CFlow_Admin::LINK ); ?>" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Editar', 'comment-flow' ); ?></a>
									<a class="<?php echo esc_attr( CFlow_Admin::LINK ); ?>" href="<?php echo esc_url( $leads_url ); ?>"><?php esc_html_e( 'Leads', 'comment-flow' ); ?></a>
									<a class="<?php echo esc_attr( CFlow_Admin::LINK ); ?>" href="<?php echo esc_url( CFlow_Admin::export_url( $a['id'] ) ); ?>"><?php esc_html_e( 'CSV', 'comment-flow' ); ?></a>
									<a class="font-medium text-neutral-400 hover:text-white" href="<?php echo esc_url( CFlow_Admin::action_url( 'cflow_toggle_automation', $a['id'] ) ); ?>">
										<?php echo 'active' === $a['status'] ? esc_html__( 'Pausar', 'comment-flow' ) : esc_html__( 'Ativar', 'comment-flow' ); ?>
									</a>
									<a class="font-medium text-red-400 hover:text-red-300" href="<?php echo esc_url( CFlow_Admin::action_url( 'cflow_delete_automation', $a['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Excluir esta automação?', 'comment-flow' ) ); ?>');"><?php esc_html_e( 'Excluir', 'comment-flow' ); ?></a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<p class="mt-3 text-xs text-neutral-500"><?php esc_html_e( 'Quando mais de uma automação casa com um comentário, vale a de menor número de prioridade.', 'comment-flow' ); ?></p>
	<?php endif; ?>
</div></div>
