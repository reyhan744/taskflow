<?php
/**
 * Activity history service.
 *
 * Records every meaningful event (created, updated, status change,
 * assignment, comment, time logged, deletion) together with the acting user
 * and a human readable, already-translated message.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Activity
 */
class TaskFlow_Activity {

        /**
         * Record an activity entry.
         *
         * @param int|null                  $task_id Task id (null for task deletion events).
         * @param string                    $type    Event type.
         * @param array<string,mixed>       $meta    Structured details.
         * @param string|null               $message Prebuilt localized message.
         * @param int|null                  $user_id Acting user (defaults to current).
         * @return int Inserted id.
         */
        public function log( $task_id, $type, $meta = array(), $message = null, $user_id = null ) {
                global $wpdb;

                $user_id = null === $user_id ? (int) get_current_user_id() : (int) $user_id;

                $wpdb->insert( // phpcs:ignore WordPress.DB
                        TaskFlow_Schema::table( 'activity' ),
                        array(
                                'task_id'    => $task_id ? (int) $task_id : null,
                                'user_id'    => $user_id,
                                'type'       => sanitize_key( $type ),
                                'meta'       => wp_json_encode( $meta ),
                                'message'    => $message,
                                'created_at' => current_time( 'mysql' ),
                        )
                );

                return (int) $wpdb->insert_id;
        }

        /**
         * Recent activity entries, newest first.
         *
         * @param array<string,mixed> $args task_id, user_id, page, per_page,
         *                                  restrict_user_id (only events of that user
         *                                  or of tasks assigned/created by them).
         * @return array{items:array<int,array<string,mixed>>,total:int}
         */
        public function query( $args = array() ) {
                global $wpdb;

                $table  = TaskFlow_Schema::table( 'activity' );
                $where  = ' WHERE 1=1';
                $params = array();

                if ( ! empty( $args['task_id'] ) ) {
                        $where   .= ' AND task_id = %d';
                        $params[] = absint( $args['task_id'] );
                }

                if ( ! empty( $args['user_id'] ) ) {
                        $where   .= ' AND user_id = %d';
                        $params[] = absint( $args['user_id'] );
                }

                if ( ! empty( $args['restrict_user_id'] ) ) {
                        $tasks     = TaskFlow_Schema::table( 'tasks' );
                        $restrict  = (int) $args['restrict_user_id'];
                        $where    .= " AND ( user_id = %d OR ( task_id IS NOT NULL AND task_id IN (
                                SELECT id FROM {$tasks} WHERE created_by = %d OR assignee_id = %d
                        ) ) )";
                        array_push( $params, $restrict, $restrict, $restrict );
                }

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + prepared fragments.
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
         * Decode the meta JSON of activity rows in place.
         *
         * @param array<int,array<string,mixed>> $rows Raw rows.
         * @return array<int,array<string,mixed>>
         */
        public function hydrate_meta( $rows ) {
                foreach ( $rows as &$row ) {
                        $decoded      = json_decode( (string) ( $row['meta'] ?? '' ), true );
                        $row['meta']  = is_array( $decoded ) ? $decoded : array();
                }

                return $rows;
        }
}
