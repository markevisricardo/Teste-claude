<?php
/**
 * Criar / editar post.
 *
 * @var int          $id        Post (0 = novo).
 * @var array        $post      Post.
 * @var array        $channels  Canais por ID.
 * @var int[]        $selected  Canais marcados.
 * @var string       $next_slot Próximo horário livre (UTC) ou ''.
 * @var DateTimeZone $tz
 */

defined( 'ABSPATH' ) || exit;

$pflow_active   = 'compose';
$pflow_title    = $id ? __( 'Editar post', 'post-flow' ) : __( 'Criar post', 'post-flow' );
$pflow_subtitle = __( 'Escreva uma vez e escolha onde e quando publicar.', 'post-flow' );
$pflow_actions  = '';

$pflow_types    = PFlow_Networks::types();
$pflow_next     = '' !== $next_slot ? PFlow_Schedule::to_local( $next_slot, $tz ) : null;
$pflow_existing = $post['scheduled_at'] && 'scheduled' === $post['status'] ? PFlow_Schedule::to_local( $post['scheduled_at'], $tz ) : null;
$pflow_default  = $pflow_existing ? $pflow_existing : ( $pflow_next ? $pflow_next : new DateTimeImmutable( '+1 hour', $tz ) );
$pflow_when     = $pflow_existing && ! $post['in_queue'] ? 'schedule' : 'queue';
$pflow_min_date = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
?>
<div class="mx-auto max-w-7xl pb-16">
	<?php require PFLOW_DIR . 'app/views/partials/header.php'; ?>

	<?php if ( ! $channels ) : ?>
		<section class="<?php echo esc_attr( PFlow_App::CARD ); ?> mx-auto max-w-2xl text-center">
			<h2 class="m-0 p-0 text-lg font-semibold text-white"><?php esc_html_e( 'Primeiro, conecte um canal', 'post-flow' ); ?></h2>
			<p class="mt-2 text-sm text-neutral-400"><?php esc_html_e( 'Você pode escrever rascunhos, mas para publicar precisa de ao menos um canal.', 'post-flow' ); ?></p>
			<a href="<?php echo esc_url( PFlow_App::url( 'channels' ) ); ?>" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> mt-6"><?php esc_html_e( 'Conectar canais', 'post-flow' ); ?></a>
		</section>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]" id="pflow-compose">
		<input type="hidden" name="action" value="pflow_save_post">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<?php wp_nonce_field( 'pflow_save_post' ); ?>

		<div class="space-y-6">
			<!-- Canais -->
			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
				<div class="mb-4 flex items-center justify-between">
					<h2 class="m-0 p-0 text-base font-semibold text-white"><?php esc_html_e( 'Publicar em', 'post-flow' ); ?></h2>
					<?php if ( count( $channels ) > 1 ) : ?>
						<button type="button" class="cursor-pointer border-0 bg-transparent p-0 text-xs <?php echo esc_attr( PFlow_App::LINK ); ?>" data-pflow-all><?php esc_html_e( 'Marcar todos', 'post-flow' ); ?></button>
					<?php endif; ?>
				</div>
				<div class="flex flex-wrap gap-2">
					<?php foreach ( $channels as $pflow_channel ) : ?>
						<?php
						$pflow_type = $pflow_types[ $pflow_channel['network'] ];
						$pflow_off  = 'active' !== $pflow_channel['status'];
						?>
						<label class="group relative cursor-pointer <?php echo $pflow_off ? 'opacity-50' : ''; ?>" title="<?php echo esc_attr( PFlow_Networks::label( $pflow_channel['network'] ) . ' · ' . PFlow_Channels::display_name( $pflow_channel ) ); ?>">
							<input type="checkbox" name="channels[]" value="<?php echo (int) $pflow_channel['id']; ?>" class="peer sr-only"
								data-network="<?php echo esc_attr( $pflow_channel['network'] ); ?>"
								data-label="<?php echo esc_attr( $pflow_type['label'] ); ?>"
								data-limit="<?php echo (int) $pflow_type['limit']; ?>"
								data-max-media="<?php echo (int) $pflow_type['max_media']; ?>"
								data-needs-media="<?php echo $pflow_type['needs_media'] ? '1' : '0'; ?>"
								<?php checked( in_array( $pflow_channel['id'], $selected, true ) ); ?>
								<?php disabled( $pflow_off ); ?>>
							<span class="flex items-center gap-2 rounded-full border border-solid border-neutral-800 bg-black py-1.5 pl-1.5 pr-3 text-sm text-neutral-300 transition-colors peer-checked:border-violet-500 peer-checked:bg-violet-500/10 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-violet-400">
								<?php echo PFlow_Channels::avatar( $pflow_channel, 'h-7 w-7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span class="max-w-[160px] truncate"><?php echo esc_html( PFlow_Channels::display_name( $pflow_channel ) ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
					<a href="<?php echo esc_url( PFlow_App::url( 'channels' ) ); ?>" class="flex items-center gap-2 rounded-full border border-dashed border-neutral-700 px-3 py-1.5 text-sm text-neutral-400 hover:border-violet-500 hover:text-violet-300">+ <?php esc_html_e( 'Canal', 'post-flow' ); ?></a>
				</div>
			</section>

			<!-- Conteúdo -->
			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
				<label for="pflow-content" class="<?php echo esc_attr( PFlow_App::LABEL ); ?>"><?php esc_html_e( 'Texto', 'post-flow' ); ?></label>
				<textarea id="pflow-content" name="content" rows="9" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2 resize-y" placeholder="<?php esc_attr_e( 'O que você quer compartilhar?', 'post-flow' ); ?>"><?php echo esc_textarea( $post['content'] ); ?></textarea>
				<div class="mt-3 flex flex-wrap gap-2" data-pflow-counters></div>

				<label for="pflow-link" class="<?php echo esc_attr( PFlow_App::LABEL ); ?> mt-6"><?php esc_html_e( 'Link (opcional)', 'post-flow' ); ?></label>
				<input type="url" id="pflow-link" name="link" value="<?php echo esc_attr( $post['link'] ); ?>" placeholder="https://" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-2">
				<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>"><?php esc_html_e( 'No Facebook sem imagem vira a prévia do link; nas outras redes vai no fim do texto. No Instagram, links no texto não são clicáveis.', 'post-flow' ); ?></p>

				<p class="<?php echo esc_attr( PFlow_App::LABEL ); ?> mt-6"><?php esc_html_e( 'Imagens', 'post-flow' ); ?></p>
				<div class="mt-2 flex flex-wrap gap-3" data-pflow-media>
					<?php foreach ( $post['media_ids'] as $pflow_mid ) : ?>
						<label class="group relative block h-24 w-24 cursor-pointer overflow-hidden rounded-xl border border-solid border-neutral-800">
							<input type="checkbox" name="keep_media[]" value="<?php echo (int) $pflow_mid; ?>" class="peer sr-only" checked data-pflow-keep>
							<img src="<?php echo esc_url( PFlow_Media::thumb( $pflow_mid ) ); ?>" alt="" class="h-full w-full object-cover peer-[:not(:checked)]:opacity-25">
							<span class="absolute right-1 top-1 rounded-md bg-black/70 px-1.5 py-0.5 text-[10px] font-semibold text-white"><?php esc_html_e( 'remover', 'post-flow' ); ?></span>
						</label>
					<?php endforeach; ?>
					<label class="flex h-24 w-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-neutral-700 text-xs text-neutral-400 hover:border-violet-500 hover:text-violet-300">
						<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
						<?php esc_html_e( 'Adicionar', 'post-flow' ); ?>
						<input type="file" name="media[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple class="sr-only" data-pflow-file>
					</label>
				</div>
				<p class="<?php echo esc_attr( PFlow_App::HELP ); ?>"><?php esc_html_e( 'JPG, PNG, WebP ou GIF até 8 MB (convertidos para JPG). Várias imagens viram carrossel. No Instagram, use proporção entre 4:5 e 1,91:1.', 'post-flow' ); ?></p>
			</section>
		</div>

		<!-- Quando -->
		<aside class="space-y-6">
			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?> lg:sticky lg:top-6">
				<h2 class="m-0 mb-4 p-0 text-base font-semibold text-white"><?php esc_html_e( 'Quando publicar', 'post-flow' ); ?></h2>
				<div class="space-y-2">
					<?php
					$pflow_options = array(
						'queue'    => array(
							__( 'Adicionar à fila', 'post-flow' ),
							$pflow_existing && $post['in_queue']
								/* translators: %s: data e hora */
								? sprintf( __( 'Mantém o horário: %s', 'post-flow' ), wp_date( 'D, d/m H:i', $pflow_existing->getTimestamp(), $tz ) )
								: ( $pflow_next
									/* translators: %s: data e hora */
									? sprintf( __( 'Próximo horário livre: %s', 'post-flow' ), wp_date( 'D, d/m H:i', $pflow_next->getTimestamp(), $tz ) )
									: __( 'Configure os horários primeiro.', 'post-flow' ) ),
						),
						'schedule' => array( __( 'Escolher data e hora', 'post-flow' ), __( 'Num horário específico.', 'post-flow' ) ),
						'now'      => array( __( 'Publicar agora', 'post-flow' ), __( 'Sai assim que você confirmar.', 'post-flow' ) ),
					);
					foreach ( $pflow_options as $pflow_value => $pflow_option ) :
						?>
						<label class="flex cursor-pointer items-start gap-3 rounded-xl border border-solid border-neutral-800 p-3 transition-colors hover:border-neutral-700 has-[:checked]:border-violet-500 has-[:checked]:bg-violet-500/5">
							<input type="radio" name="when" value="<?php echo esc_attr( $pflow_value ); ?>" class="mt-1 accent-violet-500" <?php checked( $pflow_when, $pflow_value ); ?> data-pflow-when>
							<span>
								<span class="block text-sm font-medium text-white"><?php echo esc_html( $pflow_option[0] ); ?></span>
								<span class="mt-0.5 block text-xs text-neutral-500"><?php echo esc_html( $pflow_option[1] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="mt-4 grid grid-cols-2 gap-3" data-pflow-datetime>
					<div>
						<label for="pflow-date" class="text-xs font-medium text-neutral-400"><?php esc_html_e( 'Data', 'post-flow' ); ?></label>
						<input type="date" id="pflow-date" name="date" min="<?php echo esc_attr( $pflow_min_date ); ?>" value="<?php echo esc_attr( $pflow_default->format( 'Y-m-d' ) ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-1 [color-scheme:dark]">
					</div>
					<div>
						<label for="pflow-time" class="text-xs font-medium text-neutral-400"><?php esc_html_e( 'Hora', 'post-flow' ); ?></label>
						<input type="time" id="pflow-time" name="time" value="<?php echo esc_attr( $pflow_default->format( 'H:i' ) ); ?>" class="<?php echo esc_attr( PFlow_App::INPUT ); ?> mt-1 [color-scheme:dark]">
					</div>
					<p class="col-span-2 text-xs text-neutral-500">
						<?php
						/* translators: %s: fuso horário */
						echo esc_html( sprintf( __( 'Fuso: %s (mude em Horários).', 'post-flow' ), $tz->getName() ) );
						?>
					</p>
				</div>

				<ul class="mt-4 hidden list-none space-y-1 rounded-xl border border-solid border-amber-500/30 bg-amber-500/5 p-3 text-xs leading-5 text-amber-100" data-pflow-warnings></ul>

				<div class="mt-6 flex flex-col gap-2">
					<button type="submit" class="<?php echo esc_attr( PFlow_App::BTN_PRIMARY ); ?> w-full" data-pflow-submit><?php esc_html_e( 'Adicionar à fila', 'post-flow' ); ?></button>
					<button type="submit" name="as_draft" value="1" formnovalidate class="<?php echo esc_attr( PFlow_App::BTN_SECONDARY ); ?> w-full"><?php esc_html_e( 'Salvar rascunho', 'post-flow' ); ?></button>
				</div>
			</section>

			<!-- Prévia -->
			<section class="<?php echo esc_attr( PFlow_App::CARD ); ?>">
				<h2 class="m-0 mb-4 p-0 text-sm font-semibold text-neutral-400"><?php esc_html_e( 'Prévia', 'post-flow' ); ?></h2>
				<div class="rounded-xl border border-solid border-neutral-800 bg-black p-4">
					<div class="flex items-center gap-2">
						<span class="h-8 w-8 rounded-full bg-gradient-to-br from-violet-500 to-indigo-700"></span>
						<span class="text-sm font-semibold text-white"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
					</div>
					<p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-neutral-200" data-pflow-preview><?php echo esc_html( $post['content'] ); ?></p>
					<div class="mt-3 grid grid-cols-2 gap-1" data-pflow-preview-media></div>
				</div>
			</section>
		</aside>
	</form>
</div>
<script>
( function () {
	var form      = document.getElementById( 'pflow-compose' );
	if ( ! form ) { return; }
	var text      = form.querySelector( '#pflow-content' );
	var link      = form.querySelector( '#pflow-link' );
	var counters  = form.querySelector( '[data-pflow-counters]' );
	var warnings  = form.querySelector( '[data-pflow-warnings]' );
	var preview   = form.querySelector( '[data-pflow-preview]' );
	var previewM  = form.querySelector( '[data-pflow-preview-media]' );
	var file      = form.querySelector( '[data-pflow-file]' );
	var submit    = form.querySelector( '[data-pflow-submit]' );
	var datetime  = form.querySelector( '[data-pflow-datetime]' );
	var labels    = <?php echo wp_json_encode( array( 'queue' => __( 'Adicionar à fila', 'post-flow' ), 'schedule' => __( 'Agendar', 'post-flow' ), 'now' => __( 'Publicar agora', 'post-flow' ) ) ); ?>;
	var i18n      = <?php echo wp_json_encode( array( 'needs' => __( '%s exige ao menos uma imagem.', 'post-flow' ), 'max' => __( '%1$s aceita até %2$d imagens.', 'post-flow' ), 'long' => __( 'O texto passa do limite do %s.', 'post-flow' ), 'none' => __( 'Escolha ao menos um canal.', 'post-flow' ) ) ); ?>;

	function len( value ) { return Array.from( value ).length; }

	function mediaCount() {
		var kept = form.querySelectorAll( '[data-pflow-keep]:checked' ).length;
		return kept + ( file && file.files ? file.files.length : 0 );
	}

	function finalText( network, media ) {
		var body = text.value.trim();
		var url  = link.value.trim();
		if ( ! url || ( network === 'facebook' && media === 0 ) || body.indexOf( url ) !== -1 ) { return body; }
		return body ? body + '\n\n' + url : url;
	}

	function update() {
		var media    = mediaCount();
		var selected = Array.prototype.filter.call( form.querySelectorAll( 'input[name="channels[]"]' ), function ( c ) { return c.checked; } );
		var seen     = {};
		var problems = [];
		counters.innerHTML = '';

		selected.forEach( function ( c ) {
			var network = c.getAttribute( 'data-network' );
			if ( seen[ network ] ) { return; }
			seen[ network ] = true;
			var label = c.getAttribute( 'data-label' );
			var limit = parseInt( c.getAttribute( 'data-limit' ), 10 );
			var used  = len( finalText( network, media ) );
			var chip  = document.createElement( 'span' );
			chip.className = 'rounded-full px-2.5 py-1 text-xs font-medium tabular-nums ring-1 ring-inset ' + ( used > limit ? 'bg-red-500/10 text-red-300 ring-red-500/30' : 'bg-white/5 text-neutral-300 ring-white/10' );
			chip.textContent = label + ' ' + used + '/' + limit;
			counters.appendChild( chip );
			if ( used > limit ) { problems.push( i18n.long.replace( '%s', label ) ); }
			if ( c.getAttribute( 'data-needs-media' ) === '1' && media === 0 ) { problems.push( i18n.needs.replace( '%s', label ) ); }
			var max = parseInt( c.getAttribute( 'data-max-media' ), 10 );
			if ( media > max ) { problems.push( i18n.max.replace( '%1$s', label ).replace( '%2$d', max ) ); }
		} );
		if ( ! selected.length ) { problems.push( i18n.none ); }

		warnings.innerHTML = '';
		problems.forEach( function ( p ) {
			var li = document.createElement( 'li' );
			li.textContent = '• ' + p;
			warnings.appendChild( li );
		} );
		warnings.classList.toggle( 'hidden', ! problems.length );

		preview.textContent = finalText( '', media ) || '…';

		var when = form.querySelector( '[data-pflow-when]:checked' );
		var mode = when ? when.value : 'queue';
		submit.textContent = labels[ mode ];
		datetime.classList.toggle( 'hidden', mode !== 'schedule' );
	}

	function previewImages() {
		previewM.innerHTML = '';
		form.querySelectorAll( '[data-pflow-keep]:checked' ).forEach( function ( input ) {
			var img = input.parentNode.querySelector( 'img' ).cloneNode();
			img.className = 'h-24 w-full rounded-lg object-cover';
			previewM.appendChild( img );
		} );
		if ( file && file.files ) {
			Array.prototype.forEach.call( file.files, function ( f ) {
				var img = document.createElement( 'img' );
				img.src = URL.createObjectURL( f );
				img.alt = '';
				img.className = 'h-24 w-full rounded-lg object-cover';
				previewM.appendChild( img );
			} );
		}
	}

	form.addEventListener( 'input', update );
	form.addEventListener( 'change', function () { update(); previewImages(); } );
	var all = form.querySelector( '[data-pflow-all]' );
	if ( all ) {
		all.addEventListener( 'click', function () {
			form.querySelectorAll( 'input[name="channels[]"]:not(:disabled)' ).forEach( function ( c ) { c.checked = true; } );
			update();
		} );
	}
	form.addEventListener( 'submit', function ( e ) {
		var when = form.querySelector( '[data-pflow-when]:checked' );
		if ( e.submitter && e.submitter.name === 'as_draft' ) { return; }
		if ( when && when.value === 'now' && ! window.confirm( <?php echo wp_json_encode( __( 'Publicar agora nos canais marcados?', 'post-flow' ) ); ?> ) ) {
			e.preventDefault();
			return;
		}
		submit.disabled = true;
		submit.textContent = <?php echo wp_json_encode( __( 'Enviando…', 'post-flow' ) ); ?>;
	} );
	update();
	previewImages();
} )();
</script>
