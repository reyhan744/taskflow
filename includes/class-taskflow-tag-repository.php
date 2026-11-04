<?php
/**
 * Tag repository.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Tag_Repository
 */
class TaskFlow_Tag_Repository {

        /**
         * All tags ordered by name.
         *
         * @return array<int,array<string,mixed>>
         */
        public function all() {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tags' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table name.
                return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
        }

        /**
         * Fetch one tag.
         *
         * @param int $id Tag id.
         * @return array<string,mixed>|null
         */
        public function find( $id ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tags' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

                return $row ? $row : null;
        }

        /**
         * Find a tag by slug.
         *
         * @param string $slug Tag slug.
         * @return array<string,mixed>|null
         */
        public function find_by_slug( $slug ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tags' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );

                return $row ? $row : null;
        }

        /**
         * Create a tag, or return the existing tag with the same slug.
         *
         * @param string $name  Tag name.
         * @param string $color Hex color.
         * @return int Tag id.
         */
        public function first_or_create( $name, $color = '#2271b1' ) {
                $slug = TaskFlow_Validation::slug( $name );

                // Idempotent: reuse the existing tag when the slug already exists.
                $existing = $this->find_by_slug( $slug );
                if ( $existing ) {
                        return (int) $existing['id'];
                }

                $table = TaskFlow_Schema::table( 'tags' );

                global $wpdb;
                $wpdb->insert( // phpcs:ignore WordPress.DB
                        $table,
                        array(
                                'name'       => $name,
                                'slug'       => $this->unique_slug( $slug ),
                                'color'      => $color,
                                'created_at' => current_time( 'mysql' ),
                        )
                );

                return (int) $wpdb->insert_id;
        }

        /**
         * Update a tag.
         *
         * @param int                 $id   Tag id.
         * @param array<string,mixed> $data Column values.
         * @return void
         */
        public function update( $id, $data ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'tags' );
                $wpdb->update( $table, $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
        }

        /**
         * Delete a tag and detach it from tasks.
         *
         * @param int $id Tag id.
         * @return void
         */
        public function delete( $id ) {
                global $wpdb;

                $id    = (int) $id;
                $table = TaskFlow_Schema::table( 'tags' );
                $pivot = TaskFlow_Schema::table( 'task_tags' );

                $wpdb->delete( $pivot, array( 'tag_id' => $id ) ); // phpcs:ignore WordPress.DB
                $wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
        }

        /**
         * Tags for a set of task ids, indexed by task id.
         *
         * @param array<int,int> $task_ids Task ids.
         * @return array<int,array<int,array<string,mixed>>>
         */
        public function for_tasks( $task_ids ) {
                global $wpdb;

                $task_ids = array_values( array_filter( array_map( 'absint', (array) $task_ids ) ) );
                $map      = array();

                if ( ! $task_ids ) {
                        return $map;
                }

                $pivot       = TaskFlow_Schema::table( 'task_tags' );
                $tags        = TaskFlow_Schema::table( 'tags' );
                $placehold   = implode( ', ', array_fill( 0, count( $task_ids ), '%d' ) );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static tables, placeholders whitelisted above.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT tt.task_id, g.id, g.name, g.color
                                FROM {$pivot} tt
                                INNER JOIN {$tags} g ON ( g.id = tt.tag_id )
                                WHERE tt.task_id IN ( $placehold )
                                ORDER BY g.name ASC",
                                $task_ids
                        ),
                        ARRAY_A
                );

                foreach ( (array) $rows as $row ) {
                        $task_id = (int) $row['task_id'];
                        if ( ! isset( $map[ $task_id ] ) ) {
                                $map[ $task_id ] = array();
                        }
                        $map[ $task_id ][] = array(
                                'id'    => (int) $row['id'],
                                'name'  => $row['name'],
                                'color' => $row['color'],
                        );
                }

                return $map;
        }

        /**
         * Make a slug unique among existing tags.
         *
         * @param string $slug Candidate slug.
         * @return string
         */
        private function unique_slug( $slug ) {
                $candidate = $slug;
                $counter   = 1;

                while ( $this->find_by_slug( $candidate ) ) {
                        $counter ++;
                        $candidate = $slug . '-' . $counter;
                }

                return $candidate;
        }
}
