<?php
/**
 * Tests for export, import, comments and time tracking.
 *
 * @package TaskFlow
 */

/**
 * Class ExportImportTest
 */
class ExportImportTest extends TaskFlow_TestCase {

	public function test_csv_contains_headers_and_rows(): void {
		$this->make_task(
			array(
				'title'    => 'CSV row',
				'priority' => 'high',
			)
		);

		$csv = ( new TaskFlow_Exporter() )->csv();

		$this->assertStringStartsWith( "\xEF\xBB\xBF", $csv, 'UTF-8 BOM for Excel' );
		$this->assertStringContainsString( 'title,description,status,priority,due_date,project,assignee,created_by,tags', $csv );
		$this->assertStringContainsString( 'CSV row', $csv );
	}

	public function test_json_export_structure(): void {
		$this->make_task( array( 'title' => 'JSON row' ) );

		$rows = ( new TaskFlow_Exporter() )->json();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'JSON row', $rows[0]['title'] );
		$this->assertArrayHasKey( 'assignee', $rows[0] );
		$this->assertArrayHasKey( 'tags', $rows[0] );
		$this->assertArrayHasKey( 'can_edit', $rows[0] );
	}

	public function test_import_creates_tasks_with_relations(): void {
		$csv  = "title,description,status,priority,due_date,project,tags,assignee\n";
		$csv .= 'Task one,Description one,in_progress,high,2026-12-01 10:00,Imported Project,alpha|beta,admin' . "\n";
		$csv .= ',No title here,new,low,,,,,' . "\n";
		$csv .= 'Task two,,bogus,bogus,,,,' . "\n";

		$report = ( new TaskFlow_Importer() )->import( $csv );

		$this->assertSame( 2, $report['created'] );
		$this->assertSame( 1, $report['failed'], 'row without title reported' );

		$tasks = ( new TaskFlow_Task_Repository() )->query( array( 'search' => 'Task' ) );

		$this->assertSame( 2, $tasks['total'] );

		$first = $tasks['items'][0]['title'] === 'Task one' ? $tasks['items'][0] : $tasks['items'][1];

		$this->assertSame( 'in_progress', $first['status'], 'valid values respected' );

		$second = $first === $tasks['items'][0] ? $tasks['items'][1] : $tasks['items'][0];

		$this->assertSame( 'new', $second['status'], 'invalid values fall back to defaults' );
		$this->assertSame( 'medium', $second['priority'] );

		$projects = ( new TaskFlow_Project_Repository() )->all();

		$this->assertCount( 1, $projects, 'project created on the fly' );
		$this->assertSame( 'Imported Project', $projects[0]['name'] );

		$tags = ( new TaskFlow_Tag_Repository() )->all();

		$this->assertCount( 2, $tags, 'alpha + beta tags created' );
	}

	public function test_import_requires_title_column(): void {
		$report = ( new TaskFlow_Importer() )->import( "name,description\nFoo,Bar\n" );

		$this->assertSame( 0, $report['created'] );
		$this->assertSame( 1, $report['failed'] );
	}

	public function test_import_empty_reports_error(): void {
		$report = ( new TaskFlow_Importer() )->import( '' );

		$this->assertSame( 1, $report['failed'] );
		$this->assertNotEmpty( $report['errors'] );
	}

	public function test_sample_csv_is_importable(): void {
		$report = ( new TaskFlow_Importer() )->import( TaskFlow_Importer::sample_csv() );

		$this->assertSame( 3, $report['created'], 'the bundled template round-trips' );
		$this->assertSame( 0, $report['failed'] );
	}
}
