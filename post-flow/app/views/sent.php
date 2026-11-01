<?php
/**
 * Publicados: histórico com o link de cada post e as falhas.
 *
 * @var array        $posts
 * @var int          $total
 * @var int          $paged
 * @var int          $per_page
 * @var array        $channels
 * @var DateTimeZone $tz
 */

defined( 'ABSPATH' ) || exit;

$pflow_active   = 'sent';
$pflow_title    = __( 'Publicados', 'post-flow' );
/* translators: %s: total */
$pflow_subtitle = sprintf( _n( '%s post no histórico.', '%s posts no histórico.', $total, 'post-flow' ), number_format_i18n( $total ) );
$pflow_actions  = '';
?>
<div class="mx-auto max-w-4xl pb-16">
	<?php require PFLOW_DIR . 'app/views/partials/header.php'; ?>

	<?php if ( ! $posts ) : ?>
		<section class="<?php echo esc_attr( PFlow_App::CARD ); ?> text-center">
			<h2 class="m-0 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'Nada publicado ainda', 'post-flow' ); ?></h2>
			<p class="mt-2 text-sm text-neutral-400"><?php esc_html_e( 'Os posts aparecem aqui assim que saem, com o link de cada rede.', 'post-flow' ); ?></p>
		</section>
	<?php else : ?>
		<div class="space-y-3">
			<?php
			foreach ( $posts as $card ) {
				$context = 'sent';
				include PFLOW_DIR . 'app/views/partials/post-card.php';
			}
			?>
		</div>
		<?php
		$pflow_pages = (int) ceil( $total / $per_page );
		if ( $pflow_pages > 1 ) :
			?>
			<nav class="mt-8"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', PFlow_App::url( 'sent' ) ), 'format' => '', 'current' => $paged, 'total' => $pflow_pages ) ) ); ?></nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
