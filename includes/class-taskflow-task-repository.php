<?php
/**
 * Task repository – the only place that talks SQL about tasks.
 *
 * Every statement is built with $wpdb->prepare() and whitelisted ORDER BY
 * columns. Visibility (role + ownership) is enforced inside the queries via
 * TaskFlow_Capabilities::visibility_sql(), not only in the controllers.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Task_Repository
 */
class TaskFlow_Task_Repository {

        /**
         * Fetch one task row by id (undecorated).
         *
         * @param int $id Task id.
         * @return array<string,mixed>|null
         */
        public function find( $id ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tasks' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a static schema constant.
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT t.* FROM {$table} t WHERE t.id = %d", $id ), ARRAY_A );

                return $row ? $row : null;
        }

        /**
         * Query tasks with filters, search, ordering and pagination.
         *
         * Supported filters:
         *  - search      : partial match on title.
         *  - status      : array of statuses (OR).
         *  - priority    : array of priorities (OR).
         *  - assignee_id : int, or the literal "me".
         *  - created_by  : int, or the literal "me".
         *  - project_id  : int, or 0/-1 meaning "no project".
         *  - tag_id      : int (JOIN through the pivot table).
         *  - overdue     : bool – past due and not completed.
         *  - due_from / due_to : date bounds.
         *
         * @param array<string,mixed> $args Filter arguments.
         * @return array{items:array<int,array<string,mixed>>,total:int}
         */
        public function query( $args = array() ) {
                global $wpdb;

                $table    = TaskFlow_Schema::table( 'tasks' );
                $pivot    = TaskFlow_Schema::table( 'task_tags' );
                $where    = ' WHERE 1=1';
                $params   = array();
                $joins    = '';

                if ( ! empty( $args['search'] ) ) {
                        $like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
                        $where   .= ' AND t.title LIKE %s';
                        $params[] = $like;
                }

                if ( ! empty( $args['status'] ) ) {
                        $statuses = array_values( array_intersect( (array) $args['status'], TaskFlow_Validation::statuses() ) );
                        if ( $statuses ) {
                                $placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
                                $where       .= " AND t.status IN ( $placeholders )";
                                $params       = array_merge( $params, $statuses );
                        }
                }

                if ( ! empty( $args['priority'] ) ) {
                        $priorities = array_values( array_intersect( (array) $args['priority'], TaskFlow_Validation::priorities() ) );
                        if ( $priorities ) {
                                $placeholders = implode( ', ', array_fill( 0, count( $priorities ), '%s' ) );
                                $where       .= " AND t.priority IN ( $placeholders )";
                                $params       = array_merge( $params, $priorities );
                        }
                }

                $assignee = $this->resolve_user_filter( $args['assignee_id'] ?? null );
                if ( -1 === $assignee ) {
                        $where .= ' AND t.assignee_id IS NULL';
                } elseif ( $assignee > 0 ) {
                        $where   .= ' AND t.assignee_id = %d';
                        $params[] = $assignee;
                }

                $creator = $this->resolve_user_filter( $args['created_by'] ?? null );
                if ( $creator > 0 ) {
                        $where   .= ' AND t.created_by = %d';
                        $params[] = $creator;
                }

                if ( isset( $args['project_id'] ) && '' !== $args['project_id'] && null !== $args['project_id'] ) {
                        $project_id = absint( $args['project_id'] );
                        if ( $project_id > 0 ) {
                                $where   .= ' AND t.project_id = %d';
                                $params[] = $project_id;
                        } else {
                                $where .= ' AND t.project_id IS NULL';
                        }
                }

                if ( ! empty( $args['tag_id'] ) ) {
                        $joins   .= ' INNER JOIN ' . $pivot . ' tt ON ( tt.task_id = t.id AND tt.tag_id = %d )';
                        $params[] = absint( $args['tag_id'] );
                }

                if ( ! empty( $args['overdue'] ) ) {
                        $where   .= " AND t.due_date IS NOT NULL AND t.due_date < %s AND t.status != 'completed'";
                        $params[] = current_time( 'mysql' );
                }

                if ( ! empty( $args['due_from'] ) ) {
                        $where   .= ' AND t.due_date >= %s';
                        $params[] = (string) $args['due_from'];
                }

                if ( ! empty( $args['due_to'] ) ) {
                        $where   .= ' AND t.due_date <= %s';
                        $params[] = (string) $args['due_to'];
                }

                // Ownership / role visibility is enforced inside the SQL itself.
                $where .= TaskFlow_Capabilities::visibility_sql();

                list( $order_sql, $order_params ) = $this->build_order_sql( $args );

                $count_sql = "SELECT COUNT( DISTINCT t.id ) FROM {$table} t{$joins}{$where}";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- composed exclusively from whitelisted, prepared fragments.
                $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

                $per_page = isset( $args['per_page'] ) ? max( 1, min( 200, absint( $args['per_page'] ) ) ) : 10;
                $page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
                $offset   = ( $page - 1 ) * $per_page;

                $rows_sql = "SELECT DISTINCT t.* FROM {$table} t{$joins}{$where}{$order_sql} LIMIT %d OFFSET %d";
                $params[] = $per_page;
                $params[] = $offset;

                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- composed exclusively from whitelisted, prepared fragments.
                $rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $params ), ARRAY_A );

                return array(
                        'items' => $rows ? $rows : array(),
                        'total' => $total,
                );
        }

        /**
         * Insert a task and sync its tags. Returns the new id.
         *
         * @param array<string,mixed> $data Column values (already validated).
         * @param array<int,int>      $tag_ids Tag ids to attach.
         * @return int
         */
        public function insert( $data, $tag_ids = array() ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tasks' );

                $wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB -- array format whitelisted internally by wpdb.

                $task_id = (int) $wpdb->insert_id;
                $this->sync_tags( $task_id, $tag_ids );
                $this->resequence( (string) $data['status'] );

                return $task_id;
        }

        /**
         * Update a task row. $data keys must match real columns.
         *
         * @param int                 $id Task id.
         * @param array<string,mixed> $data Column values.
         * @param array<int,int>|null $tag_ids Replace tags when an array is given.
         * @return void
         */
        public function update( $id, $data, $tag_ids = null ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tasks' );

                if ( ! empty( $data ) ) {
                        $wpdb->update( $table, $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
                }

                if ( is_array( $tag_ids ) ) {
                        $this->sync_tags( (int) $id, $tag_ids );
                }

                if ( isset( $data['status'] ) ) {
                        $this->resequence( (string) $data['status'] );
                }
        }

        /**
         * Delete a task and its dependent rows (tags, comments, time logs).
         * Activity rows keep their history but lose the task link (task_id NULL)
         * so the audit trail survives deletion.
         *
         * @param int $id Task id.
         * @return void
         */
        public function delete( $id ) {
                global $wpdb;

                $id = (int) $id;

                $wpdb->delete( TaskFlow_Schema::table( 'task_tags' ), array( 'task_id' => $id ) ); // phpcs:ignore WordPress.DB
                $wpdb->delete( TaskFlow_Schema::table( 'comments' ), array( 'task_id' => $id ) ); // phpcs:ignore WordPress.DB
                $wpdb->delete( TaskFlow_Schema::table( 'time_logs' ), array( 'task_id' => $id ) ); // phpcs:ignore WordPress.DB

                $activity = TaskFlow_Schema::table( 'activity' );
                $wpdb->update( $activity, array( 'task_id' => null ), array( 'task_id' => $id ) ); // phpcs:ignore WordPress.DB

                $wpdb->delete( TaskFlow_Schema::table( 'tasks' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
        }

        /**
         * Change status with Kanban ordering. The target column is resequenced so
         * positions stay sequential.
         *
         * @param int    $id      Task id.
         * @param string $status  Target status.
         * @param int    $position Requested position within the column (optional).
         * @return void
         */
        public function change_status( $id, $status, $position = null ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tasks' );
                $id    = (int) $id;

                $data = array(
                        'status'     => $status,
                        'updated_at' => current_time( 'mysql' ),
                );

                if ( 'completed' === $status ) {
                        $data['completed_at'] = current_time( 'mysql' );
                } else {
                        $data['completed_at'] = null;
                }

                if ( null !== $position ) {
                        $data['position'] = absint( $position );
                }

                $wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
                $this->resequence( $status );
        }

        /**
         * Rewrite positions of a whole column as 0..n-1.
         *
         * @param string $status Column (status) to resequence.
         * @return void
         */
        public function resequence( $status ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tasks' );
                $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static table name.
                        $wpdb->prepare(
                                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                "SELECT id FROM {$table} WHERE status = %s ORDER BY position ASC, id ASC",
                                $status
                        ),
                        ARRAY_A
                );

                if ( ! $rows ) {
                        return;
                }

                foreach ( array_values( $rows ) as $index => $row ) {
                        $wpdb->update( $table, array( 'position' => $index ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB
                }
        }

        /**
         * Replace the tag set of a task.
         *
         * @param int            $task_id Task id.
         * @param array<int,int> $tag_ids Tag ids.
         * @return void
         */
        public function sync_tags( $task_id, $tag_ids ) {
                global $wpdb;

                $pivot   = TaskFlow_Schema::table( 'task_tags' );
                $task_id = (int) $task_id;

                $wpdb->delete( $pivot, array( 'task_id' => $task_id ) ); // phpcs:ignore WordPress.DB

                $tag_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $tag_ids ) ) ) );

                foreach ( $tag_ids as $tag_id ) {
                        $wpdb->insert( // phpcs:ignore WordPress.DB
                                $pivot,
                                array(
                                        'task_id' => $task_id,
                                        'tag_id'  => $tag_id,
                                )
                        );
                }
        }

        /**
         * Tag ids attached to a task.
         *
         * @param int $task_id Task id.
         * @return array<int,int>
         */
        public function tag_ids( $task_id ) {
                global $wpdb;

                $pivot = TaskFlow_Schema::table( 'task_tags' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table name.
                $rows = $wpdb->get_col( $wpdb->prepare( "SELECT tag_id FROM {$pivot} WHERE task_id = %d", $task_id ) );

                return array_map( 'absint', (array) $rows );
        }

        /**
         * Increment/decrement the cached comments counter.
         *
         * @param int  $task_id Task id.
         * @param int  $delta   +1 or -1.
         * @return void
         */
        public function bump_comments_count( $task_id, $delta ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tasks' );
                $delta = (int) $delta;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table name, %i-style handled below.
                $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$table} SET comments_count = GREATEST( comments_count + {$delta}, 0 ) WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                (int) $task_id
                        )
                );
        }

        /**
         * Add logged seconds to the cached time counter.
         *
         * @param int $task_id Task id.
         * @param int $seconds Seconds to add.
         * @return void
         */
        public function add_time_spent( $task_id, $seconds ) {
                global $wpdb;

                $table   = TaskFlow_Schema::table( 'tasks' );
                $seconds = (int) $seconds;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$table} SET time_spent = time_spent + {$seconds} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                (int) $task_id
                        )
                );
        }

        /**
         * Count tasks grouped by status (for the dashboard).
         *
         * @return array<string,int>
         */
        public function count_by_status() {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tasks' );
                $where = TaskFlow_Capabilities::visibility_sql();

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table + visibility fragment.
                $rows = $wpdb->get_results(
                        "SELECT status, COUNT( * ) AS total FROM {$table} t WHERE 1=1{$where} GROUP BY status",
                        ARRAY_A
                );

                $out = array_fill_keys( TaskFlow_Validation::statuses(), 0 );

                foreach ( (array) $rows as $row ) {
                        $out[ $row['status'] ] = (int) $row['total'];
                }

                return $out;
        }

        /**
         * Build the ORDER BY clause from whitelisted columns only.
         *
         * @param array<string,mixed> $args Query args.
         * @return array{0:string,1:array<int,mixed>} SQL fragment and params.
         */
        private function build_order_sql( $args ) {
                $orderby = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'created_at';
                $order   = isset( $args['order'] ) ? strtoupper( (string) $args['order'] ) : 'DESC';

                if ( ! in_array( $orderby, TaskFlow_Validation::order_by_columns(), true ) ) {
                        $orderby = 'created_at';
                }

                $order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

                if ( 'priority' === $orderby ) {
                        $sql = ' ORDER BY CASE t.priority WHEN \'urgent\' THEN 0 WHEN \'high\' THEN 1 WHEN \'medium\' THEN 2 ELSE 3 END ' . $order . ', t.id DESC';
                        return array( $sql, array() );
                }

                $sql = " ORDER BY t.{$orderby} {$order}, t.id DESC";

                return array( $sql, array() );
        }

        /**
         * Normalize a user filter: "me", numeric or -1 (unassigned).
         *
         * @param mixed $value Raw filter value.
         * @return int -1 = unassigned, 0 = no filter, >0 = user id.
         */
        private function resolve_user_filter( $value ) {
                if ( null === $value || '' === $value || 'all' === $value ) {
                        return 0;
                }

                if ( 'me' === $value ) {
                        return (int) get_current_user_id();
                }

                if ( 'none' === $value ) {
                        return -1;
                }

                return absint( $value );
        }
}
