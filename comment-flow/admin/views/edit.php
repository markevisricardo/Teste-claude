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

$cflow_active   = $id ? '' : 'cflow-edit';
$cflow_title    = $id ? __( 'Editar automação', 'comment-flow' ) : __( 'Nova automação', 'comment-flow' );
$cflow_subtitle = __( 'Gatilho, resposta pública, captura de dados e mensagem no direct.', 'comment-flow' );
$cflow_actions  = sprintf(
	'<a href="%1$s" class="%2$s">%3$s</a>',
	esc_url( admin_url( 'admin.php?page=cflow' ) ),
	esc_attr( CFlow_Admin::BTN_SECONDARY ),
	esc_html__( '← Voltar', 'comment-flow' )
);

$step = static function ( $number, $title, $description = '' ) {
	?>
	<div class="mb-6 flex items-start gap-4">
		<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white shadow-lg shadow-blue-600/30"><?php echo (int) $number; ?></span>
		<div>
			<h2 class="m-0 p-0 text-lg font-semibold leading-8 text-white"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $description ) : ?>
				<p class="mt-1 text-sm leading-6 text-neutral-400"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
};
?>
<div class="wrap cflow-app"><div class="mx-auto max-w-7xl pb-16">
	<?php require CFLOW_DIR . 'admin/views/partials/header.php'; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
		<input type="hidden" name="action" value="cflow_save_automation">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<?php wp_nonce_field( 'cflow_save_automation' ); ?>

		<div class="space-y-6">
			<!-- 1. Gatilho -->
			<section class="<?php echo esc_attr( CFlow_Admin::CARD ); ?>">
				<?php $step( 1, __( 'Gatilho', 'comment-flow' ), __( 'Quando esta automação deve responder.', 'comment-flow' ) ); ?>

				<div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_auto]">
					<div>
						<label for="cflow-name" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Nome', 'comment-flow' ); ?></label>
						<input type="text" id="cflow-name" name="name" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" value="<?php echo esc_attr( $automation['name'] ); ?>" placeholder="<?php esc_attr_e( 'Ex.: E-book grátis', 'comment-flow' ); ?>">
					</div>
					<div class="md:pt-8">
						<label class="inline-flex cursor-pointer items-center gap-3">
							<input type="checkbox" name="status" value="1" class="peer sr-only" <?php checked( 'active', $automation['status'] ); ?>>
							<span class="<?php echo esc_attr( CFlow_Admin::TOGGLE ); ?>"></span>
							<span class="text-sm font-medium text-white"><?php esc_html_e( 'Ativa', 'comment-flow' ); ?></span>
						</label>
					</div>
				</div>

				<div class="mt-6">
					<span class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Post', 'comment-flow' ); ?></span>
					<div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4 xl:grid-cols-6">
						<label class="relative flex aspect-square cursor-pointer items-center justify-center rounded-xl border border-solid border-neutral-800 bg-neutral-900 p-3 text-center text-xs font-medium text-neutral-300 transition-colors hover:border-neutral-600">
							<input type="radio" name="media_id" value="" class="peer sr-only" <?php checked( 'any', $selected_kind ); ?>>
							<span><?php esc_html_e( 'Qualquer post ou reel', 'comment-flow' ); ?></span>
							<span class="pointer-events-none absolute inset-0 rounded-xl ring-2 ring-inset ring-transparent transition peer-checked:ring-blue-500 peer-focus-visible:ring-blue-300"></span>
						</label>
						<?php if ( is_array( $media ) ) : ?>
							<?php foreach ( $media as $item ) : ?>
								<?php
								$thumb   = ! empty( $item['thumbnail_url'] ) ? $item['thumbnail_url'] : ( isset( $item['media_url'] ) && 'VIDEO' !== ( $item['media_type'] ?? '' ) ? $item['media_url'] : '' );
								$caption = isset( $item['caption'] ) ? wp_trim_words( $item['caption'], 8 ) : '';
								?>
								<label class="group relative block aspect-square cursor-pointer overflow-hidden rounded-xl border border-solid border-neutral-800 bg-neutral-900" title="<?php echo esc_attr( $caption ); ?>">
									<input type="radio" name="media_id" value="<?php echo esc_attr( $item['id'] ); ?>" class="peer sr-only" <?php checked( $item['id'], $selected_kind ); ?>>
									<?php if ( $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover opacity-60 transition-opacity group-hover:opacity-90 peer-checked:opacity-100">
									<?php else : ?>
										<span class="absolute inset-0 flex items-center justify-center p-2 text-center text-xs text-neutral-400"><?php echo esc_html( $caption ? $caption : $item['id'] ); ?></span>
									<?php endif; ?>
									<span class="pointer-events-none absolute inset-0 rounded-xl ring-2 ring-inset ring-transparent transition peer-checked:ring-blue-500 peer-focus-visible:ring-blue-300"></span>
									<span class="pointer-events-none absolute right-2 top-2 hidden h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg peer-checked:flex">
										<svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
									</span>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
						<label class="relative flex aspect-square cursor-pointer items-center justify-center rounded-xl border border-dashed border-neutral-700 p-3 text-center text-xs font-medium text-neutral-400 transition-colors hover:border-neutral-500">
							<input type="radio" name="media_id" value="manual" class="peer sr-only" <?php checked( 'manual', $selected_kind ); ?>>
							<span><?php esc_html_e( 'ID manual', 'comment-flow' ); ?></span>
							<span class="pointer-events-none absolute inset-0 rounded-xl ring-2 ring-inset ring-transparent transition peer-checked:ring-blue-500 peer-focus-visible:ring-blue-300"></span>
						</label>
					</div>
					<input type="text" name="media_id_manual" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-3 max-w-md" value="<?php echo esc_attr( $is_manual ? $automation['media_id'] : '' ); ?>" placeholder="<?php esc_attr_e( 'ID da mídia (quando "ID manual")', 'comment-flow' ); ?>">
					<?php if ( is_wp_error( $media ) ) : ?>
						<p class="mt-2 text-xs text-red-400"><?php echo esc_html( sprintf( /* translators: %s: erro */ __( 'Não foi possível carregar seus posts: %s', 'comment-flow' ), $media->get_error_message() ) ); ?></p>
					<?php elseif ( ! CFlow_Settings::is_connected() ) : ?>
						<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Conecte sua conta para escolher um post pela miniatura.', 'comment-flow' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="mt-6 grid gap-6 md:grid-cols-[minmax(0,1fr)_240px]">
					<div>
						<label for="cflow-keywords" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Palavras-chave', 'comment-flow' ); ?></label>
						<textarea id="cflow-keywords" name="keywords" rows="3" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" placeholder="quero, eu quero, link"><?php echo esc_textarea( $automation['keywords'] ); ?></textarea>
						<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Separe por vírgula ou linha. Maiúsculas e acentos são ignorados. Vazio = qualquer comentário.', 'comment-flow' ); ?></p>
					</div>
					<div class="space-y-6">
						<div>
							<label for="cflow-match" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Comparação', 'comment-flow' ); ?></label>
							<select id="cflow-match" name="match_mode" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2">
								<option value="word" <?php selected( 'word', $automation['match_mode'] ); ?>><?php esc_html_e( 'Contém a palavra', 'comment-flow' ); ?></option>
								<option value="contains" <?php selected( 'contains', $automation['match_mode'] ); ?>><?php esc_html_e( 'Contém o trecho', 'comment-flow' ); ?></option>
								<option value="exact" <?php selected( 'exact', $automation['match_mode'] ); ?>><?php esc_html_e( 'Exatamente igual', 'comment-flow' ); ?></option>
							</select>
						</div>
						<div>
							<label for="cflow-priority" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Prioridade', 'comment-flow' ); ?></label>
							<input type="number" id="cflow-priority" name="priority" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" value="<?php echo (int) $automation['priority']; ?>">
							<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Menor número = avaliada primeiro.', 'comment-flow' ); ?></p>
						</div>
					</div>
				</div>
			</section>

			<!-- 2. Resposta pública -->
			<section class="<?php echo esc_attr( CFlow_Admin::CARD ); ?>">
				<?php $step( 2, __( 'Resposta pública no comentário', 'comment-flow' ), __( 'Uma variação por linha; o plugin sorteia uma para parecer mais natural. Vazio = não responder publicamente.', 'comment-flow' ) ); ?>
				<textarea name="public_replies" rows="4" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?>"><?php echo esc_textarea( $automation['public_replies'] ); ?></textarea>
				<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Use {username} para mencionar a pessoa.', 'comment-flow' ); ?></p>
			</section>

			<!-- 3. Captura -->
			<section class="<?php echo esc_attr( CFlow_Admin::CARD ); ?>">
				<?php $step( 3, __( 'Captura de e-mail e telefone', 'comment-flow' ), __( 'Opcional. A primeira DM vira um convite; quando a pessoa responde, o plugin pede os dados e só então envia a mensagem do passo 4.', 'comment-flow' ) ); ?>

				<div class="flex flex-wrap gap-6">
					<label class="inline-flex cursor-pointer items-center gap-3">
						<input type="checkbox" name="capture_email" value="1" class="peer sr-only" <?php checked( 1, (int) $automation['capture_email'] ); ?>>
						<span class="<?php echo esc_attr( CFlow_Admin::TOGGLE ); ?>"></span>
						<span class="text-sm font-medium text-white"><?php esc_html_e( 'Pedir e-mail', 'comment-flow' ); ?></span>
					</label>
					<label class="inline-flex cursor-pointer items-center gap-3">
						<input type="checkbox" name="capture_phone" value="1" class="peer sr-only" <?php checked( 1, (int) $automation['capture_phone'] ); ?>>
						<span class="<?php echo esc_attr( CFlow_Admin::TOGGLE ); ?>"></span>
						<span class="text-sm font-medium text-white"><?php esc_html_e( 'Pedir telefone (WhatsApp)', 'comment-flow' ); ?></span>
					</label>
				</div>

				<div class="mt-6 grid gap-6 md:grid-cols-[minmax(0,1fr)_220px]">
					<div>
						<label for="cflow-optin" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Convite (primeira DM)', 'comment-flow' ); ?></label>
						<textarea id="cflow-optin" name="optin_text" rows="3" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" placeholder="<?php echo esc_attr( $defaults['optin_text'] ); ?>"><?php echo esc_textarea( (string) $automation['optin_text'] ); ?></textarea>
					</div>
					<div>
						<label for="cflow-optin-btn" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Botão de resposta', 'comment-flow' ); ?></label>
						<input type="text" id="cflow-optin-btn" name="optin_button" maxlength="20" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" value="<?php echo esc_attr( (string) $automation['optin_button'] ); ?>" placeholder="<?php echo esc_attr( $defaults['optin_button'] ); ?>">
						<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Até 20 caracteres.', 'comment-flow' ); ?></p>
					</div>
				</div>

				<div class="mt-6 grid gap-6 md:grid-cols-2">
					<div>
						<label for="cflow-email-q" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Pergunta do e-mail', 'comment-flow' ); ?></label>
						<textarea id="cflow-email-q" name="email_question" rows="3" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" placeholder="<?php echo esc_attr( $defaults['email_question'] ); ?>"><?php echo esc_textarea( (string) $automation['email_question'] ); ?></textarea>
						<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Diga para que o e-mail será usado: o consentimento fica registrado no lead (LGPD).', 'comment-flow' ); ?></p>
					</div>
					<div>
						<label for="cflow-phone-q" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Pergunta do telefone', 'comment-flow' ); ?></label>
						<textarea id="cflow-phone-q" name="phone_question" rows="3" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" placeholder="<?php echo esc_attr( $defaults['phone_question'] ); ?>"><?php echo esc_textarea( (string) $automation['phone_question'] ); ?></textarea>
						<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Quem responder PULAR segue sem informar o telefone.', 'comment-flow' ); ?></p>
					</div>
				</div>
			</section>

			<!-- 4. DM -->
			<section class="<?php echo esc_attr( CFlow_Admin::CARD ); ?>">
				<?php $step( 4, __( 'Mensagem no direct', 'comment-flow' ), __( 'Sem captura, é a Private Reply enviada uma vez por comentário. Com captura, é a entrega final, depois que a pessoa informa os dados.', 'comment-flow' ) ); ?>
				<textarea name="dm_text" rows="5" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?>"><?php echo esc_textarea( $automation['dm_text'] ); ?></textarea>
				<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Use {username} para mencionar a pessoa.', 'comment-flow' ); ?></p>

				<div class="mt-6 grid gap-6 md:grid-cols-[220px_minmax(0,1fr)]">
					<div>
						<label for="cflow-btn-label" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Texto do botão', 'comment-flow' ); ?></label>
						<input type="text" id="cflow-btn-label" name="dm_button_label" maxlength="20" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" value="<?php echo esc_attr( $automation['dm_button_label'] ); ?>" placeholder="<?php esc_attr_e( 'Ex.: Baixar e-book', 'comment-flow' ); ?>">
					</div>
					<div>
						<label for="cflow-btn-url" class="<?php echo esc_attr( CFlow_Admin::LABEL ); ?>"><?php esc_html_e( 'Link do botão', 'comment-flow' ); ?></label>
						<input type="url" id="cflow-btn-url" name="dm_button_url" class="<?php echo esc_attr( CFlow_Admin::INPUT ); ?> mt-2" value="<?php echo esc_attr( $automation['dm_button_url'] ); ?>" placeholder="https://">
					</div>
				</div>
				<p class="<?php echo esc_attr( CFlow_Admin::HELP ); ?>"><?php esc_html_e( 'Opcional. Sem botão, a DM vai só com o texto (você pode colar o link no texto).', 'comment-flow' ); ?></p>
			</section>
		</div>

		<!-- Coluna lateral -->
		<aside>
			<div class="space-y-4 lg:sticky lg:top-10">
				<div class="<?php echo esc_attr( CFlow_Admin::CARD ); ?>">
					<button type="submit" class="<?php echo esc_attr( CFlow_Admin::BTN_PRIMARY ); ?> w-full"><?php esc_html_e( 'Salvar automação', 'comment-flow' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cflow' ) ); ?>" class="<?php echo esc_attr( CFlow_Admin::BTN_SECONDARY ); ?> mt-3 w-full"><?php esc_html_e( 'Cancelar', 'comment-flow' ); ?></a>
				</div>
				<div class="rounded-2xl border border-solid border-neutral-800 p-5 text-sm text-neutral-400">
					<p class="mb-3 text-xs font-semibold uppercase tracking-wider text-neutral-500"><?php esc_html_e( 'Como funciona', 'comment-flow' ); ?></p>
					<ol class="m-0 list-none space-y-3 p-0">
						<li class="flex gap-3"><span class="font-semibold text-blue-400">1</span><?php esc_html_e( 'A pessoa comenta a palavra-chave.', 'comment-flow' ); ?></li>
						<li class="flex gap-3"><span class="font-semibold text-blue-400">2</span><?php esc_html_e( 'O plugin responde no comentário.', 'comment-flow' ); ?></li>
						<li class="flex gap-3"><span class="font-semibold text-blue-400">3</span><?php esc_html_e( 'Pede e-mail/telefone na DM, se ligado.', 'comment-flow' ); ?></li>
						<li class="flex gap-3"><span class="font-semibold text-blue-400">4</span><?php esc_html_e( 'Envia a mensagem com o link.', 'comment-flow' ); ?></li>
					</ol>
				</div>
			</div>
		</aside>
	</form>
</div></div>
