<?php
/**
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

$connected = CFlow_Settings::is_connected();
$days_left = $settings['token_expires_at'] ? (int) floor( ( $settings['token_expires_at'] - time() ) / DAY_IN_SECONDS ) : 0;
?>
<div class="wrap cflow">
	<h1><?php esc_html_e( 'Configurações', 'comment-flow' ); ?></h1>

	<?php if ( ! is_ssl() && 0 !== strpos( home_url(), 'https://' ) ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Seu site não usa HTTPS. A Meta só entrega webhooks e aceita redirecionamentos em HTTPS.', 'comment-flow' ); ?></p></div>
	<?php endif; ?>
	<?php if ( '' === get_option( 'permalink_structure' ) ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Os links permanentes estão no modo "Simples". Ative um modo amigável (ex.: "Nome do post") para que as URLs abaixo não tenham "?rest_route=".', 'comment-flow' ); ?></p></div>
	<?php endif; ?>

	<div class="cflow-card">
		<h2><?php esc_html_e( '1. Dados para cadastrar no app da Meta', 'comment-flow' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'URL de redirecionamento OAuth', 'comment-flow' ); ?></th>
				<td><input type="text" readonly class="large-text code" value="<?php echo esc_attr( CFlow_Settings::oauth_redirect_url() ); ?>" onclick="this.select()"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'URL de callback do webhook', 'comment-flow' ); ?></th>
				<td><input type="text" readonly class="large-text code" value="<?php echo esc_attr( CFlow_Settings::webhook_url() ); ?>" onclick="this.select()"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Token de verificação', 'comment-flow' ); ?></th>
				<td><input type="text" readonly class="regular-text code" value="<?php echo esc_attr( $settings['verify_token'] ); ?>" onclick="this.select()"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Campos do webhook', 'comment-flow' ); ?></th>
				<td><code>comments</code>, <code>messages</code></td>
			</tr>
		</table>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cflow-card">
		<h2><?php esc_html_e( '2. Credenciais do app', 'comment-flow' ); ?></h2>
		<input type="hidden" name="action" value="cflow_save_settings">
		<input type="hidden" name="verify_token" value="<?php echo esc_attr( $settings['verify_token'] ); ?>">
		<?php wp_nonce_field( 'cflow_save_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="cflow-app-id"><?php esc_html_e( 'Instagram App ID', 'comment-flow' ); ?></label></th>
				<td><input type="text" id="cflow-app-id" name="app_id" class="regular-text" value="<?php echo esc_attr( $settings['app_id'] ); ?>" autocomplete="off"></td>
			</tr>
			<tr>
				<th><label for="cflow-app-secret"><?php esc_html_e( 'Instagram App Secret', 'comment-flow' ); ?></label></th>
				<td>
					<input type="password" id="cflow-app-secret" name="app_secret" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $settings['app_secret'] ? esc_attr__( '•••••••• (salvo — deixe vazio para manter)', 'comment-flow' ) : ''; ?>">
					<p class="description"><?php esc_html_e( 'Use o App ID e o App Secret do Instagram (em "Instagram > Configuração da API com login do Instagram"), não os do app do Facebook.', 'comment-flow' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cflow-delay"><?php esc_html_e( 'Atraso antes de responder', 'comment-flow' ); ?></label></th>
				<td>
					<input type="number" id="cflow-delay" name="delay_seconds" min="0" max="300" class="small-text" value="<?php echo (int) $settings['delay_seconds']; ?>"> <?php esc_html_e( 'segundos', 'comment-flow' ); ?>
					<p class="description"><?php esc_html_e( 'Um pequeno atraso deixa as respostas mais naturais.', 'comment-flow' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Salvar credenciais', 'comment-flow' ) ); ?>
	</form>

	<div class="cflow-card">
		<h2><?php esc_html_e( '3. Conta do Instagram', 'comment-flow' ); ?></h2>
		<?php if ( $connected ) : ?>
			<p>
				<?php
				printf(
					/* translators: 1: @username 2: ID */
					esc_html__( 'Conectado como %1$s (ID %2$s).', 'comment-flow' ),
					'<strong>@' . esc_html( $settings['ig_username'] ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput
					'<code>' . esc_html( $settings['ig_user_id'] ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput
				);
				?>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %d: dias */
					esc_html__( 'O token expira em %d dias e é renovado automaticamente.', 'comment-flow' ),
					(int) $days_left
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cflow_disconnect">
				<?php wp_nonce_field( 'cflow_disconnect' ); ?>
				<?php submit_button( __( 'Desconectar', 'comment-flow' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cflow_connect">
				<?php wp_nonce_field( 'cflow_connect' ); ?>
				<?php submit_button( __( 'Conectar com o Instagram', 'comment-flow' ), 'primary', 'submit', false, '' === $settings['app_id'] || '' === $settings['app_secret'] ? array( 'disabled' => 'disabled' ) : array() ); ?>
			</form>
			<p class="description"><?php esc_html_e( 'A conta precisa ser Profissional (Empresa ou Criador de conteúdo).', 'comment-flow' ); ?></p>
		<?php endif; ?>
	</div>
</div>
