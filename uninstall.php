<?php
/**
 * Uninstall handler.
 *
 * Data removal is opt-in: tables and settings are only dropped when the
 * administrator enabled "Delete all data when the plugin is deleted" in
 * TaskFlow → Settings. Otherwise everything survives so the plugin can be
 * reinstalled with its history intact.
 *
 * This file is executed by WordPress when the plugin is deleted via the
 * Plugins screen. It must stay standalone (no TaskFlow classes loaded).
 *
 * @package TaskFlow
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$taskflow_settings = get_option( 'taskflow_settings' );
$taskflow_delete   = is_array( $taskflow_settings )
	&& ! empty( $taskflow_settings['delete_data_on_uninstall'] );

if ( ! $taskflow_delete ) {
	return; // Keep data (documented default behavior).
}

global $wpdb;

$taskflow_tables = array(
	$wpdb->prefix . 'taskflow_tasks',
	$wpdb->prefix . 'taskflow_projects',
	$wpdb->prefix . 'taskflow_tags',
	$wpdb->prefix . 'taskflow_task_tags',
	$wpdb->prefix . 'taskflow_activity',
	$wpdb->prefix . 'taskflow_comments',
	$wpdb->prefix . 'taskflow_notifications',
	$wpdb->prefix . 'taskflow_email_outbox',
	$wpdb->prefix . 'taskflow_email_inbox',
	$wpdb->prefix . 'taskflow_payments',
	$wpdb->prefix . 'taskflow_time_logs',
);

foreach ( $taskflow_tables as $taskflow_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are hardcoded above, never user input.
	$wpdb->query( "DROP TABLE IF EXISTS {$taskflow_table}" );
}

delete_option( 'taskflow_settings' );
delete_option( 'taskflow_db_version' );
delete_option( 'taskflow_version' );
delete_option( 'taskflow_activated_at' );

// Remove role capabilities.
$taskflow_caps = array( 'taskflow_manage', 'taskflow_edit_others_tasks', 'taskflow_edit_tasks' );

foreach ( array_keys( (array) wp_roles()->get_names() ) as $taskflow_role_key ) {
	$taskflow_role = get_role( $taskflow_role_key );

	if ( ! $taskflow_role ) {
		continue;
	}

	foreach ( $taskflow_caps as $taskflow_cap ) {
		$taskflow_role->remove_cap( $taskflow_cap );
	}
}
