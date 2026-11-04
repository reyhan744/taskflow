<?php
/**
 * CSV import of tasks (creative extension).
 *
 * Expected header row:
 * title,description,status,priority,due_date,project,tags,assignee
 *
 * - title is required for every row; rows without a title are reported.
 * - status/priority fall back to defaults when omitted or unknown.
 * - project is matched by name and created on the fly.
 * - tags is a pipe-separated list ("urgent|frontend") created on the fly.
 * - assignee is matched by user_login or e-mail.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Importer
 */
class TaskFlow_Importer {

        /**
         * Import CSV content.
         *
         * @param string $csv Raw CSV text.
         * @return array{created:int,failed:int,errors:array<int,array<string,mixed>>}
         */
        public function import( $csv ) {
                $rows   = $this->parse( $csv );
                $report = array(
                        'created' => 0,
                        'failed'  => 0,
                        'errors'  => array(),
                );

                if ( ! $rows ) {
                        $report['errors'][] = array(
                                'row'     => 0,
                                'message' => __( 'The CSV content is empty or invalid.', 'taskflow' ),
                        );
                        $report['failed'] = 1;

                        return $report;
                }

                $header = array_shift( $rows );
                $header = array_map(
                        static function ( $column ) {
                                return trim( strtolower( (string) $column ) );
                        },
                        (array) $header
                );

                if ( ! in_array( 'title', $header, true ) ) {
                        $report['errors'][] = array(
                                'row'     => 1,
                                'message' => __( 'The CSV header must contain a "title" column.', 'taskflow' ),
                        );
                        $report['failed'] = 1;

                        return $report;
                }

                $service = new TaskFlow_Task_Service();

                foreach ( array_values( $rows ) as $index => $row ) {
                        $row_number = $index + 2; // 1-based, +1 for the header row.
                        $values     = array();

                        foreach ( $header as $position => $key ) {
                                $values[ $key ] = isset( $row[ $position ] ) ? trim( (string) $row[ $position ] ) : '';
                        }

                        if ( '' === $values['title'] ) {
                                $report['failed'] ++;
                                $report['errors'][] = array(
                                        'row'     => $row_number,
                                        'message' => __( 'Missing required "title".', 'taskflow' ),
                                );
                                continue;
                        }

                        $status = '' !== ( $values['status'] ?? '' ) ? TaskFlow_Validation::status( $values['status'] ) : 'new';
                        if ( is_wp_error( $status ) ) {
                                $status = 'new';
                        }

                        $priority = '' !== ( $values['priority'] ?? '' ) ? TaskFlow_Validation::priority( $values['priority'] ) : 'medium';
                        if ( is_wp_error( $priority ) ) {
                                $priority = 'medium';
                        }

                        $due = null;
                        if ( '' !== ( $values['due_date'] ?? '' ) ) {
                                $due = TaskFlow_Validation::datetime( $values['due_date'] );
                                if ( is_wp_error( $due ) ) {
                                        $report['errors'][] = array(
                                                'row'     => $row_number,
                                                'message' => sprintf(
                                                        /* translators: %s: raw value */
                                                        __( 'Ignored invalid due date "%s".', 'taskflow' ),
                                                        $values['due_date']
                                                ),
                                        );
                                        $due = null;
                                }
                        }

                        $tag_ids = array();
                        if ( '' !== ( $values['tags'] ?? '' ) ) {
                                $tag_repo = new TaskFlow_Tag_Repository();

                                foreach ( array_filter( array_map( 'trim', explode( '|', $values['tags'] ) ) ) as $tag_name ) {
                                        $tag_ids[] = $tag_repo->first_or_create( sanitize_text_field( $tag_name ) );
                                }
                        }

                        $data = array(
                                'title'       => sanitize_text_field( $values['title'] ),
                                'description' => sanitize_textarea_field( $values['description'] ?? '' ),
                                'status'      => $status,
                                'priority'    => $priority,
                                'due_date'    => $due,
                                'project_id'  => $this->resolve_project( $values['project'] ?? '' ),
                                'assignee_id' => $this->resolve_assignee( $values['assignee'] ?? '' ),
                        );

                        try {
                                $service->create_task( $data, $tag_ids );
                                $report['created'] ++;
                        } catch ( Throwable $exception ) {
                                $report['failed'] ++;
                                $report['errors'][] = array(
                                        'row'     => $row_number,
                                        'message' => $exception->getMessage(),
                                );
                        }
                }

                return $report;
        }

        /**
         * Parse CSV text into rows (handles BOM and newlines inside quotes).
         *
         * @param string $csv Raw CSV.
         * @return array<int,array<int,string>>
         */
        private function parse( $csv ) {
                $csv = (string) $csv;

                if ( 0 === strpos( $csv, "\xEF\xBB\xBF" ) ) {
                        $csv = substr( $csv, 3 );
                }

                $rows = array();
                $stream = fopen( 'php://temp', 'r+' );
                fwrite( $stream, $csv );
                rewind( $stream );

                while ( ( $row = fgetcsv( $stream, 0, ',', '"', '\\' ) ) !== false ) {
                        if ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) {
                                continue; // Skip blank lines.
                        }
                        $rows[] = $row;
                }

                fclose( $stream );

                return $rows;
        }

        /**
         * Find or create a project by name.
         *
         * @param string $name Project name.
         * @return int|null
         */
        private function resolve_project( $name ) {
                global $wpdb;

                $name = trim( sanitize_text_field( (string) $name ) );

                if ( '' === $name ) {
                        return null;
                }

                $table = TaskFlow_Schema::table( 'projects' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s", $name ) );

                if ( $existing ) {
                        return (int) $existing;
                }

                return ( new TaskFlow_Project_Repository() )->insert(
                        array(
                                'name'        => $name,
                                'description' => '',
                                'color'       => '#2271b1',
                                'created_by'  => get_current_user_id(),
                                'created_at'  => current_time( 'mysql' ),
                                'updated_at'  => current_time( 'mysql' ),
                        )
                );
        }

        /**
         * Resolve an assignee by login or e-mail.
         *
         * @param string $login User login or e-mail.
         * @return int|null
         */
        private function resolve_assignee( $login ) {
                $login = trim( (string) $login );

                if ( '' === $login ) {
                        return null;
                }

                $user = get_user_by( 'login', $login );

                if ( ! $user && is_email( $login ) ) {
                        $user = get_user_by( 'email', $login );
                }

                return $user ? (int) $user->ID : null;
        }

        /**
         * Sample CSV for the Settings screen template download.
         *
         * @return string
         */
        public static function sample_csv() {
                $lines = array(
                        'title,description,status,priority,due_date,project,tags,assignee',
                        'Design the login page,High-fidelity design for the login screen,in_progress,high,2026-11-20 14:00,Website,design|frontend,admin',
                        'Fix mobile menu,Menu overlaps content on small screens,new,urgent,2026-11-15 09:00,Website,bug,',
                        'Write release notes,Summarize v1.0 changes,new,low,,Website,docs,',
                );

                return "\xEF\xBB\xBF" . implode( "\n", $lines ) . "\n";
        }
}
