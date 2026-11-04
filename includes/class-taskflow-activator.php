<?php
/**
 * Activation / deactivation routines.
 *
 * Activation: create tables, register capabilities, seed default settings.
 * Deactivation: remove custom capabilities, keep all data intact so the
 * plugin can be re-activated without data loss. Data removal happens only
 * on uninstall and only when the admin opted in (see uninstall.php and the
 * Settings screen).
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Activator
 */
class TaskFlow_Activator {

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		TaskFlow_Schema::install();
		TaskFlow_Settings::install_defaults();

		// Ensure capabilities exist even if the settings option was removed.
		TaskFlow_Capabilities::apply_role_caps( TaskFlow_Settings::all() );

		update_option( 'taskflow_activated_at', current_time( 'mysql' ), false );
		update_option( 'taskflow_version', TASKFLOW_VERSION, false );
	}

	/**
	 * Plugin deactivation. Data and settings are intentionally preserved.
	 *
	 * @return void
	 */
	public static function deactivate() {
		TaskFlow_Capabilities::remove_role_caps();
		delete_option( 'taskflow_version' );
	}
}
