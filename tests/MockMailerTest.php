<?php
/**
 * Tests for the MOCK mailer (outbox/inbox) and the notifications service.
 *
 * @package TaskFlow
 */

/**
 * Class MockMailerTest
 */
class MockMailerTest extends TaskFlow_TestCase {

	public function test_send_stores_mock_outbox_row(): void {
		$mailer = new TaskFlow_Mock_Mailer();
		$sent   = $mailer->send( 'someone@example.com', '[MOCK] Hello', 'Body text' );

		$this->assertSame( 'someone@example.com', $sent['to_email'] );
		$this->assertSame( 'sent', $sent['status'] );
		$this->assertNotNull( $sent['sent_at'] );
		$this->assertNull( $sent['error'] );
		$this->assertStringContainsString( 'MOCK', $sent['subject'] );

		$outbox = $mailer->outbox();

		$this->assertSame( 1, $outbox['total'] );
	}

	public function test_simulate_incoming_fills_inbox(): void {
		$mailer = new TaskFlow_Mock_Mailer();

		$received = $mailer->simulate_incoming( 'client@example.com', 'Client', '[MOCK] Question', 'How do I…?' );

		$this->assertSame( 'client@example.com', $received['from_email'] );
		$this->assertSame( 'Client', $received['from_name'] );
		$this->assertSame( 0, (int) $received['is_read'] );

		$inbox = $mailer->inbox();

		$this->assertSame( 1, $inbox['total'] );

		$mailer->mark_inbox_read( $received['id'] );

		$this->assertSame( 1, (int) $mailer->find_inbox( $received['id'] )['is_read'] );
	}

	public function test_build_task_body_marks_mock(): void {
		$body = TaskFlow_Mock_Mailer::build_task_body(
			'Test subject',
			array( 'Priority' => 'high' )
		);

		$this->assertStringContainsString( '[MOCK / DEMO]', $body );
		$this->assertStringContainsString( 'Test subject', $body );
		$this->assertStringContainsString( 'Priority: high', $body );
	}

	public function test_notifications_mark_read(): void {
		$notifications = new TaskFlow_Notifications();

		$notifications->add( 3, 'assigned', 'First note', 1 );
		$notifications->add( 3, 'comment', 'Second note', 1 );

		$this->assertSame( 2, $notifications->unread_count( 3 ) );

		$list = $notifications->for_user( 3 );

		$this->assertSame( 2, $list['total'] );
		$this->assertSame( 2, $list['unread'] );

		$first = $list['items'][1]['id']; // Oldest (id ASC within newest-first list).

		$notifications->mark_read( 3, array( $first ) );

		$this->assertSame( 1, $notifications->unread_count( 3 ) );

		$notifications->mark_read( 3, null );

		$this->assertSame( 0, $notifications->unread_count( 3 ) );
	}

	public function test_notifications_are_per_user(): void {
		$notifications = new TaskFlow_Notifications();

		$notifications->add( 3, 'assigned', 'Only for Andy' );

		$this->assertSame( 1, $notifications->unread_count( 3 ) );
		$this->assertSame( 0, $notifications->unread_count( 2 ) );
		$this->assertSame( 0, $notifications->unread_count( 1 ) );
	}
}
