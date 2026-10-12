<?php
/**
 * @var array $leads
 * @var int   $total
 * @var int   $paged
 * @var int   $per_page
 * @var array $filters
 * @var array $automations
 * @var array $names
 */

defined( 'ABSPATH' ) || exit;

$pages = max( 1, (int) ceil( $total / $per_page ) );

$cflow_active   = 'cflow-leads';
$cflow_title    = __( 'Leads', 'comment-flow' );
$cflow_subtitle = sprintf(
	/* translators: %s: quantidade de leads */
	_n( '%s pessoa interagiu com suas automações.', '%s pessoas interagiram com suas automações.', $total, 'comment-flow' ),
	number_format_i18n( $total )
);
$cflow_actions  = sprintf(
	'<a href="%1$s" class="%2$s"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.6L6.3 8.24a.75.75 0 1 0-1.1 1.02l4.25 4.5a.75.75 0 0 0 1.1 0l4.25-4.5a.75.75 0 0 0-1.1-1.02l-2.95 3.11v-8.6Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>%3$s</a>',
	esc_url( CFlow_Admin::export_url( $filters['automation_id'], $filters['search'] ) ),
	esc_attr( CFlow_Admin::BTN_PRIMARY ),
	$filters['automation_id'] ? esc_html__( 'Exportar CSV desta automação', 'comment-flow' ) : esc_html__( 'Exportar CSV', 'comment-flow' )
);
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-7xl pb-16">
	<?php require CFLOW_DIR . 'admin/views/partials/header.php'; ?>

	<form method="get" class="mb-6 flex flex-wrap items-center gap-3">
		<input type="hidden" name="page" value="cflow-leads">
		<div class="w-full sm:w-64">
		<select name="automation_id" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?>">
			<option value="0"><?php esc_html_e( 'Todas as automações', 'comment-flow' ); ?></option>
			<?php foreach ( $automations as $a ) : ?>
				<option value="<?php echo (int) $a['id']; ?>" <?php selected( (int) $a['id'], $filters['automation_id'] ); ?>><?php echo esc_html( $a['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		</div>
		<div class="relative min-w-[260px] flex-1 sm:max-w-sm">
			<svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
			<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> pl-9" placeholder="<?php esc_attr_e( '@usuário, e-mail ou telefone', 'comment-flow' ); ?>">
		</div>
		<button type="submit" class="<?php echo esc_attr( CFlow_Admin::BTN_SECONDARY ); ?>"><?php esc_html_e( 'Filtrar', 'comment-flow' ); ?></button>
	</form>

	<div class="overflow-hidden rounded-2xl border border-solid border-neutral-800 bg-neutral-950">
		<div class="overflow-x-auto">
			<table class="w-full border-collapse">
				<thead class="border-0 border-b border-solid border-neutral-800 bg-black/40">
					<tr>
						<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Usuário', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Contato', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Automação', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Último comentário', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Captura', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><?php esc_html_e( 'Primeiro contato', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_Admin::TH ); ?>"><span class="sr-only"><?php esc_html_e( 'Ações', 'comment-flow' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $leads ) ) : ?>
					<tr><td colspan="7" class="px-4 py-16 text-center text-sm text-neutral-500"><?php esc_html_e( 'Nenhum lead encontrado.', 'comment-flow' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $leads as $lead ) : ?>
					<?php $initial = strtoupper( mb_substr( '' !== $lead['username'] ? $lead['username'] : '?', 0, 1 ) ); ?>
					<tr class="border-0 border-b border-solid border-neutral-900 transition-colors last:border-b-0 hover:bg-white/[0.02]">
						<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?>">
							<div class="flex items-center gap-3">
								<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-sm font-semibold text-white"><?php echo esc_html( $initial ); ?></span>
								<?php if ( '' !== $lead['username'] ) : ?>
									<a href="<?php echo esc_url( 'https://instagram.com/' . rawurlencode( $lead['username'] ) ); ?>" target="_blank" rel="noopener noreferrer" class="font-semibold text-white hover:text-blue-300">@<?php echo esc_html( $lead['username'] ); ?></a>
								<?php else : ?>
									<code><?php echo esc_html( $lead['comment_from_id'] ); ?></code>
								<?php endif; ?>
							</div>
						</td>
						<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?>">
							<div class="space-y-1">
								<?php if ( '' !== $lead['email'] ) : ?>
									<a class="block text-white hover:text-blue-300" href="<?php echo esc_url( 'mailto:' . $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a>
								<?php endif; ?>
								<?php if ( '' !== $lead['phone'] ) : ?>
									<a class="block text-blue-400 hover:text-blue-300" href="<?php echo esc_url( 'https://wa.me/' . ltrim( $lead['phone'], '+' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $lead['phone'] ); ?></a>
								<?php endif; ?>
								<?php if ( '' === $lead['email'] && '' === $lead['phone'] ) : ?>
									<span class="text-neutral-600">—</span>
								<?php endif; ?>
							</div>
						</td>
						<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?>">
							<a class="text-neutral-300 hover:text-blue-300" href="<?php echo esc_url( add_query_arg( array( 'page' => 'cflow-leads', 'automation_id' => (int) $lead['automation_id'] ), admin_url( 'admin.php' ) ) ); ?>">
								<?php echo esc_html( isset( $names[ $lead['automation_id'] ] ) ? $names[ $lead['automation_id'] ] : '#' . $lead['automation_id'] ); ?>
							</a>
						</td>
						<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?> max-w-xs">
							<span class="text-neutral-300"><?php echo esc_html( wp_trim_words( (string) $lead['last_comment'], 10 ) ); ?></span>
							<?php if ( (int) $lead['comments_count'] > 1 ) : ?>
								<span class="ml-1 text-xs text-neutral-500"><?php echo (int) $lead['comments_count']; ?>×</span>
							<?php endif; ?>
						</td>
						<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?>">
							<?php echo CFlow_Admin::badge( $lead['capture_step'], CFlow_Leads::step_label( $lead['capture_step'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php if ( ! empty( $lead['last_error'] ) ) : ?>
								<p class="mt-2 max-w-[220px] text-xs text-red-400"><?php echo esc_html( $lead['last_error'] ); ?></p>
							<?php endif; ?>
						</td>
						<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?> whitespace-nowrap text-xs text-neutral-500"><?php echo esc_html( $lead['created_at'] ); ?> UTC</td>
						<td class="<?php echo esc_attr( CFlow_Admin::TD ); ?> text-right">
							<a class="text-xs font-medium text-red-400 hover:text-red-300" href="<?php echo esc_url( CFlow_Admin::action_url( 'cflow_delete_lead', $lead['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Excluir este lead e seus dados? Use para atender pedidos de exclusão (LGPD).', 'comment-flow' ) ); ?>');"><?php esc_html_e( 'Excluir', 'comment-flow' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="mt-4 flex flex-wrap items-center justify-between gap-4">
		<p class="text-xs text-neutral-500"><?php esc_html_e( 'O CSV usa ";" como separador e abre direto no Excel. Ele respeita o filtro e a busca acima.', 'comment-flow' ); ?></p>
		<?php if ( $pages > 1 ) : ?>
			<div>
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '←',
							'next_text' => '→',
						)
					)
				);
				?>
			</div>
		<?php endif; ?>
	</div>
</div></div>
