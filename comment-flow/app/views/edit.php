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

$cflow_active   = $id ? '' : 'edit';
$cflow_title    = $id ? __( 'Editar automação', 'comment-flow' ) : __( 'Nova automação', 'comment-flow' );
$cflow_subtitle = __( 'Gatilho, resposta pública, captura de dados e mensagem no direct.', 'comment-flow' );
$cflow_actions  = sprintf(
	'<a href="%1$s" class="%2$s">%3$s</a>',
	esc_url( CFlow_App::url() ),
	esc_attr( CFlow_App::BTN_SECONDARY ),
	esc_html__( '← Voltar', 'comment-flow' )
);

$step = static function ( $number, $title, $description = '' ) {
	?>
	<div class="mb-6 flex items-start gap-4">
		<span data-cflow-step class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white shadow-lg shadow-blue-600/30"><?php echo (int) $number; ?></span>
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
<div class="mx-auto max-w-7xl pb-16">
	<?php require CFLOW_DIR . 'app/views/partials/header.php'; ?>

	<form id="cflow-automation-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
		<input type="hidden" name="action" value="cflow_save_automation">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<?php wp_nonce_field( 'cflow_save_automation' ); ?>

		<div class="space-y-6">
			<!-- 1. Gatilho -->
			<section class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
				<?php $step( 1, __( 'Gatilho', 'comment-flow' ), __( 'Quando esta automação deve responder.', 'comment-flow' ) ); ?>

				<div class="mb-6 grid gap-3 md:grid-cols-3" role="radiogroup" aria-label="<?php esc_attr_e( 'Tipo de gatilho', 'comment-flow' ); ?>">
					<?php
					$trigger_info = array(
						'comment'    => array( __( 'Alguém comenta uma palavra-chave num post ou reel.', 'comment-flow' ), 'M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z' ),
						'dm_keyword' => array( __( 'Alguém manda uma palavra-chave no direct (ex.: "OI").', 'comment-flow' ), 'M4 4h16v12H5.2L4 17.2V4Zm4 5h8M8 12h5' ),
						'dm_welcome' => array( __( 'Alguém manda a primeira mensagem para você no direct.', 'comment-flow' ), 'M12 21s-7-4.4-9.3-9C1.1 8.6 3 5 6.5 5c2 0 3.3 1.1 4 2.4h3c.7-1.3 2-2.4 4-2.4C21 5 22.9 8.6 21.3 12 19 16.6 12 21 12 21Z' ),
					);
					foreach ( CFlow_App::triggers() as $trigger_key => $trigger_label ) :
						?>
						<label class="relative flex cursor-pointer flex-col gap-2 rounded-xl border border-solid border-neutral-800 bg-black p-4 transition-colors hover:border-neutral-600">
							<input type="radio" name="trigger_type" value="<?php echo esc_attr( $trigger_key ); ?>" class="peer sr-only" <?php checked( $trigger_key, $automation['trigger_type'] ); ?>>
							<svg class="h-5 w-5 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo esc_attr( $trigger_info[ $trigger_key ][1] ); ?>"/></svg>
							<span class="text-sm font-semibold text-white"><?php echo esc_html( $trigger_label ); ?></span>
							<span class="text-xs leading-5 text-neutral-400"><?php echo esc_html( $trigger_info[ $trigger_key ][0] ); ?></span>
							<span class="pointer-events-none absolute inset-0 rounded-xl ring-2 ring-inset ring-transparent transition peer-checked:bg-blue-500/5 peer-checked:ring-blue-500 peer-focus-visible:ring-blue-300"></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p data-cflow-show="dm_welcome" class="mb-6 rounded-xl border border-solid border-blue-500/30 bg-blue-500/5 px-4 py-3 text-xs leading-5 text-blue-100"><?php esc_html_e( 'O Instagram não avisa quando alguém segue a conta, mas novos seguidores costumam mandar mensagem. Divulgue na bio: "Me manda OI no direct" ou o link ig.me/m/seuusuario. Quem já conversava com você antes não recebe as boas-vindas.', 'comment-flow' ); ?></p>

				<div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_auto]">
					<div>
						<label for="cflow-name" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Nome', 'comment-flow' ); ?></label>
						<input type="text" id="cflow-name" name="name" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" value="<?php echo esc_attr( $automation['name'] ); ?>" placeholder="<?php esc_attr_e( 'Ex.: E-book grátis', 'comment-flow' ); ?>">
					</div>
					<div class="md:pt-8">
						<label class="inline-flex cursor-pointer items-center gap-3">
							<input type="checkbox" name="status" value="1" class="peer sr-only" <?php checked( 'active', $automation['status'] ); ?>>
							<span class="<?php echo esc_attr( CFlow_App::TOGGLE ); ?>"></span>
							<span class="text-sm font-medium text-white"><?php esc_html_e( 'Ativa', 'comment-flow' ); ?></span>
						</label>
					</div>
				</div>

				<div class="mt-6" data-cflow-show="comment">
					<span class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Post', 'comment-flow' ); ?></span>
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
					<input type="text" name="media_id_manual" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-3 max-w-md" value="<?php echo esc_attr( $is_manual ? $automation['media_id'] : '' ); ?>" placeholder="<?php esc_attr_e( 'ID da mídia (quando "ID manual")', 'comment-flow' ); ?>">
					<?php if ( is_wp_error( $media ) ) : ?>
						<p class="mt-2 text-xs text-red-400"><?php echo esc_html( sprintf( /* translators: %s: erro */ __( 'Não foi possível carregar seus posts: %s', 'comment-flow' ), $media->get_error_message() ) ); ?></p>
					<?php elseif ( ! CFlow_Accounts::is_connected() ) : ?>
						<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Conecte sua conta para escolher um post pela miniatura.', 'comment-flow' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="mt-6 grid gap-6 md:grid-cols-[minmax(0,1fr)_240px]">
					<div data-cflow-show="comment dm_keyword">
						<label for="cflow-keywords" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Palavras-chave', 'comment-flow' ); ?></label>
						<textarea id="cflow-keywords" name="keywords" rows="3" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" placeholder="quero, eu quero, link"><?php echo esc_textarea( $automation['keywords'] ); ?></textarea>
						<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><span data-cflow-show="comment"><?php esc_html_e( 'Separe por vírgula ou linha. Maiúsculas e acentos são ignorados. Vazio = qualquer comentário.', 'comment-flow' ); ?></span><span data-cflow-show="dm_keyword"><?php esc_html_e( 'Separe por vírgula ou linha. Maiúsculas e acentos são ignorados. Obrigatório para DM.', 'comment-flow' ); ?></span></p>
					</div>
					<div class="space-y-6">
						<div data-cflow-show="comment dm_keyword">
							<label for="cflow-match" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Comparação', 'comment-flow' ); ?></label>
							<select id="cflow-match" name="match_mode" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2">
								<option value="word" <?php selected( 'word', $automation['match_mode'] ); ?>><?php esc_html_e( 'Contém a palavra', 'comment-flow' ); ?></option>
								<option value="contains" <?php selected( 'contains', $automation['match_mode'] ); ?>><?php esc_html_e( 'Contém o trecho', 'comment-flow' ); ?></option>
								<option value="exact" <?php selected( 'exact', $automation['match_mode'] ); ?>><?php esc_html_e( 'Exatamente igual', 'comment-flow' ); ?></option>
							</select>
						</div>
						<div>
							<label for="cflow-priority" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Prioridade', 'comment-flow' ); ?></label>
							<input type="number" id="cflow-priority" name="priority" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" value="<?php echo (int) $automation['priority']; ?>">
							<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Menor número = avaliada primeiro.', 'comment-flow' ); ?></p>
						</div>
					</div>
				</div>
			</section>

			<!-- 2. Resposta pública -->
			<section data-cflow-show="comment" class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
				<?php $step( 2, __( 'Resposta pública no comentário', 'comment-flow' ), __( 'Uma variação por linha; o plugin sorteia uma para parecer mais natural. Vazio = não responder publicamente.', 'comment-flow' ) ); ?>
				<textarea name="public_replies" rows="4" class="<?php echo esc_attr( CFlow_App::INPUT ); ?>"><?php echo esc_textarea( $automation['public_replies'] ); ?></textarea>
				<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Use {username} para mencionar a pessoa.', 'comment-flow' ); ?></p>
			</section>

			<!-- 3. Captura -->
			<section class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
				<?php $step( 3, __( 'Antes de entregar (opcional)', 'comment-flow' ), __( 'Peça e-mail e telefone e/ou exija que a pessoa siga a conta antes de receber a mensagem final.', 'comment-flow' ) ); ?>

				<div class="flex flex-wrap gap-6">
					<label class="inline-flex cursor-pointer items-center gap-3">
						<input type="checkbox" name="capture_email" value="1" class="peer sr-only" <?php checked( 1, (int) $automation['capture_email'] ); ?>>
						<span class="<?php echo esc_attr( CFlow_App::TOGGLE ); ?>"></span>
						<span class="text-sm font-medium text-white"><?php esc_html_e( 'Pedir e-mail', 'comment-flow' ); ?></span>
					</label>
					<label class="inline-flex cursor-pointer items-center gap-3">
						<input type="checkbox" name="capture_phone" value="1" class="peer sr-only" <?php checked( 1, (int) $automation['capture_phone'] ); ?>>
						<span class="<?php echo esc_attr( CFlow_App::TOGGLE ); ?>"></span>
						<span class="text-sm font-medium text-white"><?php esc_html_e( 'Pedir telefone (WhatsApp)', 'comment-flow' ); ?></span>
					</label>
					<label class="inline-flex cursor-pointer items-center gap-3">
						<input type="checkbox" name="require_follow" value="1" class="peer sr-only" <?php checked( 1, (int) ( $automation['require_follow'] ?? 0 ) ); ?>>
						<span class="<?php echo esc_attr( CFlow_App::TOGGLE ); ?>"></span>
						<span class="text-sm font-medium text-white"><?php esc_html_e( 'Exigir que siga a conta', 'comment-flow' ); ?></span>
					</label>
				</div>
				<p data-cflow-show="comment" class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Com qualquer opção ligada, a primeira DM vira o convite abaixo; quando a pessoa responde, o plugin segue o fluxo.', 'comment-flow' ); ?></p>

				<div data-cflow-show="comment" class="mt-6 grid gap-6 md:grid-cols-[minmax(0,1fr)_220px]">
					<div>
						<label for="cflow-optin" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Convite (primeira DM)', 'comment-flow' ); ?></label>
						<textarea id="cflow-optin" name="optin_text" rows="3" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" placeholder="<?php echo esc_attr( $defaults['optin_text'] ); ?>"><?php echo esc_textarea( (string) $automation['optin_text'] ); ?></textarea>
					</div>
					<div>
						<label for="cflow-optin-btn" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Botão de resposta', 'comment-flow' ); ?></label>
						<input type="text" id="cflow-optin-btn" name="optin_button" maxlength="20" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" value="<?php echo esc_attr( (string) $automation['optin_button'] ); ?>" placeholder="<?php echo esc_attr( $defaults['optin_button'] ); ?>">
						<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Até 20 caracteres.', 'comment-flow' ); ?></p>
					</div>
				</div>

				<div class="mt-6 grid gap-6 md:grid-cols-2">
					<div>
						<label for="cflow-email-q" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Pergunta do e-mail', 'comment-flow' ); ?></label>
						<textarea id="cflow-email-q" name="email_question" rows="3" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" placeholder="<?php echo esc_attr( $defaults['email_question'] ); ?>"><?php echo esc_textarea( (string) $automation['email_question'] ); ?></textarea>
						<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Diga para que o e-mail será usado: o consentimento fica registrado no lead (LGPD).', 'comment-flow' ); ?></p>
					</div>
					<div>
						<label for="cflow-phone-q" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Pergunta do telefone', 'comment-flow' ); ?></label>
						<textarea id="cflow-phone-q" name="phone_question" rows="3" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" placeholder="<?php echo esc_attr( $defaults['phone_question'] ); ?>"><?php echo esc_textarea( (string) $automation['phone_question'] ); ?></textarea>
						<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Quem responder PULAR segue sem informar o telefone.', 'comment-flow' ); ?></p>
					</div>
				</div>

				<div class="mt-6 border-0 border-t border-solid border-neutral-900 pt-6">
					<p class="text-sm font-semibold text-white"><?php esc_html_e( 'Seguir para liberar', 'comment-flow' ); ?></p>
					<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Se a pessoa ainda não segue a conta, o plugin pede para seguir e confere de novo quando ela tocar no botão.', 'comment-flow' ); ?></p>
					<div class="mt-4 grid gap-6 md:grid-cols-[minmax(0,1fr)_220px]">
						<div>
							<label for="cflow-follow-prompt" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Pedido para seguir', 'comment-flow' ); ?></label>
							<textarea id="cflow-follow-prompt" name="follow_prompt" rows="3" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" placeholder="<?php echo esc_attr( $defaults['follow_prompt'] ); ?>"><?php echo esc_textarea( (string) ( $automation['follow_prompt'] ?? '' ) ); ?></textarea>
						</div>
						<div>
							<label for="cflow-follow-btn" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Botão', 'comment-flow' ); ?></label>
							<input type="text" id="cflow-follow-btn" name="follow_button" maxlength="20" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" value="<?php echo esc_attr( (string) ( $automation['follow_button'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $defaults['follow_button'] ); ?>">
						</div>
					</div>
					<div class="mt-6">
						<label for="cflow-follow-retry" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Se ainda não seguiu', 'comment-flow' ); ?></label>
						<textarea id="cflow-follow-retry" name="follow_retry" rows="2" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" placeholder="<?php echo esc_attr( $defaults['follow_retry'] ); ?>"><?php echo esc_textarea( (string) ( $automation['follow_retry'] ?? '' ) ); ?></textarea>
					</div>
				</div>
			</section>

			<!-- 4. DM -->
			<section class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
				<?php $step( 4, __( 'Mensagem no direct (entrega)', 'comment-flow' ), __( 'A mensagem final, com o link ou o material. Em comentários sem as opções do passo anterior, é a própria Private Reply.', 'comment-flow' ) ); ?>
				<textarea name="dm_text" rows="5" class="<?php echo esc_attr( CFlow_App::INPUT ); ?>"><?php echo esc_textarea( $automation['dm_text'] ); ?></textarea>
				<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Use {username} para mencionar a pessoa.', 'comment-flow' ); ?></p>

				<div class="mt-6 grid gap-6 md:grid-cols-[220px_minmax(0,1fr)]">
					<div>
						<label for="cflow-btn-label" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Texto do botão', 'comment-flow' ); ?></label>
						<input type="text" id="cflow-btn-label" name="dm_button_label" maxlength="20" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" value="<?php echo esc_attr( $automation['dm_button_label'] ); ?>" placeholder="<?php esc_attr_e( 'Ex.: Baixar e-book', 'comment-flow' ); ?>">
					</div>
					<div>
						<label for="cflow-btn-url" class="<?php echo esc_attr( CFlow_App::LABEL ); ?>"><?php esc_html_e( 'Link do botão', 'comment-flow' ); ?></label>
						<input type="url" id="cflow-btn-url" name="dm_button_url" class="<?php echo esc_attr( CFlow_App::INPUT ); ?> mt-2" value="<?php echo esc_attr( $automation['dm_button_url'] ); ?>" placeholder="https://">
					</div>
				</div>
				<p class="<?php echo esc_attr( CFlow_App::HELP ); ?>"><?php esc_html_e( 'Opcional. Sem botão, a DM vai só com o texto (você pode colar o link no texto).', 'comment-flow' ); ?></p>
			</section>
		</div>

		<!-- Coluna lateral -->
		<aside>
			<div class="space-y-4 lg:sticky lg:top-10">
				<div class="<?php echo esc_attr( CFlow_App::CARD ); ?>">
					<button type="submit" class="<?php echo esc_attr( CFlow_App::BTN_PRIMARY ); ?> w-full"><?php esc_html_e( 'Salvar automação', 'comment-flow' ); ?></button>
					<a href="<?php echo esc_url( CFlow_App::url() ); ?>" class="<?php echo esc_attr( CFlow_App::BTN_SECONDARY ); ?> mt-3 w-full"><?php esc_html_e( 'Cancelar', 'comment-flow' ); ?></a>
				</div>
				<div class="rounded-2xl border border-solid border-neutral-800 p-5 text-sm text-neutral-400">
					<p class="mb-3 text-xs font-semibold uppercase tracking-wider text-neutral-500"><?php esc_html_e( 'Como funciona', 'comment-flow' ); ?></p>
					<ol class="m-0 list-none space-y-3 p-0">
						<li class="flex gap-3"><span class="font-semibold text-blue-400">1</span>
							<span data-cflow-show="comment"><?php esc_html_e( 'A pessoa comenta a palavra-chave e o plugin responde no comentário.', 'comment-flow' ); ?></span>
							<span data-cflow-show="dm_keyword"><?php esc_html_e( 'A pessoa manda a palavra-chave no direct.', 'comment-flow' ); ?></span>
							<span data-cflow-show="dm_welcome"><?php esc_html_e( 'A pessoa manda a primeira mensagem no direct.', 'comment-flow' ); ?></span>
						</li>
						<li class="flex gap-3"><span class="font-semibold text-blue-400">2</span><?php esc_html_e( 'Pede e-mail/telefone, se ligado.', 'comment-flow' ); ?></li>
						<li class="flex gap-3"><span class="font-semibold text-blue-400">3</span><?php esc_html_e( 'Confere se segue a conta, se ligado.', 'comment-flow' ); ?></li>
						<li class="flex gap-3"><span class="font-semibold text-blue-400">4</span><?php esc_html_e( 'Envia a mensagem com o link.', 'comment-flow' ); ?></li>
					</ol>
				</div>
			</div>
		</aside>
	</form>
</div>
<script>
( function () {
	var form = document.getElementById( 'cflow-automation-form' );
	if ( ! form ) {
		return;
	}
	function apply() {
		var checked = form.querySelector( 'input[name="trigger_type"]:checked' );
		var trigger = checked ? checked.value : 'comment';
		form.querySelectorAll( '[data-cflow-show]' ).forEach( function ( el ) {
			el.hidden = el.getAttribute( 'data-cflow-show' ).split( ' ' ).indexOf( trigger ) === -1;
		} );
		var n = 1;
		form.querySelectorAll( '[data-cflow-step]' ).forEach( function ( el ) {
			if ( ! el.closest( '[hidden]' ) ) {
				el.textContent = n++;
			}
		} );
	}
	form.addEventListener( 'change', function ( event ) {
		if ( 'trigger_type' === event.target.name ) {
			apply();
		}
	} );
	apply();
} )();
</script>
