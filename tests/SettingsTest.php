<?php
/**
 * Tests for settings sanitization and validation helpers.
 *
 * @package TaskFlow
 */

/**
 * Class SettingsTest
 */
class SettingsTest extends TaskFlow_TestCase {

	public function test_defaults_installed(): void {
		$settings = TaskFlow_Settings::all();

		$this->assertSame( 'USD', $settings['currency'] );
		$this->assertSame( 10, $settings['items_per_page'] );
		$this->assertFalse( $settings['delete_data_on_uninstall'], 'data is kept by default' );
		$this->assertTrue( $settings['notify_on_assignment'] );
	}

	public function test_update_sanitizes_and_whitelists(): void {
		$stored = TaskFlow_Settings::update(
			array(
				'currency'                  => '<b>IRT</b> ',
				'items_per_page'            => 999,
				'notify_on_assignment'      => 'false',
				'delete_data_on_uninstall'  => true,
				'extra_edit_roles'          => array( 'Author', '<script>x</script>', 'administrator' ),
				'evil_key'                  => 'injected',
			)
		);

		$this->assertSame( 'IRT', $stored['currency'], 'tags stripped, trimmed' );
		$this->assertSame( 100, $stored['items_per_page'], 'clamped to 100' );
		$this->assertFalse( $stored['notify_on_assignment'], 'string false coerced' );
		$this->assertTrue( $stored['delete_data_on_uninstall'] );
		$this->assertSame( array( 'author' ), $stored['extra_edit_roles'], 'sanitized, validated against real roles, admin excluded' );
		$this->assertArrayNotHasKey( 'evil_key', $stored, 'unknown keys dropped' );
		$this->assertArrayNotHasKey( 'evil_key', get_option( 'taskflow_settings' ) );
	}

	public function test_per_page_clamped_low(): void {
		$stored = TaskFlow_Settings::update( array( 'items_per_page' => 0 ) );

		$this->assertSame( 5, $stored['items_per_page'] );
	}
}
