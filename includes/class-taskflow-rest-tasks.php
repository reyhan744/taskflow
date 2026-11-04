<?php
/**
 * REST controller: tasks, projects, tags, users, dashboard, activity,
 * notifications, comments and time tracking.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Rest_Tasks
 */
class TaskFlow_Rest_Tasks {

        /**
         * Route registration.
         *
         * @return void
         */
        public static function register() {
                $ns = TASKFLOW_REST_NAMESPACE;

                register_rest_route(
                        $ns,
                        '/context',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'get_context' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/tasks',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'list_tasks' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                        'args'                => self::list_args(),
                                ),
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( __CLASS__, 'create_task' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/tasks/(?P<id>\d+)',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'get_task' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_view_task' ),
                                ),
                                array(
                                        'methods'             => 'PUT',
                                        'callback'            => array( __CLASS__, 'update_task' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit_task' ),
                                ),
                                array(
                                        'methods'             => WP_REST_Server::DELETABLE,
                                        'callback'            => array( __CLASS__, 'delete_task' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit_task' ),
                                ),
                                'args' => array(
                                        'id' => array(
                                                'validate_callback' => static function ( $value ) {
                                                        return absint( $value ) > 0;
                                                },
                                        ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/tasks/(?P<id>\d+)/status',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( __CLASS__, 'change_status' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_change_status' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/tasks/(?P<id>\d+)/comments',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'list_comments' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_view_task' ),
                                ),
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( __CLASS__, 'add_comment' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_change_status' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/tasks/(?P<id>\d+)/time',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'task_time_logs' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_view_task' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/time/start',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( __CLASS__, 'start_timer' ),
                                        'permission_callback' => array( __CLASS__, 'perm_timer' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/time/stop',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( __CLASS__, 'stop_timer' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/time/active',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'active_timer' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/projects',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'list_projects' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( __CLASS__, 'create_project' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit_others' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/projects/(?P<id>\d+)',
                        array(
                                array(
                                        'methods'             => 'PUT',
                                        'callback'            => array( __CLASS__, 'update_project' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit_others' ),
                                ),
                                array(
                                        'methods'             => WP_REST_Server::DELETABLE,
                                        'callback'            => array( __CLASS__, 'delete_project' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit_others' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/tags',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'list_tags' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( __CLASS__, 'create_tag' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit_others' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/tags/(?P<id>\d+)',
                        array(
                                array(
                                        'methods'             => 'PUT',
                                        'callback'            => array( __CLASS__, 'update_tag' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit_others' ),
                                ),
                                array(
                                        'methods'             => WP_REST_Server::DELETABLE,
                                        'callback'            => array( __CLASS__, 'delete_tag' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit_others' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/users',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'list_users' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/dashboard',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'dashboard' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/activity',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'activity' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                        'args'                => array(
                                                'task_id' => array(
                                                        'default'           => 0,
                                                        'sanitize_callback' => 'absint',
                                                ),
                                                'page' => array(
                                                        'default'           => 1,
                                                        'sanitize_callback' => 'absint',
                                                ),
                                                'per_page' => array(
                                                        'default'           => 20,
                                                        'sanitize_callback' => 'absint',
                                                ),
                                        ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/notifications',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( __CLASS__, 'notifications' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                        )
                );

                register_rest_route(
                        $ns,
                        '/notifications/read',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( __CLASS__, 'notifications_read' ),
                                        'permission_callback' => array( 'TaskFlow_REST', 'perm_edit' ),
                                ),
                        )
                );
        }

        /**
         * Permission helper: the caller may operate the timer on the task given in
         * the body (assignee, owner, editor or admin).
         *
         * @param WP_REST_Request $request Current request.
         * @return bool|WP_Error
         */
        public static function perm_timer( $request ) {
                $task = ( new TaskFlow_Task_Repository() )->find( absint( $request->get_param( 'task_id' ) ) );

                if ( ! $task ) {
                        return TaskFlow_REST::not_found();
                }

                if ( TaskFlow_Capabilities::can_change_status( $task ) ) {
                        return true;
                }

                return TaskFlow_REST::forbidden( 'taskflow_rest_forbidden_task', __( 'You are not allowed to update this task.', 'taskflow' ) );
        }

        /**
         * Bootstrap payload for the Vue app.
         *
         * @return WP_REST_Response
         */
        public static function get_context() {
                $settings = TaskFlow_Settings::all();

                return rest_ensure_response(
                        array(
                                'version'   => TASKFLOW_VERSION,
                                'user'      => TaskFlow_Rest_Utils::user_brief( get_current_user_id() ),
                                'caps'      => array(
                                        'manage'      => TaskFlow_Capabilities::can_manage(),
                                        'edit_others' => TaskFlow_Capabilities::can_edit_others(),
                                        'edit'        => TaskFlow_Capabilities::can_edit(),
                                ),
                                'settings'  => array(
                                        'currency'       => $settings['currency'],
                                        'items_per_page' => $settings['items_per_page'],
                                ),
                                'unread'    => ( new TaskFlow_Notifications() )->unread_count( get_current_user_id() ),
                                'locale'    => get_user_locale(),
                                'is_rtl'    => is_rtl(),
                                'timezone'  => wp_timezone_string(),
                        )
                );
        }

        /**
         * GET /tasks – filtered, searchable, sorted, paginated list.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function list_tasks( $request ) {
                $args = array(
                        'search'     => sanitize_text_field( (string) $request->get_param( 'search' ) ),
                        'status'     => self::csv_param( $request->get_param( 'status' ) ),
                        'priority'   => self::csv_param( $request->get_param( 'priority' ) ),
                        'assignee_id' => self::user_param( $request->get_param( 'assignee_id' ) ),
                        'created_by' => self::user_param( $request->get_param( 'created_by' ) ),
                        'project_id' => self::project_param( $request->get_param( 'project_id' ) ),
                        'tag_id'     => absint( $request->get_param( 'tag_id' ) ),
                        'overdue'    => (bool) $request->get_param( 'overdue' ),
                        'orderby'    => sanitize_key( (string) $request->get_param( 'orderby' ) ),
                        'order'      => sanitize_key( (string) $request->get_param( 'order' ) ),
                        'page'       => max( 1, absint( $request->get_param( 'page' ) ) ),
                        'per_page'   => absint( $request->get_param( 'per_page' ) ),
                );

                if ( $args['per_page'] < 1 ) {
                        $args['per_page'] = (int) TaskFlow_Settings::get( 'items_per_page', 10 );
                }

                $result = ( new TaskFlow_Task_Repository() )->query( $args );
                $items  = TaskFlow_Rest_Utils::decorate_tasks( $result['items'] );

                return rest_ensure_response(
                        array(
                                'items'       => $items,
                                'total'       => $result['total'],
                                'page'        => $args['page'],
                                'per_page'    => $args['per_page'],
                                'total_pages' => (int) ceil( $result['total'] / max( 1, $args['per_page'] ) ),
                        )
                );
        }

        /**
         * POST /tasks – create a task.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response|WP_Error
         */
        public static function create_task( $request ) {
                $data = self::collect_task_data( $request, null );

                if ( is_wp_error( $data ) ) {
                        return $data;
                }

                $service = new TaskFlow_Task_Service();
                $task    = $service->create_task( $data, self::int_array( $request->get_param( 'tag_ids' ) ) );

                $response = rest_ensure_response(
                        array(
                                'item'    => TaskFlow_Rest_Utils::decorate_tasks( array( $task ) ),
                                'message' => __( 'The task was created.', 'taskflow' ),
                        )
                );
                $response->set_status( 201 );

                return $response;
        }

        /**
         * GET /tasks/{id} – task with relations plus recent activity, comments
         * and time logs.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function get_task( $request ) {
                $id   = absint( $request['id'] );
                $repo = new TaskFlow_Task_Repository();
                $task = $repo->find( $id );

                $activity = ( new TaskFlow_Activity() )->query( array( 'task_id' => $id, 'per_page' => 30 ) );

                return rest_ensure_response(
                        array(
                                'item'      => TaskFlow_Rest_Utils::decorate_tasks( array( $task ) ),
                                'activity'  => TaskFlow_Rest_Utils::decorate_activity(
                                        ( new TaskFlow_Activity() )->hydrate_meta( $activity['items'] )
                                ),
                                'comments'  => ( new TaskFlow_Comments() )->for_task( $id ),
                                'time_logs' => ( new TaskFlow_Time_Tracker() )->for_task( $id ),
                                'active_timer' => self::timer_for_task( $id ),
                        )
                );
        }

        /**
         * PUT /tasks/{id} – partial update.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response|WP_Error
         */
        public static function update_task( $request ) {
                $id   = absint( $request['id'] );
                $data = self::collect_task_data( $request, ( new TaskFlow_Task_Repository() )->find( $id ) );

                if ( is_wp_error( $data ) ) {
                        return $data;
                }

                $tag_ids = null;

                if ( $request->offsetExists( 'tag_ids' ) || $request->offsetExists( 'tags' ) ) {
                        $tag_ids = self::int_array( $request->get_param( 'tag_ids' ) );
                }

                $service = new TaskFlow_Task_Service();
                $task    = $service->update_task( $id, $data, $tag_ids );

                if ( ! $task ) {
                        return TaskFlow_REST::not_found();
                }

                return rest_ensure_response(
                        array(
                                'item'    => TaskFlow_Rest_Utils::decorate_tasks( array( $task ) ),
                                'message' => __( 'The task was updated.', 'taskflow' ),
                        )
                );
        }

        /**
         * DELETE /tasks/{id}.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function delete_task( $request ) {
                $id = absint( $request['id'] );

                ( new TaskFlow_Task_Service() )->delete_task( $id );

                return rest_ensure_response(
                        array(
                                'deleted' => true,
                                'id'      => $id,
                                'message' => __( 'The task was deleted.', 'taskflow' ),
                        )
                );
        }

        /**
         * POST /tasks/{id}/status – Kanban move.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response|WP_Error
         */
        public static function change_status( $request ) {
                $status = TaskFlow_Validation::status( $request->get_param( 'status' ) );

                if ( is_wp_error( $status ) ) {
                        return TaskFlow_REST::bad_request( $status );
                }

                $position = null;
                if ( null !== $request->get_param( 'position' ) && '' !== $request->get_param( 'position' ) ) {
                        $position = absint( $request->get_param( 'position' ) );
                }

                $task = ( new TaskFlow_Task_Service() )->move_task( absint( $request['id'] ), $status, $position );

                if ( ! $task ) {
                        return TaskFlow_REST::not_found();
                }

                return rest_ensure_response(
                        array(
                                'item'    => TaskFlow_Rest_Utils::decorate_tasks( array( $task ) ),
                                'message' => __( 'The task status was updated.', 'taskflow' ),
                        )
                );
        }

        /**
         * GET /tasks/{id}/comments.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function list_comments( $request ) {
                return rest_ensure_response(
                        array(
                                'items' => ( new TaskFlow_Comments() )->for_task( absint( $request['id'] ) ),
                        )
                );
        }

        /**
         * POST /tasks/{id}/comments.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response|WP_Error
         */
        public static function add_comment( $request ) {
                $comment = ( new TaskFlow_Comments() )->add( absint( $request['id'] ), (string) $request->get_param( 'content' ) );

                if ( is_wp_error( $comment ) ) {
                        return TaskFlow_REST::bad_request( $comment );
                }

                return rest_ensure_response(
                        array(
                                'item'    => $comment,
                                'message' => __( 'The comment was added.', 'taskflow' ),
                        )
                );
        }

        /**
         * GET /tasks/{id}/time.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function task_time_logs( $request ) {
                return rest_ensure_response(
                        array(
                                'items' => ( new TaskFlow_Time_Tracker() )->for_task( absint( $request['id'] ) ),
                        )
                );
        }

        /**
         * POST /time/start.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function start_timer( $request ) {
                $tracker = new TaskFlow_Time_Tracker();
                $log     = $tracker->start( absint( $request->get_param( 'task_id' ) ) );

                return rest_ensure_response(
                        array(
                                'item'    => $log,
                                'message' => __( 'The timer started.', 'taskflow' ),
                        )
                );
        }

        /**
         * POST /time/stop.
         *
         * @return WP_REST_Response
         */
        public static function stop_timer() {
                $tracker = new TaskFlow_Time_Tracker();
                $log     = $tracker->stop();

                return rest_ensure_response(
                        array(
                                'item'    => $log,
                                'message' => $log ? __( 'The timer stopped.', 'taskflow' ) : __( 'No running timer.', 'taskflow' ),
                        )
                );
        }

        /**
         * GET /time/active.
         *
         * @return WP_REST_Response
         */
        public static function active_timer() {
                return rest_ensure_response(
                        array(
                                'item' => ( new TaskFlow_Time_Tracker() )->active(),
                        )
                );
        }

        /**
         * GET /projects.
         *
         * @return WP_REST_Response
         */
        public static function list_projects() {
                $repository = new TaskFlow_Project_Repository();
                $items      = array();

                foreach ( $repository->all() as $project ) {
                        $items[] = array(
                                'id'          => (int) $project['id'],
                                'name'        => $project['name'],
                                'description' => (string) $project['description'],
                                'color'       => $project['color'],
                                'created_at'  => $project['created_at'],
                        );
                }

                return rest_ensure_response( array( 'items' => $items ) );
        }

        /**
         * POST /projects.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response|WP_Error
         */
        public static function create_project( $request ) {
                $name = sanitize_text_field( (string) $request->get_param( 'name' ) );

                if ( '' === $name ) {
                        return TaskFlow_REST::bad_request( __( 'Project name is required.', 'taskflow' ) );
                }

                $repository = new TaskFlow_Project_Repository();
                $id         = $repository->insert(
                        array(
                                'name'        => $name,
                                'description' => sanitize_textarea_field( (string) $request->get_param( 'description' ) ),
                                'color'       => TaskFlow_Validation::color( $request->get_param( 'color' ) ),
                                'created_by'  => get_current_user_id(),
                                'created_at'  => current_time( 'mysql' ),
                                'updated_at'  => current_time( 'mysql' ),
                        )
                );

                ( new TaskFlow_Activity() )->log(
                        null,
                        'project_created',
                        array( 'name' => $name ),
                        sprintf(
                                /* translators: %s: project name */
                                __( 'Created the project "%s".', 'taskflow' ),
                                $name
                        )
                );

                return rest_ensure_response(
                        array(
                                'item'    => $repository->find( $id ),
                                'message' => __( 'The project was created.', 'taskflow' ),
                        )
                );
        }

        /**
         * PUT /projects/{id}.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response|WP_Error
         */
        public static function update_project( $request ) {
                $id         = absint( $request['id'] );
                $repository = new TaskFlow_Project_Repository();
                $project    = $repository->find( $id );

                if ( ! $project ) {
                        return TaskFlow_REST::not_found();
                }

                $data = array( 'updated_at' => current_time( 'mysql' ) );

                if ( $request->offsetExists( 'name' ) ) {
                        $name = sanitize_text_field( (string) $request->get_param( 'name' ) );

                        if ( '' === $name ) {
                                return TaskFlow_REST::bad_request( __( 'Project name is required.', 'taskflow' ) );
                        }

                        $data['name'] = $name;
                }

                if ( $request->offsetExists( 'description' ) ) {
                        $data['description'] = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
                }

                if ( $request->offsetExists( 'color' ) ) {
                        $data['color'] = TaskFlow_Validation::color( $request->get_param( 'color' ) );
                }

                $repository->update( $id, $data );

                return rest_ensure_response(
                        array(
                                'item'    => $repository->find( $id ),
                                'message' => __( 'The project was updated.', 'taskflow' ),
                        )
                );
        }

        /**
         * DELETE /projects/{id}.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function delete_project( $request ) {
                $id = absint( $request['id'] );

                ( new TaskFlow_Project_Repository() )->delete( $id );

                return rest_ensure_response(
                        array(
                                'deleted' => true,
                                'id'      => $id,
                                'message' => __( 'The project was deleted. Its tasks were kept without a project.', 'taskflow' ),
                        )
                );
        }

        /**
         * GET /tags.
         *
         * @return WP_REST_Response
         */
        public static function list_tags() {
                $items = array();

                foreach ( ( new TaskFlow_Tag_Repository() )->all() as $tag ) {
                        $items[] = array(
                                'id'    => (int) $tag['id'],
                                'name'  => $tag['name'],
                                'slug'  => $tag['slug'],
                                'color' => $tag['color'],
                        );
                }

                return rest_ensure_response( array( 'items' => $items ) );
        }

        /**
         * POST /tags.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response|WP_Error
         */
        public static function create_tag( $request ) {
                $name = sanitize_text_field( (string) $request->get_param( 'name' ) );

                if ( '' === $name ) {
                        return TaskFlow_REST::bad_request( __( 'Tag name is required.', 'taskflow' ) );
                }

                $repository = new TaskFlow_Tag_Repository();
                $id         = $repository->first_or_create( $name, TaskFlow_Validation::color( $request->get_param( 'color' ) ) );

                return rest_ensure_response(
                        array(
                                'item'    => $repository->find( $id ),
                                'message' => __( 'The tag was saved.', 'taskflow' ),
                        )
                );
        }

        /**
         * PUT /tags/{id}.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response|WP_Error
         */
        public static function update_tag( $request ) {
                $id         = absint( $request['id'] );
                $repository = new TaskFlow_Tag_Repository();

                if ( ! $repository->find( $id ) ) {
                        return TaskFlow_REST::not_found();
                }

                $data = array();

                if ( $request->offsetExists( 'name' ) ) {
                        $name = sanitize_text_field( (string) $request->get_param( 'name' ) );

                        if ( '' === $name ) {
                                return TaskFlow_REST::bad_request( __( 'Tag name is required.', 'taskflow' ) );
                        }

                        $data['name'] = $name;
                }

                if ( $request->offsetExists( 'color' ) ) {
                        $data['color'] = TaskFlow_Validation::color( $request->get_param( 'color' ) );
                }

                if ( $data ) {
                        $repository->update( $id, $data );
                }

                return rest_ensure_response(
                        array(
                                'item'    => $repository->find( $id ),
                                'message' => __( 'The tag was updated.', 'taskflow' ),
                        )
                );
        }

        /**
         * DELETE /tags/{id}.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function delete_tag( $request ) {
                $id = absint( $request['id'] );

                ( new TaskFlow_Tag_Repository() )->delete( $id );

                return rest_ensure_response(
                        array(
                                'deleted' => true,
                                'id'      => $id,
                                'message' => __( 'The tag was deleted.', 'taskflow' ),
                        )
                );
        }

        /**
         * GET /users – assignable users.
         *
         * @return WP_REST_Response
         */
        public static function list_users() {
                return rest_ensure_response(
                        array(
                                'items' => TaskFlow_Rest_Utils::assignable_users(),
                        )
                );
        }

        /**
         * GET /dashboard.
         *
         * @return WP_REST_Response
         */
        public static function dashboard() {
                return rest_ensure_response(
                        array(
                                'stats' => ( new TaskFlow_Dashboard() )->stats(),
                        )
                );
        }

        /**
         * GET /activity – global feed. Users without "edit others" only see their
         * own events and events of their tasks (server enforced).
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function activity( $request ) {
                $activity = new TaskFlow_Activity();
                $args     = array(
                        'page'     => max( 1, absint( $request->get_param( 'page' ) ) ),
                        'per_page' => min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) ),
                );

                if ( $request->get_param( 'task_id' ) ) {
                        $args['task_id'] = absint( $request->get_param( 'task_id' ) );
                } elseif ( ! TaskFlow_Capabilities::can_edit_others() ) {
                        $args['restrict_user_id'] = get_current_user_id();
                }

                $result = $activity->query( $args );

                return rest_ensure_response(
                        array(
                                'items'       => TaskFlow_Rest_Utils::decorate_activity( $activity->hydrate_meta( $result['items'] ) ),
                                'total'       => $result['total'],
                                'page'        => $args['page'],
                                'total_pages' => (int) ceil( $result['total'] / max( 1, $args['per_page'] ) ),
                        )
                );
        }

        /**
         * GET /notifications – notifications of the current user only.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function notifications( $request ) {
                $result = ( new TaskFlow_Notifications() )->for_user( get_current_user_id(), array( 'per_page' => 30 ) );

                return rest_ensure_response(
                        array(
                                'items'  => $result['items'],
                                'total'  => $result['total'],
                                'unread' => $result['unread'],
                        )
                );
        }

        /**
         * POST /notifications/read.
         *
         * @param WP_REST_Request $request Current request.
         * @return WP_REST_Response
         */
        public static function notifications_read( $request ) {
                $ids = null;

                if ( $request->offsetExists( 'ids' ) ) {
                        $ids = self::int_array( $request->get_param( 'ids' ) );
                }

                $updated = ( new TaskFlow_Notifications() )->mark_read( get_current_user_id(), $ids );

                return rest_ensure_response(
                        array(
                                'updated' => $updated,
                        )
                );
        }

        /**
         * Shared list arguments.
         *
         * @return array<string,mixed>
         */
        private static function list_args() {
                return array(
                        'search' => array(
                                'default'           => '',
                                'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'status' => array(
                                'default' => '',
                        ),
                        'priority' => array(
                                'default' => '',
                        ),
                        'assignee_id' => array(
                                'default' => '',
                        ),
                        'created_by' => array(
                                'default' => '',
                        ),
                        'project_id' => array(
                                'default' => '',
                        ),
                        'tag_id' => array(
                                'default'           => 0,
                                'sanitize_callback' => 'absint',
                        ),
                        'overdue' => array(
                                'default' => false,
                        ),
                        'orderby' => array(
                                'default'           => 'created_at',
                                'sanitize_callback' => 'sanitize_key',
                        ),
                        'order' => array(
                                'default'           => 'DESC',
                                'sanitize_callback' => 'sanitize_key',
                        ),
                        'page' => array(
                                'default'           => 1,
                                'sanitize_callback' => 'absint',
                        ),
                        'per_page' => array(
                                'default'           => 0,
                                'sanitize_callback' => 'absint',
                        ),
                );
        }

        /**
         * Parse a comma separated parameter into an array of whitelisted values.
         *
         * @param mixed $value Raw parameter.
         * @return array<int,string>
         */
        private static function csv_param( $value ) {
                if ( is_array( $value ) ) {
                        $value = implode( ',', $value );
                }

                $parts = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );

                return array_values( $parts );
        }

        /**
         * Normalize a user filter param ("me", "none", numeric, csv, empty).
         *
         * @param mixed $value Raw parameter.
         * @return mixed
         */
        private static function user_param( $value ) {
                if ( is_array( $value ) ) {
                        $value = implode( ',', $value );
                }

                $value = trim( (string) $value );

                if ( '' === $value || 'all' === $value ) {
                        return '';
                }

                return $value;
        }

        /**
         * Normalize a project filter param ("none" = without project).
         *
         * @param mixed $value Raw parameter.
         * @return mixed
         */
        private static function project_param( $value ) {
                $value = trim( (string) $value );

                if ( 'none' === $value ) {
                        return 0;
                }

                return $value;
        }

        /**
         * Coerce a parameter into an array of positive integers.
         *
         * @param mixed $value Raw parameter.
         * @return array<int,int>
         */
        private static function int_array( $value ) {
                if ( is_string( $value ) ) {
                        $value = explode( ',', $value );
                }

                if ( ! is_array( $value ) ) {
                        return array();
                }

                return array_values( array_filter( array_map( 'absint', $value ) ) );
        }

        /**
         * Collect + validate task fields present in the request.
         *
         * @param WP_REST_Request           $request  Current request.
         * @param array<string,mixed>|null  $existing Existing row for partial updates.
         * @return array<string,mixed>|WP_Error
         */
        private static function collect_task_data( $request, $existing = null ) {
                $data   = array();
                $create = null === $existing;

                if ( $create || $request->offsetExists( 'title' ) ) {
                        $title = sanitize_text_field( (string) $request->get_param( 'title' ) );

                        if ( '' === $title ) {
                                return TaskFlow_REST::bad_request( __( 'The task title is required.', 'taskflow' ) );
                        }

                        $data['title'] = mb_substr( $title, 0, 255 );
                }

                if ( $request->offsetExists( 'description' ) ) {
                        $data['description'] = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
                }

                if ( $request->offsetExists( 'status' ) ) {
                        $status = TaskFlow_Validation::status( $request->get_param( 'status' ) );

                        if ( is_wp_error( $status ) ) {
                                return TaskFlow_REST::bad_request( $status );
                        }

                        $data['status'] = $status;
                } elseif ( $create ) {
                        $data['status'] = 'new';
                }

                if ( $request->offsetExists( 'priority' ) ) {
                        $priority = TaskFlow_Validation::priority( $request->get_param( 'priority' ) );

                        if ( is_wp_error( $priority ) ) {
                                return TaskFlow_REST::bad_request( $priority );
                        }

                        $data['priority'] = $priority;
                } elseif ( $create ) {
                        $data['priority'] = 'medium';
                }

                if ( $request->offsetExists( 'due_date' ) ) {
                        $due = TaskFlow_Validation::datetime( $request->get_param( 'due_date' ) );

                        if ( is_wp_error( $due ) ) {
                                return TaskFlow_REST::bad_request( $due );
                        }

                        $data['due_date'] = $due;
                }

                if ( $request->offsetExists( 'project_id' ) ) {
                        $project_id = absint( $request->get_param( 'project_id' ) );

                        if ( $project_id > 0 ) {
                                $project = ( new TaskFlow_Project_Repository() )->find( $project_id );

                                if ( ! $project ) {
                                        return TaskFlow_REST::bad_request( __( 'The selected project does not exist.', 'taskflow' ) );
                                }

                                $data['project_id'] = $project_id;
                        } else {
                                $data['project_id'] = null;
                        }
                }

                if ( $request->offsetExists( 'assignee_id' ) ) {
                        $assignee_id = absint( $request->get_param( 'assignee_id' ) );

                        if ( $assignee_id > 0 ) {
                                if ( ! get_userdata( $assignee_id ) ) {
                                        return TaskFlow_REST::bad_request( __( 'The selected user does not exist.', 'taskflow' ) );
                                }

                                $data['assignee_id'] = $assignee_id;
                        } else {
                                $data['assignee_id'] = null;
                        }
                }

                if ( $request->offsetExists( 'position' ) ) {
                        $data['position'] = absint( $request->get_param( 'position' ) );
                }

                return $data;
        }

        /**
         * Running timer for a task (any user) for the task detail panel.
         *
         * @param int $task_id Task id.
         * @return array<string,mixed>|null
         */
        private static function timer_for_task( $task_id ) {
                $tracker = new TaskFlow_Time_Tracker();
                $mine    = $tracker->active();

                if ( $mine && (int) $mine['task_id'] === (int) $task_id ) {
                        return $mine;
                }

                return null;
        }
}
