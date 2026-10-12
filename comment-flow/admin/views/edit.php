<?php
/**
 * @var int            $id
 * @var array          $automation
 * @var array|WP_Error $media
 */

defined( 'ABSPATH' ) || exit;

$media_ids     = is_array( $media ) ? wp_list_pluck( $media, 'id' ) : array();
$is_manual     = '' !== $automation['media_id'] && ! in_array( $automation['media_id'], $media_ids, true );
$selected_kind = '' === $automation['media_id'] ? 'any' : ( $is_manual ? 'manual' : $automation['media_id'] );
$defaults      = CFlow_Conversation::defaults();
?>
<div class="wrap cflow">
	<h1><?php echo $id ? esc_html__( 'Editar automação', 'comment-flow' ) : esc_html__( 'Nova automação', 'comment-flow' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cflow-form">
		<input type="hidden" name="action" value="cflow_save_automation">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<?php wp_nonce_field( 'cflow_save_automation' ); ?>

		<div class="cflow-card">
			<h2><?php esc_html_e( '1. Gatilho', 'comment-flow' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="cflow-name"><?php esc_html_e( 'Nome', 'comment-flow' ); ?></label></th>
					<td><input type="text" id="cflow-name" name="name" class="regular-text" value="<?php echo esc_attr( $automation['name'] ); ?>" placeholder="<?php esc_attr_e( 'Ex.: E-book grátis', 'comment-flow' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Ativa', 'comment-flow' ); ?></th>
					<td><label><input type="checkbox" name="status" value="1" <?php checked( 'active', $automation['status'] ); ?>> <?php esc_html_e( 'Responder comentários com esta automação', 'comment-flow' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Post', 'comment-flow' ); ?></th>
					<td>
						<div class="cflow-media-grid">
							<label class="cflow-media cflow-media--text">
								<input type="radio" name="media_id" value="" <?php checked( 'any', $selected_kind ); ?>>
								<span><?php esc_html_e( 'Qualquer post ou reel', 'comment-flow' ); ?></span>
							</label>
							<?php if ( is_array( $media ) ) : ?>
								<?php foreach ( $media as $item ) : ?>
									<?php
									$thumb   = ! empty( $item['thumbnail_url'] ) ? $item['thumbnail_url'] : ( isset( $item['media_url'] ) && 'VIDEO' !== ( $item['media_type'] ?? '' ) ? $item['media_url'] : '' );
									$caption = isset( $item['caption'] ) ? wp_trim_words( $item['caption'], 8 ) : '';
									?>
									<label class="cflow-media" title="<?php echo esc_attr( $caption ); ?>">
										<input type="radio" name="media_id" value="<?php echo esc_attr( $item['id'] ); ?>" <?php checked( $item['id'], $selected_kind ); ?>>
										<?php if ( $thumb ) : ?>
											<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy">
										<?php else : ?>
											<span><?php echo esc_html( $caption ? $caption : $item['id'] ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							<?php endif; ?>
							<label class="cflow-media cflow-media--text">
								<input type="radio" name="media_id" value="manual" <?php checked( 'manual', $selected_kind ); ?>>
								<span><?php esc_html_e( 'ID manual', 'comment-flow' ); ?></span>
							</label>
						</div>
						<p>
							<input type="text" name="media_id_manual" class="regular-text" value="<?php echo esc_attr( $is_manual ? $automation['media_id'] : '' ); ?>" placeholder="<?php esc_attr_e( 'ID da mídia (quando "ID manual")', 'comment-flow' ); ?>">
						</p>
						<?php if ( is_wp_error( $media ) ) : ?>
							<p class="description cflow-error"><?php echo esc_html( sprintf( /* translators: %s: erro */ __( 'Não foi possível carregar seus posts: %s', 'comment-flow' ), $media->get_error_message() ) ); ?></p>
						<?php elseif ( ! CFlow_Settings::is_connected() ) : ?>
							<p class="description"><?php esc_html_e( 'Conecte sua conta para escolher um post pela miniatura.', 'comment-flow' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="cflow-keywords"><?php esc_html_e( 'Palavras-chave', 'comment-flow' ); ?></label></th>
					<td>
						<textarea id="cflow-keywords" name="keywords" rows="3" class="large-text" placeholder="quero, eu quero, link"><?php echo esc_textarea( $automation['keywords'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Separe por vírgula ou linha. Maiúsculas e acentos são ignorados. Deixe vazio para responder a qualquer comentário.', 'comment-flow' ); ?></p>
						<p>
							<select name="match_mode">
								<option value="word" <?php selected( 'word', $automation['match_mode'] ); ?>><?php esc_html_e( 'Contém a palavra (recomendado)', 'comment-flow' ); ?></option>
								<option value="contains" <?php selected( 'contains', $automation['match_mode'] ); ?>><?php esc_html_e( 'Contém o trecho em qualquer lugar', 'comment-flow' ); ?></option>
								<option value="exact" <?php selected( 'exact', $automation['match_mode'] ); ?>><?php esc_html_e( 'Comentário exatamente igual', 'comment-flow' ); ?></option>
							</select>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="cflow-priority"><?php esc_html_e( 'Prioridade', 'comment-flow' ); ?></label></th>
					<td>
						<input type="number" id="cflow-priority" name="priority" class="small-text" value="<?php echo (int) $automation['priority']; ?>">
						<p class="description"><?php esc_html_e( 'Menor número = avaliada primeiro.', 'comment-flow' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="cflow-card">
			<h2><?php esc_html_e( '2. Resposta pública no comentário', 'comment-flow' ); ?></h2>
			<textarea name="public_replies" rows="4" class="large-text"><?php echo esc_textarea( $automation['public_replies'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Uma variação por linha; o plugin sorteia uma para parecer mais natural. Use {username} para mencionar a pessoa. Deixe vazio para não responder publicamente.', 'comment-flow' ); ?></p>
		</div>

		<div class="cflow-card">
			<h2><?php esc_html_e( '3. Captura de e-mail e telefone (opcional)', 'comment-flow' ); ?></h2>
			<p>
				<label><input type="checkbox" name="capture_email" value="1" <?php checked( 1, (int) $automation['capture_email'] ); ?>> <?php esc_html_e( 'Pedir e-mail', 'comment-flow' ); ?></label>
				&nbsp;&nbsp;
				<label><input type="checkbox" name="capture_phone" value="1" <?php checked( 1, (int) $automation['capture_phone'] ); ?>> <?php esc_html_e( 'Pedir telefone (WhatsApp)', 'comment-flow' ); ?></label>
			</p>
			<p class="description"><?php esc_html_e( 'Com a captura ligada, a primeira DM é o convite abaixo. Quando a pessoa responde, o plugin pede os dados e só então envia a mensagem final (passo 4). Os dados aparecem em Comment Flow > Leads.', 'comment-flow' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="cflow-optin"><?php esc_html_e( 'Convite (primeira DM)', 'comment-flow' ); ?></label></th>
					<td><textarea id="cflow-optin" name="optin_text" rows="3" class="large-text" placeholder="<?php echo esc_attr( $defaults['optin_text'] ); ?>"><?php echo esc_textarea( (string) $automation['optin_text'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="cflow-optin-btn"><?php esc_html_e( 'Botão de resposta', 'comment-flow' ); ?></label></th>
					<td><input type="text" id="cflow-optin-btn" name="optin_button" maxlength="20" class="regular-text" value="<?php echo esc_attr( (string) $automation['optin_button'] ); ?>" placeholder="<?php echo esc_attr( $defaults['optin_button'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cflow-email-q"><?php esc_html_e( 'Pergunta do e-mail', 'comment-flow' ); ?></label></th>
					<td>
						<textarea id="cflow-email-q" name="email_question" rows="2" class="large-text" placeholder="<?php echo esc_attr( $defaults['email_question'] ); ?>"><?php echo esc_textarea( (string) $automation['email_question'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Deixe claro para que o e-mail será usado: o consentimento é registrado junto com o lead (LGPD).', 'comment-flow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="cflow-phone-q"><?php esc_html_e( 'Pergunta do telefone', 'comment-flow' ); ?></label></th>
					<td>
						<textarea id="cflow-phone-q" name="phone_question" rows="2" class="large-text" placeholder="<?php echo esc_attr( $defaults['phone_question'] ); ?>"><?php echo esc_textarea( (string) $automation['phone_question'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Quem responder PULAR segue sem informar o telefone.', 'comment-flow' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="cflow-card">
			<h2><?php esc_html_e( '4. Mensagem no direct', 'comment-flow' ); ?></h2>
			<textarea name="dm_text" rows="5" class="large-text"><?php echo esc_textarea( $automation['dm_text'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Sem captura, é a Private Reply enviada uma única vez por comentário. Com captura, é a entrega final, enviada depois que a pessoa informa os dados. Use {username} para mencionar a pessoa.', 'comment-flow' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="cflow-btn-label"><?php esc_html_e( 'Texto do botão', 'comment-flow' ); ?></label></th>
					<td><input type="text" id="cflow-btn-label" name="dm_button_label" maxlength="20" class="regular-text" value="<?php echo esc_attr( $automation['dm_button_label'] ); ?>" placeholder="<?php esc_attr_e( 'Ex.: Baixar e-book', 'comment-flow' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cflow-btn-url"><?php esc_html_e( 'Link do botão', 'comment-flow' ); ?></label></th>
					<td>
						<input type="url" id="cflow-btn-url" name="dm_button_url" class="regular-text" value="<?php echo esc_attr( $automation['dm_button_url'] ); ?>" placeholder="https://">
						<p class="description"><?php esc_html_e( 'Opcional. Sem botão, a DM vai só com o texto (você pode colar o link no texto).', 'comment-flow' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Salvar automação', 'comment-flow' ) ); ?>
	</form>
</div>
