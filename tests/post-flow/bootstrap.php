<?php
/**
 * Stubs mínimos do WordPress para testar a lógica do Post Flow sem banco.
 * Não vão para o site (a pasta tests/ não faz parte do plugin).
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'PFLOW_GRAPH_VERSION', 'v23.0' );
define( 'PFLOW_LINKEDIN_VERSION', '202606' );
define( 'PFLOW_DIR', dirname( __DIR__, 2 ) . '/post-flow/' );

$GLOBALS['pflow_test_options'] = array();
$GLOBALS['pflow_test_http']    = array();

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function __( $text ) {
	return $text;
}
function _n( $single, $plural, $n ) {
	return 1 === (int) $n ? $single : $plural;
}
function number_format_i18n( $n ) {
	return number_format( (float) $n, 0, ',', '.' );
}
function absint( $v ) {
	return abs( (int) $v );
}
function wp_salt() {
	return 'test-salt';
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function get_option( $key, $default = false ) {
	return $GLOBALS['pflow_test_options'][ $key ] ?? $default;
}
function update_option( $key, $value ) {
	$GLOBALS['pflow_test_options'][ $key ] = $value;
	return true;
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}
function wp_timezone() {
	return new DateTimeZone( 'America/Sao_Paulo' );
}
function add_query_arg( $args, $url ) {
	$parts = array();
	foreach ( $args as $k => $v ) {
		$parts[] = $k . '=' . $v;
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $parts );
}
function rest_url( $path ) {
	return 'https://example.com/wp-json/' . ltrim( $path, '/' );
}
function wp_generate_password( $length ) {
	return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', 4 ), 0, $length );
}

/**
 * HTTP falso: devolve as respostas enfileiradas em $GLOBALS['pflow_test_http']
 * e guarda as requisições feitas.
 */
function wp_remote_request( $url, $args ) {
	$GLOBALS['pflow_test_requests'][] = array( 'url' => $url, 'args' => $args );
	$next = array_shift( $GLOBALS['pflow_test_http'] );
	if ( null === $next ) {
		return new WP_Error( 'http', 'sem resposta preparada para ' . $url );
	}
	return $next;
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['code'];
}
function wp_remote_retrieve_body( $r ) {
	return is_string( $r['body'] ) ? $r['body'] : json_encode( $r['body'] );
}
function wp_remote_retrieve_headers( $r ) {
	return $r['headers'] ?? array();
}

require PFLOW_DIR . 'includes/class-pflow-crypto.php';
require PFLOW_DIR . 'includes/class-pflow-tenant.php';
require PFLOW_DIR . 'includes/class-pflow-settings.php';
require PFLOW_DIR . 'includes/class-pflow-http.php';
require PFLOW_DIR . 'includes/class-pflow-networks.php';
require PFLOW_DIR . 'includes/providers/class-pflow-provider.php';
require PFLOW_DIR . 'includes/providers/class-pflow-provider-meta.php';
require PFLOW_DIR . 'includes/providers/class-pflow-provider-linkedin.php';
require PFLOW_DIR . 'includes/providers/class-pflow-provider-x.php';
require PFLOW_DIR . 'includes/providers/class-pflow-provider-threads.php';
require PFLOW_DIR . 'includes/providers/class-pflow-provider-google.php';
require PFLOW_DIR . 'includes/providers/class-pflow-provider-pinterest.php';
require PFLOW_DIR . 'includes/providers/class-pflow-provider-whatsapp.php';
require PFLOW_DIR . 'includes/class-pflow-posts.php';
require PFLOW_DIR . 'includes/class-pflow-schedule.php';
require PFLOW_DIR . 'includes/class-pflow-oauth.php';
