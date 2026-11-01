<?php
/**
 * Horários da fila e fuso horário.
 *
 * @var array        $slots   days (int[]) e times (string[]).
 * @var string[]     $preview Próximos horários livres (UTC).
 * @var DateTimeZone $tz
 */

defined( 'ABSPATH' ) || exit;

$pflow_active   = 'schedule';
$pflow_title    = __( 'Horários de publicação', 'post-flow' );
$pflow_subtitle = __( '"Adicionar à fila" usa o próximo destes horários que estiver livre.', 'post-flow' );
$pflow_actions  = '';
$pflow_times    = $slots['times'] ? $slots['times'] : array( '09:00' );
?>
<div class="mx-auto max-w-4xl pb-16">
	<?php require PFLOW_DIR . 'app/views/partials/header.php'; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grid gap-6 md:grid-cols-[minmax(0,1fr)_280px]">
		<input type="hidden" name="action" value="pflow_save_schedule">
		<?php wp_nonce_field( 'pflow_save_schedule' ); ?>

		<div class="space-y-6">
			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
				<h2 class="m-0 mb-4 p-0 text-base font-semibold text-white"><?php esc_html_e( 'Dias da semana', 'post-flow' ); ?></h2>
				<div class="flex flex-wrap gap-2">
					<?php foreach ( PFlow_Schedule::weekday_names() as $pflow_day => $pflow_name ) : ?>
						<label class="cursor-pointer">
							<input type="checkbox" name="days[]" value="<?php echo (int) $pflow_day; ?>" class="peer sr-only" <?php checked( in_array( $pflow_day, $slots['days'], true ) ); ?>>
							<span class="block rounded-lg border border-solid border-neutral-800 px-4 py-2 text-sm font-medium text-neutral-400 transition-colors peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-violet-400"><?php echo esc_html( $pflow_name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
				<h2 class="m-0 mb-4 p-0 text-base font-semibold text-white"><?php esc_html_e( 'Horários', 'post-flow' ); ?></h2>
				<div class="flex flex-wrap gap-3" data-pflow-times>
					<?php foreach ( $pflow_times as $pflow_time ) : ?>
						<span class="flex items-center gap-1 rounded-lg border border-solid border-neutral-800 bg-black pl-1 pr-1" data-pflow-time>
							<input type="time" name="times[]" value="<?php echo esc_attr( $pflow_time ); ?>" class="w-28 border-0 bg-transparent px-2 py-1.5 text-sm text-white [color-scheme:dark]">
							<button type="button" class="cursor-pointer rounded-md border-0 bg-transparent px-2 py-1 text-neutral-500 hover:bg-white/5 hover:text-red-300" data-pflow-remove aria-label="<?php esc_attr_e( 'Remover horário', 'post-flow' ); ?>">×</button>
						</span>
					<?php endforeach; ?>
					<button type="button" class="<?php echo esc_attr( PFlow_App::BTN_SMALL ); ?>" data-pflow-add>+ <?php esc_html_e( 'Horário', 'post-flow' ); ?></button>
				</div>
			</section>

			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
				<label for="pflow-timezone" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Fuso horário', 'post-flow' ); ?></label>
				<select id="pflow-timezone" name="timezone" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
					<?php
					$pflow_current = $tz->getName();
					foreach ( timezone_identifiers_list() as $pflow_zone ) {
						printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $pflow_zone ), selected( $pflow_current, $pflow_zone, false ), esc_html( str_replace( '_', ' ', $pflow_zone ) ) );
					}
					?>
				</select>
				<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>"><?php esc_html_e( 'Todas as datas do painel aparecem neste fuso.', 'post-flow' ); ?></p>
			</section>

			<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?>"><?php esc_html_e( 'Salvar horários', 'post-flow' ); ?></button>
		</div>

		<aside class="<?php echo esc_attr( PFlow_App::CARD ); ?> h-fit">
			<h2 class="m-0 mb-4 p-0 text-sm font-semibold text-neutral-400"><?php esc_html_e( 'Próximos horários livres', 'post-flow' ); ?></h2>
			<?php if ( ! $preview ) : ?>
				<p class="text-sm text-neutral-500"><?php esc_html_e( 'Escolha ao menos um dia e um horário.', 'post-flow' ); ?></p>
			<?php else : ?>
				<ol class="space-y-2">
					<?php foreach ( $preview as $pflow_slot ) : ?>
						<?php $pflow_local = PFlow_Schedule::to_local( $pflow_slot, $tz ); ?>
						<li class="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2 text-sm">
							<span class="text-neutral-300"><?php echo esc_html( PFlow_Schedule::day_label( $pflow_local, $tz ) ); ?></span>
							<span class="font-semibold tabular-nums text-white"><?php echo esc_html( $pflow_local->format( 'H:i' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</aside>
	</form>
</div>
<script>
( function () {
	var box = document.querySelector( '[data-pflow-times]' );
	if ( ! box ) { return; }
	box.addEventListener( 'click', function ( e ) {
		var remove = e.target.closest( '[data-pflow-remove]' );
		if ( remove && box.querySelectorAll( '[data-pflow-time]' ).length > 1 ) {
			remove.closest( '[data-pflow-time]' ).remove();
		}
		if ( e.target.closest( '[data-pflow-add]' ) ) {
			var rows  = box.querySelectorAll( '[data-pflow-time]' );
			var clone = rows[ rows.length - 1 ].cloneNode( true );
			clone.querySelector( 'input' ).value = '';
			box.insertBefore( clone, e.target.closest( '[data-pflow-add]' ) );
			clone.querySelector( 'input' ).focus();
		}
	} );
} )();
</script>
