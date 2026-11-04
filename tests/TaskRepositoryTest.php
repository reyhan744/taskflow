<?php
/**
 * Tests for the task repository: CRUD, search, filters, ordering, tags,
 * Kanban sequencing and visibility scoping.
 *
 * @package TaskFlow
 */

/**
 * Class TaskRepositoryTest
 */
class TaskRepositoryTest extends TaskFlow_TestCase {

        public function test_insert_and_find_task(): void {
                $repository = new TaskFlow_Task_Repository();
                $id         = $repository->insert(
                        array(
                                'title'      => 'Write docs',
                                'status'     => 'new',
                                'priority'   => 'high',
                                'created_by' => 1,
                                'created_at' => current_time( 'mysql' ),
                                'updated_at' => current_time( 'mysql' ),
                        )
                );

                $task = $repository->find( $id );

                $this->assertNotNull( $task );
                $this->assertSame( 'Write docs', $task['title'] );
                $this->assertSame( 'high', $task['priority'] );
                $this->assertSame( 'new', $task['status'] );
        }

        public function test_update_task_persists(): void {
                $repository = new TaskFlow_Task_Repository();
                $task       = $this->make_task( array( 'title' => 'Before' ) );

                $repository->update( $task['id'], array( 'title' => 'After', 'updated_at' => current_time( 'mysql' ) ) );

                $this->assertSame( 'After', $repository->find( $task['id'] )['title'] );
        }

        public function test_delete_removes_task_and_tags(): void {
                $repository = new TaskFlow_Task_Repository();
                $tags       = new TaskFlow_Tag_Repository();
                $tag_id     = $tags->first_or_create( 'docs' );
                $task       = $this->make_task( array( 'title' => 'Doomed' ), array( $tag_id ) );

                $this->assertSame( array( $tag_id ), $repository->tag_ids( $task['id'] ) );

                $repository->delete( $task['id'] );

                $this->assertNull( $repository->find( $task['id'] ) );
                $this->assertSame( array(), $repository->tag_ids( $task['id'] ) );
        }

        public function test_search_matches_title(): void {
                $this->make_task( array( 'title' => 'Design the landing page' ) );
                $this->make_task( array( 'title' => 'Fix the mobile menu' ) );

                $result = ( new TaskFlow_Task_Repository() )->query( array( 'search' => 'landing' ) );

                $this->assertSame( 1, $result['total'] );
                $this->assertSame( 'Design the landing page', $result['items'][0]['title'] );
        }

        public function test_filters_by_status_and_priority(): void {
                $this->make_task( array( 'title' => 'A', 'status' => 'new', 'priority' => 'low' ) );
                $this->make_task( array( 'title' => 'B', 'status' => 'in_progress', 'priority' => 'urgent' ) );
                $this->make_task( array( 'title' => 'C', 'status' => 'completed', 'priority' => 'medium' ) );

                $repository = new TaskFlow_Task_Repository();

                $open = $repository->query( array( 'status' => array( 'new', 'in_progress' ) ) );
                $this->assertSame( 2, $open['total'] );

                $urgent = $repository->query( array( 'priority' => array( 'urgent' ) ) );
                $this->assertSame( 1, $urgent['total'] );
                $this->assertSame( 'B', $urgent['items'][0]['title'] );
        }

        public function test_filters_by_assignee_and_project(): void {
                $projects = new TaskFlow_Project_Repository();
                $pid      = $projects->insert(
                        array(
                                'name'        => 'Website',
                                'created_by'  => 1,
                                'created_at'  => current_time( 'mysql' ),
                                'updated_at'  => current_time( 'mysql' ),
                        )
                );

                $this->make_task( array( 'title' => 'Mine', 'assignee_id' => 3 ) );
                $this->make_task( array( 'title' => 'Theirs', 'assignee_id' => 2, 'project_id' => $pid ) );

                $repository = new TaskFlow_Task_Repository();

                $by_user  = $repository->query( array( 'assignee_id' => 3 ) );
                $by_proj  = $repository->query( array( 'project_id' => $pid ) );
                $no_proj  = $repository->query( array( 'project_id' => 0 ) );

                $this->assertSame( 1, $by_user['total'] );
                $this->assertSame( 'Mine', $by_user['items'][0]['title'] );
                $this->assertSame( 1, $by_proj['total'] );
                $this->assertSame( 'Theirs', $by_proj['items'][0]['title'] );
                $this->assertSame( 1, $no_proj['total'] );
                $this->assertSame( 'Mine', $no_proj['items'][0]['title'] );
        }

        public function test_orderby_priority_whitelist(): void {
                $this->make_task( array( 'title' => 'Low one', 'priority' => 'low' ) );
                $this->make_task( array( 'title' => 'Urgent one', 'priority' => 'urgent' ) );
                $this->make_task( array( 'title' => 'High one', 'priority' => 'high' ) );

                $result = ( new TaskFlow_Task_Repository() )->query(
                        array(
                                'orderby' => 'priority',
                                'order'   => 'ASC',
                        )
                );

                $this->assertSame( 'Urgent one', $result['items'][0]['title'] );
                $this->assertSame( 'Low one', $result['items'][2]['title'] );
        }

        public function test_orderby_rejects_unknown_column(): void {
                $this->make_task( array( 'title' => 'Single' ) );

                $result = ( new TaskFlow_Task_Repository() )->query(
                        array(
                                'orderby' => 'title; DROP TABLE wp_taskflow_tasks; --',
                                'order'   => 'ASC',
                        )
                );

                $this->assertSame( 1, $result['total'] );
                // Table still exists, so the whitelisting worked.
                $this->assertSame( 1, ( new TaskFlow_Task_Repository() )->query( array() )['total'] );
        }

        public function test_overdue_filter(): void {
                $this->make_task(
                        array(
                                'title'    => 'Late',
                                'due_date' => date( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
                        )
                );
                $this->make_task(
                        array(
                                'title'    => 'Upcoming',
                                'due_date' => date( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
                        )
                );
                $this->make_task(
                        array(
                                'title'    => 'Late but done',
                                'status'   => 'completed',
                                'due_date' => date( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
                        )
                );

                $result = ( new TaskFlow_Task_Repository() )->query( array( 'overdue' => 1 ) );

                $this->assertSame( 1, $result['total'] );
                $this->assertSame( 'Late', $result['items'][0]['title'] );
        }

        public function test_kanban_status_change_resequences(): void {
                $repository = new TaskFlow_Task_Repository();
                $a          = $this->make_task( array( 'title' => 'A' ) );
                $b          = $this->make_task( array( 'title' => 'B' ) );
                $c          = $this->make_task( array( 'title' => 'C' ) );

                $repository->change_status( $b['id'], 'in_progress', 0 );
                $repository->change_status( $c['id'], 'in_progress', 1 );

                $moved = $repository->query( array( 'status' => array( 'in_progress' ), 'orderby' => 'position', 'order' => 'ASC' ) );

                $this->assertSame( 2, $moved['total'] );
                $this->assertSame( 'B', $moved['items'][0]['title'] );
                $this->assertSame( 'C', $moved['items'][1]['title'] );
                $this->assertSame( 0, (int) $moved['items'][0]['position'] );
                $this->assertSame( 1, (int) $moved['items'][1]['position'] );

                // Completing stamps completed_at; reopening clears it.
                $repository->change_status( $a['id'], 'completed' );
                $this->assertNotNull( $repository->find( $a['id'] )['completed_at'] );

                $repository->change_status( $a['id'], 'new' );
                $this->assertNull( $repository->find( $a['id'] )['completed_at'] );
        }

        public function test_visibility_scopes_queries_for_plain_users(): void {
                wp_set_current_user( 3 ); // Author: sees only own/assigned.

                $this->make_task( array( 'title' => 'Authored by me', 'created_by' => 3 ) );
                $this->make_task( array( 'title' => 'Assigned to me', 'created_by' => 1, 'assignee_id' => 3 ) );
                $this->make_task( array( 'title' => 'Hidden', 'created_by' => 1, 'assignee_id' => 2 ) );

                $result = ( new TaskFlow_Task_Repository() )->query( array() );

                $this->assertSame( 2, $result['total'] );
        }

        public function test_tag_sync_replaces_set(): void {
                $tags       = new TaskFlow_Tag_Repository();
                $repository = new TaskFlow_Task_Repository();
                $one        = $tags->first_or_create( 'one' );
                $two        = $tags->first_or_create( 'two' );
                $three      = $tags->first_or_create( 'three' );

                $task = $this->make_task( array( 'title' => 'Tagged' ), array( $one, $two ) );

                $this->assertEqualsCanonicalizing( array( $one, $two ), $repository->tag_ids( $task['id'] ) );

                $repository->update( $task['id'], array(), array( $three ) );

                $this->assertSame( array( $three ), $repository->tag_ids( $task['id'] ) );
        }

        public function test_first_or_create_is_idempotent(): void {
                $tags = new TaskFlow_Tag_Repository();

                $first  = $tags->first_or_create( 'design' );
                $second = $tags->first_or_create( 'design' );

                $this->assertSame( $first, $second, 'same slug returns the same tag' );
                $this->assertCount( 1, $tags->all(), 'no duplicate tag rows' );

                $other = $tags->first_or_create( 'frontend' );

                $this->assertNotSame( $first, $other );
                $this->assertCount( 2, $tags->all() );
        }
}
