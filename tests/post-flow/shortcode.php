<?php
/**
 * Testes do shortcode [post_flow_login] e dos redirecionamentos do login.
 * Rode com:  php tests/post-flow/shortcode.php
 */

require __DIR__ . '/wp-shim.php';

// Stubs extras usados pelo shortcode.
function add_shortcode() {}
function shortcode_atts( $defaults, $atts ) {
	return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) );
}
function get_queried_object_id() {
	return 42;
}
function get_queried_object() {
	return null;
}
function get_permalink() {
	return 'https://app.exemplo.com.br/comece/';
}
function remove_query_arg( $keys, $url ) {
	$parts = explode( '?', $url, 2 );
	if ( 2 > count( $parts ) ) {
		return $url;
	}
	parse_str( $parts[1], $q );
	$q = array_diff_key( $q, array_flip( (array) $keys ) );
	return $parts[0] . ( $q ? '?' . http_build_query( $q ) : '' );
}
function wp_validate_redirect( $url, $default = '' ) {
	return 'app.exemplo.com.br' === parse_url( $url, PHP_URL_HOST ) ? $url : $default;
}
function wp_enqueue_style() {}
function sanitize_email( $e ) {
	return filter_var( $e, FILTER_SANITIZE_EMAIL );
}

require_once PFLOW_DIR . 'app/class-pflow-frontend.php';
require_once PFLOW_DIR . 'app/class-pflow-login-shortcode.php';

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
function has( $html, $needle ) {
	return false !== strpos( $html, $needle );
}

update_option( 'pflow_app_url', 'https://app.exemplo.com.br/' );
PFlow_Settings::update( array( 'allow_registration' => 1 ) );

echo "Visitante\n";
$GLOBALS['pflow_test_logged_in'] = false;
$_GET = array();
$html = PFlow_Login_Shortcode::render( array() );
check( 'mostra o formulário de entrar', true, has( $html, 'value="pflow_login"' ) );
check( 'mostra o de criar conta', true, has( $html, 'value="pflow_register"' ) );
check( 'volta para a própria página em caso de erro', true, has( $html, 'name="pf_return" value="https://app.exemplo.com.br/comece/"' ) );
check( 'abre na aba Entrar', true, has( $html, 'show( "login" )' ) );
check( 'âncora para rolar até o formulário', true, has( $html, 'id="pflow-entrar"' ) );
check( 'sem redirecionamento próprio, não manda o campo', false, has( $html, 'name="pf_redirect"' ) );

$html2 = PFlow_Login_Shortcode::render( array( 'aba' => 'cadastro', 'titulo' => 'Comece grátis' ) );
check( 'segundo formulário na página tem IDs próprios', true, has( $html2, 'id="pflow-login-email-sc2"' ) && has( $html, 'id="pflow-login-email-sc1"' ) );
check( 'âncora só no primeiro', false, has( $html2, 'id="pflow-entrar"' ) );
check( 'aba="cadastro" abre em Criar conta', true, has( $html2, 'show( "register" )' ) );
check( 'mostra o título', true, has( $html2, 'Comece grátis' ) );

$html = PFlow_Login_Shortcode::render( array( 'redirecionar' => 'https://app.exemplo.com.br/boas-vindas' ) );
check( 'redirecionamento do próprio site é aceito', true, has( $html, 'name="pf_redirect" value="https://app.exemplo.com.br/boas-vindas"' ) );
$html = PFlow_Login_Shortcode::render( array( 'redirecionar' => 'https://site-malicioso.com/x' ) );
check( 'redirecionamento para outro site é ignorado', false, has( $html, 'site-malicioso' ) );

$_GET = array( 'pf_error' => 'login', 'pf_email' => 'ana@exemplo.com.br' );
$html = PFlow_Login_Shortcode::render( array() );
check( 'mostra o erro de senha', true, has( $html, 'E-mail ou senha incorretos.' ) );
check( 'mantém o e-mail digitado', true, has( $html, 'value="ana@exemplo.com.br"' ) );
$_GET = array( 'pf_error' => 'email_exists', 'pf_tab' => 'register' );
$html = PFlow_Login_Shortcode::render( array() );
check( 'erro do cadastro volta na aba Criar conta', true, has( $html, 'show( "register" )' ) && has( $html, 'Já existe uma conta com este e-mail' ) );
$_GET = array();

PFlow_Settings::update( array( 'allow_registration' => 0 ) );
$html = PFlow_Login_Shortcode::render( array( 'aba' => 'cadastro' ) );
check( 'cadastros fechados: só Entrar', array( true, false ), array( has( $html, 'value="pflow_login"' ), has( $html, 'value="pflow_register"' ) ) );
PFlow_Settings::update( array( 'allow_registration' => 1 ) );

echo "Logado\n";
$GLOBALS['pflow_test_logged_in'] = true;
$html = PFlow_Login_Shortcode::render( array( 'botao' => 'Abrir meu painel' ) );
check( 'cumprimenta pelo nome', true, has( $html, 'Olá, Ana Souza!' ) );
check( 'botão leva ao painel', true, has( $html, 'href="https://app.exemplo.com.br/"' ) && has( $html, 'Abrir meu painel' ) );
check( 'não mostra o formulário', false, has( $html, 'value="pflow_login"' ) );

echo "Redirecionamentos do login\n";
$back  = new ReflectionMethod( 'PFlow_Auth', 'back_url' );
$after = new ReflectionMethod( 'PFlow_Auth', 'after_url' );
$back->setAccessible( true );
$after->setAccessible( true );
$_POST = array( 'pf_return' => 'https://app.exemplo.com.br/comece/?pf_error=login&utm=x' );
check( 'erro volta para a página do formulário', 'https://app.exemplo.com.br/comece/?utm=x&pf_error=email#pflow-entrar', $back->invoke( null, array( 'pf_error' => 'email' ) ) );
$_POST = array( 'pf_return' => 'https://site-malicioso.com/' );
check( 'página de outro site é trocada pelo painel', 'https://app.exemplo.com.br/?pf_error=login#pflow-entrar', $back->invoke( null, array( 'pf_error' => 'login' ) ) );
$_POST = array( 'pf_redirect' => 'https://app.exemplo.com.br/boas-vindas' );
check( 'depois do login vai para o endereço pedido', 'https://app.exemplo.com.br/boas-vindas', $after->invoke( null, 'https://app.exemplo.com.br/' ) );
$_POST = array( 'pf_redirect' => 'https://site-malicioso.com/' );
check( 'endereço de outro site é ignorado', 'https://app.exemplo.com.br/', $after->invoke( null, 'https://app.exemplo.com.br/' ) );

echo "\n{$count} verificações, {$failures} falha(s).\n";
exit( $failures ? 1 : 0 );
