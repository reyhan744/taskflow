<?php
/**
 * Shared test case: clean tables + fresh users before every test.
 *
 * @package TaskFlow
 */

use PHPUnit\Framework\TestCase;

/**
 * Class TaskFlow_TestCase
 */
abstract class TaskFlow_TestCase extends TestCase {

        /**
         * Clean all tables and reset the fake users before every test.
         *
         * @return void
         */
        protected function setUp(): void {
                global $wpdb;

                foreach (
                        array(
                                'tasks',
                                'projects',
                                'tags',
                                'task_tags',
                                'activity',
                                'comments',
                                'notifications',
                                'email_outbox',
                                'email_inbox',
                                'payments',
                                'time_logs',
                        ) as $table
                ) {
                        $wpdb->query( 'DELETE FROM ' . TaskFlow_Schema::table( $table ) );
                }

                taskflow_test_reset_users();
                $GLOBALS['taskflow_options'] = array();
                TaskFlow_Settings::install_defaults();
                TaskFlow_Capabilities::apply_role_caps( TaskFlow_Settings::all() );
                wp_set_current_user( 1 );
        }

        /**
         * Create a task directly through the service with sensible defaults.
         *
         * @param array $overrides Column overrides.
         * @param array $tag_ids   Tag ids.
         * @return array<string,mixed> Created row.
         */
        protected function make_task( $overrides = array(), $tag_ids = array() ) {
                $defaults = array(
                        'title'    => 'Test task',
                        'status'   => 'new',
                        'priority' => 'medium',
                );

                $data = array_merge( $defaults, $overrides );

                return ( new TaskFlow_Task_Service() )->create_task( $data, $tag_ids );
        }

        /**
         * Assertion helper mirroring WP test toolkit.
         *
         * @param mixed $thing Value.
         * @return void
         */
        protected function assertWPError( $thing ): void {
                $this->assertTrue( is_wp_error( $thing ), 'Expected a WP_Error.' );
        }

        /**
         * Assertion helper mirroring WP test toolkit.
         *
         * @param mixed $thing Value.
         * @return void
         */
        protected function assertNotWPError( $thing ): void {
                $this->assertFalse( is_wp_error( $thing ), 'Expected a non-WP_Error value.' );
        }
}
