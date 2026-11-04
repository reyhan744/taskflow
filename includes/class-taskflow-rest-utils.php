<?php
/**
 * Shared helpers for the REST controllers: decorating raw repository rows
 * with users, projects and tags for the API responses.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Rest_Utils
 */
class TaskFlow_Rest_Utils {

	/**
	 * Attach project, assignee, creator, tags and flags to task rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Raw task rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function decorate_tasks( $rows ) {
		$projects = array();
		foreach ( ( new TaskFlow_Project_Repository() )->all() as $project ) {
			$projects[ (int) $project['id'] ] = $project;
		}

		$tag_map = ( new TaskFlow_Tag_Repository() )->for_tasks( wp_list_pluck( $rows, 'id' ) );
		$user_cache = array();

		$out = array();

		foreach ( (array) $rows as $row ) {
			$task_id = (int) $row['id'];

			$assignee = isset( $row['assignee_id'] ) && $row['assignee_id']
				? self::user_brief( (int) $row['assignee_id'], $user_cache )
				: null;
			$creator = isset( $row['created_by'] ) && $row['created_by']
				? self::user_brief( (int) $row['created_by'], $user_cache )
				: null;
			$project = ! empty( $row['project_id'] ) && isset( $projects[ (int) $row['project_id'] ] )
				? array(
					'id'    => (int) $row['project_id'],
					'name'  => $projects[ (int) $row['project_id'] ]['name'],
					'color' => $projects[ (int) $row['project_id'] ]['color'],
				)
				: null;

			$is_overdue = ! empty( $row['due_date'] )
				&& 'completed' !== $row['status']
				&& $row['due_date'] < current_time( 'mysql' );

			$out[] = array(
				'id'          => $task_id,
				'title'       => $row['title'],
				'description' => (string) ( $row['description'] ?? '' ),
				'status'      => $row['status'],
				'priority'    => $row['priority'],
				'due_date'    => $row['due_date'] ? $row['due_date'] : null,
				'project_id'  => ! empty( $row['project_id'] ) ? (int) $row['project_id'] : null,
				'project'     => $project,
				'assignee_id' => ! empty( $row['assignee_id'] ) ? (int) $row['assignee_id'] : null,
				'assignee'    => $assignee,
				'created_by'  => (int) ( $row['created_by'] ?? 0 ),
				'creator'     => $creator,
				'tags'        => $tag_map[ $task_id ] ?? array(),
				'tag_ids'     => array_map(
					static function ( $tag ) {
						return (int) $tag['id'];
					},
					$tag_map[ $task_id ] ?? array()
				),
				'position'    => (int) ( $row['position'] ?? 0 ),
				'time_spent'  => (int) ( $row['time_spent'] ?? 0 ),
				'comments_count' => (int) ( $row['comments_count'] ?? 0 ),
				'created_at'  => $row['created_at'],
				'updated_at'  => $row['updated_at'],
				'completed_at' => $row['completed_at'] ? $row['completed_at'] : null,
				'overdue'     => $is_overdue,
				'can_edit'    => TaskFlow_Capabilities::can_edit_task( $row ),
				'can_change_status' => TaskFlow_Capabilities::can_change_status( $row ),
				'can_delete'  => TaskFlow_Capabilities::can_delete_task( $row ),
			);
		}

		return $out;
	}

	/**
	 * Decorate activity rows with user info.
	 *
	 * @param array<int,array<string,mixed>> $rows Raw activity rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function decorate_activity( $rows ) {
		$cache = array();
		$out   = array();

		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'         => (int) $row['id'],
				'task_id'    => ! empty( $row['task_id'] ) ? (int) $row['task_id'] : null,
				'type'       => $row['type'],
				'meta'       => is_array( $row['meta'] ) ? $row['meta'] : array(),
				'message'    => (string) ( $row['message'] ?? '' ),
				'user_id'    => (int) $row['user_id'],
				'user'       => self::user_brief( (int) $row['user_id'], $cache ),
				'created_at' => $row['created_at'],
			);
		}

		return $out;
	}

	/**
	 * Minimal user payload with per-request caching.
	 *
	 * @param int                     $user_id User id.
	 * @param array<int,array>|null   $cache   Shared cache array.
	 * @return array<string,mixed>|null
	 */
	public static function user_brief( $user_id, &$cache = array() ) {
		if ( isset( $cache[ $user_id ] ) ) {
			return $cache[ $user_id ];
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			$cache[ $user_id ] = null;

			return null;
		}

		$cache[ $user_id ] = array(
			'id'     => (int) $user->ID,
			'name'   => $user->display_name,
			'avatar' => get_avatar_url( $user->ID, array( 'size' => 48 ) ),
		);

		return $cache[ $user_id ];
	}

	/**
	 * Users that can be assigned to tasks (configurable via roles).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function assignable_users() {
		$assignable_roles = apply_filters(
			'taskflow_assignable_roles',
			array( 'administrator', 'editor', 'author', 'contributor' )
		);

		$users = get_users(
			array(
				'role__in' => (array) $assignable_roles,
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'number'   => 500,
			)
		);

		$out = array();

		foreach ( $users as $user ) {
			$out[] = array(
				'id'     => (int) $user->ID,
				'name'   => $user->display_name,
				'login'  => $user->user_login,
				'email'  => $user->user_email,
				'avatar' => get_avatar_url( $user->ID, array( 'size' => 48 ) ),
				'roles'  => array_values( (array) $user->roles ),
			);
		}

		return $out;
	}
}
