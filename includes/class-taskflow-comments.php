<?php
/**
 * Task comments with @mentions (creative extension).
 *
 * Mentions are parsed from "@username" tokens, resolved to real users and
 * trigger in-app notifications (+ optional mock e-mail) for the mentioned
 * users and, depending on settings, for the task owner/assignee.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Comments
 */
class TaskFlow_Comments {

        /**
         * @var TaskFlow_Notifications
         */
        private $notifications;

        /**
         * @var TaskFlow_Mock_Mailer
         */
        private $mailer;

        /**
         * Constructor.
         *
         * @param TaskFlow_Notifications|null $notifications Notifications service.
         * @param TaskFlow_Mock_Mailer|null   $mailer        Mock mailer.
         */
        public function __construct( $notifications = null, $mailer = null ) {
                $this->notifications = $notifications ? $notifications : new TaskFlow_Notifications();
                $this->mailer        = $mailer ? $mailer : new TaskFlow_Mock_Mailer();
        }

        /**
         * Add a comment to a task.
         *
         * @param int    $task_id Task id.
         * @param string $content Comment text.
         * @return array<string,mixed>|WP_Error Comment row or error.
         */
        public function add( $task_id, $content ) {
                global $wpdb;

                $content = trim( wp_strip_all_tags( (string) $content ) );

                if ( '' === $content ) {
                        return new WP_Error( 'taskflow_empty_comment', __( 'Comment cannot be empty.', 'taskflow' ) );
                }

                $mentions = $this->extract_mentions( $content );

                $wpdb->insert( // phpcs:ignore WordPress.DB
                        TaskFlow_Schema::table( 'comments' ),
                        array(
                                'task_id'    => (int) $task_id,
                                'user_id'    => get_current_user_id(),
                                'content'    => $content,
                                'mentions'   => wp_json_encode( $mentions ),
                                'created_at' => current_time( 'mysql' ),
                        )
                );

                $comment_id = (int) $wpdb->insert_id;

                ( new TaskFlow_Task_Repository() )->bump_comments_count( $task_id, 1 );

                $task = ( new TaskFlow_Task_Repository() )->find( $task_id );
                $actor = (int) get_current_user_id();

                ( new TaskFlow_Activity() )->log(
                        $task_id,
                        'comment_added',
                        array( 'comment_id' => $comment_id ),
                        __( 'Added a comment.', 'taskflow' )
                );

                if ( $task ) {
                        $settings = TaskFlow_Settings::all();

                        if ( ! empty( $settings['notify_on_comment'] ) ) {
                                $targets = array_unique(
                                        array_filter(
                                                array_merge(
                                                        $mentions,
                                                        array( (int) $task['created_by'], (int) $task['assignee_id'] )
                                                )
                                        )
                                );

                                foreach ( $targets as $target ) {
                                        if ( $target === $actor ) {
                                                continue;
                                        }

                                        $this->notifications->add(
                                                $target,
                                                'comment',
                                                sprintf(
                                                        /* translators: 1: task title, 2: acting user name */
                                                        __( 'New comment on "%1$s" by %2$s.', 'taskflow' ),
                                                        $task['title'],
                                                        $this->display_name( $actor )
                                                ),
                                                $task_id
                                        );
                                }
                        }

                        if ( $mentions && ! empty( $settings['mock_email_on_assignment'] ) ) {
                                foreach ( $mentions as $mentioned_id ) {
                                        $user = get_userdata( $mentioned_id );

                                        if ( $user && $user->user_email ) {
                                                $this->mailer->send(
                                                        $user->user_email,
                                                        sprintf(
                                                                /* translators: 1: task title */
                                                                __( '[TaskFlow MOCK Email] You were mentioned in: %s', 'taskflow' ),
                                                                $task['title']
                                                        ),
                                                        TaskFlow_Mock_Mailer::build_task_body(
                                                                sprintf(
                                                                        /* translators: 1: user name, 2: task title */
                                                                        __( '%1$s mentioned you in the task "%2$s".', 'taskflow' ),
                                                                        $this->display_name( $actor ),
                                                                        $task['title']
                                                                ),
                                                                array(
                                                                        __( 'Comment', 'taskflow' ) => wp_strip_all_tags( $content ),
                                                                )
                                                        )
                                                );
                                        }
                                }
                        }
                }

                $comment = $this->find( $comment_id );

                return $comment ? $comment : new WP_Error( 'taskflow_comment_failed', __( 'The comment could not be saved.', 'taskflow' ) );
        }

        /**
         * Comments of a task, oldest first.
         *
         * @param int $task_id Task id.
         * @return array<int,array<string,mixed>>
         */
        public function for_task( $task_id ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'comments' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static table name.
                $rows = $wpdb->get_results(
                        $wpdb->prepare( "SELECT * FROM {$table} WHERE task_id = %d ORDER BY id ASC", (int) $task_id ),
                        ARRAY_A
                );

                $out = array();

                foreach ( (array) $rows as $row ) {
                        $row['id']         = (int) $row['id'];
                        $row['task_id']    = (int) $row['task_id'];
                        $row['user_id']    = (int) $row['user_id'];
                        $row['user']       = $this->user_brief( (int) $row['user_id'] );
                        $row['mentions']   = array_map( 'absint', (array) json_decode( (string) $row['mentions'], true ) );
                        $out[] = $row;
                }

                return $out;
        }

        /**
         * Fetch a single comment (decorated).
         *
         * @param int $id Comment id.
         * @return array<string,mixed>|null
         */
        public function find( $id ) {
                global $wpdb;

                $table = TaskFlow_Schema::table( 'comments' );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

                if ( ! $row ) {
                        return null;
                }

                $row['id']       = (int) $row['id'];
                $row['user_id']  = (int) $row['user_id'];
                $row['user']     = $this->user_brief( (int) $row['user_id'] );
                $row['mentions'] = array_map( 'absint', (array) json_decode( (string) $row['mentions'], true ) );

                return $row;
        }

        /**
         * Extract @username mentions and resolve them to user ids.
         *
         * @param string $content Comment text.
         * @return array<int,int>
         */
        private function extract_mentions( $content ) {
                $ids = array();

                if ( ! preg_match_all( '/@([A-Za-z0-9_.\-]{2,60})/u', (string) $content, $matches ) ) {
                        return $ids;
                }

                foreach ( array_unique( (array) $matches[1] ) as $login ) {
                        $user = get_user_by( 'login', $login );

                        if ( ! $user && is_email( $login ) ) {
                                $user = get_user_by( 'email', $login );
                        }

                        if ( $user ) {
                                $ids[] = (int) $user->ID;
                        }
                }

                return array_values( array_unique( $ids ) );
        }

        /**
         * Minimal user payload.
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

        /**
         * Display name helper that never fails.
         *
         * @param int $user_id User id.
         * @return string
         */
        private function display_name( $user_id ) {
                $user = get_userdata( (int) $user_id );

                return $user ? $user->display_name : (string) $user_id;
        }
}
