<?php
/**
 * Testes da lógica do Post Flow (sem WordPress nem banco).
 * Rode com:  npm run test:postflow   (ou php tests/post-flow/run.php)
 */

require __DIR__ . '/bootstrap.php';

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

$sp  = new DateTimeZone( 'America/Sao_Paulo' ); // UTC-3, sem horário de verão.
$utc = new DateTimeZone( 'UTC' );

echo "Fila (próximo horário livre)\n";
// Segunda, 03/11/2025 10:00 em São Paulo = 13:00 UTC.
$now = ( new DateTimeImmutable( '2025-11-03 10:00:00', $sp ) )->getTimestamp();
check( 'pega o próximo horário do mesmo dia', '2025-11-03 15:30:00', PFlow_Schedule::next_slot( array( 1, 2, 3, 4, 5 ), array( '09:00', '12:30', '18:00' ), $sp, $now ) );
check( 'pula horário ocupado', '2025-11-03 21:00:00', PFlow_Schedule::next_slot( array( 1, 2, 3, 4, 5 ), array( '09:00', '12:30', '18:00' ), $sp, $now, array( '2025-11-03 15:30' ) ) );
check( 'vai para o dia seguinte', '2025-11-04 12:00:00', PFlow_Schedule::next_slot( array( 1, 2, 3, 4, 5 ), array( '09:00', '12:30', '18:00' ), $sp, $now, array( '2025-11-03 15:30', '2025-11-03 21:00' ) ) );
$friday = ( new DateTimeImmutable( '2025-11-07 19:00:00', $sp ) )->getTimestamp();
check( 'pula o fim de semana', '2025-11-10 12:00:00', PFlow_Schedule::next_slot( array( 1, 2, 3, 4, 5 ), array( '09:00', '12:30', '18:00' ), $sp, $friday ) );
check( 'sem horários devolve vazio', '', PFlow_Schedule::next_slot( array(), array( '09:00' ), $sp, $now ) );
check( 'horário a menos de 1 minuto é pulado', '2025-11-03 15:30:00', PFlow_Schedule::next_slot( array( 1 ), array( '10:00', '12:30' ), $sp, $now - 30 ) );
check( 'sanitiza dias e horários', array( 'days' => array( 0, 3 ), 'times' => array( '07:05', '18:00' ) ), PFlow_Schedule::sanitize( array( '3', 9, '0', 3 ), array( '18:00', '7:05', '25:00', 'xx', '18:00' ) ) );
check( 'converte data local para UTC', '2025-12-24 21:30:00', PFlow_Schedule::local_to_utc( '2025-12-24', '18:30', $sp ) );
check( 'rejeita data inválida', '', PFlow_Schedule::local_to_utc( '24/12/2025', '18:30', $sp ) );
check( 'converte UTC para local', '2025-12-24 18:30', PFlow_Schedule::to_local( '2025-12-24 21:30:00', $sp )->format( 'Y-m-d H:i' ) );

echo "Regras das redes\n";
check( 'X: limite de 280', array( 'X aceita até 280 caracteres.' ), PFlow_Networks::validate( 'x', str_repeat( 'a', 281 ), 0 ) );
check( 'X: 280 passa', array(), PFlow_Networks::validate( 'x', str_repeat( 'á', 280 ), 0 ) );
check( 'Instagram exige imagem', array( 'Instagram exige ao menos uma imagem.' ), PFlow_Networks::validate( 'instagram', 'oi', 0 ) );
check( 'X: até 4 imagens', array( 'X aceita até 4 imagens.' ), PFlow_Networks::validate( 'x', 'oi', 5 ) );
check( 'texto e imagem vazios', array( 'Escreva um texto ou adicione uma imagem.' ), PFlow_Networks::validate( 'linkedin', '  ', 0 ) );
check( 'emoji conta como 1 caractere', 3, PFlow_Networks::text_length( 'a🚀b' ) );
check( 'link vai para o fim do texto', "Novo post\n\nhttps://ex.com", PFlow_Networks::compose_text( 'x', 'Novo post', 'https://ex.com', 0 ) );
check( 'Facebook sem imagem usa a prévia do link', 'Novo post', PFlow_Networks::compose_text( 'facebook', 'Novo post', 'https://ex.com', 0 ) );
check( 'Facebook com imagem põe o link no texto', "Novo post\n\nhttps://ex.com", PFlow_Networks::compose_text( 'facebook', 'Novo post', 'https://ex.com', 1 ) );
check( 'link já no texto não duplica', 'Veja https://ex.com', PFlow_Networks::compose_text( 'x', 'Veja https://ex.com', 'https://ex.com', 0 ) );

echo "Status do post\n";
check( 'sem destinos = rascunho', 'draft', PFlow_Posts::status_from_targets( array() ) );
check( 'pendente = agendado', 'scheduled', PFlow_Posts::status_from_targets( array( 'sent', 'pending' ) ) );
check( 'tudo publicado', 'sent', PFlow_Posts::status_from_targets( array( 'sent', 'sent' ) ) );
check( 'misto = em parte', 'partial', PFlow_Posts::status_from_targets( array( 'sent', 'failed' ) ) );
check( 'tudo falhou', 'failed', PFlow_Posts::status_from_targets( array( 'failed' ) ) );
check( 'IDs de mídia', array( 3, 7 ), PFlow_Posts::parse_ids( '3,,7,3,abc' ) );

echo "LinkedIn (little text)\n";
check( 'escapa reservados e mantém hashtags', 'Oi \\(teste\\) \\@ana {hashtag|\\#|marketing} \\_x\\_', PFlow_Provider_LinkedIn::escape( 'Oi (teste) @ana #marketing _x_' ) );
check( 'texto comum fica igual', 'Bom dia, pessoal!', PFlow_Provider_LinkedIn::escape( 'Bom dia, pessoal!' ) );

echo "Criptografia e assinatura da Meta\n";
$enc = PFlow_Crypto::encrypt( 'token-secreto' );
check( 'token criptografado não aparece em claro', false, strpos( $enc, 'token-secreto' ) !== false );
check( 'descriptografa', 'token-secreto', PFlow_Crypto::decrypt( $enc ) );
check( 'valor adulterado vira vazio', '', PFlow_Crypto::decrypt( substr( $enc, 0, -4 ) . 'AAAA' ) );
$b64     = static function ( $v ) {
	return rtrim( strtr( base64_encode( $v ), '+/', '-_' ), '=' );
};
$payload = $b64( json_encode( array( 'user_id' => '123', 'algorithm' => 'HMAC-SHA256' ) ) );
$signed  = $b64( hash_hmac( 'sha256', $payload, 'segredo', true ) ) . '.' . $payload;
check( 'signed_request válido', '123', PFlow_OAuth::parse_signed_request( $signed, 'segredo' )['user_id'] ?? null );
check( 'signed_request com outra chave', null, PFlow_OAuth::parse_signed_request( $signed, 'outra' ) );

echo "HTTP e mensagens de erro\n";
check( 'erro da Meta', 'Invalid OAuth access token.', PFlow_Http::error_message( array( 'error' => array( 'message' => 'Invalid OAuth access token.' ) ), 400 ) );
check( 'erro do X', 'You are not allowed to create a Tweet with duplicate content.', PFlow_Http::error_message( array( 'detail' => 'You are not allowed to create a Tweet with duplicate content.' ), 403 ) );
check( 'erro sem corpo', 'A rede social respondeu com erro HTTP 502.', PFlow_Http::error_message( '', 502 ) );
respond( 503, array( 'message' => 'down' ) );
$err = PFlow_Http::request( 'GET', 'https://api.test/x' );
check( '5xx pode tentar de novo', true, PFlow_Http::is_retryable( $err ) );
respond( 400, array( 'message' => 'bad' ) );
check( '4xx não tenta de novo', false, PFlow_Http::is_retryable( PFlow_Http::request( 'GET', 'https://api.test/x' ) ) );

echo "X: PKCE e multipart\n";
$GLOBALS['pflow_test_options']['pflow_settings'] = array( 'x_client_id' => 'cid', 'x_client_secret' => PFlow_Crypto::encrypt( 'sec' ) );
$x   = new PFlow_Provider_X();
$url = $x->authorize_url( 'STATE', 'https://example.com/cb', 'verifier123' );
$exp = rtrim( strtr( base64_encode( hash( 'sha256', 'verifier123', true ) ), '+/', '-_' ), '=' );
check( 'code_challenge S256', true, false !== strpos( $url, 'code_challenge=' . $exp ) && false !== strpos( $url, 'code_challenge_method=S256' ) );
$body = PFlow_Provider_X::multipart( 'BND', array( 'media_category' => 'tweet_image' ), 'media', 'a.jpg', 'image/jpeg', 'BYTES' );
check( 'multipart', "--BND\r\nContent-Disposition: form-data; name=\"media_category\"\r\n\r\ntweet_image\r\n--BND\r\nContent-Disposition: form-data; name=\"media\"; filename=\"a.jpg\"\r\nContent-Type: image/jpeg\r\n\r\nBYTES\r\n--BND--\r\n", $body );

echo "Publicação (respostas simuladas)\n";
// X com imagem: upload → tweet.
$GLOBALS['pflow_test_requests'] = array();
$tmp                            = tempnam( sys_get_temp_dir(), 'pf' );
file_put_contents( $tmp, 'JPEGDATA' );
respond( 200, array( 'data' => array( 'id' => 'M1' ) ) );
respond( 201, array( 'data' => array( 'id' => '999' ) ) );
$res = $x->publish(
	array( 'access_token' => 'AT', 'username' => 'marca' ),
	array( 'text' => 'Olá', 'link' => '', 'media' => array( array( 'url' => 'https://ex.com/a.jpg', 'path' => $tmp, 'mime' => 'image/jpeg' ) ) )
);
check( 'X: link do post', 'https://x.com/marca/status/999', $res['remote_url'] ?? null );
check( 'X: tweet leva o media_id', array( 'text' => 'Olá', 'media' => array( 'media_ids' => array( 'M1' ) ) ), json_decode( $GLOBALS['pflow_test_requests'][1]['args']['body'], true ) );

// Facebook com 2 fotos: 2 uploads sem publicar + feed com attached_media.
$GLOBALS['pflow_test_options']['pflow_settings'] = array( 'meta_app_id' => 'a', 'meta_app_secret' => PFlow_Crypto::encrypt( 'b' ) );
$GLOBALS['pflow_test_requests']                  = array();
respond( 200, array( 'id' => 'P1' ) );
respond( 200, array( 'id' => 'P2' ) );
respond( 200, array( 'id' => 'PAGE_POST' ) );
$meta = new PFlow_Provider_Meta();
$res  = $meta->publish(
	array( 'network' => 'facebook', 'external_id' => 'PAGE', 'access_token' => 'PT' ),
	array(
		'text'  => 'Fotos',
		'link'  => '',
		'media' => array(
			array( 'url' => 'https://ex.com/1.jpg', 'path' => '', 'mime' => 'image/jpeg' ),
			array( 'url' => 'https://ex.com/2.jpg', 'path' => '', 'mime' => 'image/jpeg' ),
		),
	)
);
$feed = $GLOBALS['pflow_test_requests'][2]['args']['body'];
check( 'Facebook: fotos anexadas ao post', '{"media_fbid":"P2"}', $feed['attached_media[1]'] ?? null );
check( 'Facebook: foto enviada sem publicar', 'false', $GLOBALS['pflow_test_requests'][0]['args']['body']['published'] ?? null );
check( 'Facebook: link do post', 'https://www.facebook.com/PAGE_POST', $res['remote_url'] ?? null );

// Instagram: contêiner → status FINISHED → publish → permalink.
$GLOBALS['pflow_test_requests'] = array();
respond( 200, array( 'id' => 'C1' ) );
respond( 200, array( 'status_code' => 'FINISHED' ) );
respond( 200, array( 'id' => 'IGM' ) );
respond( 200, array( 'permalink' => 'https://www.instagram.com/p/abc/' ) );
$res = $meta->publish(
	array( 'network' => 'instagram', 'external_id' => 'IG', 'access_token' => 'PT' ),
	array( 'text' => 'Legenda', 'link' => '', 'media' => array( array( 'url' => 'https://ex.com/1.jpg', 'path' => '', 'mime' => 'image/jpeg' ) ) )
);
check( 'Instagram: link do post', 'https://www.instagram.com/p/abc/', $res['remote_url'] ?? null );
check( 'Instagram: publica o contêiner', 'C1', $GLOBALS['pflow_test_requests'][2]['args']['body']['creation_id'] ?? null );

// Instagram: imagem recusada.
respond( 200, array( 'id' => 'C2' ) );
respond( 200, array( 'status_code' => 'ERROR' ) );
$res = $meta->publish(
	array( 'network' => 'instagram', 'external_id' => 'IG', 'access_token' => 'PT' ),
	array( 'text' => 'x', 'link' => '', 'media' => array( array( 'url' => 'https://ex.com/1.jpg', 'path' => '', 'mime' => 'image/jpeg' ) ) )
);
check( 'Instagram: erro de mídia vira falha', true, is_wp_error( $res ) && ! PFlow_Http::is_retryable( $res ) );

// LinkedIn: o URN vem no cabeçalho x-restli-id.
respond( 201, '', array( 'x-restli-id' => 'urn:li:share:42' ) );
$res = ( new PFlow_Provider_LinkedIn() )->publish( array( 'access_token' => 'T', 'external_id' => 'abc' ), array( 'text' => 'Oi', 'link' => '', 'media' => array() ) );
check( 'LinkedIn: link do post', 'https://www.linkedin.com/feed/update/urn:li:share:42/', $res['remote_url'] ?? null );

// Threads texto: contêiner → publish → permalink.
respond( 200, array( 'id' => 'TC' ) );
respond( 200, array( 'id' => 'TP' ) );
respond( 200, array( 'permalink' => 'https://www.threads.net/@marca/post/xyz' ) );
$res = ( new PFlow_Provider_Threads() )->publish( array( 'access_token' => 'T', 'external_id' => 'U' ), array( 'text' => 'Oi', 'link' => '', 'media' => array() ) );
check( 'Threads: link do post', 'https://www.threads.net/@marca/post/xyz', $res['remote_url'] ?? null );

// X: renovação do token quando está para vencer.
$GLOBALS['pflow_test_options']['pflow_settings'] = array( 'x_client_id' => 'cid', 'x_client_secret' => PFlow_Crypto::encrypt( 'sec' ) );
respond( 200, array( 'access_token' => 'NEW', 'refresh_token' => 'R2', 'expires_in' => 7200 ) );
$tokens = $x->refresh( array( 'refresh_token' => 'R1', 'token_expires_at' => time() + 60 ) );
check( 'X: renova token perto de vencer', array( 'NEW', 'R2' ), array( $tokens['access_token'] ?? '', $tokens['refresh_token'] ?? '' ) );
check( 'X: não renova token novo', null, $x->refresh( array( 'refresh_token' => 'R1', 'token_expires_at' => time() + 3600 ) ) );

echo "\n{$count} verificações, {$failures} falha(s).\n";
exit( $failures ? 1 : 0 );
