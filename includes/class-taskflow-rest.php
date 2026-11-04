<?php
/**
 * REST API bootstrap.
 *
 * Registers every TaskFlow route under the taskflow/v1 namespace. Route
 * groups live in dedicated controllers:
 *
 *  - TaskFlow_Rest_Tasks : tasks, projects, tags, users, dashboard, activity, notifications, comments, time tracking.
 *  - TaskFlow_Rest_Admin : settings, export/import, demo data, system info.
 *  - TaskFlow_Rest_Mocks : mock payment gateway and mock e-mail.
 *
 * Security model (applies to every single route):
 *  - permission_callback on every route (capability + ownership checks),
 *  - cookie authentication protected by the X-WP-Nonce header,
 *  - all input validated/sanitized server-side, all SQL prepared.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_REST
 */
class TaskFlow_REST {

	/**
	 * Register all route groups.
	 *
	 * @return void
	 */
	public static function register_routes() {
		foreach ( array( 'TaskFlow_Rest_Tasks', 'TaskFlow_Rest_Admin', 'TaskFlow_Rest_Mocks' ) as $controller ) {
			call_user_func( array( $controller, 'register' ) );
		}
	}

	/**
	 * Permission: the user may use TaskFlow at all (view own tasks).
	 *
	 * @return bool|WP_Error
	 */
	public static function perm_edit() {
		$check = self::check( array( TaskFlow_Capabilities::class, 'can_edit' ), 'taskflow_rest_forbidden' );

		return $check;
	}

	/**
	 * Permission: the user may manage projects/tags (editors and admins).
	 *
	 * @return bool|WP_Error
	 */
	public static function perm_edit_others() {
		return self::check( array( TaskFlow_Capabilities::class, 'can_edit_others' ), 'taskflow_rest_forbidden' );
	}

	/**
	 * Permission: full plugin administration.
	 *
	 * @return bool|WP_Error
	 */
	public static function perm_manage() {
		return self::check( array( TaskFlow_Capabilities::class, 'can_manage' ), 'taskflow_rest_forbidden_admin' );
	}

	/**
	 * Permission: manage OR edit-others (shared admin tools such as mock mail).
	 *
	 * @return bool|WP_Error
	 */
	public static function perm_manage_or_edit_others() {
		if ( TaskFlow_Capabilities::can_manage() || TaskFlow_Capabilities::can_edit_others() ) {
			return true;
		}

		return self::forbidden( 'taskflow_rest_forbidden', __( 'You are not allowed to perform this action.', 'taskflow' ) );
	}

	/**
	 * Permission: the current user may view the task in {id}.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|WP_Error
	 */
	public static function perm_view_task( $request ) {
		$task = ( new TaskFlow_Task_Repository() )->find( absint( $request['id'] ) );

		if ( ! $task ) {
			return self::not_found();
		}

		if ( TaskFlow_Capabilities::can_view_task( $task ) ) {
			return true;
		}

		return self::forbidden( 'taskflow_rest_forbidden_task', __( 'You are not allowed to view this task.', 'taskflow' ) );
	}

	/**
	 * Permission: the current user may edit the task content in {id}.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|WP_Error
	 */
	public static function perm_edit_task( $request ) {
		$task = ( new TaskFlow_Task_Repository() )->find( absint( $request['id'] ) );

		if ( ! $task ) {
			return self::not_found();
		}

		if ( TaskFlow_Capabilities::can_edit_task( $task ) ) {
			return true;
		}

		return self::forbidden( 'taskflow_rest_forbidden_task', __( 'You are not allowed to edit this task.', 'taskflow' ) );
	}

	/**
	 * Permission: the current user may change the status of the task in {id}
	 * (owners, assignees, editors, admins).
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|WP_Error
	 */
	public static function perm_change_status( $request ) {
		$task = ( new TaskFlow_Task_Repository() )->find( absint( $request['id'] ) );

		if ( ! $task ) {
			return self::not_found();
		}

		if ( TaskFlow_Capabilities::can_change_status( $task ) ) {
			return true;
		}

		return self::forbidden( 'taskflow_rest_forbidden_task', __( 'You are not allowed to update this task.', 'taskflow' ) );
	}

	/**
	 * Generic capability check helper.
	 *
	 * @param callable $callback Capability check.
	 * @param string   $code     Error code.
	 * @return bool|WP_Error
	 */
	private static function check( $callback, $code ) {
		if ( call_user_func( $callback ) ) {
			return true;
		}

		$messages = array(
			'taskflow_rest_forbidden'       => __( 'You are not allowed to use TaskFlow.', 'taskflow' ),
			'taskflow_rest_forbidden_admin' => __( 'This action requires administrator permissions.', 'taskflow' ),
		);

		return self::forbidden( $code, $messages[ $code ] ?? __( 'You are not allowed to perform this action.', 'taskflow' ) );
	}

	/**
	 * Standard 403 error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Localized message.
	 * @return WP_Error
	 */
	public static function forbidden( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => 403 ) );
	}

	/**
	 * Standard 404 error.
	 *
	 * @param string $message Optional message.
	 * @return WP_Error
	 */
	public static function not_found( $message = '' ) {
		return new WP_Error(
			'taskflow_rest_not_found',
			$message ? $message : __( 'The requested item does not exist.', 'taskflow' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Standard 400 error.
	 *
	 * @param WP_Error|string $error Error or message.
	 * @return WP_Error
	 */
	public static function bad_request( $error ) {
		if ( is_wp_error( $error ) ) {
			$error->add_data( array( 'status' => 400 ) );

			return $error;
		}

		return new WP_Error( 'taskflow_rest_invalid', $error, array( 'status' => 400 ) );
	}
}
