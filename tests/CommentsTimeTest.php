<?php
/**
 * Tests for comments with @mentions and the time tracker.
 *
 * @package TaskFlow
 */

/**
 * Class CommentsTimeTest
 */
class CommentsTimeTest extends TaskFlow_TestCase {

        public function test_comment_stored_and_counted(): void {
                $task = $this->make_task( array( 'title' => 'Commented task' ) );

                $comments = new TaskFlow_Comments();
                $comment  = $comments->add( $task['id'], 'First <b>comment</b> from @editor' );

                $this->assertNotWPError( $comment );
                $this->assertSame( 'First comment from @editor', $comment['content'], 'tags stripped' );
                $this->assertSame( array( 2 ), $comment['mentions'], 'editor resolved from @mention' );

                $list = $comments->for_task( $task['id'] );

                $this->assertCount( 1, $list );
                $this->assertSame( 1, (int) ( new TaskFlow_Task_Repository() )->find( $task['id'] )['comments_count'] );
        }

        public function test_mentioned_user_gets_notification_and_mock_email(): void {
                $task = $this->make_task( array( 'title' => 'Mention task', 'assignee_id' => 3 ) );

                // Clear notifications from the assignment itself.
                ( new TaskFlow_Notifications() )->mark_read( 2, null );
                ( new TaskFlow_Notifications() )->mark_read( 3, null );

                wp_set_current_user( 1 ); // Admin comments and mentions @editor + @author.

                ( new TaskFlow_Comments() )->add( $task['id'], 'Ping @editor and @author' );

                $editor_notes = ( new TaskFlow_Notifications() )->for_user( 2 );
                $author_notes = ( new TaskFlow_Notifications() )->for_user( 3 );

                $this->assertSame( 1, $editor_notes['unread'], 'mentioned editor notified' );
                $this->assertSame( 1, $author_notes['unread'], 'mentioned assignee/author notified' );
                $this->assertSame( 0, ( new TaskFlow_Notifications() )->for_user( 1 )['unread'], 'actor is never self-notified' );

                $outbox = ( new TaskFlow_Mock_Mailer() )->outbox();

                $this->assertSame( 3, $outbox['total'], 'assignment + 2 mention mock mails' );
                $this->assertStringContainsString( 'mentioned', $outbox['items'][0]['subject'] );
        }

        public function test_empty_comment_rejected(): void {
                $task = $this->make_task();

                $this->assertWPError( ( new TaskFlow_Comments() )->add( $task['id'], '   ' ) );
        }

        public function test_timer_start_stop_accumulates(): void {
                $task    = $this->make_task( array( 'title' => 'Timed task' ) );
                $tracker = new TaskFlow_Time_Tracker();

                $running = $tracker->start( $task['id'], 3 );

                $this->assertNull( $running['ended_at'], 'timer is running' );

                $active = $tracker->active( 3 );

                $this->assertNotNull( $active );
                $this->assertSame( (int) $task['id'], (int) $active['task_id'] );

                // Simulate 90 seconds of work.
                global $wpdb;
                $wpdb->update(
                        TaskFlow_Schema::table( 'time_logs' ),
                        array(
                                'started_at' => date( 'Y-m-d H:i:s', time() - 90 ),
                        ),
                        array( 'id' => $active['id'] )
                );

                $finished = $tracker->stop( 3 );

                $this->assertGreaterThanOrEqual( 89, (int) $finished['duration'] );
                $this->assertLessThanOrEqual( 95, (int) $finished['duration'] );

                $this->assertNull( $tracker->active( 3 ) );
                $this->assertGreaterThanOrEqual( 89, (int) ( new TaskFlow_Task_Repository() )->find( $task['id'] )['time_spent'] );
        }

        public function test_starting_new_timer_stops_previous(): void {
                $task_a  = $this->make_task( array( 'title' => 'A' ) );
                $task_b  = $this->make_task( array( 'title' => 'B' ) );
                $tracker = new TaskFlow_Time_Tracker();

                $tracker->start( $task_a['id'], 3 );
                $tracker->start( $task_b['id'], 3 );

                $active = $tracker->active( 3 );

                $this->assertSame( (int) $task_b['id'], (int) $active['task_id'], 'only one running timer' );

                $logs = $tracker->for_task( $task_a['id'] );

                $this->assertCount( 1, $logs );
                $this->assertNotNull( $logs[0]['ended_at'], 'previous timer closed' );
        }

        public function test_time_logged_activity_written(): void {
                $task    = $this->make_task( array( 'title' => 'Logged' ) );
                $tracker = new TaskFlow_Time_Tracker();

                $active = $tracker->start( $task['id'], 3 );

                global $wpdb;
                $wpdb->update(
                        TaskFlow_Schema::table( 'time_logs' ),
                        array( 'started_at' => date( 'Y-m-d H:i:s', time() - 30 ) ),
                        array( 'id' => $active['id'] )
                );

                $tracker->stop( 3 );

                $activity = ( new TaskFlow_Activity() )->query( array( 'task_id' => $task['id'] ) );
                $types    = wp_list_pluck( $activity['items'], 'type' );

                $this->assertContains( 'time_logged', $types );
        }
}
