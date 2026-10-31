<?php
/**
 * wp-admin → Post Flow → Usuários.
 *
 * @var WP_User[] $users
 * @var array     $stats user_id => { networks: {rede: n}, posts: {status: n} }
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap pflow-app"><div class="mx-auto max-w-7xl pb-16 pt-4">
	<div class="pflow-hero relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-violet-600 via-violet-700 to-indigo-900 px-6 py-7 shadow-2xl shadow-violet-900/40 sm:px-8"><div class="relative">
		<h1 class="m-0 p-0 text-3xl font-semibold tracking-tight text-white"><?php esc_html_e( 'Usuários da plataforma', 'post-flow' ); ?></h1>
		<p class="mt-1 text-sm text-violet-100">
			<?php
			/* translators: %d: quantidade */
			echo esc_html( sprintf( _n( '%d pessoa cadastrada.', '%d pessoas cadastradas.', count( $users ), 'post-flow' ), count( $users ) ) );
			?>
		</p>
	</div></div>
	<hr class="wp-header-end m-0 border-0">

	<div class="overflow-hidden rounded-2xl border border-solid border-neutral-800 bg-neutral-950">
		<div class="overflow-x-auto">
			<table class="w-full border-collapse">
				<thead class="border-0 border-b border-solid border-neutral-800 bg-black/40">
					<tr>
						<th class="<?php echo esc_attr( PFlow_App::TH ); ?>"><?php esc_html_e( 'Usuário', 'post-flow' ); ?></th>
						<th class="<?php echo esc_attr( PFlow_App::TH ); ?>"><?php esc_html_e( 'Canais', 'post-flow' ); ?></th>
						<th class="<?php echo esc_attr( PFlow_App::TH ); ?> text-right"><?php esc_html_e( 'Agendados', 'post-flow' ); ?></th>
						<th class="<?php echo esc_attr( PFlow_App::TH ); ?> text-right"><?php esc_html_e( 'Publicados', 'post-flow' ); ?></th>
						<th class="<?php echo esc_attr( PFlow_App::TH ); ?> text-right"><?php esc_html_e( 'Com falha', 'post-flow' ); ?></th>
						<th class="<?php echo esc_attr( PFlow_App::TH ); ?>"><?php esc_html_e( 'Cadastro (UTC)', 'post-flow' ); ?></th>
						<th class="<?php echo esc_attr( PFlow_App::TH ); ?>"></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $users ) ) : ?>
					<tr><td colspan="7" class="px-4 py-16 text-center text-sm text-neutral-500"><?php esc_html_e( 'Ninguém se cadastrou ainda.', 'post-flow' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $users as $u ) : ?>
					<?php
					$s     = ( isset( $stats[ $u->ID ] ) ? $stats[ $u->ID ] : array() ) + array( 'networks' => array(), 'posts' => array() );
					$posts = $s['posts'] + array( 'scheduled' => 0, 'sent' => 0, 'partial' => 0, 'failed' => 0 );
					?>
					<tr class="border-0 border-b border-solid border-neutral-900 last:border-b-0">
						<td class="<?php echo esc_attr( PFlow_App::TD ); ?>">
							<p class="font-semibold text-white"><?php echo esc_html( $u->display_name ); ?></p>
							<p class="mt-0.5 text-xs text-neutral-500"><?php echo esc_html( $u->user_email ); ?></p>
						</td>
						<td class="<?php echo esc_attr( PFlow_App::TD ); ?>">
							<?php if ( ! $s['networks'] ) : ?>
								<span class="text-xs text-neutral-500"><?php esc_html_e( 'nenhum', 'post-flow' ); ?></span>
							<?php endif; ?>
							<span class="flex flex-wrap gap-1.5">
								<?php foreach ( $s['networks'] as $network => $n ) : ?>
									<?php $type = PFlow_Networks::type( $network ); ?>
									<span class="inline-flex items-center gap-1 rounded-full bg-white/5 px-2 py-0.5 text-xs text-white"><span class="h-2 w-2 rounded-full" style="background:<?php echo esc_attr( $type ? $type['color'] : '#888' ); ?>"></span><?php echo esc_html( PFlow_Networks::label( $network ) . ' ' . $n ); ?></span>
								<?php endforeach; ?>
							</span>
						</td>
						<td class="<?php echo esc_attr( PFlow_App::TD ); ?> text-right tabular-nums"><?php echo esc_html( number_format_i18n( $posts['scheduled'] ) ); ?></td>
						<td class="<?php echo esc_attr( PFlow_App::TD ); ?> text-right tabular-nums"><?php echo esc_html( number_format_i18n( $posts['sent'] + $posts['partial'] ) ); ?></td>
						<td class="<?php echo esc_attr( PFlow_App::TD ); ?> text-right tabular-nums"><?php echo esc_html( number_format_i18n( $posts['failed'] ) ); ?></td>
						<td class="<?php echo esc_attr( PFlow_App::TD ); ?> whitespace-nowrap text-xs text-neutral-500"><?php echo esc_html( $u->user_registered ); ?></td>
						<td class="<?php echo esc_attr( PFlow_App::TD ); ?> text-right"><a class="<?php echo esc_attr( PFlow_App::LINK ); ?> text-xs" href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>"><?php esc_html_e( 'Editar', 'post-flow' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<p class="mt-3 text-xs text-neutral-500"><?php esc_html_e( 'Para bloquear alguém, edite o usuário e troque a função ou exclua a conta. Ao excluir pelo wp-admin, os dados do Post Flow da pessoa também são apagados.', 'post-flow' ); ?></p>
</div></div>
