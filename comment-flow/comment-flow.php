<?php
/**
 * Plugin Name:       Comment Flow – Automação de Comentários do Instagram
 * Description:       Plataforma de automações para Instagram pela API oficial da Meta. Cada usuário se cadastra, conecta o próprio Instagram e vê só os seus dados. Painel no shortcode [comment_flow].
 * Version:           0.6.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Comment Flow
 * License:           GPL-2.0-or-later
 * Text Domain:       comment-flow
 */

defined( 'ABSPATH' ) || exit;

define( 'CFLOW_VERSION', '0.6.0' );
define( 'CFLOW_DB_VERSION', '4' );
define( 'CFLOW_FILE', __FILE__ );
define( 'CFLOW_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFLOW_URL', plugin_dir_url( __FILE__ ) );
define( 'CFLOW_GRAPH_VERSION', 'v23.0' );

require_once CFLOW_DIR . 'includes/class-cflow-crypto.php';
require_once CFLOW_DIR . 'includes/class-cflow-settings.php';
require_once CFLOW_DIR . 'includes/class-cflow-db.php';
require_once CFLOW_DIR . 'includes/class-cflow-tenant.php';
require_once CFLOW_DIR . 'includes/class-cflow-accounts.php';
require_once CFLOW_DIR . 'includes/class-cflow-instagram-api.php';
require_once CFLOW_DIR . 'includes/class-cflow-matcher.php';
require_once CFLOW_DIR . 'includes/class-cflow-queue.php';
require_once CFLOW_DIR . 'includes/class-cflow-leads.php';
require_once CFLOW_DIR . 'includes/class-cflow-contacts.php';
require_once CFLOW_DIR . 'includes/class-cflow-processor.php';
require_once CFLOW_DIR . 'includes/class-cflow-conversation.php';
require_once CFLOW_DIR . 'includes/class-cflow-webhook.php';
require_once CFLOW_DIR . 'includes/class-cflow-oauth.php';
require_once CFLOW_DIR . 'includes/class-cflow-token-refresher.php';
require_once CFLOW_DIR . 'app/class-cflow-app.php';
require_once CFLOW_DIR . 'app/class-cflow-auth.php';
require_once CFLOW_DIR . 'app/class-cflow-frontend.php';

if ( is_admin() ) {
	require_once CFLOW_DIR . 'admin/class-cflow-admin.php';
}

register_activation_hook( __FILE__, array( 'CFlow_DB', 'install' ) );
register_activation_hook( __FILE__, array( 'CFlow_Auth', 'add_role' ) );
register_activation_hook( __FILE__, array( 'CFlow_Token_Refresher', 'schedule' ) );
register_deactivation_hook( __FILE__, array( 'CFlow_Token_Refresher', 'unschedule' ) );

add_action(
	'plugins_loaded',
	static function () {
		CFlow_DB::maybe_upgrade();
		CFlow_Auth::add_role();
		CFlow_Webhook::init();
		CFlow_Queue::init();
		CFlow_OAuth::init();
		CFlow_Token_Refresher::init();
		CFlow_App::init();
		CFlow_Auth::init();
		CFlow_Frontend::init();

		if ( is_admin() ) {
			CFlow_Admin::init();
		}
	}
);
