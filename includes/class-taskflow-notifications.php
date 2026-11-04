<?php
/**
 * In-plugin notification service (bell icon in the TaskFlow UI).
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Notifications
 */
class TaskFlow_Notifications {

	/**
	 * Create a notification for a user.
	 *
	 * @param int             $user_id Recipient.
	 * @param string          $type    Event type.
	 * @param string          $message Localized message.
	 * @param int|string|null $task_id Related task.
	 * @return int Inserted id.
	 */
	public function add( $user_id, $type, $message, $task_id = null ) {
		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id < 1 || '' === (string) $message ) {
			return 0;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB
			TaskFlow_Schema::table( 'notifications' ),
			array(
				'user_id'    => $user_id,
				'task_id'    => $task_id ? absint( $task_id ) : null,
				'type'       => sanitize_key( $type ),
				'message'    => (string) $message,
				'is_read'    => 0,
				'created_at' => current_time( 'mysql' ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * List notifications of a user (never of anyone else – server enforced).
	 *
	 * @param int   $user_id Recipient user id.
	 * @param array $args    page, per_page, unread_only.
	 * @return array{items:array<int,array<string,mixed>>,total:int,unread:int}
	 */
	public function for_user( $user_id, $args = array() ) {
		global $wpdb;

		$user_id = (int) $user_id;
		$table   = TaskFlow_Schema::table( 'notifications' );
		$where   = ' WHERE user_id = %d';
		$params  = array( $user_id );

		if ( ! empty( $args['unread_only'] ) ) {
			$where   .= ' AND is_read = 0';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + prepared fragment.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) FROM {$table}{$where}", $params ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + prepared fragment.
		$unread = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT( * ) FROM {$table} WHERE user_id = %d AND is_read = 0", $user_id )
		);

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, absint( $args['per_page'] ) ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;

		$query_params   = $params;
		$query_params[] = $per_page;
		$query_params[] = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + prepared fragments.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d", $query_params ),
			ARRAY_A
		);

		return array(
			'items'  => $rows ? $rows : array(),
			'total'  => $total,
			'unread' => $unread,
		);
	}

	/**
	 * Mark notifications as read. Without ids, all of the user's notifications
	 * are marked read.
	 *
	 * @param int               $user_id Owner.
	 * @param array<int,int>|null $ids Specific ids or null for all.
	 * @return int Number of updated rows.
	 */
	public function mark_read( $user_id, $ids = null ) {
		global $wpdb;

		$user_id = (int) $user_id;
		$table   = TaskFlow_Schema::table( 'notifications' );

		if ( null === $ids ) {
			return (int) $wpdb->update( $table, array( 'is_read' => 1 ), array( 'user_id' => $user_id ) ); // phpcs:ignore WordPress.DB
		}

		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built from count only.
			$wpdb->prepare(
				"UPDATE {$table} SET is_read = 1 WHERE user_id = %d AND id IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $user_id ), $ids )
			)
		);
	}

	/**
	 * Unread counter for the header badge.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function unread_count( $user_id ) {
		global $wpdb;

		$table = TaskFlow_Schema::table( 'notifications' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT( * ) FROM {$table} WHERE user_id = %d AND is_read = 0", (int) $user_id )
		);
	}
}
