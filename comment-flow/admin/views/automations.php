<?php
/**
 * @var array $automations
 * @var array $stats
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cflow">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Automações', 'comment-flow' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Nova automação', 'comment-flow' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( ! CFlow_Settings::is_connected() ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'Nenhuma conta do Instagram conectada.', 'comment-flow' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-settings' ) ); ?>"><?php esc_html_e( 'Conectar agora', 'comment-flow' ); ?></a>
		</p></div>
	<?php endif; ?>

	<?php if ( empty( $automations ) ) : ?>
		<div class="cflow-empty">
			<p><?php esc_html_e( 'Você ainda não criou nenhuma automação.', 'comment-flow' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-edit' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Criar a primeira', 'comment-flow' ); ?></a>
		</div>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nome', 'comment-flow' ); ?></th>
					<th><?php esc_html_e( 'Post', 'comment-flow' ); ?></th>
					<th><?php esc_html_e( 'Palavras-chave', 'comment-flow' ); ?></th>
					<th><?php esc_html_e( 'Prioridade', 'comment-flow' ); ?></th>
					<th><?php esc_html_e( 'Comentários', 'comment-flow' ); ?></th>
					<th><?php esc_html_e( 'DMs enviadas', 'comment-flow' ); ?></th>
					<th><?php esc_html_e( 'Leads', 'comment-flow' ); ?></th>
					<th><?php esc_html_e( 'E-mails', 'comment-flow' ); ?></th>
					<th><?php esc_html_e( 'Status', 'comment-flow' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $automations as $a ) : ?>
				<?php $s = isset( $stats[ (int) $a['id'] ] ) ? $stats[ (int) $a['id'] ] : array(); ?>
				<?php $s += array( 'matched' => 0, 'dms' => 0, 'leads' => 0, 'emails' => 0 ); ?>
				<?php $leads_url = add_query_arg( array( 'page' => 'cflow-leads', 'automation_id' => (int) $a['id'] ), admin_url( 'admin.php' ) ); ?>
				<tr>
					<td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-edit&id=' . (int) $a['id'] ) ); ?>"><?php echo esc_html( $a['name'] ); ?></a></strong></td>
					<td><?php echo '' === $a['media_id'] ? esc_html__( 'Qualquer post', 'comment-flow' ) : '<code>' . esc_html( $a['media_id'] ) . '</code>'; ?></td>
					<td><?php echo '' === trim( $a['keywords'] ) ? '<em>' . esc_html__( 'qualquer comentário', 'comment-flow' ) . '</em>' : esc_html( implode( ', ', CFlow_Matcher::split_lines( $a['keywords'], ',' ) ) ); ?></td>
					<td><?php echo (int) $a['priority']; ?></td>
					<td><?php echo (int) $s['matched']; ?></td>
					<td><?php echo (int) $s['dms']; ?></td>
					<td><a href="<?php echo esc_url( $leads_url ); ?>"><?php echo (int) $s['leads']; ?></a></td>
					<td><?php echo (int) $s['emails']; ?></td>
					<td>
						<span class="cflow-badge cflow-badge--<?php echo esc_attr( $a['status'] ); ?>">
							<?php echo 'active' === $a['status'] ? esc_html__( 'Ativa', 'comment-flow' ) : esc_html__( 'Pausada', 'comment-flow' ); ?>
						</span>
					</td>
					<td class="cflow-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-edit&id=' . (int) $a['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'comment-flow' ); ?></a> |
						<a href="<?php echo esc_url( $leads_url ); ?>"><?php esc_html_e( 'Ver leads', 'comment-flow' ); ?></a> |
						<a href="<?php echo esc_url( CFlow_Admin::export_url( $a['id'] ) ); ?>"><?php esc_html_e( 'Exportar CSV', 'comment-flow' ); ?></a> |
						<a href="<?php echo esc_url( CFlow_Admin::action_url( 'cflow_toggle_automation', $a['id'] ) ); ?>">
							<?php echo 'active' === $a['status'] ? esc_html__( 'Pausar', 'comment-flow' ) : esc_html__( 'Ativar', 'comment-flow' ); ?>
						</a> |
						<a class="cflow-delete" href="<?php echo esc_url( CFlow_Admin::action_url( 'cflow_delete_automation', $a['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Excluir esta automação?', 'comment-flow' ) ); ?>');"><?php esc_html_e( 'Excluir', 'comment-flow' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Quando mais de uma automação casa com um comentário, vale a de menor número de prioridade.', 'comment-flow' ); ?></p>
	<?php endif; ?>
</div>
