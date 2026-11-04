<?php
/**
 * Class DashboardTest
 */
class DashboardTest extends TaskFlow_TestCase {

        public function test_counts_live_data(): void {
                $this->make_task( array( 'title' => 'A', 'status' => 'new' ) );
                $this->make_task(
                        array(
                                'title'    => 'B',
                                'status'   => 'in_progress',
                                'priority' => 'urgent',
                        )
                );
                $this->make_task( array( 'title' => 'C', 'status' => 'completed' ) );
                $this->make_task(
                        array(
                                'title'    => 'Late',
                                'status'   => 'new',
                                'due_date' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
                        )
                );

                $stats = ( new TaskFlow_Dashboard() )->stats();
                $counts = $stats['counts'];

                $this->assertSame( 4, $counts['total'] );
                $this->assertSame( 3, $counts['open'] );
                $this->assertSame( 1, $counts['completed'] );
                $this->assertSame( 1, $counts['overdue'] );
                $this->assertSame( 25.0, $stats['completion_rate'] );
                $this->assertSame( 1, $stats['by_priority']['urgent'] );
        }

        public function test_dashboard_respects_visibility(): void {
                wp_set_current_user( 3 );

                $this->make_task( array( 'title' => 'Mine', 'created_by' => 3 ) );
                $this->make_task( array( 'title' => 'Hidden', 'created_by' => 1, 'assignee_id' => 2 ) );

                $stats = ( new TaskFlow_Dashboard() )->stats();

                $this->assertSame( 1, $stats['counts']['total'], 'author only sees own task' );
        }
}
