<?php
/**
 * Plugin Name:       TaskFlow – Team Task Management
 * Plugin URI:        https://github.com/reyhan744/taskflow
 * Description:       Standalone team task management for WordPress: tasks, projects, tags, assignment, role-based access, Kanban, activity log, dashboard, mock payment & email, CSV/JSON export. Theme-independent, built with PHP + Vue.js 3 and a secure REST API.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Reyhan M
 * Author URI:        https://github.com/reyhan744
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       taskflow
 * Domain Path:       /languages
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

define( 'TASKFLOW_VERSION', '1.0.0' );
define( 'TASKFLOW_DB_VERSION', '1.0.0' );
define( 'TASKFLOW_PLUGIN_FILE', __FILE__ );
define( 'TASKFLOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TASKFLOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TASKFLOW_REST_NAMESPACE', 'taskflow/v1' );

/**
 * Autoloader: TaskFlow_Task_Repository => includes/class-taskflow-task-repository.php
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
function taskflow_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'TaskFlow_' ) ) {
		return;
	}

	$slug = strtolower( str_replace( '_', '-', $class_name ) );
	$file = TASKFLOW_PLUGIN_DIR . 'includes/class-' . $slug . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'taskflow_autoload' );

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return void
 */
function taskflow_boot() {
	add_action( 'init', array( 'TaskFlow_Plugin', 'load_textdomain' ) );
	add_action( 'rest_api_init', array( 'TaskFlow_REST', 'register_routes' ) );
	add_action( 'admin_menu', array( 'TaskFlow_Assets', 'register_menu' ) );
	add_action( 'admin_enqueue_scripts', array( 'TaskFlow_Assets', 'enqueue_assets' ) );

	/**
	 * Fires after TaskFlow has finished booting.
	 *
	 * Extension point for future integrations (real gateways, webhooks, ...).
	 */
	do_action( 'taskflow_loaded' );
}
add_action( 'plugins_loaded', 'taskflow_boot' );

register_activation_hook( __FILE__, array( 'TaskFlow_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TaskFlow_Activator', 'deactivate' ) );
