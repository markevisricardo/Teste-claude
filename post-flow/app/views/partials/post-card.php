<?php
/**
 * Cartão de um post (fila, rascunhos e publicados).
 *
 * @var array        $card     Post (com targets).
 * @var array        $channels Canais do usuário, por ID.
 * @var DateTimeZone $tz       Fuso do usuário.
 * @var string       $context  queue, draft ou sent.
 */

defined( 'ABSPATH' ) || exit;

$pflow_when  = PFlow_Schedule::to_local( 'sent' === $context ? $card['sent_at'] : $card['scheduled_at'], $tz );
$pflow_media = array_slice( $card['media_ids'], 0, 4 );
$pflow_ids   = 'draft' === $context ? PFlow_Posts::channel_ids( $card ) : array();
?>
<article class="rounded-2xl border border-solid border-neutral-800 bg-neutral-950 p-5 shadow-lg shadow-black/30 transition-colors hover:border-neutral-700">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="flex items-center gap-3">
			<?php if ( 'draft' !== $context && $pflow_when ) : ?>
				<span class="rounded-lg bg-white/5 px-2.5 py-1 text-sm font-semibold tabular-nums text-white"><?php echo esc_html( wp_date( 'H:i', $pflow_when->getTimestamp(), $tz ) ); ?></span>
			<?php endif; ?>
			<?php if ( 'sent' === $context && $pflow_when ) : ?>
				<span class="text-xs text-neutral-500"><?php echo esc_html( wp_date( 'd/m/Y', $pflow_when->getTimestamp(), $tz ) ); ?></span>
			<?php endif; ?>
			<?php if ( 'queue' === $context && $card['in_queue'] ) : ?>
				<span class="text-xs text-neutral-500"><?php esc_html_e( 'na fila', 'post-flow' ); ?></span>
			<?php elseif ( 'queue' === $context ) : ?>
				<span class="text-xs text-neutral-500"><?php esc_html_e( 'horário fixo', 'post-flow' ); ?></span>
			<?php endif; ?>
			<?php echo 'queue' === $context ? '' : PFlow_App::badge( $card['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<div class="flex items-center gap-1.5">
			<?php if ( 'draft' === $context ) : ?>
				<?php foreach ( $pflow_ids as $pflow_cid ) : ?>
					<?php if ( isset( $channels[ $pflow_cid ] ) ) : ?>
						<?php echo PFlow_Channels::avatar( $channels[ $pflow_cid ], 'h-7 w-7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php else : ?>
				<?php foreach ( $card['targets'] as $pflow_target ) : ?>
					<?php echo PFlow_Channels::avatar( PFlow_App::target_channel( $pflow_target, $channels ), 'h-7 w-7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="mt-4 flex gap-4">
		<div class="min-w-0 flex-1">
			<?php if ( '' !== trim( $card['content'] ) ) : ?>
				<p class="whitespace-pre-line break-words text-sm leading-6 text-neutral-200"><?php echo esc_html( PFlow_App::excerpt( $card['content'], 'sent' === $context ? 280 : 420 ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $card['link'] ) : ?>
				<p class="mt-2 truncate text-xs"><a href="<?php echo esc_url( $card['link'] ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( PFlow_App::LINK ); ?>"><?php echo esc_html( preg_replace( '#^https?://#', '', $card['link'] ) ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php if ( $pflow_media ) : ?>
			<div class="grid shrink-0 <?php echo count( $pflow_media ) > 1 ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-1">
				<?php foreach ( $pflow_media as $pflow_mid ) : ?>
					<img src="<?php echo esc_url( PFlow_Media::thumb( $pflow_mid ) ); ?>" alt="" class="<?php echo count( $pflow_media ) > 1 ? 'h-11 w-11' : 'h-24 w-24'; ?> rounded-lg object-cover" loading="lazy">
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( 'draft' !== $context ) : ?>
		<?php
		$pflow_show = array_filter(
			$card['targets'],
			static function ( $t ) use ( $context ) {
				return 'sent' === $context || '' !== (string) $t['error'] || 'publishing' === $t['status'];
			}
		);
		?>
		<?php if ( $pflow_show ) : ?>
			<ul class="mt-4 space-y-2 border-0 border-t border-solid border-neutral-800 pt-4">
				<?php foreach ( $pflow_show as $pflow_target ) : ?>
					<?php $pflow_ch = PFlow_App::target_channel( $pflow_target, $channels ); ?>
					<li class="flex flex-wrap items-center justify-between gap-2 text-xs">
						<span class="flex min-w-0 items-center gap-2 text-neutral-300">
							<span class="font-medium text-white"><?php echo esc_html( PFlow_Networks::label( $pflow_ch['network'] ) ); ?></span>
							<span class="truncate text-neutral-500"><?php echo esc_html( PFlow_Channels::display_name( $pflow_ch ) ); ?></span>
						</span>
						<span class="flex items-center gap-2">
							<?php if ( 'sent' === $pflow_target['status'] && '' === $pflow_target['remote_url'] && '' !== $pflow_target['remote_id'] && 'whatsapp' === $pflow_ch['network'] ) : ?>
								<span class="text-neutral-400">
									<?php
									/* translators: %s: "3 de 5 contatos" */
									echo esc_html( sprintf( __( 'Enviado a %s', 'post-flow' ), $pflow_target['remote_id'] ) );
									?>
								</span>
							<?php endif; ?>
							<?php if ( 'sent' === $pflow_target['status'] && '' !== $pflow_target['remote_url'] ) : ?>
								<a href="<?php echo esc_url( $pflow_target['remote_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( PFlow_App::LINK ); ?>"><?php esc_html_e( 'Ver post ↗', 'post-flow' ); ?></a>
							<?php endif; ?>
							<?php echo PFlow_App::badge( $pflow_target['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</span>
						<?php if ( '' !== (string) $pflow_target['error'] && 'sent' !== $pflow_target['status'] ) : ?>
							<span class="w-full rounded-lg bg-red-500/5 px-3 py-2 leading-5 text-red-200/90">
								<?php
								echo 'pending' === $pflow_target['status']
									/* translators: %s: erro */
									? esc_html( sprintf( __( 'Vamos tentar de novo. Último erro: %s', 'post-flow' ), $pflow_target['error'] ) )
									: esc_html( $pflow_target['error'] );
								?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>

	<div class="mt-4 flex flex-wrap items-center gap-2">
		<?php if ( 'sent' !== $context && PFlow_Posts::is_editable( $card ) ) : ?>
			<a href="<?php echo esc_url( PFlow_App::url( 'compose', array( 'id' => $card['id'] ) ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_SMALL ); ?>"><?php esc_html_e( 'Editar', 'post-flow' ); ?></a>
		<?php endif; ?>
		<?php if ( 'queue' === $context ) : ?>
			<a href="<?php echo esc_url( PFlow_App::action_url( 'publish_now', array( 'id' => $card['id'] ) ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_SMALL ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Publicar este post agora em todos os canais dele?', 'post-flow' ) ); ?>');"><?php esc_html_e( 'Publicar agora', 'post-flow' ); ?></a>
		<?php endif; ?>
		<?php if ( 'sent' === $context && in_array( $card['status'], array( 'partial', 'failed' ), true ) ) : ?>
			<a href="<?php echo esc_url( PFlow_App::action_url( 'retry_post', array( 'id' => $card['id'] ) ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_SMALL ); ?>"><?php esc_html_e( 'Tentar de novo', 'post-flow' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( PFlow_App::action_url( 'duplicate_post', array( 'id' => $card['id'] ) ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_SMALL ); ?>"><?php esc_html_e( 'Duplicar', 'post-flow' ); ?></a>
		<a href="<?php echo esc_url( PFlow_App::action_url( 'delete_post', array( 'id' => $card['id'], 'back' => 'sent' === $context ? 'sent' : 'queue' ) ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_SMALL ); ?> hover:border-red-500 hover:text-red-300" onclick="return confirm('<?php echo esc_js( 'sent' === $context ? __( 'Tirar este post do histórico? Ele continua publicado nas redes.', 'post-flow' ) : __( 'Excluir este post?', 'post-flow' ) ); ?>');"><?php esc_html_e( 'Excluir', 'post-flow' ); ?></a>
	</div>
</article>
