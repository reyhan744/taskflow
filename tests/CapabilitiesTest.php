<?php
/**
 * Tests for role capabilities and per-task ownership rules.
 *
 * @package TaskFlow
 */

/**
 * Class CapabilitiesTest
 */
class CapabilitiesTest extends TaskFlow_TestCase {

	public function test_default_role_mapping(): void {
		$this->assertTrue( TaskFlow_Capabilities::can_manage( 1 ), 'admins manage' );
		$this->assertTrue( TaskFlow_Capabilities::can_edit_others( 2 ), 'editors edit others' );
		$this->assertTrue( TaskFlow_Capabilities::can_edit( 3 ), 'authors edit' );
		$this->assertFalse( TaskFlow_Capabilities::can_edit( 4 ), 'subscribers cannot edit' );
		$this->assertFalse( TaskFlow_Capabilities::can_manage( 2 ), 'editors do not manage' );
	}

	public function test_settings_can_grant_extra_roles(): void {
		wp_set_current_user( 4 );

		$this->assertFalse( TaskFlow_Capabilities::can_edit( 4 ) );

		TaskFlow_Settings::update( array( 'extra_edit_roles' => array( 'subscriber' ) ) );

		$this->assertTrue( TaskFlow_Capabilities::can_edit( 4 ), 'subscriber got the edit capability' );

		TaskFlow_Settings::update( array( 'extra_edit_roles' => array() ) );

		$this->assertFalse( TaskFlow_Capabilities::can_edit( 4 ), 'capability revoked again' );
	}

	public function test_owner_sees_and_edits_own_task(): void {
		wp_set_current_user( 3 );

		$task = $this->make_task( array( 'title' => 'Mine', 'created_by' => 3 ) );

		$this->assertTrue( TaskFlow_Capabilities::can_view_task( $task ) );
		$this->assertTrue( TaskFlow_Capabilities::can_edit_task( $task ) );
		$this->assertTrue( TaskFlow_Capabilities::can_delete_task( $task ) );
	}

	public function test_assignee_can_change_status_but_not_edit(): void {
		wp_set_current_user( 3 );

		$task = $this->make_task( array( 'title' => 'Assigned', 'created_by' => 1, 'assignee_id' => 3 ) );

		$this->assertTrue( TaskFlow_Capabilities::can_view_task( $task ) );
		$this->assertTrue( TaskFlow_Capabilities::can_change_status( $task ) );
		$this->assertFalse( TaskFlow_Capabilities::can_edit_task( $task ) );
		$this->assertFalse( TaskFlow_Capabilities::can_delete_task( $task ) );
	}

	public function test_outsider_cannot_touch_task(): void {
		$task = $this->make_task( array( 'title' => 'Secret', 'created_by' => 2, 'assignee_id' => 2 ) );

		wp_set_current_user( 3 );

		$this->assertFalse( TaskFlow_Capabilities::can_view_task( $task ) );
		$this->assertFalse( TaskFlow_Capabilities::can_edit_task( $task ) );
		$this->assertFalse( TaskFlow_Capabilities::can_change_status( $task ) );

		wp_set_current_user( 4 );

		$this->assertFalse( TaskFlow_Capabilities::can_view_task( $task ) );
	}

	public function test_editor_and_admin_see_everything(): void {
		$task = $this->make_task( array( 'title' => 'Anyone', 'created_by' => 3, 'assignee_id' => 3 ) );

		wp_set_current_user( 2 );
		$this->assertTrue( TaskFlow_Capabilities::can_view_task( $task ) );
		$this->assertTrue( TaskFlow_Capabilities::can_edit_task( $task ) );

		wp_set_current_user( 1 );
		$this->assertTrue( TaskFlow_Capabilities::can_view_task( $task ) );
		$this->assertTrue( TaskFlow_Capabilities::can_delete_task( $task ) );
	}

	public function test_visibility_sql_scopes_non_privileged(): void {
		wp_set_current_user( 3 );

		$sql = TaskFlow_Capabilities::visibility_sql();

		$this->assertStringContainsString( 'created_by', $sql );
		$this->assertStringContainsString( 'assignee_id', $sql );

		wp_set_current_user( 2 );

		$this->assertSame( '', TaskFlow_Capabilities::visibility_sql() );
	}
}
