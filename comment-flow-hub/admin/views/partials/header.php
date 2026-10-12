<?php
/**
 * Cabeçalho do hub.
 *
 * @var string $hub_active   Slug da página atual.
 * @var string $hub_title    Título.
 * @var string $hub_subtitle Subtítulo.
 */

defined( 'ABSPATH' ) || exit;

$hub_nav    = array(
	'cfhub'            => __( 'Licenças', 'comment-flow-hub' ),
	'cfhub-deliveries' => __( 'Entregas', 'comment-flow-hub' ),
	'cfhub-settings'   => __( 'Configurações', 'comment-flow-hub' ),
);
$hub_notice = CFHub_Admin::flash();
?>
<header class="mb-8">
	<div class="flex flex-wrap items-center justify-between gap-4 border-0 border-b border-solid border-neutral-900 pb-6 pt-2">
		<div class="flex items-center gap-3">
			<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 shadow-lg shadow-blue-600/30">
				<svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="4" cy="6" r="2"/><circle cx="20" cy="6" r="2"/><circle cx="4" cy="18" r="2"/><circle cx="20" cy="18" r="2"/><path d="M6 7l4 3.5M18 7l-4 3.5M6 17l4-3.5M18 17l-4-3.5"/></svg>
			</span>
			<div>
				<p class="text-base font-semibold tracking-tight text-white">Comment Flow Hub</p>
				<p class="text-xs text-neutral-500"><?php esc_html_e( 'App central e licenças dos clientes', 'comment-flow-hub' ); ?></p>
			</div>
		</div>
		<?php if ( CFHub_Settings::has_app() ) : ?>
			<span class="inline-flex items-center gap-2 rounded-full border border-solid border-blue-500/30 bg-blue-500/10 px-3 py-1.5 text-xs font-medium text-blue-200"><span class="h-2 w-2 rounded-full bg-blue-400"></span><?php esc_html_e( 'App da Meta configurado', 'comment-flow-hub' ); ?></span>
		<?php else : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfhub-settings' ) ); ?>" class="inline-flex items-center gap-2 rounded-full border border-solid border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-200"><span class="h-2 w-2 rounded-full bg-red-400"></span><?php esc_html_e( 'Configure o app da Meta', 'comment-flow-hub' ); ?></a>
		<?php endif; ?>
	</div>

	<nav class="mt-4 flex gap-1 overflow-x-auto">
		<?php foreach ( $hub_nav as $slug => $label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="<?php echo $slug === $hub_active ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white'; ?> whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="mt-8">
		<h1 class="m-0 p-0 text-2xl font-semibold leading-tight tracking-tight text-white"><?php echo esc_html( $hub_title ); ?></h1>
		<p class="mt-1 text-sm text-neutral-400"><?php echo esc_html( $hub_subtitle ); ?></p>
	</div>

	<?php if ( $hub_notice ) : ?>
		<?php $is_error = 'error' === $hub_notice['type']; ?>
		<div role="status" class="<?php echo $is_error ? 'border-red-500/30 bg-red-500/10 text-red-200' : 'border-blue-500/30 bg-blue-500/10 text-blue-100'; ?> mt-6 flex items-start gap-3 rounded-xl border border-solid px-4 py-3 text-sm">
			<span class="<?php echo $is_error ? 'bg-red-400' : 'bg-blue-400'; ?> mt-1.5 h-2 w-2 shrink-0 rounded-full"></span>
			<span><?php echo esc_html( $hub_notice['message'] ); ?></span>
		</div>
	<?php endif; ?>
	<hr class="wp-header-end m-0 border-0">
</header>
