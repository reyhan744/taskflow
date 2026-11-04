<?php
/**
 * Role based access control.
 *
 * TaskFlow registers three custom capabilities and maps them onto the
 * standard roles. Every REST route re-checks capabilities server-side and
 * every per-task operation additionally verifies ownership, so the UI can
 * never grant access the API would deny.
 *
 *  taskflow_manage             – plugin administration: settings, import/export, mock payment, demo data.
 *  taskflow_edit_others_tasks  – see and edit every task, manage projects/tags.
 *  taskflow_edit_tasks         – create tasks and edit own tasks (owners and assignees may change status).
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Capabilities
 */
class TaskFlow_Capabilities {

	const MANAGE      = 'taskflow_manage';
	const EDIT_OTHERS = 'taskflow_edit_others_tasks';
	const EDIT        = 'taskflow_edit_tasks';

	/**
	 * Default role → capabilities mapping. The edit capability can be widened
	 * or narrowed from the Settings screen (see apply_role_caps()).
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function default_role_caps() {
		return array(
			'administrator' => array(
				self::MANAGE      => true,
				self::EDIT_OTHERS => true,
				self::EDIT        => true,
			),
			'editor'        => array(
				self::MANAGE      => false,
				self::EDIT_OTHERS => true,
				self::EDIT        => true,
			),
			'author'        => array(
				self::MANAGE      => false,
				self::EDIT_OTHERS => false,
				self::EDIT        => true,
			),
			'contributor'   => array(
				self::MANAGE      => false,
				self::EDIT_OTHERS => false,
				self::EDIT        => true,
			),
		);
	}

	/**
	 * Apply capability mapping to roles.
	 *
	 * Roles not present in the mapping have their TaskFlow caps removed, so a
	 * narrowed settings configuration takes effect immediately.
	 *
	 * @param array<string,mixed>|null $settings Optional settings override.
	 * @return void
	 */
	public static function apply_role_caps( $settings = null ) {
		$roles = wp_roles();
		if ( ! $roles ) {
			return;
		}

		$settings = $settings ? $settings : TaskFlow_Settings::all();
		$defaults = self::default_role_caps();

		foreach ( $roles->get_names() as $role_key => $label ) {
			$role = get_role( $role_key );
			if ( ! $role ) {
				continue;
			}

			$caps = isset( $defaults[ $role_key ] ) ? $defaults[ $role_key ] : array(
				self::MANAGE      => false,
				self::EDIT_OTHERS => false,
				self::EDIT        => false,
			);

			// Settings may extend the base mapping to extra roles.
			$extra = isset( $settings['extra_edit_roles'] ) && is_array( $settings['extra_edit_roles'] )
				? (array) $settings['extra_edit_roles']
				: array();

			if ( empty( $caps[ self::EDIT ] ) && in_array( $role_key, $extra, true ) ) {
				$caps[ self::EDIT ] = true;
			}

			foreach ( array( self::MANAGE, self::EDIT_OTHERS, self::EDIT ) as $cap ) {
				if ( ! empty( $caps[ $cap ] ) ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Remove every TaskFlow capability from every role (deactivation).
	 *
	 * @return void
	 */
	public static function remove_role_caps() {
		$roles = wp_roles();
		if ( ! $roles ) {
			return;
		}

		foreach ( array_keys( $roles->get_names() ) as $role_key ) {
			$role = get_role( $role_key );
			if ( ! $role ) {
				continue;
			}

			foreach ( array( self::MANAGE, self::EDIT_OTHERS, self::EDIT ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Can the current (or given) user administrate TaskFlow?
	 *
	 * @param int|null $user_id Optional user id.
	 * @return bool
	 */
	public static function can_manage( $user_id = null ) {
		return self::user_can( $user_id, self::MANAGE );
	}

	/**
	 * Can the user edit tasks that belong to other users?
	 *
	 * @param int|null $user_id Optional user id.
	 * @return bool
	 */
	public static function can_edit_others( $user_id = null ) {
		return self::user_can( $user_id, self::EDIT_OTHERS );
	}

	/**
	 * Can the user create and edit tasks at all?
	 *
	 * @param int|null $user_id Optional user id.
	 * @return bool
	 */
	public static function can_edit( $user_id = null ) {
		return self::user_can( $user_id, self::EDIT );
	}

	/**
	 * Can the user view a specific task? Owners, assignees and users allowed
	 * to edit others' tasks can see it.
	 *
	 * @param array<string,mixed> $task Raw task row (created_by / assignee_id keys).
	 * @param int|null            $user_id Optional user id.
	 * @return bool
	 */
	public static function can_view_task( $task, $user_id = null ) {
		$user_id = self::resolve_user( $user_id );

		if ( self::can_edit_others( $user_id ) ) {
			return true;
		}

		if ( ! self::can_edit( $user_id ) ) {
			return false;
		}

		return (int) $user_id === (int) ( $task['created_by'] ?? 0 )
			|| (int) $user_id === (int) ( $task['assignee_id'] ?? 0 );
	}

	/**
	 * Can the user modify a task's content? Owners and users allowed to edit
	 * others' tasks; assignees may only change status (see can_change_status).
	 *
	 * @param array<string,mixed> $task Raw task row.
	 * @param int|null            $user_id Optional user id.
	 * @return bool
	 */
	public static function can_edit_task( $task, $user_id = null ) {
		$user_id = self::resolve_user( $user_id );

		if ( self::can_edit_others( $user_id ) ) {
			return true;
		}

		return self::can_edit( $user_id ) && (int) $user_id === (int) ( $task['created_by'] ?? 0 );
	}

	/**
	 * Can the user move a task between statuses (Kanban)? Assignees can move
	 * their own tasks even though they cannot retitle them.
	 *
	 * @param array<string,mixed> $task Raw task row.
	 * @param int|null            $user_id Optional user id.
	 * @return bool
	 */
	public static function can_change_status( $task, $user_id = null ) {
		$user_id = self::resolve_user( $user_id );

		if ( self::can_edit_task( $task, $user_id ) ) {
			return true;
		}

		return self::can_edit( $user_id ) && (int) $user_id === (int) ( $task['assignee_id'] ?? 0 );
	}

	/**
	 * Can the user delete a task?
	 *
	 * @param array<string,mixed> $task Raw task row.
	 * @param int|null            $user_id Optional user id.
	 * @return bool
	 */
	public static function can_delete_task( $task, $user_id = null ) {
		return self::can_edit_task( $task, $user_id );
	}

	/**
	 * WHERE fragment that restricts a query to the tasks the user may see.
	 *
	 * @return string SQL fragment (starts with " AND"), or empty string for privileged users.
	 */
	public static function visibility_sql() {
		global $wpdb;

		$user_id = get_current_user_id();

		if ( self::can_edit_others( $user_id ) ) {
			return '';
		}

		return $wpdb->prepare(
			' AND ( t.created_by = %d OR t.assignee_id = %d )',
			$user_id,
			$user_id
		);
	}

	/**
	 * Wrapper around WP user capability checks.
	 *
	 * @param int|null $user_id User id or null for current user.
	 * @param string   $cap     Capability name.
	 * @return bool
	 */
	private static function user_can( $user_id, $cap ) {
		if ( null === $user_id ) {
			return current_user_can( $cap );
		}

		$user = get_userdata( (int) $user_id );

		return $user && user_can( $user, $cap );
	}

	/**
	 * Resolve a nullable user id to the current user id.
	 *
	 * @param int|null $user_id Optional explicit user id.
	 * @return int
	 */
	private static function resolve_user( $user_id ) {
		return null === $user_id ? (int) get_current_user_id() : (int) $user_id;
	}
}
