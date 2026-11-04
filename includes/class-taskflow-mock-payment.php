<?php
/**
 * MOCK payment gateway adapter.
 *
 * Implements the adapter contract a real gateway (ZarinPal, Stripe, ...) would
 * fulfil: create a payment request, receive a reference, resolve it to
 * success / failed / pending, and finalize pending transactions. No real
 * money, no card data, no external service – everything is simulated and
 * persisted with an explicit MOCK marker.
 *
 * Swapping in a real gateway later means replacing this class with an adapter
 * that talks to the provider API; the task layer only depends on the
 * create()/finalize()/find()/all() contract.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Mock_Payment
 */
class TaskFlow_Mock_Payment {

	const STATUS_SUCCESS = 'success';
	const STATUS_FAILED  = 'failed';
	const STATUS_PENDING = 'pending';

	/**
	 * Create a simulated transaction.
	 *
	 * @param float  $amount   Positive amount.
	 * @param string $currency Currency code.
	 * @param string $simulate Requested outcome: success|failed|pending.
	 * @param string $order_ref Optional client order reference.
	 * @return array<string,mixed> Stored transaction row.
	 */
	public function create( $amount, $currency, $simulate = self::STATUS_SUCCESS, $order_ref = '' ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$gateway_ref = 'TFMOCK-' . strtoupper( wp_generate_password( 10, false, false ) );
		$order_ref   = '' === trim( (string) $order_ref )
			? 'ORD-' . strtoupper( wp_generate_password( 8, false, false ) )
			: sanitize_text_field( (string) $order_ref );

		$messages = array(
			self::STATUS_SUCCESS => __( 'Simulated payment completed successfully (MOCK).', 'taskflow' ),
			self::STATUS_FAILED  => __( 'Simulated payment was declined by the mock gateway (MOCK).', 'taskflow' ),
			self::STATUS_PENDING => __( 'Simulated payment is awaiting finalization (MOCK).', 'taskflow' ),
		);

		$simulate = in_array( $simulate, array( self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_PENDING ), true )
			? $simulate
			: self::STATUS_PENDING;

		$wpdb->insert( // phpcs:ignore WordPress.DB
			TaskFlow_Schema::table( 'payments' ),
			array(
				'order_ref'   => $order_ref,
				'amount'      => round( (float) $amount, 2 ),
				'currency'    => sanitize_text_field( $currency ),
				'status'      => $simulate,
				'gateway_ref' => $gateway_ref,
				'message'     => $messages[ $simulate ],
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);

		return (array) $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Finalize a pending transaction.
	 *
	 * @param int    $id     Transaction id.
	 * @param string $result success|failed.
	 * @return array<string,mixed>|null Updated row.
	 */
	public function finalize( $id, $result ) {
		global $wpdb;

		$row = $this->find( $id );

		if ( ! $row ) {
			return null;
		}

		if ( self::STATUS_PENDING !== $row['status'] ) {
			return $row;
		}

		$result  = self::STATUS_FAILED === $result ? self::STATUS_FAILED : self::STATUS_SUCCESS;
		$message = self::STATUS_SUCCESS === $result
			? __( 'Simulated payment completed successfully (MOCK).', 'taskflow' )
			: __( 'Simulated payment was declined by the mock gateway (MOCK).', 'taskflow' );

		$wpdb->update( // phpcs:ignore WordPress.DB
			TaskFlow_Schema::table( 'payments' ),
			array(
				'status'     => $result,
				'message'    => $message,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);

		return (array) $this->find( $id );
	}

	/**
	 * Fetch one transaction.
	 *
	 * @param int $id Transaction id.
	 * @return array<string,mixed>|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = TaskFlow_Schema::table( 'payments' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * List transactions, newest first, optional status filter and search on
	 * the order reference.
	 *
	 * @param array $args page, per_page, status, search.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public function all( $args = array() ) {
		global $wpdb;

		$table  = TaskFlow_Schema::table( 'payments' );
		$where  = ' WHERE 1=1';
		$params = array();

		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_PENDING ), true ) ) {
			$where   .= ' AND status = %s';
			$params[] = (string) $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where   .= ' AND ( order_ref LIKE %s OR gateway_ref LIKE %s )';
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
	 * Aggregate totals per status (for the payments screen header).
	 *
	 * @return array<string,float>
	 */
	public function totals() {
		global $wpdb;

		$table = TaskFlow_Schema::table( 'payments' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT status, SUM( amount ) AS total FROM {$table} GROUP BY status", ARRAY_A );

		$out = array(
			self::STATUS_SUCCESS => 0.0,
			self::STATUS_FAILED  => 0.0,
			self::STATUS_PENDING => 0.0,
		);

		foreach ( (array) $rows as $row ) {
			$out[ $row['status'] ] = round( (float) $row['total'], 2 );
		}

		return $out;
	}
}
