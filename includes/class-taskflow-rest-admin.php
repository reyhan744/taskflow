<?php
/**
 * REST controller: settings, export/import, demo data, system info.
 * All routes require the taskflow_manage capability.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Rest_Admin
 */
class TaskFlow_Rest_Admin {

	/**
	 * Route registration.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = TASKFLOW_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'update_settings' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'export' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
					'args'                => array(
						'format' => array(
							'default'           => 'csv',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'import' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/import/sample',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'import_sample' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/seed',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'seed' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/system',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'system_info' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
			)
		);
	}

	/**
	 * GET /settings.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_settings() {
		$settings = TaskFlow_Settings::all();
		$roles    = array();

		foreach ( wp_roles()->get_names() as $role_key => $label ) {
			$roles[] = array(
				'key'   => $role_key,
				'label' => translate_user_role( $label ),
			);
		}

		return rest_ensure_response(
			array(
				'settings' => $settings,
				'roles'    => $roles,
			)
		);
	}

	/**
	 * POST /settings.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public static function update_settings( $request ) {
		$sanitized = TaskFlow_Settings::update( (array) $request->get_params() );

		return rest_ensure_response(
			array(
				'settings' => $sanitized,
				'message'  => __( 'The settings were saved.', 'taskflow' ),
			)
		);
	}

	/**
	 * GET /export?format=csv|json – raw file download.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public static function export( $request ) {
		$exporter = new TaskFlow_Exporter();
		$format   = 'json' === $request->get_param( 'format' ) ? 'json' : 'csv';
		$stamp    = gmdate( 'Ymd-His' );

		$response = new WP_REST_Response();

		if ( 'json' === $format ) {
			$data = array(
				'plugin'    => 'taskflow',
				'version'   => TASKFLOW_VERSION,
				'exported_at' => current_time( 'mysql' ),
				'tasks'     => $exporter->json(),
			);

			$response->set_data( $data );
			$response->set_headers(
				array(
					'Content-Type'        => 'application/json; charset=utf-8',
					'Content-Disposition' => 'attachment; filename="taskflow-export-' . $stamp . '.json"',
				)
			);
		} else {
			$response->set_data( $exporter->csv() );
			$response->set_headers(
				array(
					'Content-Type'        => 'text/csv; charset=utf-8',
					'Content-Disposition' => 'attachment; filename="taskflow-export-' . $stamp . '.csv"',
				)
			);
		}

		return $response;
	}

	/**
	 * POST /import – body param csv (raw text).
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public static function import( $request ) {
		$csv = (string) $request->get_param( 'csv' );

		// Also accept a multipart file upload named "file".
		$files = $request->get_file_params();
		if ( ! $csv && isset( $files['file']['tmp_name'] ) && is_uploaded_file( $files['file']['tmp_name'] ) ) {
			$check = wp_check_filetype( $files['file']['name'], array( 'csv' => 'text/csv' ) );

			if ( 'csv' !== $check['ext'] ) {
				return TaskFlow_REST::bad_request( __( 'Only .csv files are supported.', 'taskflow' ) );
			}

			$csv = (string) file_get_contents( $files['file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$report = ( new TaskFlow_Importer() )->import( $csv );

		return rest_ensure_response(
			array(
				'report'  => $report,
				'message' => sprintf(
					/* translators: 1: created count, 2: failed count */
					__( 'Import finished: %1$d created, %2$d failed.', 'taskflow' ),
					$report['created'],
					$report['failed']
				),
			)
		);
	}

	/**
	 * GET /import/sample – sample CSV download.
	 *
	 * @return WP_REST_Response
	 */
	public static function import_sample() {
		$response = new WP_REST_Response();
		$response->set_data( TaskFlow_Importer::sample_csv() );
		$response->set_headers(
			array(
				'Content-Type'        => 'text/csv; charset=utf-8',
				'Content-Disposition' => 'attachment; filename="taskflow-import-template.csv"',
			)
		);

		return $response;
	}

	/**
	 * POST /seed – create clearly-labeled DEMO data for evaluation.
	 *
	 * @return WP_REST_Response
	 */
	public static function seed() {
		$service  = new TaskFlow_Task_Service();
		$projects = new TaskFlow_Project_Repository();
		$tags     = new TaskFlow_Tag_Repository();
		$users    = TaskFlow_Rest_Utils::assignable_users();

		$project_ids = array();

		foreach ( array( 'Website', 'Mobile App' ) as $name ) {
			$existing = null;

			foreach ( $projects->all() as $project ) {
				if ( $project['name'] === $name ) {
					$existing = (int) $project['id'];
					break;
				}
			}

			$project_ids[ $name ] = $existing ? $existing : $projects->insert(
				array(
					'name'        => $name,
					'description' => __( 'DEMO project created by the seed tool (MOCK/DEMO).', 'taskflow' ),
					'color'       => 'Mobile App' === $name ? '#8e44ad' : '#2271b1',
					'created_by'  => get_current_user_id(),
					'created_at'  => current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				)
			);
		}

		$tag_ids = array();
		foreach ( array( 'design', 'bug', 'docs' ) as $tag ) {
			$tag_ids[ $tag ] = $tags->first_or_create( $tag );
		}

		$assignee = $users ? (int) $users[0]['id'] : null;
		$now      = time();

		$demo_tasks = array(
			array(
				'title'    => 'DEMO: Design the landing page',
				'status'   => 'in_progress',
				'priority' => 'high',
				'due'      => '+3 days',
			),
			array(
				'title'    => 'DEMO: Fix mobile navigation overlap',
				'status'   => 'new',
				'priority' => 'urgent',
				'due'      => '-1 day',
			),
			array(
				'title'    => 'DEMO: Write v1.0 release notes',
				'status'   => 'new',
				'priority' => 'low',
				'due'      => '+7 days',
			),
			array(
				'title'    => 'DEMO: Cross-browser QA pass',
				'status'   => 'completed',
				'priority' => 'medium',
				'due'      => '+10 days',
			),
			array(
				'title'    => 'DEMO: Prepare onboarding e-mail templates',
				'status'   => 'in_progress',
				'priority' => 'medium',
				'due'      => '+2 days',
			),
		);

		$created = 0;

		foreach ( $demo_tasks as $demo ) {
			$service->create_task(
				array(
					'title'       => $demo['title'],
					'description' => __( 'This task was generated by the demo-data seeder and is marked as MOCK/DEMO on purpose.', 'taskflow' ),
					'status'      => $demo['status'],
					'priority'    => $demo['priority'],
					'due_date'    => date( 'Y-m-d H:i:s', strtotime( $demo['due'], $now ) ),
					'project_id'  => $project_ids['Website'],
					'assignee_id' => $assignee,
				),
				array( $tag_ids['design'], $tag_ids['docs'] )
			);
			$created ++;
		}

		return rest_ensure_response(
			array(
				'created' => $created,
				'message' => sprintf(
					/* translators: %d: number of demo tasks */
					__( 'DEMO data created (%d tasks, marked as MOCK/DEMO).', 'taskflow' ),
					$created
				),
			)
		);
	}

	/**
	 * GET /system – environment summary for the Settings screen.
	 *
	 * @return WP_REST_Response
	 */
	public static function system_info() {
		global $wpdb;

		$tables = array();

		foreach ( array( 'tasks', 'projects', 'tags', 'activity', 'notifications', 'payments', 'email_outbox', 'email_inbox', 'comments', 'time_logs' ) as $name ) {
			$table           = TaskFlow_Schema::table( $name );
			$tables[ $name ] = array(
				'table' => $table,
				'rows'  => (int) $wpdb->get_var( "SELECT COUNT( * ) FROM {$table}" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			);
		}

		return rest_ensure_response(
			array(
				'plugin_version' => TASKFLOW_VERSION,
				'db_version'     => get_option( 'taskflow_db_version' ),
				'wordpress'      => get_bloginfo( 'version' ),
				'php'            => PHP_VERSION,
				'database'       => $wpdb->db_version(),
				'locale'         => get_locale(),
				'active_theme'   => wp_get_theme()->get( 'Name' ),
				'tables'         => $tables,
				'uninstall_mode' => TaskFlow_Settings::get( 'delete_data_on_uninstall' )
					? __( 'Delete all data on uninstall', 'taskflow' )
					: __( 'Keep data after uninstall', 'taskflow' ),
			)
		);
	}
}
