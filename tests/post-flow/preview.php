<?php
/**
 * Gera as telas do Post Flow em HTML estático (dados de exemplo), para
 * conferir o visual e tirar prints sem um WordPress.
 * Uso: php tests/post-flow/preview.php <pasta-de-saída>
 */

require __DIR__ . '/wp-shim.php';

$out = rtrim( $argv[1] ?? sys_get_temp_dir() . '/pflow-preview', '/' );
@mkdir( $out, 0777, true );
copy( PFLOW_DIR . 'assets/app.css', $out . '/app.css' );

// Imagens de exemplo (gradientes) geradas com GD.
$colors = array( array( 124, 58, 237, 236, 72, 153 ), array( 14, 165, 233, 16, 185, 129 ), array( 249, 115, 22, 234, 179, 8 ) );
foreach ( $colors as $i => $c ) {
	$img = imagecreatetruecolor( 600, 600 );
	for ( $y = 0; $y < 600; $y++ ) {
		$t   = $y / 599;
		$col = imagecolorallocate( $img, (int) ( $c[0] + ( $c[3] - $c[0] ) * $t ), (int) ( $c[1] + ( $c[4] - $c[1] ) * $t ), (int) ( $c[2] + ( $c[5] - $c[2] ) * $t ) );
		imageline( $img, 0, $y, 599, $y, $col );
	}
	$white = imagecolorallocatealpha( $img, 255, 255, 255, 70 );
	imagefilledellipse( $img, 420, 180, 260, 260, $white );
	imagejpeg( $img, $out . '/img' . ( $i + 1 ) . '.jpg', 85 );
	$GLOBALS['pflow_test_media'][ $i + 1 ] = 'img' . ( $i + 1 ) . '.jpg';
}

PFlow_Settings::update(
	array(
		'meta_app_id'        => '1234567890',
		'meta_app_secret'    => 'x',
		'linkedin_client_id' => '78abc',
		'linkedin_client_secret' => 'x',
		'x_client_id'        => 'Zm9v',
		'x_client_secret'    => 'x',
		'max_channels'       => 10,
	)
);

// Dados de exemplo.
PFlow_Tenant::set( 1 );
$ch = array(
	'fb' => PFlow_Channels::upsert( array( 'network' => 'facebook', 'external_id' => '1', 'provider_user_id' => 'f', 'name' => 'Clínica Sorriso', 'username' => '', 'avatar_url' => '', 'access_token' => 't', 'refresh_token' => '', 'token_expires_at' => 0, 'meta' => array() ) ),
	'ig' => PFlow_Channels::upsert( array( 'network' => 'instagram', 'external_id' => '2', 'provider_user_id' => 'f', 'name' => 'Clínica Sorriso', 'username' => 'clinicasorriso', 'avatar_url' => '', 'access_token' => 't', 'refresh_token' => '', 'token_expires_at' => 0, 'meta' => array() ) ),
	'li' => PFlow_Channels::upsert( array( 'network' => 'linkedin', 'external_id' => '3', 'provider_user_id' => 'l', 'name' => 'Ana Souza', 'username' => '', 'avatar_url' => '', 'access_token' => 't', 'refresh_token' => '', 'token_expires_at' => time() + 48 * DAY_IN_SECONDS, 'meta' => array() ) ),
	'x'  => PFlow_Channels::upsert( array( 'network' => 'x', 'external_id' => '4', 'provider_user_id' => 'x', 'name' => 'Ana Souza', 'username' => 'anasouza', 'avatar_url' => '', 'access_token' => 't', 'refresh_token' => 'r', 'token_expires_at' => time() + 7200, 'meta' => array() ) ),
	'th' => PFlow_Channels::upsert( array( 'network' => 'threads', 'external_id' => '5', 'provider_user_id' => 't', 'name' => 'Clínica Sorriso', 'username' => 'clinicasorriso', 'avatar_url' => '', 'access_token' => 't', 'refresh_token' => '', 'token_expires_at' => time() - 10, 'meta' => array() ) ),
);
PFlow_Channels::mark( $ch['th'], 'expired', 'A conexão venceu.' );
PFlow_Schedule::save( 1, array( 1, 2, 3, 4, 5 ), array( '09:00', '12:30', '18:00' ) );

$tz   = PFlow_Schedule::timezone( 1 );
$at   = static function ( $days, $time ) use ( $tz ) {
	$d = ( new DateTimeImmutable( 'today', $tz ) )->modify( "+{$days} days" );
	return PFlow_Schedule::local_to_utc( $d->format( 'Y-m-d' ), $time, $tz );
};
$save = static function ( $content, $link, $media, $channels, $due, $queue = 1 ) {
	return PFlow_Posts::save( array( 'content' => $content, 'link' => $link, 'media_ids' => $media, 'in_queue' => $queue ), $channels, $due );
};
$save( "Novembro Azul também é cuidado com a saúde bucal 💙\n\nAgende a sua avaliação gratuita esta semana.", 'https://clinicasorriso.com.br/agenda', array( 1 ), array( $ch['fb'], $ch['ig'], $ch['li'] ), $at( 1, '09:00' ) );
$save( '5 hábitos simples que deixam o sorriso mais branco sem clareamento. Thread 🧵', '', array(), array( $ch['x'], $ch['li'] ), $at( 1, '12:30' ) );
$save( 'Bastidores da nossa nova sala de atendimento infantil. Ficou linda!', '', array( 2, 3 ), array( $ch['fb'], $ch['ig'] ), $at( 2, '18:00' ) );
$save( 'Webinar gratuito: como montar a rotina de higiene bucal da família. Inscrições abertas!', 'https://clinicasorriso.com.br/webinar', array(), array( $ch['fb'], $ch['li'], $ch['x'] ), $at( 3, '10:15' ), 0 );
$save( 'Rascunho: depoimento da Dona Maria sobre a prótese nova', '', array( 3 ), array( $ch['ig'] ), '' );
$save( 'Ideia de post: perguntas frequentes sobre aparelho invisível', '', array(), array( $ch['fb'], $ch['ig'], $ch['li'] ), '' );

// Publicados (marcados direto no banco).
global $wpdb;
$sent = static function ( $content, $media, $map, $ago ) use ( $save, $wpdb ) {
	$when = gmdate( 'Y-m-d H:i:s', time() - $ago );
	$id   = $save( $content, '', $media, array_keys( $map ), $when, 0 );
	foreach ( $map as $channel => $result ) {
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE wp_pflow_targets SET status = %s, remote_url = %s, error = %s, published_at = %s WHERE post_id = %d AND channel_id = %d',
				$result[0],
				$result[1],
				$result[2],
				$when,
				$id,
				$channel
			)
		);
	}
	PFlow_Posts::refresh_status( $id );
};
$sent( 'Hoje é dia de campanha de prevenção! Passe na clínica até as 18h. 🦷', array( 1 ), array( $ch['fb'] => array( 'sent', 'https://facebook.com/x', '' ), $ch['ig'] => array( 'sent', 'https://instagram.com/p/x', '' ) ), 3 * HOUR_IN_SECONDS );
$sent( 'Você sabia? Trocar a escova a cada 3 meses faz diferença.', array(), array( $ch['x'] => array( 'failed', '', 'You are not allowed to create a Tweet with duplicate content.' ), $ch['li'] => array( 'sent', 'https://linkedin.com/feed/update/x', '' ) ), DAY_IN_SECONDS + 3600 );

// Telas do painel.
$GLOBALS['pflow_test_logged_in'] = true;
$pages                           = array(
	'queue'    => array(),
	'compose'  => array(),
	'calendar' => array(),
	'sent'     => array(),
	'channels' => array(),
	'schedule' => array(),
	'account'  => array(),
);
$notices = array(
	'queue'    => array( 'success', 'Post agendado para amanhã às 09:00.' ),
	'channels' => array( 'success', 'Canal conectado! Agora é só criar o seu primeiro post.' ),
);
function pflow_page( $body ) {
	return "<!DOCTYPE html><html lang=\"pt-BR\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><link rel=\"stylesheet\" href=\"app.css\"></head><body class=\"pflow-screen\">{$body}</body></html>";
}
foreach ( $pages as $view => $args ) {
	$_GET = array( 'pf' => $view ) + $args;
	if ( isset( $notices[ $view ] ) ) {
		PFlow_App::notice( 1, $notices[ $view ][0], $notices[ $view ][1] );
	}
	file_put_contents( $out . '/' . $view . '.html', pflow_page( PFlow_App::render() ) );
}

// Escolha de contas após o OAuth da Meta.
set_transient(
	'pflow_pick_1',
	PFlow_Crypto::encrypt(
		wp_json_encode(
			array(
				array( 'network' => 'facebook', 'external_id' => '10', 'name' => 'Clínica Sorriso Centro', 'username' => '', 'avatar_url' => '' ),
				array( 'network' => 'instagram', 'external_id' => '11', 'name' => 'Sorriso Centro', 'username' => 'sorrisocentro', 'avatar_url' => '' ),
				array( 'network' => 'facebook', 'external_id' => '12', 'name' => 'Clínica Sorriso Zona Sul', 'username' => '', 'avatar_url' => '' ),
			)
		)
	)
);
$_GET = array( 'pf' => 'channels', 'pick' => '1' );
file_put_contents( $out . '/channels-pick.html', pflow_page( PFlow_App::render() ) );

// Tela de entrar / criar conta.
$GLOBALS['pflow_test_logged_in'] = false;
$_GET                            = array( 'pf_tab' => 'register' );
file_put_contents( $out . '/auth.html', pflow_page( PFlow_App::render() ) );

echo "Telas geradas em {$out}\n";
