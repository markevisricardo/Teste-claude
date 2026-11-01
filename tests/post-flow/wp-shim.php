<?php
/**
 * Camada extra de stubs para os testes de integração e a prévia das telas:
 * um $wpdb sobre SQLite (em memória) e as funções de template do WordPress.
 */

require_once __DIR__ . '/bootstrap.php';

define( 'PFLOW_VERSION', 'test' );
define( 'PFLOW_DB_VERSION', '1' );
define( 'PFLOW_URL', 'assets-base/' );
define( 'ARRAY_A', 'ARRAY_A' );

/**
 * wpdb mínimo sobre PDO SQLite.
 */
class PFlow_Test_DB {
	public $prefix   = 'wp_';
	public $insert_id = 0;
	private $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->sqliteCreateFunction(
			'FIELD',
			static function ( $value, ...$list ) {
				$i = array_search( $value, $list, true );
				return false === $i ? 0 : $i + 1;
			}
		);
		$this->pdo->sqliteCreateFunction(
			'FIND_IN_SET',
			static function ( $needle, $haystack ) {
				$i = array_search( (string) $needle, explode( ',', (string) $haystack ), true );
				return false === $i ? 0 : $i + 1;
			}
		);
		$this->pdo->exec( "CREATE TABLE wp_pflow_channels (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT DEFAULT 0, network TEXT DEFAULT '', external_id TEXT DEFAULT '', provider_user_id TEXT DEFAULT '', name TEXT DEFAULT '', username TEXT DEFAULT '', avatar_url TEXT DEFAULT '', access_token TEXT DEFAULT '', refresh_token TEXT DEFAULT '', token_expires_at INT DEFAULT 0, meta TEXT, status TEXT DEFAULT 'active', last_error TEXT, created_at TEXT, updated_at TEXT, UNIQUE(user_id, network, external_id))" );
		$this->pdo->exec( "CREATE TABLE wp_pflow_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT DEFAULT 0, content TEXT DEFAULT '', link TEXT DEFAULT '', media_ids TEXT DEFAULT '', channel_ids TEXT DEFAULT '', video_url TEXT DEFAULT '', video_bytes INT DEFAULT 0, status TEXT DEFAULT 'draft', in_queue INT DEFAULT 0, scheduled_at TEXT, sent_at TEXT, created_at TEXT, updated_at TEXT)" );
		$this->pdo->exec( "CREATE TABLE wp_pflow_targets (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INT DEFAULT 0, user_id INT DEFAULT 0, channel_id INT DEFAULT 0, status TEXT DEFAULT 'pending', due_at TEXT, attempts INT DEFAULT 0, remote_id TEXT DEFAULT '', remote_url TEXT DEFAULT '', error TEXT, locked_at TEXT, published_at TEXT)" );
	}

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$i   = 0;
		$pdo = $this->pdo;
		return preg_replace_callback(
			'/%[dsf]/',
			static function ( $m ) use ( &$i, $args, $pdo ) {
				$v = $args[ $i++ ];
				return '%d' === $m[0] ? (string) (int) $v : ( '%f' === $m[0] ? (string) (float) $v : $pdo->quote( (string) $v ) );
			},
			$sql
		);
	}

	public function query( $sql ) {
		return $this->pdo->exec( $sql );
	}

	public function get_results( $sql ) {
		return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
	}

	public function get_row( $sql ) {
		$row = $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC );
		return $row ? $row : null;
	}

	public function get_var( $sql ) {
		$v = $this->pdo->query( $sql )->fetchColumn();
		return false === $v ? null : $v;
	}

	public function get_col( $sql ) {
		return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_COLUMN );
	}

	public function insert( $table, $data ) {
		$cols = array_keys( $data );
		$st   = $this->pdo->prepare( "INSERT INTO {$table} (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', array_fill( 0, count( $cols ), '?' ) ) . ')' );
		$st->execute( array_values( $data ) );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return 1;
	}

	public function update( $table, $data, $where ) {
		$set = implode( ',', array_map( static function ( $c ) { return "{$c} = ?"; }, array_keys( $data ) ) );
		$wh  = implode( ' AND ', array_map( static function ( $c ) { return "{$c} = ?"; }, array_keys( $where ) ) );
		$st  = $this->pdo->prepare( "UPDATE {$table} SET {$set} WHERE {$wh}" );
		$st->execute( array_merge( array_values( $data ), array_values( $where ) ) );
		return $st->rowCount();
	}

	public function delete( $table, $where ) {
		$wh = implode( ' AND ', array_map( static function ( $c ) { return "{$c} = ?"; }, array_keys( $where ) ) );
		$st = $this->pdo->prepare( "DELETE FROM {$table} WHERE {$wh}" );
		$st->execute( array_values( $where ) );
		return $st->rowCount();
	}
}
$GLOBALS['wpdb'] = new PFlow_Test_DB();

/* Usuário, transients e meta. */
$GLOBALS['pflow_test_transient'] = array();
$GLOBALS['pflow_test_usermeta']  = array();
$GLOBALS['pflow_test_now']       = null;

class WP_User {
	public $ID           = 1;
	public $display_name = 'Ana Souza';
	public $user_email   = 'ana@exemplo.com.br';
	public $roles        = array( 'pflow_member' );
	public function exists() {
		return true;
	}
}
$GLOBALS['pflow_test_user'] = new WP_User();
function get_current_user_id() {
	return $GLOBALS['pflow_test_user']->ID;
}
function wp_get_current_user() {
	return $GLOBALS['pflow_test_user'];
}
function is_user_logged_in() {
	return ! empty( $GLOBALS['pflow_test_logged_in'] );
}
function get_transient( $k ) {
	return $GLOBALS['pflow_test_transient'][ $k ] ?? false;
}
function set_transient( $k, $v ) {
	$GLOBALS['pflow_test_transient'][ $k ] = $v;
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['pflow_test_transient'][ $k ] );
	return true;
}
function get_user_meta( $u, $k ) {
	return $GLOBALS['pflow_test_usermeta'][ $u ][ $k ] ?? '';
}
function update_user_meta( $u, $k, $v ) {
	$GLOBALS['pflow_test_usermeta'][ $u ][ $k ] = $v;
	return true;
}
function current_time( $type, $gmt = false ) {
	return gmdate( 'Y-m-d H:i:s' );
}

/* Escape e i18n. */
function esc_html( $t ) {
	return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $t ) {
	return esc_html( $t );
}
function esc_textarea( $t ) {
	return esc_html( $t );
}
function esc_url( $u ) {
	return esc_html( $u );
}
function esc_url_raw( $u ) {
	return (string) $u;
}
function esc_js( $t ) {
	return addslashes( (string) $t );
}
function esc_sql( $t ) {
	return str_replace( "'", "''", (string) $t );
}
function esc_html__( $t ) {
	return esc_html( $t );
}
function esc_attr__( $t ) {
	return esc_attr( $t );
}
function esc_html_e( $t ) {
	echo esc_html( $t );
}
function esc_attr_e( $t ) {
	echo esc_attr( $t );
}
function wp_kses_post( $t ) {
	return $t;
}
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}
function sanitize_text_field( $t ) {
	return trim( strip_tags( (string) $t ) );
}
function wp_unslash( $v ) {
	return $v;
}

/* Formulários e URLs. */
function checked( $a, $b = true, $echo = true ) {
	$r = (string) $a === (string) $b ? ' checked' : '';
	if ( $echo ) {
		echo $r; // phpcs:ignore
	}
	return $r;
}
function selected( $a, $b = true, $echo = true ) {
	$r = (string) $a === (string) $b ? ' selected' : '';
	if ( $echo ) {
		echo $r; // phpcs:ignore
	}
	return $r;
}
function disabled( $a, $b = true ) {
	echo (string) $a === (string) $b ? ' disabled' : ''; // phpcs:ignore
}
function wp_nonce_field() {
	echo '<input type="hidden" name="_wpnonce" value="x">';
}
function wp_nonce_url( $u ) {
	return $u . '&_wpnonce=x';
}
function admin_url( $p = '' ) {
	return 'https://app.exemplo.com.br/wp-admin/' . $p;
}
function home_url( $p = '' ) {
	return 'https://app.exemplo.com.br' . $p;
}
function wp_logout_url() {
	return '#sair';
}
function wp_lostpassword_url() {
	return '#senha';
}
function get_edit_user_link() {
	return '#';
}
function wp_list_pluck( $list, $field ) {
	return array_map(
		static function ( $i ) use ( $field ) {
			return is_object( $i ) ? $i->$field : $i[ $field ];
		},
		$list
	);
}
function wp_list_filter( $list, $args ) {
	return array_filter(
		$list,
		static function ( $i ) use ( $args ) {
			foreach ( $args as $k => $v ) {
				if ( ( $i[ $k ] ?? null ) !== $v ) {
					return false;
				}
			}
			return true;
		}
	);
}
function wp_date( $format, $ts, $tz = null ) {
	$d    = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz ? $tz : wp_timezone() );
	$days = array( 'Sun' => 'dom', 'Mon' => 'seg', 'Tue' => 'ter', 'Wed' => 'qua', 'Thu' => 'qui', 'Fri' => 'sex', 'Sat' => 'sáb' );
	$full = array( 'Sunday' => 'domingo', 'Monday' => 'segunda-feira', 'Tuesday' => 'terça-feira', 'Wednesday' => 'quarta-feira', 'Thursday' => 'quinta-feira', 'Friday' => 'sexta-feira', 'Saturday' => 'sábado' );
	$mon  = array( 'January' => 'janeiro', 'February' => 'fevereiro', 'March' => 'março', 'April' => 'abril', 'May' => 'maio', 'June' => 'junho', 'July' => 'julho', 'August' => 'agosto', 'September' => 'setembro', 'October' => 'outubro', 'November' => 'novembro', 'December' => 'dezembro' );
	return strtr( strtr( strtr( $d->format( $format ), $full ), $days ), $mon );
}
function paginate_links() {
	return '';
}
// Anexos: registro em $GLOBALS['pflow_test_files'][id] = path, mime, url
// (ou, nas imagens de exemplo, $GLOBALS['pflow_test_media'][id] = url).
function pflow_test_file( $id ) {
	if ( isset( $GLOBALS['pflow_test_files'][ $id ] ) ) {
		return $GLOBALS['pflow_test_files'][ $id ];
	}
	return array( 'path' => '/tmp/pflow-' . $id . '.jpg', 'mime' => 'image/jpeg', 'url' => $GLOBALS['pflow_test_media'][ $id ] ?? '' );
}
function wp_get_attachment_image_src( $id ) {
	return array( pflow_test_file( $id )['url'], 300, 300 );
}
function wp_get_attachment_url( $id ) {
	return pflow_test_file( $id )['url'];
}
function get_attached_file( $id ) {
	return pflow_test_file( $id )['path'];
}
function get_post_mime_type( $id = 0 ) {
	return pflow_test_file( $id )['mime'];
}
function wp_upload_dir() {
	$dir = sys_get_temp_dir() . '/pflow-uploads';
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true );
	}
	return array( 'path' => $dir, 'url' => 'https://app.exemplo.com.br/wp-content/uploads', 'error' => false );
}
function wp_unique_filename( $dir, $name ) {
	return uniqid() . '-' . $name;
}
function wp_insert_attachment( $args, $path ) {
	$id                                   = 1000 + count( $GLOBALS['pflow_test_files'] ?? array() );
	$GLOBALS['pflow_test_files'][ $id ]   = array( 'path' => $path, 'mime' => $args['post_mime_type'], 'url' => $args['guid'] );
	return $id;
}
function wp_generate_attachment_metadata() {
	return array();
}
function wp_update_attachment_metadata() {}
function get_post_meta() {
	return 1;
}
function wp_delete_file( $path ) {
	@unlink( $path );
}
function wp_next_scheduled() {
	return $GLOBALS['pflow_test_next'] ?? false;
}
function wp_schedule_single_event( $time, $hook, $args = array() ) {
	$GLOBALS['pflow_test_events'][] = array( $hook, $args );
}
function set_url_scheme( $u ) {
	return $u;
}
function wp_safe_redirect( $url ) {
	throw new PFlow_Test_Redirect( $url );
}
function check_admin_referer() {
	return true;
}
function wp_delete_attachment( $id ) {
	$GLOBALS['pflow_test_deleted'][] = $id;
}
function wp_create_nonce() {
	return 'nonce';
}
function delete_user_meta( $u, $k ) {
	unset( $GLOBALS['pflow_test_usermeta'][ $u ][ $k ] );
}
function add_action() {}
function add_filter() {}

class PFlow_Test_Redirect extends Exception {
	public $url;
	public function __construct( $url ) {
		parent::__construct( 'redirect' );
		$this->url = $url;
	}
}

require_once PFLOW_DIR . 'includes/class-pflow-db.php';
require_once PFLOW_DIR . 'includes/class-pflow-channels.php';
require_once PFLOW_DIR . 'includes/class-pflow-media.php';
require_once PFLOW_DIR . 'includes/class-pflow-drive.php';
require_once PFLOW_DIR . 'includes/class-pflow-publisher.php';
require_once PFLOW_DIR . 'includes/class-pflow-whatsapp-web.php';
require_once PFLOW_DIR . 'app/class-pflow-app.php';
require_once PFLOW_DIR . 'app/class-pflow-auth.php';
