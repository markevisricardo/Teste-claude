<?php
/**
 * Cabeçalho comum: marca, status da conexão, navegação e avisos.
 *
 * @var string $cflow_active   Slug da página atual (cflow, cflow-edit, ...).
 * @var string $cflow_title    Título da página.
 * @var string $cflow_subtitle Subtítulo (opcional).
 * @var string $cflow_actions  HTML já escapado dos botões do topo (opcional).
 */

defined( 'ABSPATH' ) || exit;

$cflow_nav = array(
	'cflow'          => __( 'Automações', 'comment-flow' ),
	'cflow-edit'     => __( 'Nova automação', 'comment-flow' ),
	'cflow-leads'    => __( 'Leads', 'comment-flow' ),
	'cflow-logs'     => __( 'Logs', 'comment-flow' ),
	'cflow-settings' => __( 'Configurações', 'comment-flow' ),
);
$cflow_connected = CFlow_Settings::is_connected();
$cflow_username  = (string) CFlow_Settings::get( 'ig_username' );
$cflow_notice    = CFlow_Admin::flash();
?>
<header class="mb-8">
	<div class="flex flex-wrap items-center justify-between gap-4 border-0 border-b border-solid border-neutral-900 pb-6 pt-2">
		<div class="flex items-center gap-3">
			<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 shadow-lg shadow-blue-600/30">
				<svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01"/></svg>
			</span>
			<div>
				<p class="text-base font-semibold tracking-tight text-white">Comment Flow</p>
				<p class="text-xs text-neutral-500"><?php esc_html_e( 'Automação de comentários do Instagram', 'comment-flow' ); ?></p>
			</div>
		</div>

		<?php if ( $cflow_connected ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-settings' ) ); ?>" class="inline-flex items-center gap-2 rounded-full border border-solid border-blue-500/30 bg-blue-500/10 px-3 py-1.5 text-xs font-medium text-blue-200 hover:text-white">
				<span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-60"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-blue-400"></span></span>
				<?php
				/* translators: %s: @usuário */
				echo esc_html( sprintf( __( 'Conectado como @%s', 'comment-flow' ), $cflow_username ) );
				?>
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow-settings' ) ); ?>" class="inline-flex items-center gap-2 rounded-full border border-solid border-neutral-700 px-3 py-1.5 text-xs font-medium text-neutral-300 hover:border-blue-500 hover:text-white">
				<span class="h-2 w-2 rounded-full bg-neutral-600"></span>
				<?php esc_html_e( 'Instagram não conectado', 'comment-flow' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<nav class="mt-4 flex gap-1 overflow-x-auto" aria-label="<?php esc_attr_e( 'Navegação do Comment Flow', 'comment-flow' ); ?>">
		<?php foreach ( $cflow_nav as $slug => $label ) : ?>
			<?php $is_current = $slug === $cflow_active; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"
				class="<?php echo $is_current ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white'; ?> whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors"
				<?php echo $is_current ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="mt-8 flex flex-wrap items-end justify-between gap-4">
		<div>
			<h1 class="m-0 p-0 text-2xl font-semibold leading-tight tracking-tight text-white"><?php echo esc_html( $cflow_title ); ?></h1>
			<?php if ( ! empty( $cflow_subtitle ) ) : ?>
				<p class="mt-1 text-sm text-neutral-400"><?php echo esc_html( $cflow_subtitle ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $cflow_actions ) ) : ?>
			<div class="flex flex-wrap items-center gap-2"><?php echo $cflow_actions; // phpcs:ignore WordPress.Security.EscapeOutput -- montado com esc_* na view. ?></div>
		<?php endif; ?>
	</div>

	<?php if ( $cflow_notice ) : ?>
		<?php $is_error = in_array( $cflow_notice['type'], array( 'error', 'warning' ), true ); ?>
		<div role="status" class="<?php echo $is_error ? 'border-red-500/30 bg-red-500/10 text-red-200' : 'border-blue-500/30 bg-blue-500/10 text-blue-100'; ?> mt-6 flex items-start gap-3 rounded-xl border border-solid px-4 py-3 text-sm">
			<span class="<?php echo $is_error ? 'bg-red-400' : 'bg-blue-400'; ?> mt-1.5 h-2 w-2 shrink-0 rounded-full"></span>
			<span><?php echo esc_html( $cflow_notice['message'] ); ?></span>
		</div>
	<?php endif; ?>

	<hr class="wp-header-end m-0 border-0">
</header>
