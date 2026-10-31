<?php
/**
 * Canais: conectar redes, escolher contas e remover.
 *
 * @var array    $channels Canais conectados.
 * @var array    $pending  Contas aguardando escolha (após o OAuth).
 * @var int|null $left     Canais que ainda cabem no plano (null = sem limite).
 */

defined( 'ABSPATH' ) || exit;

$pflow_active   = 'channels';
$pflow_title    = __( 'Canais', 'post-flow' );
$pflow_subtitle = null === $left
	? __( 'Conecte as contas onde você quer publicar.', 'post-flow' )
	/* translators: %d: canais restantes */
	: sprintf( _n( 'Você ainda pode conectar %d canal.', 'Você ainda pode conectar %d canais.', $left, 'post-flow' ), $left );
$pflow_actions  = '';
?>
<div class="mx-auto max-w-5xl pb-16">
	<?php require PFLOW_DIR . 'app/views/partials/header.php'; ?>

	<?php if ( $pending ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( PFlow_App::CARD ); ?> mb-8 border-violet-500/40">
			<input type="hidden" name="action" value="pflow_pick_channels">
			<?php wp_nonce_field( 'pflow_pick_channels' ); ?>
			<h2 class="m-0 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'Quais contas você quer conectar?', 'post-flow' ); ?></h2>
			<p class="mt-1 text-sm text-neutral-400"><?php esc_html_e( 'Encontramos mais de uma conta nessa conexão.', 'post-flow' ); ?></p>
			<div class="mt-6 grid gap-3 sm:grid-cols-2">
				<?php foreach ( $pending as $pflow_i => $pflow_account ) : ?>
					<label class="flex cursor-pointer items-center gap-3 rounded-xl border border-solid border-neutral-800 p-3 hover:border-neutral-700 has-[:checked]:border-violet-500 has-[:checked]:bg-violet-500/5">
						<input type="checkbox" name="accounts[]" value="<?php echo (int) $pflow_i; ?>" class="accent-violet-500" checked>
						<?php echo PFlow_Channels::avatar( $pflow_account ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span class="min-w-0">
							<span class="block truncate text-sm font-medium text-white"><?php echo esc_html( PFlow_Channels::display_name( $pflow_account ) ); ?></span>
							<span class="block text-xs text-neutral-500"><?php echo esc_html( PFlow_Networks::label( $pflow_account['network'] ) ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> mt-6"><?php esc_html_e( 'Conectar selecionados', 'post-flow' ); ?></button>
		</form>
	<?php endif; ?>

	<?php if ( $channels ) : ?>
		<section class="mb-10">
			<h2 class="m-0 mb-4 p-0 text-base font-semibold text-white"><?php esc_html_e( 'Conectados', 'post-flow' ); ?></h2>
			<div class="overflow-hidden rounded-2xl border border-solid border-neutral-800 bg-neutral-950">
				<?php foreach ( $channels as $pflow_channel ) : ?>
					<?php $pflow_provider = PFlow_Networks::type( $pflow_channel['network'] )['provider']; ?>
					<div class="flex flex-wrap items-center justify-between gap-4 border-0 border-b border-solid border-neutral-800 px-5 py-4 last:border-b-0">
						<div class="flex min-w-0 items-center gap-3">
							<?php echo PFlow_Channels::avatar( $pflow_channel, 'h-11 w-11' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<div class="min-w-0">
								<p class="truncate text-sm font-semibold text-white"><?php echo esc_html( PFlow_Channels::display_name( $pflow_channel ) ); ?></p>
								<p class="mt-0.5 text-xs text-neutral-500">
									<?php echo esc_html( PFlow_Networks::label( $pflow_channel['network'] ) ); ?>
									<?php if ( 'active' === $pflow_channel['status'] && $pflow_channel['token_expires_at'] && 'x' !== $pflow_channel['network'] ) : ?>
										·
										<?php
										$pflow_days = max( 0, (int) floor( ( $pflow_channel['token_expires_at'] - time() ) / DAY_IN_SECONDS ) );
										/* translators: %d: dias */
										echo esc_html( sprintf( _n( 'conexão vale mais %d dia', 'conexão vale mais %d dias', $pflow_days, 'post-flow' ), $pflow_days ) );
										?>
									<?php endif; ?>
								</p>
								<?php if ( 'active' !== $pflow_channel['status'] && '' !== (string) $pflow_channel['last_error'] ) : ?>
									<p class="mt-1 text-xs text-red-300"><?php echo esc_html( $pflow_channel['last_error'] ); ?></p>
								<?php endif; ?>
							</div>
						</div>
						<div class="flex items-center gap-2">
							<?php echo PFlow_App::badge( $pflow_channel['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<a href="<?php echo esc_url( PFlow_App::action_url( 'connect', array( 'provider' => $pflow_provider ) ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_SMALL ); ?>"><?php esc_html_e( 'Reconectar', 'post-flow' ); ?></a>
							<a href="<?php echo esc_url( PFlow_App::action_url( 'disconnect', array( 'id' => $pflow_channel['id'] ) ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_SMALL ); ?> hover:border-red-500 hover:text-red-300" onclick="return confirm('<?php echo esc_js( __( 'Remover este canal? Ele sai dos posts que ainda não foram publicados.', 'post-flow' ) ); ?>');"><?php esc_html_e( 'Remover', 'post-flow' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section>
		<h2 class="m-0 mb-4 p-0 text-base font-semibold text-white"><?php esc_html_e( 'Conectar nova rede', 'post-flow' ); ?></h2>
		<div class="grid gap-4 sm:grid-cols-2">
			<?php foreach ( PFlow_Networks::providers() as $pflow_id => $pflow_info ) : ?>
				<?php
				$pflow_provider = PFlow_Networks::provider( $pflow_id );
				$pflow_ready    = $pflow_provider->is_configured();
				$pflow_full     = 0 === $left;
				?>
				<div class="<?php echo esc_attr( PFlow_App::CARD ); ?> flex flex-col">
					<div class="flex items-center gap-3">
						<span class="flex -space-x-2">
							<?php foreach ( $pflow_info['networks'] as $pflow_network ) : ?>
								<?php $pflow_type = PFlow_Networks::type( $pflow_network ); ?>
								<span class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-white ring-2 ring-neutral-950" style="background:<?php echo esc_attr( $pflow_type['color'] ); ?>"><?php echo esc_html( $pflow_type['glyph'] ); ?></span>
							<?php endforeach; ?>
						</span>
						<div>
							<h3 class="m-0 p-0 text-base font-semibold text-white"><?php echo esc_html( $pflow_info['label'] ); ?></h3>
							<p class="mt-0.5 text-xs leading-5 text-neutral-500"><?php echo esc_html( $pflow_info['description'] ); ?></p>
						</div>
					</div>
					<div class="mt-6">
						<?php if ( ! $pflow_ready ) : ?>
							<span class="<?php echo esc_attr( PFlow_App::BTN_SECONDARY ); ?> cursor-not-allowed opacity-50"><?php esc_html_e( 'Indisponível no momento', 'post-flow' ); ?></span>
						<?php elseif ( $pflow_full ) : ?>
							<span class="<?php echo esc_attr( PFlow_App::BTN_SECONDARY ); ?> cursor-not-allowed opacity-50"><?php esc_html_e( 'Limite do plano atingido', 'post-flow' ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( PFlow_App::action_url( 'connect', array( 'provider' => $pflow_id ) ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?>">
								<?php
								/* translators: %s: rede */
								echo esc_html( sprintf( __( 'Conectar %s', 'post-flow' ), $pflow_info['label'] ) );
								?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="mt-6 text-xs leading-5 text-neutral-500"><?php esc_html_e( 'O Instagram precisa ser uma conta profissional (Empresa ou Criador) ligada a uma página do Facebook. Você é levado ao site de cada rede para autorizar; não guardamos a sua senha.', 'post-flow' ); ?></p>
	</section>
</div>
