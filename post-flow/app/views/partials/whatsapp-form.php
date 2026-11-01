<?php
/**
 * Formulário de conexão (ou edição) do WhatsApp Business.
 *
 * @var array|null $wa Canal do WhatsApp sendo editado, ou null para um novo.
 */

defined( 'ABSPATH' ) || exit;

$pflow_wa_meta = $wa ? $wa['meta'] : array();
$pflow_wa_key  = $wa ? (string) $wa['id'] : 'new';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mt-4 space-y-4">
	<input type="hidden" name="action" value="pflow_connect_whatsapp">
	<?php wp_nonce_field( 'pflow_connect_whatsapp' ); ?>
	<div class="grid gap-4 sm:grid-cols-2">
		<div>
			<label for="pflow-wa-id-<?php echo esc_attr( $pflow_wa_key ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'ID do número de telefone', 'post-flow' ); ?></label>
			<input type="text" inputmode="numeric" id="pflow-wa-id-<?php echo esc_attr( $pflow_wa_key ); ?>" name="phone_number_id" required value="<?php echo esc_attr( $wa ? $wa['external_id'] : '' ); ?>" <?php echo $wa ? 'readonly' : ''; ?> class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
		</div>
		<div>
			<label for="pflow-wa-token-<?php echo esc_attr( $pflow_wa_key ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Token de acesso permanente', 'post-flow' ); ?></label>
			<input type="password" id="pflow-wa-token-<?php echo esc_attr( $pflow_wa_key ); ?>" name="access_token" autocomplete="new-password" <?php echo $wa ? '' : 'required'; ?> placeholder="<?php echo $wa ? esc_attr__( '•••••• (salvo — deixe vazio para manter)', 'post-flow' ) : ''; ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
		</div>
		<div>
			<label for="pflow-wa-template-<?php echo esc_attr( $pflow_wa_key ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Nome do modelo aprovado', 'post-flow' ); ?></label>
			<input type="text" id="pflow-wa-template-<?php echo esc_attr( $pflow_wa_key ); ?>" name="template" required placeholder="novidades" value="<?php echo esc_attr( $pflow_wa_meta['template'] ?? '' ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
		</div>
		<div>
			<label for="pflow-wa-lang-<?php echo esc_attr( $pflow_wa_key ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Idioma do modelo', 'post-flow' ); ?></label>
			<input type="text" id="pflow-wa-lang-<?php echo esc_attr( $pflow_wa_key ); ?>" name="language" value="<?php echo esc_attr( $pflow_wa_meta['language'] ?? 'pt_BR' ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
		</div>
	</div>
	<label class="flex cursor-pointer items-center gap-3">
		<input type="checkbox" name="header_image" value="1" class="peer sr-only" <?php checked( ! empty( $pflow_wa_meta['header_image'] ) ); ?>>
		<span class="<?php echo esc_attr( PFlow_App::TOGGLE ); ?>"></span>
		<span class="text-sm text-white"><?php esc_html_e( 'O modelo tem mídia no cabeçalho (usa o vídeo ou a 1ª imagem do post; o tipo precisa ser o mesmo do modelo)', 'post-flow' ); ?></span>
	</label>
	<div>
		<label for="pflow-wa-to-<?php echo esc_attr( $pflow_wa_key ); ?>" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Contatos que autorizaram receber', 'post-flow' ); ?></label>
		<textarea id="pflow-wa-to-<?php echo esc_attr( $pflow_wa_key ); ?>" name="recipients" rows="4" required placeholder="5511999998888&#10;5521988887777" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2 font-mono text-xs"><?php echo esc_textarea( implode( "\n", (array) ( $pflow_wa_meta['recipients'] ?? array() ) ) ); ?></textarea>
		<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>">
			<?php
			/* translators: %d: máximo de contatos */
			echo esc_html( sprintf( __( 'Um número por linha, com DDI e DDD (até %d). Envie só para quem pediu para receber: a Meta bloqueia números denunciados como spam.', 'post-flow' ), PFlow_Provider_WhatsApp::MAX_RECIPIENTS ) );
			?>
		</p>
	</div>
	<p class="rounded-xl bg-white/5 px-4 py-3 text-xs leading-5 text-neutral-400">
		<?php esc_html_e( 'No WhatsApp Manager, crie um modelo de categoria Marketing com a variável {{1}} no meio do texto, por exemplo: "Novidade da Clínica Sorriso: {{1}} — Responda SAIR para não receber mais." O texto do post entra no lugar de {{1}} (quebras de linha viram " · ").', 'post-flow' ); ?>
	</p>
	<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?>"><?php echo esc_html( $wa ? __( 'Salvar WhatsApp', 'post-flow' ) : __( 'Conectar WhatsApp', 'post-flow' ) ); ?></button>
</form>
