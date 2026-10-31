<?php
/**
 * Conta: perfil, senha e exclusão.
 *
 * @var WP_User      $user
 * @var DateTimeZone $tz
 */

defined( 'ABSPATH' ) || exit;

$pflow_active   = 'account';
$pflow_title    = __( 'Conta', 'post-flow' );
$pflow_subtitle = $user->user_email;
$pflow_actions  = '';
?>
<div class="mx-auto max-w-3xl pb-16">
	<?php require PFLOW_DIR . 'app/views/partials/header.php'; ?>

	<div class="space-y-6">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
			<input type="hidden" name="action" value="pflow_save_account">
			<?php wp_nonce_field( 'pflow_save_account' ); ?>
			<h2 class="m-0 mb-6 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'Perfil', 'post-flow' ); ?></h2>
			<div class="grid gap-5 sm:grid-cols-2">
				<div>
					<label for="pflow-name" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Nome', 'post-flow' ); ?></label>
					<input type="text" id="pflow-name" name="name" value="<?php echo esc_attr( $user->display_name ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
				</div>
				<div>
					<label for="pflow-email-ro" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'E-mail', 'post-flow' ); ?></label>
					<input type="email" id="pflow-email-ro" value="<?php echo esc_attr( $user->user_email ); ?>" disabled class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2 opacity-60">
				</div>
			</div>
			<div class="mt-6 flex flex-wrap items-center gap-3">
				<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?>"><?php esc_html_e( 'Salvar', 'post-flow' ); ?></button>
				<a href="<?php echo esc_url( wp_lostpassword_url( PFlow_App::url() ) ); ?>" class="text-sm <?php echo esc_attr( PFlow_App::LINK ); ?>"><?php esc_html_e( 'Trocar a senha (enviamos um link por e-mail)', 'post-flow' ); ?></a>
			</div>
		</form>

		<?php if ( PFlow_Auth::is_member( $user ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rounded-2xl border border-solid border-red-500/30 bg-red-500/5 p-6">
				<input type="hidden" name="action" value="pflow_delete_account">
				<?php wp_nonce_field( 'pflow_delete_account' ); ?>
				<h2 class="m-0 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'Excluir conta', 'post-flow' ); ?></h2>
				<p class="mt-2 text-sm leading-6 text-neutral-400"><?php esc_html_e( 'Apaga a sua conta, os canais conectados (e os tokens), os posts agendados, o histórico e as imagens. Os posts já publicados continuam nas redes.', 'post-flow' ); ?></p>
				<label for="pflow-confirm" class="<?php echo esc_attr( PFlow_App::LABEL ); ?> mt-5"><?php esc_html_e( 'Digite EXCLUIR para confirmar', 'post-flow' ); ?></label>
				<div class="mt-2 flex flex-wrap gap-3">
					<input type="text" id="pflow-confirm" name="confirm" autocomplete="off" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> max-w-[220px]">
					<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_DANGER ); ?>"><?php esc_html_e( 'Excluir definitivamente', 'post-flow' ); ?></button>
				</div>
			</form>
		<?php endif; ?>
	</div>
</div>
