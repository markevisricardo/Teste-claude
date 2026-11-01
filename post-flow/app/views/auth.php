<?php
/**
 * Entrar / Criar conta (visitante).
 *
 * @var string $tab           'login' ou 'register'.
 * @var string $email         E-mail digitado antes (após um erro).
 * @var string $error_message Mensagem de erro (ou '').
 * @var bool   $can_register  Cadastros abertos.
 */

defined( 'ABSPATH' ) || exit;

$tab = $can_register ? $tab : 'login';
?>
<div class="mx-auto flex min-h-[85vh] max-w-6xl items-center">
	<div class="grid w-full items-center gap-12 lg:grid-cols-2">
		<!-- Apresentação -->
		<div class="hidden lg:block">
			<span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-600 shadow-lg shadow-violet-600/30">
				<svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
			</span>
			<h1 class="m-0 mt-8 p-0 text-4xl font-semibold leading-tight tracking-tight text-white"><?php esc_html_e( 'Planeje uma vez. Publique em todas as redes.', 'post-flow' ); ?></h1>
			<p class="mt-4 max-w-md text-base leading-7 text-neutral-400"><?php esc_html_e( 'Escreva o post, escolha os canais e deixe a fila publicar no melhor horário — pelas APIs oficiais de cada rede.', 'post-flow' ); ?></p>
			<ul class="mt-8 space-y-3 text-sm text-neutral-300">
				<?php
				$features = array(
					__( 'Facebook, Instagram, LinkedIn, X, Threads, Google, Pinterest e WhatsApp', 'post-flow' ),
					__( 'Fila com os seus horários de publicação', 'post-flow' ),
					__( 'Agendamento por data e calendário da semana', 'post-flow' ),
					__( 'Histórico com o link de cada post publicado', 'post-flow' ),
				);
				foreach ( $features as $feature ) :
					?>
					<li class="flex items-center gap-3">
						<span class="flex h-5 w-5 items-center justify-center rounded-full bg-violet-600/20 text-violet-400"><svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg></span>
						<?php echo esc_html( $feature ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<!-- Formulários -->
		<div class="mx-auto w-full max-w-md">
			<div class="mb-8 flex items-center gap-3 lg:hidden">
				<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-600"><svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>
				<span class="text-lg font-semibold text-white">Post Flow</span>
			</div>

			<?php
			$pf_uid       = 'painel';
			$return_url   = PFlow_App::url();
			$redirect_url = '';
			$card_title   = '';
			include PFLOW_DIR . 'app/views/partials/auth-card.php';
			?>
		</div>
	</div>
</div>
