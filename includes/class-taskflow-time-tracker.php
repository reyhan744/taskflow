<?php
/**
 * Per-user time tracking with start/stop timer (creative extension).
 *
 * A user has at most one running timer; starting a new one stops the previous
 * automatically (logged as a separate entry). Finished durations are added to
 * the task's cached time_spent counter and to the activity history.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Time_Tracker
 */
class TaskFlow_Time_Tracker {

	/**
	 * Start (or switch) the timer for a task.
	 *
	 * @param int $task_id Task id.
	 * @param int $user_id Acting user.
	 * @return array<string,mixed>|WP_Error
	 */
	public function start( $task_id, $user_id = null ) {
		global $wpdb;

		$user_id = null === $user_id ? (int) get_current_user_id() : (int) $user_id;
		$task_id = (int) $task_id;

		$running = $this->active( $user_id );

		if ( $running && (int) $running['task_id'] === $task_id ) {
			return $running;
		}

		if ( $running ) {
			$this->stop( $user_id );
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB
			TaskFlow_Schema::table( 'time_logs' ),
			array(
				'task_id'    => $task_id,
				'user_id'    => $user_id,
				'started_at' => current_time( 'mysql' ),
			)
		);

		return (array) $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Stop the running timer (if any) and persist the duration.
	 *
	 * @param int $user_id Acting user.
	 * @return array<string,mixed>|null Finished log row.
	 */
	public function stop( $user_id = null ) {
		global $wpdb;

		$user_id = null === $user_id ? (int) get_current_user_id() : (int) $user_id;
		$running = $this->active( $user_id );

		if ( ! $running ) {
			return null;
		}

		$ended    = current_time( 'mysql' );
		$started  = strtotime( (string) $running['started_at'] );
		$duration = max( 0, strtotime( $ended ) - $started );

		$wpdb->update( // phpcs:ignore WordPress.DB
			TaskFlow_Schema::table( 'time_logs' ),
			array(
				'ended_at' => $ended,
				'duration' => $duration,
			),
			array( 'id' => (int) $running['id'] )
		);

		( new TaskFlow_Task_Repository() )->add_time_spent( (int) $running['task_id'], $duration );

		( new TaskFlow_Activity() )->log(
			(int) $running['task_id'],
			'time_logged',
			array( 'duration' => $duration ),
			sprintf(
				/* translators: %s: human readable duration */
				__( 'Logged %s of working time.', 'taskflow' ),
				$this->human_duration( $duration )
			),
			$user_id
		);

		return (array) $this->find( (int) $running['id'] );
	}

	/**
	 * The running timer of a user.
	 *
	 * @param int $user_id User id.
	 * @return array<string,mixed>|null
	 */
	public function active( $user_id = null ) {
		global $wpdb;

		$user_id = null === $user_id ? (int) get_current_user_id() : (int) $user_id;
		$table   = TaskFlow_Schema::table( 'time_logs' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND ended_at IS NULL ORDER BY id DESC LIMIT 1", $user_id ),
			ARRAY_A
		);

		if ( $row ) {
			$row['id']      = (int) $row['id'];
			$row['task_id'] = (int) $row['task_id'];
		}

		return $row ? $row : null;
	}

	/**
	 * Time logs of a task, newest first.
	 *
	 * @param int $task_id Task id.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_task( $task_id ) {
		global $wpdb;

		$table = TaskFlow_Schema::table( 'time_logs' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE task_id = %d ORDER BY id DESC LIMIT 100", (int) $task_id ),
			ARRAY_A
		);

		foreach ( (array) $rows as &$row ) {
			$row['id']      = (int) $row['id'];
			$row['task_id'] = (int) $row['task_id'];
			$row['user_id'] = (int) $row['user_id'];
		}

		return $rows ? $rows : array();
	}

	/**
	 * Fetch a single log row.
	 *
	 * @param int $id Log id.
	 * @return array<string,mixed>|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = TaskFlow_Schema::table( 'time_logs' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( $row ) {
			$row['id']      = (int) $row['id'];
			$row['task_id'] = (int) $row['task_id'];
		}

		return $row ? $row : null;
	}

	/**
	 * Human readable duration (e.g. "1h 23m 45s").
	 *
	 * @param int $seconds Duration.
	 * @return string
	 */
	public static function human_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$hours   = intdiv( $seconds, 3600 );
		$minutes = intdiv( $seconds % 3600, 60 );
		$rest    = $seconds % 60;

		$parts = array();

		if ( $hours ) {
			$parts[] = $hours . 'h';
		}
		if ( $minutes ) {
			$parts[] = $minutes . 'm';
		}
		if ( ! $hours && ( $rest || ! $parts ) ) {
			$parts[] = $rest . 's';
		}

		return implode( ' ', $parts );
	}
}
