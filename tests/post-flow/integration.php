<?php
/**
 * Teste de integração: banco (SQLite em memória), inquilinos, fila,
 * publicador e falhas, com as APIs das redes simuladas.
 * Rode com:  php tests/post-flow/integration.php
 */

require __DIR__ . '/wp-shim.php';

$failures = 0;
$count    = 0;
function check( $label, $expected, $actual ) {
	global $failures, $count;
	++$count;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	++$failures;
	echo "  FAIL {$label}\n       esperado: " . var_export( $expected, true ) . "\n       obtido:   " . var_export( $actual, true ) . "\n";
}
function respond( $code, $body, $headers = array() ) {
	$GLOBALS['pflow_test_http'][] = array( 'code' => $code, 'body' => $body, 'headers' => $headers );
}
function account( $network, $id, $extra = array() ) {
	return $extra + array(
		'network'          => $network,
		'external_id'      => $id,
		'provider_user_id' => 'u-' . $id,
		'name'             => 'Conta ' . $id,
		'username'         => 'conta' . $id,
		'avatar_url'       => '',
		'access_token'     => 'token-' . $id,
		'refresh_token'    => '',
		'token_expires_at' => 0,
		'meta'             => array(),
	);
}
function target_of( $post_id, $channel_id ) {
	foreach ( PFlow_Posts::get( $post_id )['targets'] as $t ) {
		if ( (int) $t['channel_id'] === (int) $channel_id ) {
			return $t;
		}
	}
	return null;
}

PFlow_Settings::update(
	array(
		'meta_app_id'            => 'a',
		'meta_app_secret'        => 'b',
		'linkedin_client_id'     => 'c',
		'linkedin_client_secret' => 'd',
		'x_client_id'            => 'e',
		'x_client_secret'        => 'f',
	)
);
$past = gmdate( 'Y-m-d H:i:s', time() - 60 );

echo "Canais e inquilinos\n";
PFlow_Tenant::set( 1 );
$fb = PFlow_Channels::upsert( account( 'facebook', 'PAGE1' ) );
$x  = PFlow_Channels::upsert( account( 'x', 'X1', array( 'token_expires_at' => time() + 7200, 'refresh_token' => 'R' ) ) );
$li = PFlow_Channels::upsert( account( 'linkedin', 'LI1', array( 'token_expires_at' => time() - 10 ) ) );
check( 'reconectar não duplica', $fb, PFlow_Channels::upsert( account( 'facebook', 'PAGE1', array( 'access_token' => 'novo' ) ) ) );
check( 'token novo salvo e criptografado', 'novo', PFlow_Channels::get( $fb )['access_token'] );
check( 'banco não guarda o token em claro', 0, (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM wp_pflow_channels WHERE access_token LIKE '%novo%'" ) );
check( '3 canais do usuário 1', 3, PFlow_Channels::count() );

PFlow_Tenant::set( 2 );
$other = PFlow_Channels::upsert( account( 'facebook', 'PAGE1' ) );
check( 'mesma página em outro usuário vira outro canal', true, $other !== $fb );
check( 'usuário 2 não vê canais do 1', null, PFlow_Channels::get( $fb ) );
check( 'usuário 2 tem 1 canal', 1, PFlow_Channels::count() );

PFlow_Settings::update( array( 'max_channels' => 3 ) );
PFlow_Tenant::set( 1 );
check( 'limite de canais do plano', 0, PFlow_Channels::remaining_slots() );
PFlow_Settings::update( array( 'max_channels' => 0 ) );
check( 'sem limite', null, PFlow_Channels::remaining_slots() );

echo "Rascunho e isolamento de posts\n";
$draft = PFlow_Posts::save( array( 'content' => 'Ideia', 'link' => '', 'media_ids' => array(), 'in_queue' => 0 ), array( $fb, $x ), '' );
$post  = PFlow_Posts::get( $draft );
check( 'rascunho', 'draft', $post['status'] );
check( 'rascunho lembra os canais', array( $fb, $x ), PFlow_Posts::channel_ids( $post ) );
PFlow_Tenant::set( 2 );
check( 'usuário 2 não lê post do 1', null, PFlow_Posts::get( $draft ) );
check( 'usuário 2 não exclui post do 1', false, PFlow_Posts::delete( $draft ) );
PFlow_Tenant::set( 1 );

echo "Publicação agendada\n";
$id = PFlow_Posts::save( array( 'content' => 'Lançamento hoje!', 'link' => 'https://ex.com', 'media_ids' => array(), 'in_queue' => 0 ), array( $fb, $x ), $past );
check( 'agendado', 'scheduled', PFlow_Posts::get( $id )['status'] );
respond( 200, array( 'id' => 'PAGE1_777' ) );     // Facebook /feed.
respond( 201, array( 'data' => array( 'id' => '555' ) ) ); // X /tweets.
PFlow_Tenant::set( 0 ); // O cron roda sem usuário.
PFlow_Publisher::run();
PFlow_Tenant::set( 1 );
check( 'publicado nos dois', 'sent', PFlow_Posts::get( $id )['status'] );
check( 'link do Facebook', 'https://www.facebook.com/PAGE1_777', target_of( $id, $fb )['remote_url'] );
check( 'link do X', 'https://x.com/contaX1/status/555', target_of( $id, $x )['remote_url'] );
check( 'Facebook recebeu o link como prévia', 'https://ex.com', $GLOBALS['pflow_test_requests'][0]['args']['body']['link'] ?? null );
check( 'X recebeu o link no texto', "Lançamento hoje!\n\nhttps://ex.com", json_decode( $GLOBALS['pflow_test_requests'][1]['args']['body'], true )['text'] ?? null );
check( 'nada é publicado duas vezes', 0, count( PFlow_Posts::due_targets() ) );
check( 'post publicado não é editável', false, PFlow_Posts::is_editable( PFlow_Posts::get( $id ) ) );

echo "Falhas, nova tentativa e token vencido\n";
$GLOBALS['pflow_test_requests'] = array();
$id2                            = PFlow_Posts::save( array( 'content' => 'Segundo post', 'link' => '', 'media_ids' => array(), 'in_queue' => 0 ), array( $fb, $x, $li ), $past );
respond( 503, array( 'error' => array( 'message' => 'Service unavailable' ) ) ); // Facebook: temporário.
respond( 403, array( 'detail' => 'duplicate content' ) );                         // X: definitivo.
PFlow_Publisher::run();
$t_fb = target_of( $id2, $fb );
check( 'erro temporário volta para a fila', 'pending', $t_fb['status'] );
check( 'nova tentativa em ~5 minutos', true, strtotime( $t_fb['due_at'] . ' UTC' ) > time() + 4 * 60 );
check( 'erro definitivo falha', 'failed', target_of( $id2, $x )['status'] );
check( 'mensagem da rede guardada', 'duplicate content', target_of( $id2, $x )['error'] );
check( 'token vencido do LinkedIn falha sem chamar a API', 'failed', target_of( $id2, $li )['status'] );
check( 'só 2 chamadas HTTP (LinkedIn não foi chamado)', 2, count( $GLOBALS['pflow_test_requests'] ) );
check( 'canal do LinkedIn pede reconexão', 'expired', PFlow_Channels::get( $li )['status'] );
check( 'post continua agendado enquanto há tentativa', 'scheduled', PFlow_Posts::get( $id2 )['status'] );

// Força a nova tentativa do Facebook agora.
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'UPDATE wp_pflow_targets SET due_at = %s WHERE id = %d', $past, $t_fb['id'] ) );
respond( 200, array( 'id' => 'PAGE1_888' ) );
PFlow_Publisher::run();
check( 'segunda tentativa publica', 'sent', target_of( $id2, $fb )['status'] );
check( 'post fica "em parte"', 'partial', PFlow_Posts::get( $id2 )['status'] );

// "Tentar de novo" o X.
respond( 201, array( 'data' => array( 'id' => '556' ) ) );
PFlow_Posts::retry( $id2 );
check( 'retry reabre só os que falharam', 'pending', target_of( $id2, $x )['status'] );
check( 'retry mantém o que já saiu', 'sent', target_of( $id2, $fb )['status'] );
PFlow_Publisher::run_post( $id2 );
check( 'X publicado no retry', 'sent', target_of( $id2, $x )['status'] );

echo "Fila e remoção de canal\n";
PFlow_Schedule::save( 1, array( 0, 1, 2, 3, 4, 5, 6 ), array( '08:00', '20:00' ) );
$slot1 = PFlow_Schedule::next_for_current();
$q     = PFlow_Posts::save( array( 'content' => 'Na fila', 'link' => '', 'media_ids' => array(), 'in_queue' => 1 ), array( $x ), $slot1 );
$slot2 = PFlow_Schedule::next_for_current();
check( 'fila pula horário ocupado', true, '' !== $slot1 && $slot2 > $slot1 );
check( 'horário ocupado aparece', array( substr( $slot1, 0, 16 ) ), PFlow_Posts::taken_slots() );
check( 'ainda não vence', 0, count( PFlow_Posts::due_targets() ) );
PFlow_Channels::delete( $x );
check( 'canal removido some do post pendente', 'draft', PFlow_Posts::get( $q )['status'] );
check( 'histórico mantém o destino publicado', 'sent', target_of( $id, $x )['status'] );

echo "Destino preso em 'publicando'\n";
$id3 = PFlow_Posts::save( array( 'content' => 'Preso', 'link' => '', 'media_ids' => array(), 'in_queue' => 0 ), array( $fb ), $past );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( "UPDATE wp_pflow_targets SET status = 'publishing', locked_at = %s WHERE post_id = %d", gmdate( 'Y-m-d H:i:s', time() - 3600 ), $id3 ) );
PFlow_Posts::release_stuck();
check( 'vira falha (não arrisca publicar duas vezes)', 'failed', PFlow_Posts::get( $id3 )['status'] );

echo "Google, Pinterest e WhatsApp pelo publicador\n";
PFlow_Settings::update( array( 'google_client_id' => 'g', 'google_client_secret' => 's', 'pinterest_app_id' => 'p', 'pinterest_app_secret' => 's' ) );
$gb  = PFlow_Channels::upsert( account( 'google_business', 'accounts/1/locations/2', array( 'refresh_token' => 'GR', 'token_expires_at' => time() - 10 ) ) );
$pin = PFlow_Channels::upsert( account( 'pinterest', 'BOARD', array( 'token_expires_at' => time() + 20 * DAY_IN_SECONDS ) ) );
$wa  = PFlow_Channels::upsert( account( 'whatsapp', '1234', array( 'meta' => array( 'template' => 'novidades', 'language' => 'pt_BR', 'header_image' => false, 'recipients' => array( '5511999998888' ) ) ) ) );
check( 'meta do WhatsApp volta do banco', array( '5511999998888' ), PFlow_Channels::get( $wa )['meta']['recipients'] );
$GLOBALS['pflow_test_media'][7] = 'https://ex.com/7.jpg';
$GLOBALS['pflow_test_requests'] = array();
$id4 = PFlow_Posts::save( array( 'content' => 'Oferta da semana', 'link' => 'https://ex.com/oferta', 'media_ids' => array( 7 ), 'in_queue' => 0 ), array( $gb, $pin, $wa ), $past );
respond( 200, array( 'access_token' => 'GA2', 'expires_in' => 3600 ) );                               // Google: renova o token vencido.
respond( 200, array( 'name' => 'lp/1', 'searchUrl' => 'https://local.google.com/p/1' ) );           // Google: localPosts.
respond( 201, array( 'id' => '42' ) );                                                              // Pinterest: pin.
respond( 200, array( 'messages' => array( array( 'id' => 'wamid' ) ) ) );                          // WhatsApp: mensagem.
PFlow_Publisher::run();
check( 'publicado nos três', 'sent', PFlow_Posts::get( $id4 )['status'] );
check( 'Google usou o token renovado', 'Bearer GA2', $GLOBALS['pflow_test_requests'][1]['args']['headers']['Authorization'] ?? null );
check( 'token novo do Google salvo', 'GA2', PFlow_Channels::get( $gb )['access_token'] );
check( 'Google sem o link no texto', 'Oferta da semana', json_decode( $GLOBALS['pflow_test_requests'][1]['args']['body'], true )['summary'] ?? null );
check( 'WhatsApp registra os contatos', '1 de 1 contatos', target_of( $id4, $wa )['remote_id'] );
check( 'link do pin', 'https://www.pinterest.com/pin/42/', target_of( $id4, $pin )['remote_url'] );

echo "Exclusão da conta\n";
PFlow_Posts::delete_user_data( 1 );
check( 'canais apagados', 0, PFlow_Channels::count() );
check( 'imagens apagadas', array( 7 ), $GLOBALS['pflow_test_deleted'] ?? array() );
check( 'posts apagados', 0, (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_pflow_posts WHERE user_id = 1' ) );
PFlow_Tenant::set( 2 );
check( 'outro usuário intacto', 1, PFlow_Channels::count() );

echo "\n{$count} verificações, {$failures} falha(s).\n";
exit( $failures ? 1 : 0 );
