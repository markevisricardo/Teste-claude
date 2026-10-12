<?php
/**
 * @var array $logs
 * @var int   $total
 * @var int   $paged
 * @var int   $per_page
 * @var array $names
 */

defined( 'ABSPATH' ) || exit;

$pages = max( 1, (int) ceil( $total / $per_page ) );

$cflow_active   = 'logs';
$cflow_title    = __( 'Logs de comentários', 'comment-flow' );
$cflow_subtitle = CFlow_Queue::uses_action_scheduler()
	? __( 'Fila: Action Scheduler. Logs com mais de 90 dias são apagados automaticamente.', 'comment-flow' )
	: __( 'Fila: WP-Cron. Para mais confiabilidade, instale o Action Scheduler ou configure um cron real no servidor.', 'comment-flow' );
$cflow_actions  = '';

$check = '<svg class="h-4 w-4 text-blue-400" viewBox="0 0 20 20" fill="currentColor" aria-label="' . esc_attr__( 'Enviado', 'comment-flow' ) . '"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>';
$dash  = '<span class="text-neutral-700">—</span>';
?>
<div class="mx-auto max-w-7xl pb-16">
	<?php require CFLOW_DIR . 'app/views/partials/header.php'; ?>

	<div class="overflow-hidden rounded-2xl border border-solid border-neutral-800 bg-neutral-950">
		<div class="overflow-x-auto">
			<table class="w-full border-collapse">
				<thead class="border-0 border-b border-solid border-neutral-800 bg-black/40">
					<tr>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"><?php esc_html_e( 'Data (UTC)', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"><?php esc_html_e( 'Usuário', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"><?php esc_html_e( 'Comentário', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"><?php esc_html_e( 'Automação', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?> text-center"><?php esc_html_e( 'Resposta', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?> text-center"><?php esc_html_e( 'DM', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"><?php esc_html_e( 'Status', 'comment-flow' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="7" class="px-4 py-16 text-center text-sm text-neutral-500"><?php esc_html_e( 'Nenhum comentário recebido ainda.', 'comment-flow' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr class="border-0 border-b border-solid border-neutral-900 transition-colors last:border-b-0 hover:bg-white/[0.02]">
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?> whitespace-nowrap text-xs tabular-nums text-neutral-500"><?php echo esc_html( $log['created_at'] ); ?></td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?> whitespace-nowrap">
							<?php if ( $log['from_username'] ) : ?>
								<span class="font-semibold text-white">@<?php echo esc_html( $log['from_username'] ); ?></span>
							<?php else : ?>
								<code><?php echo esc_html( $log['from_id'] ); ?></code>
							<?php endif; ?>
						</td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?> max-w-xs"><?php echo esc_html( wp_trim_words( $log['comment_text'], 15 ) ); ?></td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?>">
							<?php echo $log['automation_id'] ? esc_html( isset( $names[ $log['automation_id'] ] ) ? $names[ $log['automation_id'] ] : '#' . $log['automation_id'] ) : $dash; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?>"><div class="flex justify-center"><?php echo $log['public_reply_sent'] ? $check : $dash; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?>"><div class="flex justify-center"><?php echo $log['dm_sent'] ? $check : $dash; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?>">
							<?php echo CFlow_App::badge( $log['status'], CFlow_App::status_label( $log['status'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php if ( ! empty( $log['error'] ) ) : ?>
								<p class="mt-2 max-w-[260px] text-xs text-red-400"><?php echo esc_html( $log['error'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php if ( $pages > 1 ) : ?>
		<div class="mt-4 flex justify-end">
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
