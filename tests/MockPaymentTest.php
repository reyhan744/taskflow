<?php
/**
 * Tests for the MOCK payment gateway adapter.
 *
 * @package TaskFlow
 */

/**
 * Class MockPaymentTest
 */
class MockPaymentTest extends TaskFlow_TestCase {

	public function test_create_success_transaction(): void {
		$gateway = new TaskFlow_Mock_Payment();
		$payment = $gateway->create( 120.5, 'USD', 'success' );

		$this->assertSame( 'success', $payment['status'] );
		$this->assertSame( 120.5, (float) $payment['amount'] );
		$this->assertStringStartsWith( 'TFMOCK-', $payment['gateway_ref'] );
		$this->assertStringContainsString( 'MOCK', (string) $payment['message'] );
		$this->assertArrayHasKey( 'created_at', $payment );
	}

	public function test_create_failed_and_pending(): void {
		$gateway = new TaskFlow_Mock_Payment();

		$failed = $gateway->create( 10, 'USD', 'failed' );
		$pending = $gateway->create( 15, 'EUR', 'pending' );

		$this->assertSame( 'failed', $failed['status'] );
		$this->assertSame( 'pending', $pending['status'] );
		$this->assertSame( 'EUR', $pending['currency'] );
	}

	public function test_finalize_pending_only(): void {
		$gateway = new TaskFlow_Mock_Payment();
		$pending = $gateway->create( 42, 'USD', 'pending' );

		$done = $gateway->finalize( $pending['id'], 'success' );

		$this->assertSame( 'success', $done['status'] );

		// Finalizing an already settled transaction is a no-op.
		$again = $gateway->finalize( $pending['id'], 'failed' );

		$this->assertSame( 'success', $again['status'] );
	}

	public function test_totals_per_status(): void {
		$gateway = new TaskFlow_Mock_Payment();

		$gateway->create( 100, 'USD', 'success' );
		$gateway->create( 50, 'USD', 'success' );
		$gateway->create( 30, 'USD', 'failed' );
		$gateway->create( 20, 'USD', 'pending' );

		$totals = $gateway->totals();

		$this->assertSame( 150.0, (float) $totals['success'] );
		$this->assertSame( 30.0, (float) $totals['failed'] );
		$this->assertSame( 20.0, (float) $totals['pending'] );
	}

	public function test_list_with_filters(): void {
		$gateway = new TaskFlow_Mock_Payment();

		$one = $gateway->create( 10, 'USD', 'success', 'ORD-ALPHA' );
		$gateway->create( 20, 'USD', 'failed', 'ORD-BETA' );

		$by_status = $gateway->all( array( 'status' => 'success' ) );
		$by_search = $gateway->all( array( 'search' => 'ALPHA' ) );

		$this->assertSame( 1, $by_status['total'] );
		$this->assertSame( 1, $by_search['total'] );
		$this->assertSame( $one['order_ref'], $by_search['items'][0]['order_ref'] );
	}

	public function test_find_unknown_returns_null(): void {
		$this->assertNull( ( new TaskFlow_Mock_Payment() )->find( 9999 ) );
	}
}
