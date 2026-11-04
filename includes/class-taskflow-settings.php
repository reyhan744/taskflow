<?php
/**
 * Plugin settings, stored as a single autoloaded option with whitelist
 * sanitization. Documents and controls what happens to data on uninstall.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Settings
 */
class TaskFlow_Settings {

	const OPTION_KEY = 'taskflow_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'currency'                   => 'USD',
			'items_per_page'             => 10,
			'notify_on_assignment'       => true,
			'notify_on_status_change'    => true,
			'notify_on_comment'          => true,
			'mock_email_on_assignment'   => true,
			'delete_data_on_uninstall'   => false,
			'extra_edit_roles'           => array(),
			'kanban_columns_order'       => array( 'new', 'in_progress', 'completed' ),
		);
	}

	/**
	 * Read all settings merged over the defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist a whitelist of settings. Unknown keys are dropped so a crafted
	 * REST payload cannot inject arbitrary options.
	 *
	 * @param array<string,mixed> $input Raw input (typically from REST).
	 * @return array<string,mixed> The sanitized settings actually stored.
	 */
	public static function update( $input ) {
		$current     = self::all();
		$sanitized   = $current;
		$bool_keys   = array(
			'notify_on_assignment',
			'notify_on_status_change',
			'notify_on_comment',
			'mock_email_on_assignment',
			'delete_data_on_uninstall',
		);

		foreach ( $bool_keys as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$sanitized[ $key ] = (bool) rest_sanitize_boolean( $input[ $key ] );
			}
		}

		if ( isset( $input['currency'] ) ) {
			$currency            = sanitize_text_field( (string) $input['currency'] );
			$sanitized['currency'] = '' === $currency ? 'USD' : substr( $currency, 0, 8 );
		}

		if ( isset( $input['items_per_page'] ) ) {
			$per_page                   = absint( $input['items_per_page'] );
			$per_page                   = min( 100, max( 5, $per_page ) );
			$sanitized['items_per_page'] = $per_page;
		}

		if ( isset( $input['extra_edit_roles'] ) ) {
			$known = array_keys( wp_roles()->get_names() );
			$extra = array_values(
				array_intersect(
					array_map( 'sanitize_key', (array) $input['extra_edit_roles'] ),
					$known
				)
			);
			// Administrators and editors already hold the capability.
			$extra                          = array_diff( $extra, array( 'administrator', 'editor' ) );
			$sanitized['extra_edit_roles']   = array_values( $extra );
		}

		update_option( self::OPTION_KEY, $sanitized, false );

		// Capability changes take effect immediately.
		TaskFlow_Capabilities::apply_role_caps( $sanitized );

		return $sanitized;
	}

	/**
	 * Seed the option on first activation so defaults are explicit.
	 *
	 * @return void
	 */
	public static function install_defaults() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::defaults(), '', false );
		}
	}
}
