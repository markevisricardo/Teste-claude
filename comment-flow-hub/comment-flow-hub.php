<?php
/**
 * Plugin Name:       Comment Flow Hub
 * Description:       Servidor central do Comment Flow: guarda o app da Meta, conecta as contas do Instagram dos clientes, recebe os webhooks e repassa cada um ao site do cliente certo. Instale só no site do hub.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Comment Flow
 * License:           GPL-2.0-or-later
 * Text Domain:       comment-flow-hub
 */

defined( 'ABSPATH' ) || exit;

define( 'CFHUB_VERSION', '0.1.0' );
define( 'CFHUB_DB_VERSION', '1' );
define( 'CFHUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFHUB_URL', plugin_dir_url( __FILE__ ) );
define( 'CFHUB_GRAPH_VERSION', 'v23.0' );

require_once CFHUB_DIR . 'includes/class-cfhub-crypto.php';
require_once CFHUB_DIR . 'includes/class-cfhub-settings.php';
require_once CFHUB_DIR . 'includes/class-cfhub-db.php';
require_once CFHUB_DIR . 'includes/class-cfhub-licenses.php';
require_once CFHUB_DIR . 'includes/class-cfhub-instagram.php';
require_once CFHUB_DIR . 'includes/class-cfhub-forwarder.php';
require_once CFHUB_DIR . 'includes/class-cfhub-rest.php';

if ( is_admin() ) {
	require_once CFHUB_DIR . 'admin/class-cfhub-admin.php';
}

register_activation_hook( __FILE__, array( 'CFHub_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'CFHub_Forwarder', 'unschedule' ) );

add_action(
	'plugins_loaded',
	static function () {
		CFHub_DB::maybe_upgrade();
		CFHub_Rest::init();
		CFHub_Forwarder::init();

		if ( is_admin() ) {
			CFHub_Admin::init();
		}
	}
);
