<?php
/**
 * wp-admin → Comment Flow → Usuários.
 *
 * @var WP_User[] $users
 * @var array     $stats user_id => { ig, automations, leads }
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-7xl pb-16 pt-4">
	<div class="mb-8">
		<h1 class="m-0 p-0 text-2xl font-semibold tracking-tight text-white"><?php esc_html_e( 'Usuários da plataforma', 'comment-flow' ); ?></h1>
		<p class="mt-1 text-sm text-neutral-400">
			<?php
			/* translators: %d: quantidade */
			echo esc_html( sprintf( _n( '%d pessoa cadastrada.', '%d pessoas cadastradas.', count( $users ), 'comment-flow' ), count( $users ) ) );
			?>
		</p>
	</div>
	<hr class="wp-header-end m-0 border-0">

	<div class="overflow-hidden rounded-2xl border border-solid border-neutral-800 bg-neutral-950">
		<div class="overflow-x-auto">
			<table class="w-full border-collapse">
				<thead class="border-0 border-b border-solid border-neutral-800 bg-black/40">
					<tr>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"><?php esc_html_e( 'Usuário', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"><?php esc_html_e( 'Instagram', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?> text-right"><?php esc_html_e( 'Automações', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?> text-right"><?php esc_html_e( 'Leads', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"><?php esc_html_e( 'Cadastro (UTC)', 'comment-flow' ); ?></th>
						<th class="<?php echo esc_attr( CFlow_App::TH ); ?>"></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $users ) ) : ?>
					<tr><td colspan="6" class="px-4 py-16 text-center text-sm text-neutral-500"><?php esc_html_e( 'Ninguém se cadastrou ainda.', 'comment-flow' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $users as $u ) : ?>
					<?php $s = ( isset( $stats[ $u->ID ] ) ? $stats[ $u->ID ] : array() ) + array( 'ig' => '', 'automations' => 0, 'leads' => 0 ); ?>
					<tr class="border-0 border-b border-solid border-neutral-900 last:border-b-0">
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?>">
							<p class="font-semibold text-white"><?php echo esc_html( $u->display_name ); ?></p>
							<p class="mt-0.5 text-xs text-neutral-500"><?php echo esc_html( $u->user_email ); ?></p>
						</td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?>">
							<?php
							echo '' !== $s['ig']
								? CFlow_App::badge( 'active', '@' . $s['ig'] ) // phpcs:ignore WordPress.Security.EscapeOutput
								: CFlow_App::badge( 'none', __( 'não conectado', 'comment-flow' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						</td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?> text-right tabular-nums"><?php echo esc_html( number_format_i18n( $s['automations'] ) ); ?></td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?> text-right tabular-nums"><?php echo esc_html( number_format_i18n( $s['leads'] ) ); ?></td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?> whitespace-nowrap text-xs text-neutral-500"><?php echo esc_html( $u->user_registered ); ?></td>
						<td class="<?php echo esc_attr( CFlow_App::TD ); ?> text-right"><a class="<?php echo esc_attr( CFlow_App::LINK ); ?> text-xs" href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>"><?php esc_html_e( 'Editar', 'comment-flow' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<p class="mt-3 text-xs text-neutral-500"><?php esc_html_e( 'Para bloquear alguém, edite o usuário e troque a função ou exclua a conta.', 'comment-flow' ); ?></p>
</div></div>
