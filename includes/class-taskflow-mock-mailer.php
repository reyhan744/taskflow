<?php
/**
 * MOCK e-mail service (adapter pattern).
 *
 * Simulates sending and receiving e-mail without ever touching SMTP/IMAP:
 * outgoing messages land in an Outbox log, incoming messages are created via
 * a simulation endpoint / sample data and stored in an Inbox. Swapping in a
 * real mail service later only requires replacing this adapter – the task
 * layer depends on the send() contract, not on the storage.
 *
 * Every record and every UI string related to this feature is explicitly
 * marked as MOCK/DEMO.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Mock_Mailer
 */
class TaskFlow_Mock_Mailer {

	/**
	 * "Send" an e-mail. The message is stored in the Outbox with status sent
	 * and a timestamp. No real transport is used.
	 *
	 * A filter (taskflow_mock_mailer_passthrough) exists for future real
	 * integrations; by default it is false and everything stays a mock.
	 *
	 * @param string $to      Recipient e-mail.
	 * @param string $subject Subject.
	 * @param string $body    Body (plain text).
	 * @return array<string,mixed> Stored outbox row.
	 */
	public function send( $to, $subject, $body ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$passthrough = (bool) apply_filters( 'taskflow_mock_mailer_passthrough', false, $to, $subject, $body );

		// Future real integrations can hook in here; mock behavior stays on by default.
		$error = null;
		if ( $passthrough && ! wp_mail( $to, '[MOCK] ' . $subject, $body ) ) {
			$error = __( 'Simulated delivery failure (passthrough enabled).', 'taskflow' );
		}

		$status = $error ? 'failed' : 'sent';

		$wpdb->insert( // phpcs:ignore WordPress.DB
			TaskFlow_Schema::table( 'email_outbox' ),
			array(
				'to_email'  => $to,
				'subject'   => $subject,
				'body'      => $body,
				'status'    => $status,
				'error'     => $error,
				'created_at' => $now,
				'sent_at'   => $error ? null : $now,
			)
		);

		return (array) $this->find_outbox( (int) $wpdb->insert_id );
	}

	/**
	 * Outbox log (sent mock e-mails), newest first.
	 *
	 * @param array $args page, per_page, search.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public function outbox( $args = array() ) {
		global $wpdb;

		$table  = TaskFlow_Schema::table( 'email_outbox' );
		$where  = ' WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where   .= ' AND ( to_email LIKE %s OR subject LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + prepared fragment.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) FROM {$table}{$where}", $params ) );

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, absint( $args['per_page'] ) ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;

		$params[] = $per_page;
		$params[] = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + prepared fragments.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d", $params ),
			ARRAY_A
		);

		return array(
			'items' => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Simulate an INCOMING e-mail into the Inbox (no IMAP involved).
	 *
	 * @param string $from_email Sender address.
	 * @param string $from_name  Sender name.
	 * @param string $subject    Subject.
	 * @param string $body       Body.
	 * @return array<string,mixed> Stored inbox row.
	 */
	public function simulate_incoming( $from_email, $from_name, $subject, $body ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB
			TaskFlow_Schema::table( 'email_inbox' ),
			array(
				'from_email'  => $from_email,
				'from_name'   => $from_name,
				'subject'     => $subject,
				'body'        => $body,
				'is_read'     => 0,
				'received_at' => current_time( 'mysql' ),
			)
		);

		return (array) $this->find_inbox( (int) $wpdb->insert_id );
	}

	/**
	 * Inbox messages, newest first.
	 *
	 * @param array $args page, per_page.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public function inbox( $args = array() ) {
		global $wpdb;

		$table = TaskFlow_Schema::table( 'email_inbox' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT( * ) FROM {$table}" );

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, absint( $args['per_page'] ) ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ),
			ARRAY_A
		);

		return array(
			'items' => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Mark an inbox message read.
	 *
	 * @param int $id Inbox id.
	 * @return void
	 */
	public function mark_inbox_read( $id ) {
		global $wpdb;

		$wpdb->update( TaskFlow_Schema::table( 'email_inbox' ), array( 'is_read' => 1 ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Fetch one outbox row.
	 *
	 * @param int $id Row id.
	 * @return array<string,mixed>|null
	 */
	public function find_outbox( $id ) {
		global $wpdb;

		$table = TaskFlow_Schema::table( 'email_outbox' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * Fetch one inbox row.
	 *
	 * @param int $id Row id.
	 * @return array<string,mixed>|null
	 */
	public function find_inbox( $id ) {
		global $wpdb;

		$table = TaskFlow_Schema::table( 'email_inbox' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * Build the standardized, localized body for automated task notifications.
	 * Every automated message carries an explicit MOCK marker.
	 *
	 * @param string               $title Subject-ish title.
	 * @param array<string,string> $lines Key/value detail lines.
	 * @return string
	 */
	public static function build_task_body( $title, $lines = array() ) {
		$body = sprintf( '[MOCK / DEMO] %s', $title ) . "\n";

		foreach ( $lines as $label => $value ) {
			$body .= $label . ': ' . $value . "\n";
		}

		return $body;
	}
}
