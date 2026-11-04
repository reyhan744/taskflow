<?php
/**
 * Schema manager: custom tables, installed via dbDelta on activation.
 *
 * TaskFlow intentionally uses dedicated tables (not posts/meta): tasks are a
 * structured domain, queried with filters, ordering and aggregates that do not
 * map cleanly onto WP_Post. All access still goes through $wpdb->prepare().
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Schema
 */
class TaskFlow_Schema {

	/**
	 * Get a fully prefixed table name.
	 *
	 * @param string $name Short table name, e.g. "tasks".
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		$map = array(
			'tasks'         => 'taskflow_tasks',
			'projects'      => 'taskflow_projects',
			'tags'          => 'taskflow_tags',
			'task_tags'     => 'taskflow_task_tags',
			'activity'      => 'taskflow_activity',
			'comments'      => 'taskflow_comments',
			'notifications' => 'taskflow_notifications',
			'email_outbox'  => 'taskflow_email_outbox',
			'email_inbox'   => 'taskflow_email_inbox',
			'payments'      => 'taskflow_payments',
			'time_logs'     => 'taskflow_time_logs',
		);

		$short = isset( $map[ $name ] ) ? $map[ $name ] : 'taskflow_' . $name;

		return $wpdb->prefix . $short;
	}

	/**
	 * MySQL DDL used with dbDelta on activation and on upgrades.
	 *
	 * @return array<string,string>
	 */
	public static function get_schema() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		return array(
			"CREATE TABLE {$wpdb->prefix}taskflow_tasks (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(255) NOT NULL,
				description LONGTEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'new',
				priority VARCHAR(20) NOT NULL DEFAULT 'medium',
				due_date DATETIME NULL DEFAULT NULL,
				project_id BIGINT UNSIGNED NULL DEFAULT NULL,
				assignee_id BIGINT UNSIGNED NULL DEFAULT NULL,
				created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				position INT NOT NULL DEFAULT 0,
				time_spent INT NOT NULL DEFAULT 0,
				comments_count INT NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				completed_at DATETIME NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY priority (priority),
				KEY project_id (project_id),
				KEY assignee_id (assignee_id),
				KEY created_by (created_by),
				KEY due_date (due_date)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_projects (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(191) NOT NULL,
				description TEXT NULL,
				color VARCHAR(7) NOT NULL DEFAULT '#2271b1',
				created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY created_by (created_by)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_tags (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(191) NOT NULL,
				slug VARCHAR(191) NOT NULL,
				color VARCHAR(7) NOT NULL DEFAULT '#2271b1',
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_task_tags (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				task_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				tag_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY task_tag (task_id, tag_id),
				KEY tag_id (tag_id)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_activity (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				task_id BIGINT UNSIGNED NULL DEFAULT NULL,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				type VARCHAR(30) NOT NULL DEFAULT 'updated',
				meta LONGTEXT NULL,
				message TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY task_id (task_id),
				KEY user_id (user_id),
				KEY type (type)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_comments (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				task_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				content TEXT NOT NULL,
				mentions LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY task_id (task_id),
				KEY user_id (user_id)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_notifications (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				task_id BIGINT UNSIGNED NULL DEFAULT NULL,
				type VARCHAR(30) NOT NULL DEFAULT 'info',
				message TEXT NOT NULL,
				is_read TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY is_read (is_read)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_email_outbox (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				to_email VARCHAR(191) NOT NULL,
				subject VARCHAR(255) NOT NULL,
				body LONGTEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'sent',
				error TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				sent_at DATETIME NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY status (status)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_email_inbox (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				from_email VARCHAR(191) NOT NULL,
				from_name VARCHAR(191) NULL DEFAULT NULL,
				subject VARCHAR(255) NOT NULL,
				body LONGTEXT NULL,
				is_read TINYINT(1) NOT NULL DEFAULT 0,
				received_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY is_read (is_read)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_payments (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				order_ref VARCHAR(64) NOT NULL,
				amount DECIMAL(14,2) NOT NULL DEFAULT 0,
				currency VARCHAR(8) NOT NULL DEFAULT 'USD',
				status VARCHAR(20) NOT NULL DEFAULT 'pending',
				gateway_ref VARCHAR(64) NULL DEFAULT NULL,
				message TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY status (status),
				KEY order_ref (order_ref)
			) $charset",
			"CREATE TABLE {$wpdb->prefix}taskflow_time_logs (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				task_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				started_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				ended_at DATETIME NULL DEFAULT NULL,
				duration INT NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY task_id (task_id),
				KEY user_id (user_id)
			) $charset",
		);
	}

	/**
	 * Create / update tables and keep the schema version.
	 *
	 * @return void
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$schema = self::get_schema();

		foreach ( $schema as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'taskflow_db_version', TASKFLOW_DB_VERSION, false );
	}

	/**
	 * Drop every TaskFlow table. Only called from uninstall when the admin
	 * explicitly enabled "delete data on uninstall".
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		foreach ( array_keys( self::table_map_for_drop() ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are internal constants, never user input.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'taskflow_db_version' );
		delete_option( 'taskflow_settings' );
	}

	/**
	 * Prefixed table names for drop operations.
	 *
	 * @return array<string,bool>
	 */
	private static function table_map_for_drop() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'taskflow_tasks'         => true,
			$wpdb->prefix . 'taskflow_projects'      => true,
			$wpdb->prefix . 'taskflow_tags'          => true,
			$wpdb->prefix . 'taskflow_task_tags'     => true,
			$wpdb->prefix . 'taskflow_activity'      => true,
			$wpdb->prefix . 'taskflow_comments'      => true,
			$wpdb->prefix . 'taskflow_notifications' => true,
			$wpdb->prefix . 'taskflow_email_outbox'  => true,
			$wpdb->prefix . 'taskflow_email_inbox'   => true,
			$wpdb->prefix . 'taskflow_payments'      => true,
			$wpdb->prefix . 'taskflow_time_logs'     => true,
		);

		return $tables;
	}

	/**
	 * SQLite-compatible DDL. Used only by the automated test suite, which runs
	 * the repositories against an in-memory SQLite database through a wpdb
	 * shim. Column definitions mirror get_schema() one-to-one.
	 *
	 * @return array<string,string>
	 */
	public static function get_sqlite_schema() {
		return array(
			'CREATE TABLE IF NOT EXISTS taskflow_tasks (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				title TEXT NOT NULL,
				description TEXT NULL,
				status TEXT NOT NULL DEFAULT "new",
				priority TEXT NOT NULL DEFAULT "medium",
				due_date TEXT NULL,
				project_id INTEGER NULL,
				assignee_id INTEGER NULL,
				created_by INTEGER NOT NULL DEFAULT 0,
				position INTEGER NOT NULL DEFAULT 0,
				time_spent INTEGER NOT NULL DEFAULT 0,
				comments_count INTEGER NOT NULL DEFAULT 0,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL,
				completed_at TEXT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_projects (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name TEXT NOT NULL,
				description TEXT NULL,
				color TEXT NOT NULL DEFAULT "#2271b1",
				created_by INTEGER NOT NULL DEFAULT 0,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_tags (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name TEXT NOT NULL,
				slug TEXT NOT NULL UNIQUE,
				color TEXT NOT NULL DEFAULT "#2271b1",
				created_at TEXT NOT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_task_tags (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				task_id INTEGER NOT NULL DEFAULT 0,
				tag_id INTEGER NOT NULL DEFAULT 0,
				UNIQUE (task_id, tag_id)
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_activity (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				task_id INTEGER NULL,
				user_id INTEGER NOT NULL DEFAULT 0,
				type TEXT NOT NULL DEFAULT "updated",
				meta TEXT NULL,
				message TEXT NULL,
				created_at TEXT NOT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_comments (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				task_id INTEGER NOT NULL DEFAULT 0,
				user_id INTEGER NOT NULL DEFAULT 0,
				content TEXT NOT NULL,
				mentions TEXT NULL,
				created_at TEXT NOT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_notifications (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NOT NULL DEFAULT 0,
				task_id INTEGER NULL,
				type TEXT NOT NULL DEFAULT "info",
				message TEXT NOT NULL,
				is_read INTEGER NOT NULL DEFAULT 0,
				created_at TEXT NOT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_email_outbox (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				to_email TEXT NOT NULL,
				subject TEXT NOT NULL,
				body TEXT NULL,
				status TEXT NOT NULL DEFAULT "sent",
				error TEXT NULL,
				created_at TEXT NOT NULL,
				sent_at TEXT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_email_inbox (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				from_email TEXT NOT NULL,
				from_name TEXT NULL,
				subject TEXT NOT NULL,
				body TEXT NULL,
				is_read INTEGER NOT NULL DEFAULT 0,
				received_at TEXT NOT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_payments (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				order_ref TEXT NOT NULL,
				amount REAL NOT NULL DEFAULT 0,
				currency TEXT NOT NULL DEFAULT "USD",
				status TEXT NOT NULL DEFAULT "pending",
				gateway_ref TEXT NULL,
				message TEXT NULL,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)',
			'CREATE TABLE IF NOT EXISTS taskflow_time_logs (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				task_id INTEGER NOT NULL DEFAULT 0,
				user_id INTEGER NOT NULL DEFAULT 0,
				started_at TEXT NOT NULL,
				ended_at TEXT NULL,
				duration INTEGER NOT NULL DEFAULT 0
			)',
		);
	}
}
