<?php
/**
 * Dashboard statistics – every number is computed live from the database,
 * restricted to what the current user is allowed to see.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Dashboard
 */
class TaskFlow_Dashboard {

        /**
         * Full dashboard payload.
         *
         * @return array<string,mixed>
         */
        public function stats() {
                global $wpdb;

                $tasks = TaskFlow_Schema::table( 'tasks' );
                $now   = current_time( 'mysql' );
                $where = TaskFlow_Capabilities::visibility_sql();

                $counts = ( new TaskFlow_Task_Repository() )->count_by_status();

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + visibility fragment.
                $totals = $wpdb->get_row(
                        "SELECT
                                COUNT( * ) AS total,
                                SUM( CASE WHEN t.due_date IS NOT NULL AND t.due_date < '{$now}' AND t.status != 'completed' THEN 1 ELSE 0 END ) AS overdue,
                                SUM( CASE WHEN t.due_date IS NOT NULL AND t.due_date >= '{$now}' AND DATE( t.due_date ) = DATE( '{$now}' ) THEN 1 ELSE 0 END ) AS due_today,
                                SUM( CASE WHEN t.assignee_id IS NULL THEN 1 ELSE 0 END ) AS unassigned,
                                SUM( CASE WHEN t.time_spent > 0 THEN t.time_spent ELSE 0 END ) AS time_spent
                        FROM {$tasks} t
                        WHERE 1=1{$where}",
                        ARRAY_A
                );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + visibility fragment.
                $by_priority = $wpdb->get_results(
                        "SELECT t.priority, COUNT( * ) AS total
                        FROM {$tasks} t
                        WHERE 1=1{$where}
                        GROUP BY t.priority",
                        ARRAY_A
                );

                $priority_map = array_fill_keys( TaskFlow_Validation::priorities(), 0 );
                foreach ( (array) $by_priority as $row ) {
                        $priority_map[ $row['priority'] ] = (int) $row['total'];
                }

                $project_stats = ( new TaskFlow_Project_Repository() )->stats();

                $activity_rows = ( new TaskFlow_Activity() )->query( array( 'per_page' => 8 ) );
                $activity_rows = ( new TaskFlow_Activity() )->hydrate_meta( $activity_rows['items'] );

                $total     = (int) ( $totals['total'] ?? 0 );
                $completed = (int) $counts['completed'];

                return array(
                        'counts'          => array(
                                'total'      => $total,
                                'new'        => (int) $counts['new'],
                                'in_progress' => (int) $counts['in_progress'],
                                'completed'  => $completed,
                                'open'       => (int) $counts['new'] + (int) $counts['in_progress'],
                                'overdue'    => (int) ( $totals['overdue'] ?? 0 ),
                                'due_today'  => (int) ( $totals['due_today'] ?? 0 ),
                                'unassigned' => (int) ( $totals['unassigned'] ?? 0 ),
                                'my_open'    => $this->my_open_tasks(),
                        ),
                        'completion_rate' => $total > 0 ? round( 100 * $completed / $total, 1 ) : 0,
                        'time_spent'      => (int) ( $totals['time_spent'] ?? 0 ),
                        'by_priority'     => $priority_map,
                        'by_project'      => $this->format_project_stats( $project_stats ),
                        'recent_activity' => $this->format_activity( $activity_rows ),
                );
        }

        /**
         * Open tasks where the current user is the assignee.
         *
         * @return int
         */
        private function my_open_tasks() {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tasks' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table name.
                return (int) $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT COUNT( * ) FROM {$table} WHERE assignee_id = %d AND status != 'completed'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                get_current_user_id()
                        )
                );
        }

        /**
         * Normalize project stats rows.
         *
         * @param array<int,array<string,mixed>> $rows Raw rows.
         * @return array<int,array<string,mixed>>
         */
        private function format_project_stats( $rows ) {
                $out = array();

                foreach ( (array) $rows as $row ) {
                        $out[] = array(
                                'id'        => (int) $row['id'],
                                'name'      => $row['name'],
                                'color'     => $row['color'],
                                'total'     => (int) $row['total'],
                                'completed' => (int) $row['completed'],
                        );
                }

                return $out;
        }

        /**
         * Normalize activity rows for the dashboard feed.
         *
         * @param array<int,array<string,mixed>> $rows Raw rows.
         * @return array<int,array<string,mixed>>
         */
        private function format_activity( $rows ) {
                $out = array();

                foreach ( (array) $rows as $row ) {
                        $out[] = array(
                                'id'         => (int) $row['id'],
                                'task_id'    => $row['task_id'] ? (int) $row['task_id'] : null,
                                'type'       => $row['type'],
                                'message'    => $row['message'],
                                'meta'       => is_array( $row['meta'] ) ? $row['meta'] : array(),
                                'user_id'    => (int) $row['user_id'],
                                'user'       => $this->user_brief( (int) $row['user_id'] ),
                                'created_at' => $row['created_at'],
                        );
                }

                return $out;
        }

        /**
         * Minimal user payload (id, name, avatar).
         *
         * @param int $user_id User id.
         * @return array<string,mixed>|null
         */
        private function user_brief( $user_id ) {
                $user = get_userdata( $user_id );

                if ( ! $user ) {
                        return null;
                }

                return array(
                        'id'     => (int) $user->ID,
                        'name'   => $user->display_name,
                        'avatar' => get_avatar_url( $user->ID, array( 'size' => 48 ) ),
                );
        }
}
