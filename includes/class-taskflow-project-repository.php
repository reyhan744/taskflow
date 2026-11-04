<?php
/**
 * Project repository.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Project_Repository
 */
class TaskFlow_Project_Repository {

        /**
         * All projects ordered by name.
         *
         * @return array<int,array<string,mixed>>
         */
        public function all() {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'projects' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table name.
                return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
        }

        /**
         * Fetch one project.
         *
         * @param int $id Project id.
         * @return array<string,mixed>|null
         */
        public function find( $id ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'projects' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

                return $row ? $row : null;
        }

        /**
         * Create a project.
         *
         * @param array<string,mixed> $data name/description/color/created_by.
         * @return int
         */
        public function insert( $data ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'projects' );
                $wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB

                return (int) $wpdb->insert_id;
        }

        /**
         * Update a project.
         *
         * @param int                 $id   Project id.
         * @param array<string,mixed> $data Column values.
         * @return void
         */
        public function update( $id, $data ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'projects' );
                $wpdb->update( $table, $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
        }

        /**
         * Delete a project. Tasks keep existing but become "no project".
         *
         * @param int $id Project id.
         * @return void
         */
        public function delete( $id ) {
                global $wpdb;

                $id    = (int) $id;
                $table = TaskFlow_Schema::table( 'projects' );
                $tasks = TaskFlow_Schema::table( 'tasks' );

                $wpdb->update( $tasks, array( 'project_id' => null ), array( 'project_id' => $id ) ); // phpcs:ignore WordPress.DB
                $wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
        }

        /**
         * Per-project task counters for the dashboard / projects screen.
         *
         * @return array<int,array<string,mixed>>
         */
        public function stats() {
                global $wpdb;

                $tasks    = TaskFlow_Schema::table( 'tasks' );
                $projects = TaskFlow_Schema::table( 'projects' );
                $where    = TaskFlow_Capabilities::visibility_sql();

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static tables + visibility fragment.
                return (array) $wpdb->get_results(
                        "SELECT p.id, p.name, p.color,
                                COUNT( t.id ) AS total,
                                SUM( CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END ) AS completed
                        FROM {$projects} p
                        LEFT JOIN {$tasks} t ON ( t.project_id = p.id )
                        WHERE 1=1{$where}
                        GROUP BY p.id, p.name, p.color
                        ORDER BY p.name ASC",
                        ARRAY_A
                );
        }
}
