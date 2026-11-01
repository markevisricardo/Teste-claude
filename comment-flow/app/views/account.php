<?php
/**
 * Minha conta: Instagram, preferências e perfil.
 *
 * @var array   $account Conta do Instagram do usuário (CFlow_Accounts).
 * @var WP_User $user    Usuário logado.
 */

defined( 'ABSPATH' ) || exit;

$connected = '' !== $account['access_token'] && '' !== $account['ig_user_id'];
$days_left = $account['token_expires_at'] ? max( 0, (int) floor( ( $account['token_expires_at'] - time() ) / DAY_IN_SECONDS ) ) : 0;
$has_app   = CFlow_Settings::has_app();

$cflow_active   = 'account';
$cflow_title    = __( 'Minha conta', 'comment-flow' );
$cflow_subtitle = __( 'Conecte o seu Instagram e ajuste as preferências.', 'comment-flow' );
$cflow_actions  = '';

$step = static function ( $number, $title, $done ) {
	?>
	<div class="mb-6 flex items-center gap-4">
		<span class="<?php echo $done ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30' : 'border border-solid border-neutral-700 text-neutral-400'; ?> flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
			<?php if ( $done ) : ?>
				<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
			<?php else : ?>
				<?php echo (int) $number; ?>
			<?php endif; ?>
		</span>
		<h2 class="m-0 p-0 text-lg font-semibold text-white"><?php echo esc_html( $title ); ?></h2>
	</div>
	<?php
};
?>
<div class="mx-auto max-w-4xl pb-16">
	<?php require CFLOW_DIR . 'app/views/partials/header.php'; ?>

	<div class="space-y-6">
		<!-- Instagram -->
		<section class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
			<?php $step( 1, __( 'Conta do Instagram', 'comment-flow' ), $connected ); ?>
			<?php if ( $connected ) : ?>
				<div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-solid border-blue-500/30 bg-blue-500/5 p-4">
					<div class="flex items-center gap-3">
						<span class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-base font-semibold text-white"><?php echo esc_html( strtoupper( mb_substr( (string) $account['ig_username'], 0, 1 ) ) ); ?></span>
						<div>
							<p class="text-sm font-semibold text-white">@<?php echo esc_html( $account['ig_username'] ); ?></p>
							<p class="mt-0.5 text-xs text-neutral-400">
								<?php
								/* translators: %d: dias */
								echo esc_html( sprintf( __( 'Conectada · a conexão é renovada sozinha (expira em %d dias)', 'comment-flow' ), $days_left ) );
								?>
							</p>
						</div>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cflow_disconnect">
						<?php wp_nonce_field( 'cflow_disconnect' ); ?>
						<button type="submit" class="<?php echo esc_attr( CFlow_App::BTN_SECONDARY ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Desconectar o Instagram? As automações param até você conectar de novo.', 'comment-flow' ) ); ?>');"><?php esc_html_e( 'Desconectar', 'comment-flow' ); ?></button>
					</form>
				</div>
			<?php else : ?>
				<p class="mb-5 text-sm leading-6 text-neutral-400"><?php esc_html_e( 'Clique abaixo, entre com a conta do Instagram que você quer automatizar e autorize. Você volta para cá em seguida.', 'comment-flow' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cflow_connect">
					<?php wp_nonce_field( 'cflow_connect' ); ?>
					<button type="submit" class="<?php echo esc_attr( CFlow_App::BTN_PRIMARY ); ?>" <?php disabled( ! $has_app ); ?>>
						<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
						<?php esc_html_e( 'Conectar com o Instagram', 'comment-flow' ); ?>
					</button>
				</form>
				<?php if ( ! $has_app ) : ?>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<p class="mt-3 text-xs text-red-400">
							<?php esc_html_e( 'Falta salvar o App ID e o App Secret do Instagram.', 'comment-flow' ); ?>
							<a class="<?php echo esc_attr( CFlow_App::LINK ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=cflow' ) ); ?>"><?php esc_html_e( 'Abrir as configurações →', 'comment-flow' ); ?></a>
						</p>
					<?php else : ?>
						<p class="mt-3 text-xs text-red-400"><?php esc_html_e( 'A plataforma ainda está sendo configurada. Tente de novo mais tarde.', 'comment-flow' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
				<ul class="mt-5 list-disc space-y-1 pl-5 text-xs leading-5 text-neutral-500">
					<li><?php esc_html_e( 'A conta precisa ser Profissional (Empresa ou Criador de conteúdo).', 'comment-flow' ); ?></li>
					<li><?php esc_html_e( 'No app do Instagram, ative: Configurações → Mensagens → Controles de mensagens → Ferramentas conectadas → Permitir acesso às mensagens.', 'comment-flow' ); ?></li>
				</ul>
			<?php endif; ?>
		</section>

		<!-- Preferências -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
			<input type="hidden" name="action" value="cflow_save_preferences">
			<?php wp_nonce_field( 'cflow_save_preferences' ); ?>
			<h2 class="m-0 mb-4 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'Preferências', 'comment-flow' ); ?></h2>
			<div class="flex flex-wrap items-end gap-3">
				<div class="max-w-[200px]">
					<label for="cflow-delay" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Atraso antes de responder', 'comment-flow' ); ?></label>
					<div class="mt-2 flex items-center gap-2">
						<input type="number" id="cflow-delay" name="delay_seconds" min="0" max="300" class="<?php echo esc_attr( CFlow_App::INPUT ); ?>" value="<?php echo (int) $account['delay_seconds']; ?>">
						<span class="text-sm text-neutral-400"><?php esc_html_e( 'segundos', 'comment-flow' ); ?></span>
					</div>
				</div>
				<button type="submit" class="<?php echo esc_attr( CFlow_App::BTN_SECONDARY ); ?>"><?php esc_html_e( 'Salvar', 'comment-flow' ); ?></button>
			</div>
			<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Um pequeno atraso deixa as respostas mais naturais.', 'comment-flow' ); ?></p>
		</form>

		<!-- Perfil -->
		<section class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
			<h2 class="m-0 mb-4 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'Perfil', 'comment-flow' ); ?></h2>
			<div class="flex flex-wrap items-center justify-between gap-4">
				<div>
					<p class="text-sm font-semibold text-white"><?php echo esc_html( $user->display_name ); ?></p>
					<p class="mt-0.5 text-sm text-neutral-400"><?php echo esc_html( $user->user_email ); ?></p>
				</div>
				<div class="flex flex-wrap gap-2">
					<a href="<?php echo esc_url( wp_lostpassword_url( CFlow_App::url() ) ); ?>" class="<?php echo esc_attr( CFlow_App::BTN_SECONDARY ); ?>"><?php esc_html_e( 'Alterar senha', 'comment-flow' ); ?></a>
					<a href="<?php echo esc_url( wp_logout_url( CFlow_App::url() ) ); ?>" class="<?php echo esc_attr( CFlow_App::BTN_SECONDARY ); ?>"><?php esc_html_e( 'Sair', 'comment-flow' ); ?></a>
				</div>
			</div>
		</section>
	</div>
</div>
