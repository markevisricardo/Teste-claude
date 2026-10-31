<?php
/**
 * Cabeçalho do painel: marca, navegação, canais e usuário.
 *
 * @var string $pflow_active   Tela atual.
 * @var string $pflow_title    Título da página.
 * @var string $pflow_subtitle Subtítulo (opcional).
 * @var string $pflow_actions  HTML já escapado dos botões do topo (opcional).
 */

defined( 'ABSPATH' ) || exit;

$pflow_nav      = array(
	'queue'    => __( 'Fila', 'post-flow' ),
	'calendar' => __( 'Calendário', 'post-flow' ),
	'sent'     => __( 'Publicados', 'post-flow' ),
	'channels' => __( 'Canais', 'post-flow' ),
	'schedule' => __( 'Horários', 'post-flow' ),
	'account'  => __( 'Conta', 'post-flow' ),
);
$pflow_user     = wp_get_current_user();
$pflow_notice   = PFlow_App::flash();
$pflow_channels = PFlow_Channels::all();
$pflow_expired  = count( wp_list_filter( $pflow_channels, array( 'status' => 'expired' ) ) );

// Erro vindo na URL (ex.: retorno do OAuth com "state" vencido).
if ( ! $pflow_notice && isset( $_GET['pf_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$pflow_messages = PFlow_Auth::messages();
	$pflow_code     = sanitize_key( wp_unslash( $_GET['pf_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $pflow_messages[ $pflow_code ] ) ) {
		$pflow_notice = array( 'type' => 'error', 'message' => $pflow_messages[ $pflow_code ] );
	}
}
?>
<header class="mx-auto mb-8 max-w-7xl">
	<div class="pflow-hero relative overflow-hidden rounded-3xl bg-gradient-to-br from-violet-600 via-violet-700 to-indigo-900 px-5 pb-8 pt-5 shadow-2xl shadow-violet-900/40 sm:px-8">
	<div class="relative flex flex-wrap items-center justify-between gap-4 border-0 border-b border-solid border-white/15 pb-5">
		<a href="<?php echo esc_url( PFlow_App::url() ); ?>" class="flex items-center gap-3">
			<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-lg shadow-violet-950/30">
				<svg class="h-5 w-5 text-violet-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
			</span>
			<span>
				<span class="block text-base font-semibold tracking-tight text-white">Post Flow</span>
				<span class="block text-xs text-violet-100/80"><?php esc_html_e( 'Agende uma vez, publique em todas as redes', 'post-flow' ); ?></span>
			</span>
		</a>

		<div class="flex flex-wrap items-center gap-3">
			<a href="<?php echo esc_url( PFlow_App::url( 'channels' ) ); ?>" class="inline-flex items-center gap-2 rounded-full border border-solid border-white/25 <?php echo $pflow_expired || ! $pflow_channels ? 'bg-black/20' : 'bg-white/15'; ?> px-3 py-1.5 text-xs font-medium text-white hover:bg-white/25">
				<?php if ( ! $pflow_channels ) : ?>
					<span class="h-2 w-2 rounded-full bg-amber-300"></span>
					<?php esc_html_e( 'Nenhum canal conectado', 'post-flow' ); ?>
				<?php elseif ( $pflow_expired ) : ?>
					<span class="h-2 w-2 rounded-full bg-red-400"></span>
					<?php
					/* translators: %d: canais */
					echo esc_html( sprintf( _n( '%d canal para reconectar', '%d canais para reconectar', $pflow_expired, 'post-flow' ), $pflow_expired ) );
					?>
				<?php else : ?>
					<span class="flex -space-x-1.5">
						<?php foreach ( array_slice( $pflow_channels, 0, 5 ) as $pflow_channel ) : ?>
							<?php $pflow_type = PFlow_Networks::type( $pflow_channel['network'] ); ?>
							<span class="flex h-5 w-5 items-center justify-center rounded-full text-[8px] font-bold text-white ring-2 ring-violet-700" style="background:<?php echo esc_attr( $pflow_type['color'] ); ?>"><?php echo esc_html( $pflow_type['glyph'] ); ?></span>
						<?php endforeach; ?>
					</span>
					<?php
					/* translators: %d: canais */
					echo esc_html( sprintf( _n( '%d canal', '%d canais', count( $pflow_channels ), 'post-flow' ), count( $pflow_channels ) ) );
					?>
				<?php endif; ?>
			</a>
			<span class="hidden h-6 w-px bg-white/20 sm:block"></span>
			<span class="hidden text-sm text-violet-100 sm:inline"><?php echo esc_html( $pflow_user->display_name ); ?></span>
			<a href="<?php echo esc_url( wp_logout_url( PFlow_App::url() ) ); ?>" class="text-sm font-medium text-violet-100 hover:text-white"><?php esc_html_e( 'Sair', 'post-flow' ); ?></a>
		</div>
	</div>

	<div class="relative mt-4 flex flex-wrap items-center justify-between gap-3">
		<nav class="flex gap-1 overflow-x-auto" aria-label="<?php esc_attr_e( 'Navegação do painel', 'post-flow' ); ?>">
			<?php foreach ( $pflow_nav as $pflow_view => $pflow_label ) : ?>
				<?php $pflow_current = $pflow_view === $pflow_active; ?>
				<a href="<?php echo esc_url( PFlow_App::url( $pflow_view ) ); ?>"
					class="<?php echo $pflow_current ? 'bg-white text-violet-700 shadow-sm' : 'text-violet-100 hover:bg-white/10 hover:text-white'; ?> whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors"
					<?php echo $pflow_current ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $pflow_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<a href="<?php echo esc_url( PFlow_App::url( 'compose' ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?>">
			<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z"/></svg>
			<?php esc_html_e( 'Criar post', 'post-flow' ); ?>
		</a>
	</div>

	<div class="relative mt-8 flex flex-wrap items-end justify-between gap-4">
		<div>
			<h1 class="m-0 p-0 text-3xl font-semibold leading-tight tracking-tight text-white"><?php echo esc_html( $pflow_title ); ?></h1>
			<?php if ( ! empty( $pflow_subtitle ) ) : ?>
				<p class="mt-1 text-sm text-violet-100"><?php echo esc_html( $pflow_subtitle ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $pflow_actions ) ) : ?>
			<div class="flex flex-wrap items-center gap-2"><?php echo $pflow_actions; // phpcs:ignore WordPress.Security.EscapeOutput -- montado com esc_* na view. ?></div>
		<?php endif; ?>
	</div>
	</div>

	<?php if ( $pflow_notice ) : ?>
		<?php
		$pflow_tones = array(
			'error'   => array( 'border-red-500/30 bg-red-500/10 text-red-200', 'bg-red-400' ),
			'warning' => array( 'border-amber-500/30 bg-amber-500/10 text-amber-100', 'bg-amber-400' ),
			'success' => array( 'border-violet-500/30 bg-violet-500/10 text-violet-100', 'bg-violet-400' ),
		);
		$pflow_tone  = isset( $pflow_tones[ $pflow_notice['type'] ] ) ? $pflow_tones[ $pflow_notice['type'] ] : $pflow_tones['success'];
		?>
		<div role="status" class="<?php echo esc_attr( $pflow_tone[0] ); ?> mt-6 flex items-start gap-3 rounded-xl border border-solid px-4 py-3 text-sm">
			<span class="<?php echo esc_attr( $pflow_tone[1] ); ?> mt-1.5 h-2 w-2 shrink-0 rounded-full"></span>
			<span><?php echo esc_html( $pflow_notice['message'] ); ?></span>
		</div>
	<?php endif; ?>
</header>
