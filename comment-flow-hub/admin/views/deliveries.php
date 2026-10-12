<?php
/**
 * @var array $deliveries
 * @var array $counts
 */

defined( 'ABSPATH' ) || exit;

$hub_active   = 'cfhub-deliveries';
$hub_title    = __( 'Entregas', 'comment-flow-hub' );
$hub_subtitle = __( 'Eventos da Meta repassados aos sites dos clientes. Falhas são tentadas de novo em 1, 5, 15, 60 e 180 minutos.', 'comment-flow-hub' );
$labels       = array(
	'delivered' => array( 'blue', __( 'Entregue', 'comment-flow-hub' ) ),
	'retry'     => array( 'white', __( 'Nova tentativa', 'comment-flow-hub' ) ),
	'pending'   => array( 'white', __( 'Enviando', 'comment-flow-hub' ) ),
	'failed'    => array( 'red', __( 'Falhou', 'comment-flow-hub' ) ),
);
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-7xl pb-16">
	<?php require CFHUB_DIR . 'admin/views/partials/header.php'; ?>

	<div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
		<?php foreach ( array( 'delivered', 'retry', 'failed' ) as $i => $status ) : ?>
			<div class="rounded-2xl border border-solid border-neutral-800 bg-neutral-950 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-neutral-500"><?php echo esc_html( $labels[ $status ][1] ); ?></p>
				<p class="<?php echo 'failed' === $status && $counts[ $status ] ? 'text-red-400' : ( 0 === $i ? 'text-blue-400' : 'text-white' ); ?> mt-2 text-3xl font-semibold tabular-nums"><?php echo esc_html( number_format_i18n( $counts[ $status ] ) ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="overflow-hidden rounded-2xl border border-solid border-neutral-800 bg-neutral-950">
		<div class="overflow-x-auto">
			<table class="w-full border-collapse">
				<thead class="border-0 border-b border-solid border-neutral-800 bg-black/40">
					<tr>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Recebido (UTC)', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Cliente', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Tentativas', 'comment-flow-hub' ); ?></th>
						<th class="<?php echo esc_attr( CFHub_Admin::TH ); ?>"><?php esc_html_e( 'Status', 'comment-flow-hub' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $deliveries ) ) : ?>
					<tr><td colspan="4" class="px-4 py-16 text-center text-sm text-neutral-500"><?php esc_html_e( 'Nenhum evento recebido ainda.', 'comment-flow-hub' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $deliveries as $d ) : ?>
					<?php $label = isset( $labels[ $d['status'] ] ) ? $labels[ $d['status'] ] : array( 'gray', $d['status'] ); ?>
					<tr class="border-0 border-b border-solid border-neutral-900 last:border-b-0">
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?> whitespace-nowrap text-xs tabular-nums text-neutral-500"><?php echo esc_html( $d['created_at'] ); ?></td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?> text-white"><?php echo esc_html( $d['client_name'] ? $d['client_name'] : '#' . $d['license_id'] ); ?></td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?> tabular-nums"><?php echo (int) $d['attempts']; ?></td>
						<td class="<?php echo esc_attr( CFHub_Admin::TD ); ?>">
							<?php echo CFHub_Admin::badge( $label[0], $label[1] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php if ( ! empty( $d['last_error'] ) && 'delivered' !== $d['status'] ) : ?>
								<p class="mt-2 text-xs text-red-400"><?php echo esc_html( $d['last_error'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div></div>
