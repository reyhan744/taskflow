<?php
/**
 * Input validation helpers shared by the REST controllers and repositories.
 *
 * All user input passes through here before it reaches the database layer:
 * whitelists for enums, normalization for dates, strict type coercion for ids.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Validation
 */
class TaskFlow_Validation {

	/**
	 * Allowed task statuses.
	 *
	 * @return array<int,string>
	 */
	public static function statuses() {
		return array( 'new', 'in_progress', 'completed' );
	}

	/**
	 * Allowed task priorities.
	 *
	 * @return array<int,string>
	 */
	public static function priorities() {
		return array( 'low', 'medium', 'high', 'urgent' );
	}

	/**
	 * Allowed order-by columns (whitelist: never interpolate request input
	 * into ORDER BY directly).
	 *
	 * @return array<int,string>
	 */
	public static function order_by_columns() {
		return array( 'created_at', 'updated_at', 'due_date', 'priority', 'title', 'position' );
	}

	/**
	 * Validate a status value.
	 *
	 * @param mixed $status Raw value.
	 * @return string|WP_Error
	 */
	public static function status( $status ) {
		$status = sanitize_key( (string) $status );

		if ( ! in_array( $status, self::statuses(), true ) ) {
			return new WP_Error(
				'taskflow_invalid_status',
				__( 'Invalid task status.', 'taskflow' )
			);
		}

		return $status;
	}

	/**
	 * Validate a priority value.
	 *
	 * @param mixed $priority Raw value.
	 * @return string|WP_Error
	 */
	public static function priority( $priority ) {
		$priority = sanitize_key( (string) $priority );

		if ( ! in_array( $priority, self::priorities(), true ) ) {
			return new WP_Error(
				'taskflow_invalid_priority',
				__( 'Invalid task priority.', 'taskflow' )
			);
		}

		return $priority;
	}

	/**
	 * Normalize a user supplied date to "Y-m-d H:i:s" (site timezone), or
	 * null for empty values. Accepts ISO-8601, datetime-local and MySQL input.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null|WP_Error
	 */
	public static function datetime( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		// datetime-local inputs send "YYYY-MM-DDTHH:MM".
		$normalized = str_replace( 'T', ' ', $value );
		$timestamp  = strtotime( $normalized );

		if ( false === $timestamp ) {
			return new WP_Error(
				'taskflow_invalid_date',
				__( 'Invalid date format.', 'taskflow' )
			);
		}

		return date( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Validate a positive integer id.
	 *
	 * @param mixed $value Raw value.
	 * @return int|WP_Error
	 */
	public static function id( $value ) {
		$id = absint( $value );

		if ( $id < 1 ) {
			return new WP_Error(
				'taskflow_invalid_id',
				__( 'Invalid identifier.', 'taskflow' )
			);
		}

		return $id;
	}

	/**
	 * Validate a positive amount.
	 *
	 * @param mixed $value Raw value.
	 * @return float|WP_Error
	 */
	public static function amount( $value ) {
		if ( ! is_numeric( $value ) ) {
			return new WP_Error(
				'taskflow_invalid_amount',
				__( 'Amount must be numeric.', 'taskflow' )
			);
		}

		$amount = (float) $value;

		if ( $amount <= 0 || $amount > 999999999999 ) {
			return new WP_Error(
				'taskflow_invalid_amount',
				__( 'Amount must be a positive number.', 'taskflow' )
			);
		}

		return round( $amount, 2 );
	}

	/**
	 * Validate an email address.
	 *
	 * @param mixed $value Raw value.
	 * @return string|WP_Error
	 */
	public static function email( $value ) {
		$email = sanitize_email( (string) $value );

		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'taskflow_invalid_email',
				__( 'Invalid email address.', 'taskflow' )
			);
		}

		return $email;
	}

	/**
	 * Sanitize a hex color, falling back to the plugin default.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function color( $value ) {
		$color = sanitize_hex_color( $value );

		return $color ? $color : '#2271b1';
	}

	/**
	 * Build a slug (used for tags) with Persian/Arabic transliteration-safe
	 * fallback: non-ASCII titles get a dash-based unique slug.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function slug( $text ) {
		$slug = sanitize_title( $text );

		if ( '' === $slug ) {
			$slug = 'tag-' . strtolower( wp_generate_password( 6, false ) );
		}

		return $slug;
	}
}
