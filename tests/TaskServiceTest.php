<?php
/**
 * Tests for the task service layer: activity logging, notifications and the
 * mock e-mail side effects.
 *
 * @package TaskFlow
 */

/**
 * Class TaskServiceTest
 */
class TaskServiceTest extends TaskFlow_TestCase {

	public function test_create_logs_activity(): void {
		$task = $this->make_task( array( 'title' => 'Logged task' ) );

		$activity = ( new TaskFlow_Activity() )->query( array( 'task_id' => $task['id'] ) );

		$this->assertSame( 1, $activity['total'] );
		$this->assertSame( 'created', $activity['items'][0]['type'] );
		$this->assertSame( 1, (int) $activity['items'][0]['user_id'] );
		$this->assertStringContainsString( 'Logged task', (string) $activity['items'][0]['message'] );
	}

	public function test_update_writes_field_level_activity(): void {
		$service = new TaskFlow_Task_Service();
		$task    = $this->make_task( array( 'title' => 'Old title', 'priority' => 'low' ) );

		$service->update_task(
			$task['id'],
			array(
				'title'      => 'New title',
				'priority'   => 'urgent',
				'assignee_id' => 3,
			)
		);

		$activity = ( new TaskFlow_Activity() )->query( array( 'task_id' => $task['id'], 'per_page' => 20 ) );
		$types    = wp_list_pluck( $activity['items'], 'type' );

		$this->assertContains( 'title_changed', $types );
		$this->assertContains( 'priority_changed', $types );
		$this->assertContains( 'assigned', $types );
	}

	public function test_assignment_creates_notification_and_mock_email(): void {
		$task = $this->make_task( array( 'title' => 'For Andy', 'assignee_id' => 3 ) );

		$notifications = ( new TaskFlow_Notifications() )->for_user( 3 );

		$this->assertSame( 1, $notifications['unread'], 'assignee got an in-app notification' );
		$this->assertStringContainsString( 'For Andy', $notifications['items'][0]['message'] );

		$outbox = ( new TaskFlow_Mock_Mailer() )->outbox();

		$this->assertSame( 1, $outbox['total'], 'assignee got a mock e-mail' );
		$this->assertSame( 'author@example.com', $outbox['items'][0]['to_email'] );
		$this->assertStringContainsString( 'MOCK', $outbox['items'][0]['subject'] );
		$this->assertSame( 'sent', $outbox['items'][0]['status'] );
		$this->assertNotNull( $outbox['items'][0]['sent_at'] );
	}

	public function test_self_assignment_skips_notification(): void {
		wp_set_current_user( 3 );

		$this->make_task( array( 'title' => 'For myself', 'assignee_id' => 3 ) );

		$this->assertSame( 0, ( new TaskFlow_Notifications() )->for_user( 3 )['unread'] );
		$this->assertSame( 0, ( new TaskFlow_Mock_Mailer() )->outbox()['total'] );
	}

	public function test_status_move_logs_and_notifies(): void {
		$service = new TaskFlow_Task_Service();
		$task    = $this->make_task( array( 'title' => 'Flow', 'created_by' => 1, 'assignee_id' => 3 ) );

		( new TaskFlow_Notifications() )->mark_read( 3, null );

		$service->move_task( $task['id'], 'in_progress' );

		$activity = ( new TaskFlow_Activity() )->query( array( 'task_id' => $task['id'] ) );
		$this->assertSame( 'status_changed', $activity['items'][0]['type'] );

		$notifications = ( new TaskFlow_Notifications() )->for_user( 3 );
		$this->assertSame( 1, $notifications['unread'], 'assignee notified about status change' );
	}

	public function test_delete_logs_event_without_task_link(): void {
		$service = new TaskFlow_Task_Service();
		$task    = $this->make_task( array( 'title' => 'Ghost' ) );

		$service->delete_task( $task['id'] );

		$activity = ( new TaskFlow_Activity() )->query( array() );

		$this->assertSame( 'deleted', $activity['items'][0]['type'] );
		$this->assertNull( $activity['items'][0]['task_id'] );
		$this->assertStringContainsString( 'Ghost', (string) $activity['items'][0]['message'] );
	}
}
