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
?>
<div class="wrap cflow">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'comment-flow' ); ?></h1>
	<a href="<?php echo esc_url( CFlow_Admin::export_url( $filters['automation_id'], $filters['search'] ) ); ?>" class="page-title-action">
		<?php
		echo $filters['automation_id']
			? esc_html__( 'Exportar CSV desta automação', 'comment-flow' )
			: esc_html__( 'Exportar CSV', 'comment-flow' );
		?>
	</a>
	<hr class="wp-header-end">

	<form method="get" class="cflow-filters">
		<input type="hidden" name="page" value="cflow-leads">
		<select name="automation_id">
			<option value="0"><?php esc_html_e( 'Todas as automações', 'comment-flow' ); ?></option>
			<?php foreach ( $automations as $a ) : ?>
				<option value="<?php echo (int) $a['id']; ?>" <?php selected( (int) $a['id'], $filters['automation_id'] ); ?>><?php echo esc_html( $a['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( '@usuário, e-mail ou telefone', 'comment-flow' ); ?>">
		<input type="submit" class="button" value="<?php esc_attr_e( 'Filtrar', 'comment-flow' ); ?>">
		<span class="cflow-count">
			<?php
			/* translators: %d: quantidade de leads */
			echo esc_html( sprintf( _n( '%d lead', '%d leads', $total, 'comment-flow' ), $total ) );
			?>
		</span>
	</form>

	<table class="widefat striped cflow-leads">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Usuário', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'E-mail', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Telefone', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Automação', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Último comentário', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Captura', 'comment-flow' ); ?></th>
				<th><?php esc_html_e( 'Primeiro contato (UTC)', 'comment-flow' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $leads ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'Nenhum lead encontrado.', 'comment-flow' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $leads as $lead ) : ?>
			<tr>
				<td>
					<?php if ( '' !== $lead['username'] ) : ?>
						<a href="<?php echo esc_url( 'https://instagram.com/' . rawurlencode( $lead['username'] ) ); ?>" target="_blank" rel="noopener noreferrer">@<?php echo esc_html( $lead['username'] ); ?></a>
					<?php else : ?>
						<code><?php echo esc_html( $lead['comment_from_id'] ); ?></code>
					<?php endif; ?>
				</td>
				<td><?php echo '' !== $lead['email'] ? '<a href="' . esc_url( 'mailto:' . $lead['email'] ) . '">' . esc_html( $lead['email'] ) . '</a>' : '—'; ?></td>
				<td><?php echo '' !== $lead['phone'] ? '<a href="' . esc_url( 'https://wa.me/' . ltrim( $lead['phone'], '+' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $lead['phone'] ) . '</a>' : '—'; ?></td>
				<td>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cflow-leads', 'automation_id' => (int) $lead['automation_id'] ), admin_url( 'admin.php' ) ) ); ?>">
						<?php echo esc_html( isset( $names[ $lead['automation_id'] ] ) ? $names[ $lead['automation_id'] ] : '#' . $lead['automation_id'] ); ?>
					</a>
				</td>
				<td>
					<?php echo esc_html( wp_trim_words( (string) $lead['last_comment'], 10 ) ); ?>
					<?php if ( (int) $lead['comments_count'] > 1 ) : ?>
						<span class="description">(<?php echo (int) $lead['comments_count']; ?>×)</span>
					<?php endif; ?>
				</td>
				<td>
					<span class="cflow-badge cflow-badge--<?php echo esc_attr( $lead['capture_step'] ); ?>"><?php echo esc_html( CFlow_Leads::step_label( $lead['capture_step'] ) ); ?></span>
					<?php if ( ! empty( $lead['last_error'] ) ) : ?>
						<div class="cflow-error"><?php echo esc_html( $lead['last_error'] ); ?></div>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $lead['created_at'] ); ?></td>
				<td class="cflow-actions">
					<a class="cflow-delete" href="<?php echo esc_url( CFlow_Admin::action_url( 'cflow_delete_lead', $lead['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Excluir este lead e seus dados? Use para atender pedidos de exclusão (LGPD).', 'comment-flow' ) ); ?>');"><?php esc_html_e( 'Excluir', 'comment-flow' ); ?></a>
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

	<p class="description"><?php esc_html_e( 'O CSV usa ";" como separador e abre direto no Excel. Ele respeita o filtro e a busca acima.', 'comment-flow' ); ?></p>
</div>
