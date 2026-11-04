<?php
/**
 * Core plugin glue: textdomain loading.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Plugin
 */
class TaskFlow_Plugin {

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain(
			'taskflow',
			false,
			dirname( plugin_basename( TASKFLOW_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
