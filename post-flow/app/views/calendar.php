<?php
/**
 * Calendário da semana (segunda a domingo).
 *
 * @var DateTimeImmutable $start    Segunda-feira da semana (fuso do usuário).
 * @var array             $days     'Y-m-d' => posts do dia (com 'local').
 * @var array             $channels Canais por ID.
 * @var DateTimeZone      $tz
 */

defined( 'ABSPATH' ) || exit;

$pflow_active   = 'calendar';
$pflow_title    = __( 'Calendário', 'post-flow' );
$pflow_end      = $start->modify( '+6 days' );
$pflow_subtitle = wp_date( 'j \d\e F', $start->getTimestamp(), $tz ) . ' – ' . wp_date( 'j \d\e F \d\e Y', $pflow_end->getTimestamp(), $tz );
$pflow_week     = static function ( DateTimeImmutable $date ) {
	return PFlow_App::url( 'calendar', array( 'week' => $date->format( 'Y-m-d' ) ) );
};
$pflow_actions  = '<a class="' . esc_attr( PFlow_App::BTN_SECONDARY ) . '" href="' . esc_url( $pflow_week( $start->modify( '-7 days' ) ) ) . '">← ' . esc_html__( 'Anterior', 'post-flow' ) . '</a>'
	. '<a class="' . esc_attr( PFlow_App::BTN_SECONDARY ) . '" href="' . esc_url( PFlow_App::url( 'calendar' ) ) . '">' . esc_html__( 'Hoje', 'post-flow' ) . '</a>'
	. '<a class="' . esc_attr( PFlow_App::BTN_SECONDARY ) . '" href="' . esc_url( $pflow_week( $start->modify( '+7 days' ) ) ) . '">' . esc_html__( 'Próxima', 'post-flow' ) . ' →</a>';
$pflow_today    = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
$pflow_tones    = array(
	'scheduled' => 'border-violet-500/40 bg-violet-500/10',
	'sent'      => 'border-emerald-500/30 bg-emerald-500/5',
	'partial'   => 'border-amber-500/40 bg-amber-500/5',
	'failed'    => 'border-red-500/40 bg-red-500/5',
);
?>
<div class="mx-auto max-w-7xl pb-16">
	<?php require PFLOW_DIR . 'app/views/partials/header.php'; ?>

	<div class="overflow-x-auto">
		<div class="grid min-w-[980px] grid-cols-7 gap-3">
			<?php foreach ( $days as $pflow_day => $pflow_posts ) : ?>
				<?php
				$pflow_date = new DateTimeImmutable( $pflow_day, $tz );
				$pflow_is   = $pflow_day === $pflow_today;
				$pflow_past = $pflow_day < $pflow_today;
				?>
				<section class="<?php echo $pflow_is ? 'border-violet-500/60' : 'border-neutral-800'; ?> flex min-h-[420px] flex-col rounded-2xl border border-solid bg-neutral-950 p-3">
					<header class="mb-3 flex items-baseline justify-between px-1">
						<span class="text-xs font-semibold uppercase tracking-wider <?php echo $pflow_is ? 'text-violet-300' : 'text-neutral-500'; ?>"><?php echo esc_html( wp_date( 'D', $pflow_date->getTimestamp(), $tz ) ); ?></span>
						<span class="text-lg font-semibold <?php echo $pflow_is ? 'text-white' : 'text-neutral-300'; ?>"><?php echo esc_html( $pflow_date->format( 'd' ) ); ?></span>
					</header>
					<div class="flex-1 space-y-2">
						<?php foreach ( $pflow_posts as $pflow_post ) : ?>
							<?php
							$pflow_href = 'scheduled' === $pflow_post['status'] && PFlow_Posts::is_editable( $pflow_post ) ? PFlow_App::url( 'compose', array( 'id' => $pflow_post['id'] ) ) : PFlow_App::url( 'sent' );
							$pflow_tone = $pflow_tones[ $pflow_post['status'] ] ?? 'border-neutral-800';
							?>
							<a href="<?php echo esc_url( $pflow_href ); ?>" class="<?php echo esc_attr( $pflow_tone ); ?> block rounded-xl border border-solid p-2.5 transition-colors hover:border-violet-400">
								<span class="flex items-center justify-between gap-2">
									<span class="text-xs font-semibold tabular-nums text-white"><?php echo esc_html( wp_date( 'H:i', $pflow_post['local']->getTimestamp(), $tz ) ); ?></span>
									<span class="flex -space-x-1">
										<?php foreach ( array_slice( $pflow_post['targets'], 0, 4 ) as $pflow_target ) : ?>
											<?php
											$pflow_ch   = PFlow_App::target_channel( $pflow_target, $channels );
											$pflow_type = PFlow_Networks::type( $pflow_ch['network'] );
											?>
											<span class="flex h-4 w-4 items-center justify-center rounded-full text-[7px] font-bold text-white ring-1 ring-neutral-950" style="background:<?php echo esc_attr( $pflow_type['color'] ); ?>"><?php echo esc_html( $pflow_type['glyph'] ); ?></span>
										<?php endforeach; ?>
									</span>
								</span>
								<?php $pflow_thumbs = PFlow_App::display_media( $pflow_post ); ?>
								<?php if ( $pflow_thumbs ) : ?>
									<span class="mt-2 block"><?php echo PFlow_App::media_thumb( $pflow_thumbs[0], 'h-16 w-full' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								<?php endif; ?>
								<span class="mt-1.5 block break-words text-xs leading-5 text-neutral-300"><?php echo esc_html( PFlow_App::excerpt( $pflow_post['content'], 70 ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
					<?php if ( ! $pflow_past ) : ?>
						<a href="<?php echo esc_url( PFlow_App::url( 'compose' ) ); ?>" class="mt-3 block rounded-lg border border-dashed border-neutral-800 py-1.5 text-center text-xs text-neutral-500 hover:border-violet-500 hover:text-violet-300">+ <?php esc_html_e( 'Post', 'post-flow' ); ?></a>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
	<p class="mt-4 flex flex-wrap gap-4 text-xs text-neutral-500">
		<span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-violet-400"></span><?php esc_html_e( 'Agendado', 'post-flow' ); ?></span>
		<span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-400"></span><?php esc_html_e( 'Publicado', 'post-flow' ); ?></span>
		<span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-400"></span><?php esc_html_e( 'Em parte', 'post-flow' ); ?></span>
		<span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-red-400"></span><?php esc_html_e( 'Falhou', 'post-flow' ); ?></span>
	</p>
</div>
