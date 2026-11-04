<?php
/**
 * CSV / JSON export of tasks (respects the caller's visibility scope).
 *
 * CSV output includes a UTF-8 BOM so Excel opens Persian text correctly.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Exporter
 */
class TaskFlow_Exporter {

	/**
	 * Export task list as CSV string.
	 *
	 * @return string
	 */
	public function csv() {
		$rows  = $this->task_rows();
		$head  = array(
			'id',
			'title',
			'description',
			'status',
			'priority',
			'due_date',
			'project',
			'assignee',
			'created_by',
			'tags',
			'time_spent_seconds',
			'comments_count',
			'created_at',
			'updated_at',
			'completed_at',
		);

		$lines = array( $head );

		foreach ( $rows as $task ) {
			$lines[] = array(
				$task['id'],
				$task['title'],
				$task['description'],
				$task['status'],
				$task['priority'],
				$task['due_date'],
				$task['project']['name'] ?? '',
				$task['assignee']['name'] ?? '',
				$task['creator']['name'] ?? '',
				implode( '|', wp_list_pluck( $task['tags'], 'name' ) ),
				$task['time_spent'],
				$task['comments_count'],
				$task['created_at'],
				$task['updated_at'],
				$task['completed_at'],
			);
		}

		$out = fopen( 'php://temp', 'r+' );

		foreach ( $lines as $line ) {
			fputcsv( $out, $line, ',', '"', '\\' );
		}

		rewind( $out );
		$csv = (string) stream_get_contents( $out );
		fclose( $out );

		// UTF-8 BOM for Excel compatibility with non-ASCII content.
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * Export task list as a JSON-ready array.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function json() {
		return $this->task_rows();
	}

	/**
	 * All tasks visible to the current user, fully decorated.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function task_rows() {
		$repository = new TaskFlow_Task_Repository();
		$result     = $repository->query(
			array(
				'per_page' => 200,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		return TaskFlow_Rest_Utils::decorate_tasks( $result['items'] );
	}
}
