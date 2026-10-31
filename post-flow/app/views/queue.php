<?php
/**
 * Fila: posts agendados, agrupados por dia, e rascunhos.
 *
 * @var array        $posts     Posts agendados (ordem cronológica), já filtrados.
 * @var int          $total     Total de posts agendados (sem filtro).
 * @var array        $drafts    Rascunhos.
 * @var array        $channels  Canais por ID.
 * @var array        $counts    Posts agendados por canal.
 * @var int          $filter    Canal filtrado (0 = todos).
 * @var string       $next_slot Próximo horário livre (UTC) ou ''.
 * @var DateTimeZone $tz
 */

defined( 'ABSPATH' ) || exit;

$pflow_active   = 'queue';
$pflow_title    = __( 'Fila de publicação', 'post-flow' );
$pflow_subtitle = '' !== $next_slot
	/* translators: %s: data e hora */
	? sprintf( __( 'Próximo horário livre: %s', 'post-flow' ), wp_date( 'D, d/m \à\s H:i', PFlow_Schedule::to_local( $next_slot, $tz )->getTimestamp(), $tz ) )
	: __( 'Defina os seus horários para usar a fila.', 'post-flow' );
$pflow_actions  = '';

$pflow_groups = array();
foreach ( $posts as $pflow_post ) {
	$pflow_local                                     = PFlow_Schedule::to_local( $pflow_post['scheduled_at'], $tz );
	$pflow_groups[ $pflow_local->format( 'Y-m-d' ) ][] = $pflow_post;
}
?>
<div class="mx-auto max-w-7xl pb-16">
	<?php require PFLOW_DIR . 'app/views/partials/header.php'; ?>

	<div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
		<!-- Canais -->
		<aside class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:block lg:space-y-1 lg:overflow-visible lg:px-0 lg:pb-0">
			<p class="mb-3 hidden px-3 text-xs font-semibold uppercase tracking-wider text-neutral-500 lg:block"><?php esc_html_e( 'Canais', 'post-flow' ); ?></p>
			<a href="<?php echo esc_url( PFlow_App::url() ); ?>" class="<?php echo 0 === $filter ? 'bg-white/10 text-white' : 'text-neutral-300 hover:bg-white/5'; ?> flex shrink-0 items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-sm font-medium">
				<span><?php esc_html_e( 'Todos os canais', 'post-flow' ); ?></span>
				<span class="text-xs text-neutral-500"><?php echo (int) $total; ?></span>
			</a>
			<?php foreach ( $channels as $pflow_channel ) : ?>
				<a href="<?php echo esc_url( PFlow_App::url( 'queue', array( 'channel' => $pflow_channel['id'] ) ) ); ?>" class="<?php echo $filter === $pflow_channel['id'] ? 'bg-white/10 text-white' : 'text-neutral-300 hover:bg-white/5'; ?> flex shrink-0 items-center gap-3 rounded-xl px-3 py-2 text-sm">
					<?php echo PFlow_Channels::avatar( $pflow_channel, 'h-8 w-8' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="min-w-0 flex-1">
						<span class="block truncate font-medium"><?php echo esc_html( PFlow_Channels::display_name( $pflow_channel ) ); ?></span>
						<span class="block text-xs <?php echo 'active' === $pflow_channel['status'] ? 'text-neutral-500' : 'text-red-300'; ?>"><?php echo esc_html( 'active' === $pflow_channel['status'] ? PFlow_Networks::label( $pflow_channel['network'] ) : __( 'Reconectar', 'post-flow' ) ); ?></span>
					</span>
					<span class="text-xs text-neutral-500"><?php echo (int) ( $counts[ $pflow_channel['id'] ] ?? 0 ); ?></span>
				</a>
			<?php endforeach; ?>
			<a href="<?php echo esc_url( PFlow_App::url( 'channels' ) ); ?>" class="flex shrink-0 items-center gap-2 rounded-xl border border-dashed lg:mt-2 border-neutral-800 px-3 py-2.5 text-sm text-neutral-400 hover:border-violet-500 hover:text-violet-300">
				<span class="text-lg leading-none">+</span> <?php esc_html_e( 'Conectar canal', 'post-flow' ); ?>
			</a>
		</aside>

		<!-- Posts -->
		<div class="min-w-0 space-y-10">
			<?php if ( ! $channels ) : ?>
				<section class="<?php echo esc_attr( PFlow_App::CARD ); ?> text-center">
					<h2 class="m-0 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'Conecte o seu primeiro canal', 'post-flow' ); ?></h2>
					<p class="mx-auto mt-2 max-w-md text-sm text-neutral-400"><?php esc_html_e( 'Facebook, Instagram, LinkedIn, X ou Threads. Depois é só escrever e agendar.', 'post-flow' ); ?></p>
					<a href="<?php echo esc_url( PFlow_App::url( 'channels' ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> mt-6"><?php esc_html_e( 'Conectar canais', 'post-flow' ); ?></a>
				</section>
			<?php elseif ( ! $posts && ! $drafts ) : ?>
				<section class="<?php echo esc_attr( PFlow_App::CARD ); ?> text-center">
					<h2 class="m-0 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'A fila está vazia', 'post-flow' ); ?></h2>
					<p class="mx-auto mt-2 max-w-md text-sm text-neutral-400"><?php esc_html_e( 'Crie um post e clique em "Adicionar à fila": ele sai no próximo horário livre.', 'post-flow' ); ?></p>
					<a href="<?php echo esc_url( PFlow_App::url( 'compose', $filter ? array( 'channel' => $filter ) : array() ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> mt-6"><?php esc_html_e( 'Criar post', 'post-flow' ); ?></a>
				</section>
			<?php endif; ?>

			<?php foreach ( $pflow_groups as $pflow_day => $pflow_list ) : ?>
				<section>
					<h2 class="m-0 mb-4 flex items-baseline gap-3 p-0 text-base font-semibold text-white">
						<?php echo esc_html( PFlow_Schedule::day_label( new DateTimeImmutable( $pflow_day, $tz ), $tz ) ); ?>
						<span class="text-xs font-normal text-neutral-500"><?php echo esc_html( wp_date( 'd/m', ( new DateTimeImmutable( $pflow_day, $tz ) )->getTimestamp(), $tz ) ); ?></span>
					</h2>
					<div class="space-y-3">
						<?php
						foreach ( $pflow_list as $card ) {
							$context = 'queue';
							include PFLOW_DIR . 'app/views/partials/post-card.php';
						}
						?>
					</div>
				</section>
			<?php endforeach; ?>

			<?php if ( $drafts ) : ?>
				<section>
					<h2 class="m-0 mb-4 p-0 text-base font-semibold text-white"><?php esc_html_e( 'Rascunhos', 'post-flow' ); ?> <span class="text-xs font-normal text-neutral-500"><?php echo (int) count( $drafts ); ?></span></h2>
					<div class="grid gap-3 xl:grid-cols-2">
						<?php
						foreach ( $drafts as $card ) {
							$context = 'draft';
							include PFLOW_DIR . 'app/views/partials/post-card.php';
						}
						?>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</div>
</div>
