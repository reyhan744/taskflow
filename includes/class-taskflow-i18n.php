<?php
/**
 * Translated string dictionary handed to the Vue.js application.
 *
 * All labels are standard gettext strings: on an English site the UI is
 * English, on a Persian (fa_IR) site the bundled translation renders the UI
 * in Persian and the layout switches to RTL automatically.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_I18n
 */
class TaskFlow_I18n {

	/**
	 * The full string dictionary.
	 *
	 * @return array<string,string>
	 */
	public static function strings() {
		return array(
			// App / navigation.
			'app_title'        => __( 'TaskFlow', 'taskflow' ),
			'app_subtitle'     => __( 'Team task management', 'taskflow' ),
			'dashboard'        => __( 'Dashboard', 'taskflow' ),
			'tasks'            => __( 'Tasks', 'taskflow' ),
			'kanban'           => __( 'Kanban', 'taskflow' ),
			'projects'         => __( 'Projects', 'taskflow' ),
			'tags'             => __( 'Tags', 'taskflow' ),
			'activity'         => __( 'Activity', 'taskflow' ),
			'notifications'    => __( 'Notifications', 'taskflow' ),
			'payments_mock'    => __( 'Payments (Mock)', 'taskflow' ),
			'mail_mock'        => __( 'Mail (Mock)', 'taskflow' ),
			'settings'         => __( 'Settings', 'taskflow' ),
			'save'             => __( 'Save', 'taskflow' ),
			'cancel'           => __( 'Cancel', 'taskflow' ),
			'close'            => __( 'Close', 'taskflow' ),
			'delete'           => __( 'Delete', 'taskflow' ),
			'edit'             => __( 'Edit', 'taskflow' ),
			'create'           => __( 'Create', 'taskflow' ),
			'update'           => __( 'Update', 'taskflow' ),
			'search'           => __( 'Search', 'taskflow' ),
			'filters'          => __( 'Filters', 'taskflow' ),
			'reset'            => __( 'Reset filters', 'taskflow' ),
			'loading'          => __( 'Loading…', 'taskflow' ),
			'retry'            => __( 'Retry', 'taskflow' ),
			'confirm'          => __( 'Confirm', 'taskflow' ),
			'yes'              => __( 'Yes', 'taskflow' ),
			'no'               => __( 'No', 'taskflow' ),
			'all'              => __( 'All', 'taskflow' ),
			'none'             => __( 'None', 'taskflow' ),
			'me'               => __( 'Me', 'taskflow' ),
			'showing'          => __( 'Showing', 'taskflow' ),
			'of'               => __( 'of', 'taskflow' ),
			'previous'         => __( 'Previous', 'taskflow' ),
			'next'             => __( 'Next', 'taskflow' ),
			'mock_notice'      => __( 'MOCK/DEMO mode: no real payment gateway or mail server is connected.', 'taskflow' ),

			// Statuses / priorities.
			'status_new'       => __( 'New', 'taskflow' ),
			'status_in_progress' => __( 'In Progress', 'taskflow' ),
			'status_completed' => __( 'Completed', 'taskflow' ),
			'priority_low'     => __( 'Low', 'taskflow' ),
			'priority_medium'  => __( 'Medium', 'taskflow' ),
			'priority_high'    => __( 'High', 'taskflow' ),
			'priority_urgent'  => __( 'Urgent', 'taskflow' ),

			// Tasks.
			'new_task'         => __( 'New Task', 'taskflow' ),
			'edit_task'        => __( 'Edit Task', 'taskflow' ),
			'task_title'       => __( 'Title', 'taskflow' ),
			'task_description' => __( 'Description', 'taskflow' ),
			'task_status'      => __( 'Status', 'taskflow' ),
			'task_priority'    => __( 'Priority', 'taskflow' ),
			'task_due_date'    => __( 'Due date', 'taskflow' ),
			'task_project'     => __( 'Project', 'taskflow' ),
			'task_assignee'    => __( 'Assignee', 'taskflow' ),
			'task_tags'        => __( 'Tags', 'taskflow' ),
			'task_unassigned'  => __( 'Unassigned', 'taskflow' ),
			'no_project'       => __( 'No project', 'taskflow' ),
			'time_spent'       => __( 'Time spent', 'taskflow' ),
			'overdue'          => __( 'Overdue', 'taskflow' ),
			'due_today'        => __( 'Due today', 'taskflow' ),
			'completed_on'     => __( 'Completed on', 'taskflow' ),
			'created_by'       => __( 'Created by', 'taskflow' ),
			'created_at'       => __( 'Created', 'taskflow' ),
			'updated_at'       => __( 'Updated', 'taskflow' ),
			'title_required'   => __( 'The task title is required.', 'taskflow' ),
			'delete_task_confirm' => __( 'Delete this task? This action cannot be undone.', 'taskflow' ),
			'no_tasks'         => __( 'No tasks found. Create the first task or adjust the filters.', 'taskflow' ),
			'comments'         => __( 'Comments', 'taskflow' ),
			'add_comment'      => __( 'Add a comment… (@username to mention)', 'taskflow' ),
			'send'             => __( 'Send', 'taskflow' ),
			'history'          => __( 'History', 'taskflow' ),
			'start_timer'      => __( 'Start timer', 'taskflow' ),
			'stop_timer'       => __( 'Stop timer', 'taskflow' ),
			'timer_running'    => __( 'Timer running', 'taskflow' ),
			'move_to'          => __( 'Move to', 'taskflow' ),

			// Filters.
			'filter_status'    => __( 'Status', 'taskflow' ),
			'filter_priority'  => __( 'Priority', 'taskflow' ),
			'filter_assignee'  => __( 'Assignee', 'taskflow' ),
			'filter_project'   => __( 'Project', 'taskflow' ),
			'filter_tag'       => __( 'Tag', 'taskflow' ),
			'filter_overdue'   => __( 'Only overdue', 'taskflow' ),
			'filter_sort'      => __( 'Sort by', 'taskflow' ),
			'sort_created'     => __( 'Created', 'taskflow' ),
			'sort_updated'     => __( 'Updated', 'taskflow' ),
			'sort_due'         => __( 'Due date', 'taskflow' ),
			'sort_priority'    => __( 'Priority', 'taskflow' ),
			'sort_title'       => __( 'Title', 'taskflow' ),
			'sort_asc'         => __( 'Ascending', 'taskflow' ),
			'sort_desc'        => __( 'Descending', 'taskflow' ),
			'my_tasks'         => __( 'Assigned to me', 'taskflow' ),

			// Dashboard.
			'dash_total'       => __( 'Total tasks', 'taskflow' ),
			'dash_open'        => __( 'Open tasks', 'taskflow' ),
			'dash_completed'   => __( 'Completed', 'taskflow' ),
			'dash_overdue'     => __( 'Overdue', 'taskflow' ),
			'dash_due_today'   => __( 'Due today', 'taskflow' ),
			'dash_unassigned'  => __( 'Unassigned', 'taskflow' ),
			'dash_my_open'     => __( 'My open tasks', 'taskflow' ),
			'dash_completion'  => __( 'Completion rate', 'taskflow' ),
			'dash_by_priority' => __( 'By priority', 'taskflow' ),
			'dash_by_project'  => __( 'Projects', 'taskflow' ),
			'dash_recent'      => __( 'Recent activity', 'taskflow' ),
			'dash_time'        => __( 'Logged time (all tasks)', 'taskflow' ),
			'no_activity'      => __( 'No activity yet.', 'taskflow' ),

			// Projects / tags.
			'new_project'      => __( 'New Project', 'taskflow' ),
			'edit_project'     => __( 'Edit Project', 'taskflow' ),
			'project_name'     => __( 'Project name', 'taskflow' ),
			'project_color'    => __( 'Color', 'taskflow' ),
			'delete_project_confirm' => __( 'Delete this project? Its tasks will be kept without a project.', 'taskflow' ),
			'no_projects'      => __( 'No projects yet. Group your tasks by creating the first project.', 'taskflow' ),
			'total_tasks'      => __( 'Tasks', 'taskflow' ),
			'new_tag'          => __( 'New Tag', 'taskflow' ),
			'tag_name'         => __( 'Tag name', 'taskflow' ),
			'no_tags'          => __( 'No tags yet.', 'taskflow' ),

			// Mock payments.
			'payments_title'   => __( 'Payments (Mock Gateway)', 'taskflow' ),
			'new_payment'      => __( 'New simulated payment', 'taskflow' ),
			'payment_amount'   => __( 'Amount', 'taskflow' ),
			'payment_currency' => __( 'Currency', 'taskflow' ),
			'payment_order_ref' => __( 'Order reference (optional)', 'taskflow' ),
			'payment_simulate' => __( 'Simulated outcome', 'taskflow' ),
			'payment_status_success' => __( 'Successful', 'taskflow' ),
			'payment_status_failed'  => __( 'Failed', 'taskflow' ),
			'payment_status_pending' => __( 'Pending', 'taskflow' ),
			'payment_finalize' => __( 'Finalize', 'taskflow' ),
			'payment_finalize_success' => __( 'Finalize as successful', 'taskflow' ),
			'payment_finalize_failed'  => __( 'Finalize as failed', 'taskflow' ),
			'payment_transactions' => __( 'Transactions', 'taskflow' ),
			'payment_reference' => __( 'Gateway reference', 'taskflow' ),
			'payment_order'    => __( 'Order', 'taskflow' ),
			'payment_totals'   => __( 'Totals', 'taskflow' ),
			'no_payments'      => __( 'No simulated transactions yet.', 'taskflow' ),

			// Mock mail.
			'mail_title'       => __( 'Mail (Mock)', 'taskflow' ),
			'mail_outbox'      => __( 'Outbox', 'taskflow' ),
			'mail_inbox'       => __( 'Inbox', 'taskflow' ),
			'compose_email'    => __( 'Compose (simulated send)', 'taskflow' ),
			'simulate_incoming' => __( 'Simulate incoming e-mail', 'taskflow' ),
			'mail_to'          => __( 'To', 'taskflow' ),
			'mail_from'        => __( 'From', 'taskflow' ),
			'mail_subject'     => __( 'Subject', 'taskflow' ),
			'mail_body'        => __( 'Message', 'taskflow' ),
			'mail_sent_at'     => __( 'Sent', 'taskflow' ),
			'mail_received_at' => __( 'Received', 'taskflow' ),
			'mail_mark_read'   => __( 'Mark as read', 'taskflow' ),
			'mail_no_outbox'   => __( 'The Outbox is empty. Assignment notifications and manual sends appear here.', 'taskflow' ),
			'mail_no_inbox'    => __( 'The Inbox is empty. Use "Simulate incoming e-mail" to add a message.', 'taskflow' ),

			// Settings.
			'settings_general' => __( 'General', 'taskflow' ),
			'settings_currency' => __( 'Currency code', 'taskflow' ),
			'settings_per_page' => __( 'Tasks per page', 'taskflow' ),
			'settings_notify'  => __( 'Notifications', 'taskflow' ),
			'settings_notify_assignment' => __( 'In-app notification on assignment', 'taskflow' ),
			'settings_notify_status' => __( 'In-app notification on status change', 'taskflow' ),
			'settings_notify_comment' => __( 'In-app notification on comment / mention', 'taskflow' ),
			'settings_mock_email' => __( 'Create MOCK e-mail in Outbox on assignment', 'taskflow' ),
			'settings_roles'   => __( 'Who can use TaskFlow', 'taskflow' ),
			'settings_roles_hint' => __( 'Administrators and editors always have access. Selected roles can create tasks and edit their own.', 'taskflow' ),
			'settings_data'    => __( 'Data', 'taskflow' ),
			'settings_export_csv' => __( 'Export tasks (CSV)', 'taskflow' ),
			'settings_export_json' => __( 'Export tasks (JSON)', 'taskflow' ),
			'settings_import'  => __( 'Import tasks (CSV)', 'taskflow' ),
			'settings_import_hint' => __( 'Header row: title,description,status,priority,due_date,project,tags,assignee', 'taskflow' ),
			'settings_import_download' => __( 'Download CSV template', 'taskflow' ),
			'settings_uninstall' => __( 'Uninstall behavior', 'taskflow' ),
			'settings_uninstall_hint' => __( 'When enabled, deleting the plugin removes all TaskFlow tables and settings permanently. When disabled, data survives re-installation.', 'taskflow' ),
			'settings_delete_on_uninstall' => __( 'Delete all data when the plugin is deleted', 'taskflow' ),
			'settings_seed'    => __( 'Create DEMO data (MOCK/DEMO)', 'taskflow' ),
			'settings_seed_hint' => __( 'Adds a few clearly labeled demo tasks, projects and tags for evaluation.', 'taskflow' ),
			'settings_system'  => __( 'System information', 'taskflow' ),
			'settings_saved'   => __( 'The settings were saved.', 'taskflow' ),

			// Toasts / errors.
			'error_generic'    => __( 'Something went wrong. Please try again.', 'taskflow' ),
			'error_connection' => __( 'Connection error. Please check your internet and try again.', 'taskflow' ),
			'error_forbidden'  => __( 'You do not have permission for this action.', 'taskflow' ),
			'error_not_found'  => __( 'The item was not found. It may have been deleted.', 'taskflow' ),
			'confirm_delete'   => __( 'Are you sure?', 'taskflow' ),
			'copied'           => __( 'Copied.', 'taskflow' ),
			'just_now'         => __( 'just now', 'taskflow' ),
			'minutes_ago'      => __( 'min ago', 'taskflow' ),
			'hours_ago'        => __( 'h ago', 'taskflow' ),
			'days_ago'         => __( 'd ago', 'taskflow' ),
			'mark_all_read'    => __( 'Mark all as read', 'taskflow' ),
			'no_notifications' => __( 'No notifications yet.', 'taskflow' ),
		);
	}
}
