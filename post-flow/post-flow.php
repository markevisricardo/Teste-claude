<?php
/**
 * Plugin Name:       Post Flow – Agendamento de posts em redes sociais
 * Description:       Plataforma multiusuário para agendar e publicar posts no Facebook, Instagram, LinkedIn, X, Threads, Google Meu Negócio, Pinterest e WhatsApp pelas APIs oficiais. Cada usuário se cadastra, conecta os próprios canais e vê só os seus dados. Painel no shortcode [post_flow].
 * Version:           0.2.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Post Flow
 * License:           GPL-2.0-or-later
 * Text Domain:       post-flow
 */

defined( 'ABSPATH' ) || exit;

define( 'PFLOW_VERSION', '0.2.0' );
define( 'PFLOW_DB_VERSION', '1' );
define( 'PFLOW_FILE', __FILE__ );
define( 'PFLOW_DIR', plugin_dir_path( __FILE__ ) );
define( 'PFLOW_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'PFLOW_GRAPH_VERSION' ) ) {
	define( 'PFLOW_GRAPH_VERSION', 'v23.0' );
}
if ( ! defined( 'PFLOW_LINKEDIN_VERSION' ) ) {
	// Versão da API REST do LinkedIn (AAAAMM). Cada versão vale por cerca de um ano.
	define( 'PFLOW_LINKEDIN_VERSION', '202606' );
}

require_once PFLOW_DIR . 'includes/class-pflow-crypto.php';
require_once PFLOW_DIR . 'includes/class-pflow-tenant.php';
require_once PFLOW_DIR . 'includes/class-pflow-settings.php';
require_once PFLOW_DIR . 'includes/class-pflow-http.php';
require_once PFLOW_DIR . 'includes/class-pflow-db.php';
require_once PFLOW_DIR . 'includes/class-pflow-networks.php';
require_once PFLOW_DIR . 'includes/providers/class-pflow-provider.php';
require_once PFLOW_DIR . 'includes/providers/class-pflow-provider-meta.php';
require_once PFLOW_DIR . 'includes/providers/class-pflow-provider-linkedin.php';
require_once PFLOW_DIR . 'includes/providers/class-pflow-provider-x.php';
require_once PFLOW_DIR . 'includes/providers/class-pflow-provider-threads.php';
require_once PFLOW_DIR . 'includes/providers/class-pflow-provider-google.php';
require_once PFLOW_DIR . 'includes/providers/class-pflow-provider-pinterest.php';
require_once PFLOW_DIR . 'includes/providers/class-pflow-provider-whatsapp.php';
require_once PFLOW_DIR . 'includes/class-pflow-channels.php';
require_once PFLOW_DIR . 'includes/class-pflow-posts.php';
require_once PFLOW_DIR . 'includes/class-pflow-schedule.php';
require_once PFLOW_DIR . 'includes/class-pflow-media.php';
require_once PFLOW_DIR . 'includes/class-pflow-publisher.php';
require_once PFLOW_DIR . 'includes/class-pflow-oauth.php';
require_once PFLOW_DIR . 'app/class-pflow-app.php';
require_once PFLOW_DIR . 'app/class-pflow-auth.php';
require_once PFLOW_DIR . 'app/class-pflow-frontend.php';

if ( is_admin() ) {
	require_once PFLOW_DIR . 'admin/class-pflow-admin.php';
}

register_activation_hook( __FILE__, array( 'PFlow_DB', 'install' ) );
register_activation_hook( __FILE__, array( 'PFlow_Auth', 'add_role' ) );
register_activation_hook( __FILE__, array( 'PFlow_Frontend', 'request_setup' ) );
register_activation_hook( __FILE__, array( 'PFlow_Publisher', 'schedule' ) );
register_deactivation_hook( __FILE__, array( 'PFlow_Publisher', 'unschedule' ) );

add_action(
	'plugins_loaded',
	static function () {
		PFlow_DB::maybe_upgrade();
		PFlow_Auth::add_role();
		PFlow_Publisher::init();
		PFlow_OAuth::init();
		PFlow_App::init();
		PFlow_Auth::init();
		PFlow_Frontend::init();

		if ( is_admin() ) {
			PFlow_Admin::init();
		}
	}
);
