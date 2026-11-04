<?php
/**
 * Task service layer – business logic between the REST controllers and the
 * repositories. Handles validation, activity logging, in-app notifications
 * and the simulated e-mail side effects, so the API layer stays thin and the
 * storage layer stays pure SQL.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Task_Service
 */
class TaskFlow_Task_Service {

	/**
	 * @var TaskFlow_Task_Repository
	 */
	private $repository;

	/**
	 * @var TaskFlow_Activity
	 */
	private $activity;

	/**
	 * @var TaskFlow_Notifications
	 */
	private $notifications;

	/**
	 * @var TaskFlow_Mock_Mailer
	 */
	private $mailer;

	/**
	 * Constructor with dependency injection (swap-friendly for tests).
	 *
	 * @param TaskFlow_Task_Repository|null $repository    Task repository.
	 * @param TaskFlow_Activity|null        $activity      Activity logger.
	 * @param TaskFlow_Notifications|null   $notifications Notifications.
	 * @param TaskFlow_Mock_Mailer|null     $mailer        Mock mailer.
	 */
	public function __construct(
		$repository = null,
		$activity = null,
		$notifications = null,
		$mailer = null
	) {
		$this->repository    = $repository ? $repository : new TaskFlow_Task_Repository();
		$this->activity      = $activity ? $activity : new TaskFlow_Activity();
		$this->notifications = $notifications ? $notifications : new TaskFlow_Notifications();
		$this->mailer        = $mailer ? $mailer : new TaskFlow_Mock_Mailer();
	}

	/**
	 * Create a task from validated request data.
	 *
	 * @param array<string,mixed> $data     Validated columns (title required).
	 * @param array<int,int>      $tag_ids  Tag ids.
	 * @return array<string,mixed> Created row.
	 */
	public function create_task( $data, $tag_ids = array() ) {
		$now   = current_time( 'mysql' );
		$row   = array_merge(
			array(
				'title'       => '',
				'description' => '',
				'status'      => 'new',
				'priority'    => 'medium',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			$data
		);

		$row['created_by'] = isset( $row['created_by'] ) && $row['created_by'] ? (int) $row['created_by'] : (int) get_current_user_id();

		$task_id = $this->repository->insert( $row, $tag_ids );

		$this->activity->log(
			$task_id,
			'created',
			array(
				'title'    => $row['title'],
				'status'   => $row['status'],
				'priority' => $row['priority'],
			),
			sprintf(
				/* translators: %s: task title */
				__( 'Created the task "%s".', 'taskflow' ),
				$row['title']
			)
		);

		$task = $this->repository->find( $task_id );

		if ( ! empty( $task['assignee_id'] ) ) {
			$this->notify_assignment( $task );
		}

		return $task;
	}

	/**
	 * Update a task. Computes a field-level diff, writes one activity entry
	 * per changed dimension and fires notifications.
	 *
	 * @param int                 $id       Task id.
	 * @param array<string,mixed> $data     Validated columns.
	 * @param array<int,int>|null $tag_ids  Replace tags when array given.
	 * @return array<string,mixed> Updated row.
	 */
	public function update_task( $id, $data, $tag_ids = null ) {
		$before = $this->repository->find( $id );

		if ( ! $before ) {
			return null;
		}

		if ( isset( $data['status'] ) && 'completed' === $data['status'] && 'completed' !== $before['status'] ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		if ( isset( $data['status'] ) && 'completed' !== $data['status'] && 'completed' === $before['status'] ) {
			$data['completed_at'] = null;
		}

		$this->repository->update( $id, $data, $tag_ids );

		$this->log_diff( $before, array_merge( $before, $data ) );

		$after = $this->repository->find( $id );

		$assignee_changed = isset( $data['assignee_id'] )
			&& (int) $data['assignee_id'] !== (int) ( $before['assignee_id'] ?? 0 );

		if ( $assignee_changed && ! empty( $after['assignee_id'] ) ) {
			$this->notify_assignment( $after );
		}

		if ( isset( $data['status'] ) && 'status' !== ( $data['__skip_status_notify'] ?? '' ) ) {
			$this->notify_status_change( $before, $after );
		}

		return $after;
	}

	/**
	 * Delete a task and log the deletion.
	 *
	 * @param int $id Task id.
	 * @return void
	 */
	public function delete_task( $id ) {
		$task = $this->repository->find( $id );

		if ( ! $task ) {
			return;
		}

		$this->repository->delete( $id );
		$this->activity->log(
			null,
			'deleted',
			array( 'title' => $task['title'] ),
			sprintf(
				/* translators: %s: task title */
				__( 'Deleted the task "%s".', 'taskflow' ),
				$task['title']
			)
		);
	}

	/**
	 * Kanban move: change status with ownership rules already verified by the
	 * controller; writes a status_changed activity entry.
	 *
	 * @param int    $id       Task id.
	 * @param string $status   Target status.
	 * @param int    $position Target position.
	 * @return array<string,mixed>|null Updated row.
	 */
	public function move_task( $id, $status, $position = null ) {
		$before = $this->repository->find( $id );

		if ( ! $before ) {
			return null;
		}

		$data = array( 'status' => $status );

		if ( 'completed' === $status && 'completed' !== $before['status'] ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		if ( 'completed' !== $status && 'completed' === $before['status'] ) {
			$data['completed_at'] = null;
		}

		$update = $data;
		$update['updated_at'] = current_time( 'mysql' );

		if ( null !== $position ) {
			$update['position'] = (int) $position;
		}

		$this->repository->update( $id, $update );
		$this->repository->resequence( $status );

		$this->activity->log(
			$id,
			'status_changed',
			array(
				'from' => $before['status'],
				'to'   => $status,
			),
			sprintf(
				/* translators: 1: previous status, 2: new status */
				__( 'Changed the status from "%1$s" to "%2$s".', 'taskflow' ),
				$before['status'],
				$status
			)
		);

		$after = $this->repository->find( $id );
		$this->notify_status_change( $before, $after );

		return $after;
	}

	/**
	 * Notify + mock-mail a user about a new assignment.
	 *
	 * @param array<string,mixed> $task Task row.
	 * @return void
	 */
	private function notify_assignment( $task ) {
		$settings = TaskFlow_Settings::all();
		$assignee = (int) ( $task['assignee_id'] ?? 0 );
		$actor    = (int) get_current_user_id();

		if ( $assignee === $actor ) {
			return; // Self-assignment is not worth a notification.
		}

		if ( ! empty( $settings['notify_on_assignment'] ) ) {
			$this->notifications->add(
				$assignee,
				'assigned',
				sprintf(
					/* translators: 1: task title, 2: acting user name */
					__( 'The task "%1$s" was assigned to you by %2$s.', 'taskflow' ),
					$task['title'],
					$this->display_name( $actor )
				),
				$task['id']
			);
		}

		if ( ! empty( $settings['mock_email_on_assignment'] ) ) {
			$user = get_userdata( $assignee );

			if ( $user && $user->user_email ) {
				$this->mailer->send(
					$user->user_email,
					sprintf(
						/* translators: %s: task title */
						__( '[TaskFlow MOCK Email] New task: %s', 'taskflow' ),
						$task['title']
					),
					TaskFlow_Mock_Mailer::build_task_body(
						sprintf(
							/* translators: 1: task title, 2: acting user name */
							__( 'You have been assigned to the task "%1$s" by %2$s.', 'taskflow' ),
							$task['title'],
							$this->display_name( $actor )
						),
						array(
							__( 'Priority', 'taskflow' ) => $task['priority'],
							__( 'Status', 'taskflow' )   => $task['status'],
							__( 'Due date', 'taskflow' ) => $task['due_date'] ? $task['due_date'] : '—',
						)
					)
				);
			}
		}
	}

	/**
	 * Notify the owner/assignee counterpart about a status change.
	 *
	 * @param array<string,mixed> $before Row before the change.
	 * @param array<string,mixed> $after  Row after the change.
	 * @return void
	 */
	private function notify_status_change( $before, $after ) {
		if ( ! $after || ( $before['status'] ?? '' ) === ( $after['status'] ?? '' ) ) {
			return;
		}

		$settings = TaskFlow_Settings::all();

		if ( empty( $settings['notify_on_status_change'] ) ) {
			return;
		}

		$actor  = (int) get_current_user_id();
		$targets = array_unique(
			array_filter(
				array(
					(int) ( $after['created_by'] ?? 0 ),
					(int) ( $after['assignee_id'] ?? 0 ),
				)
			)
		);

		foreach ( $targets as $target ) {
			if ( $target === $actor ) {
				continue;
			}

			$this->notifications->add(
				$target,
				'status_changed',
				sprintf(
					/* translators: 1: task title, 2: new status, 3: acting user name */
					__( 'The status of "%1$s" changed to "%2$s" (%3$s).', 'taskflow' ),
					$after['title'],
					$after['status'],
					$this->display_name( $actor )
				),
				$after['id']
			);
		}
	}

	/**
	 * Write activity entries for each changed field of an update.
	 *
	 * @param array<string,mixed> $before Row before update.
	 * @param array<string,mixed> $after  Merged new values.
	 * @return void
	 */
	private function log_diff( $before, $after ) {
		$fields = array( 'title', 'description', 'priority', 'due_date', 'project_id', 'assignee_id' );
		$changed = array();

		foreach ( $fields as $field ) {
			$old = $before[ $field ] ?? null;
			$new = $after[ $field ] ?? null;

			if ( (string) $old !== (string) $new ) {
				$changed[] = $field;
			}
		}

		if ( ! $changed ) {
			return;
		}

		if ( in_array( 'title', $changed, true ) ) {
			$this->activity->log(
				$before['id'],
				'title_changed',
				array(
					'from' => $before['title'],
					'to'   => $after['title'],
				),
				sprintf(
					/* translators: 1: old title, 2: new title */
					__( 'Renamed the task from "%1$s" to "%2$s".', 'taskflow' ),
					$before['title'],
					$after['title']
				)
			);
		}

		if ( in_array( 'priority', $changed, true ) ) {
			$this->activity->log(
				$before['id'],
				'priority_changed',
				array(
					'from' => $before['priority'],
					'to'   => $after['priority'],
				),
				sprintf(
					/* translators: 1: old priority, 2: new priority */
					__( 'Changed the priority from "%1$s" to "%2$s".', 'taskflow' ),
					$before['priority'],
					$after['priority']
				)
			);
		}

		if ( in_array( 'due_date', $changed, true ) ) {
			$this->activity->log(
				$before['id'],
				'due_changed',
				array(
					'from' => $before['due_date'],
					'to'   => $after['due_date'],
				),
				sprintf(
					/* translators: 1: old due date, 2: new due date */
					__( 'Changed the due date from "%1$s" to "%2$s".', 'taskflow' ),
					$before['due_date'] ? $before['due_date'] : '—',
					$after['due_date'] ? $after['due_date'] : '—'
				)
			);
		}

		if ( in_array( 'project_id', $changed, true ) ) {
			$this->activity->log(
				$before['id'],
				'project_changed',
				array(
					'from' => $before['project_id'],
					'to'   => $after['project_id'],
				),
				sprintf(
					/* translators: 1: old project, 2: new project */
					__( 'Changed the project from "%1$s" to "%2$s".', 'taskflow' ),
					$this->project_label( $before['project_id'] ),
					$this->project_label( $after['project_id'] )
				)
			);
		}

		if ( in_array( 'assignee_id', $changed, true ) ) {
			$this->activity->log(
				$before['id'],
				'assigned',
				array(
					'from' => $before['assignee_id'],
					'to'   => $after['assignee_id'],
				),
				sprintf(
					/* translators: 1: previous assignee, 2: new assignee */
					__( 'Changed the assignee from "%1$s" to "%2$s".', 'taskflow' ),
					$before['assignee_id'] ? $this->display_name( (int) $before['assignee_id'] ) : '—',
					$after['assignee_id'] ? $this->display_name( (int) $after['assignee_id'] ) : '—'
				)
			);
		}

		if ( in_array( 'description', $changed, true ) ) {
			$this->activity->log(
				$before['id'],
				'updated',
				array( 'field' => 'description' ),
				__( 'Updated the description.', 'taskflow' )
			);
		}
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

	/**
	 * Project label helper for activity messages.
	 *
	 * @param int|string|null $project_id Project id.
	 * @return string
	 */
	private function project_label( $project_id ) {
		if ( empty( $project_id ) ) {
			return '—';
		}

		$project = ( new TaskFlow_Project_Repository() )->find( (int) $project_id );

		return $project ? $project['name'] : (string) $project_id;
	}
}
