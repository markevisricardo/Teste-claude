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
?>
<div class="wrap cflow">
	<h1><?php esc_html_e( 'Logs de comentários', 'comment-flow' ); ?></h1>
	<p class="description">
		<?php
		echo esc_html(
			CFlow_Queue::uses_action_scheduler()
				? __( 'Fila: Action Scheduler.', 'comment-flow' )
				: __( 'Fila: WP-Cron. Para mais confiabilidade, instale o Action Scheduler ou configure um cron real no servidor.', 'comment-flow' )
		);
		?>
		<?php esc_html_e( 'Logs com mais de 90 dias são apagados automaticamente.', 'comment-flow' ); ?>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Data (UTC)', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Usuário', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Comentário', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Automação', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Resposta', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'DM', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Status', 'comment-flow' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $logs ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'Nenhum comentário recebido ainda.', 'comment-flow' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $logs as $log ) : ?>
			<tr>
				<td><?php echo esc_html( $log['created_at'] ); ?></td>
				<td><?php echo $log['from_username'] ? '@' . esc_html( $log['from_username'] ) : '<code>' . esc_html( $log['from_id'] ) . '</code>'; ?></td>
				<td><?php echo esc_html( wp_trim_words( $log['comment_text'], 15 ) ); ?></td>
				<td><?php echo $log['automation_id'] ? esc_html( isset( $names[ $log['automation_id'] ] ) ? $names[ $log['automation_id'] ] : '#' . $log['automation_id'] ) : '—'; ?></td>
				<td><?php echo $log['public_reply_sent'] ? '✅' : '—'; ?></td>
				<td><?php echo $log['dm_sent'] ? '✅' : '—'; ?></td>
				<td>
					<span class="cflow-badge cflow-badge--<?php echo esc_attr( $log['status'] ); ?>"><?php echo esc_html( CFlow_Admin::status_label( $log['status'] ) ); ?></span>
					<?php if ( ! empty( $log['error'] ) ) : ?>
						<div class="cflow-error"><?php echo esc_html( $log['error'] ); ?></div>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
</div>
