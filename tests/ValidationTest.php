<?php
/**
 * Class ValidationTest
 */
class ValidationTest extends TaskFlow_TestCase {

        public function test_status_whitelist(): void {
                $this->assertSame( 'new', TaskFlow_Validation::status( 'new' ) );
                $this->assertSame( 'in_progress', TaskFlow_Validation::status( ' IN_PROGRESS ' ) );

                $error = TaskFlow_Validation::status( '<script>evil</script>' );

                $this->assertWPError( $error );
                $this->assertSame( 'taskflow_invalid_status', $error->get_error_code() );
        }

        public function test_priority_whitelist(): void {
                $this->assertSame( 'urgent', TaskFlow_Validation::priority( 'urgent' ) );
                $this->assertWPError( TaskFlow_Validation::priority( 'mega' ) );
        }

        public function test_datetime_normalization(): void {
                $this->assertNull( TaskFlow_Validation::datetime( '' ) );
                $this->assertSame( '2026-11-20 14:30:00', TaskFlow_Validation::datetime( '2026-11-20T14:30' ) );
                $this->assertSame( '2026-11-20 14:30:00', TaskFlow_Validation::datetime( '2026-11-20 14:30:00' ) );
                $this->assertWPError( TaskFlow_Validation::datetime( 'not-a-date' ) );
        }

        public function test_amount_rules(): void {
                $this->assertSame( 19.99, TaskFlow_Validation::amount( '19.99' ) );
                $this->assertWPError( TaskFlow_Validation::amount( 'abc' ) );
                $this->assertWPError( TaskFlow_Validation::amount( '-5' ) );
                $this->assertWPError( TaskFlow_Validation::amount( '0' ) );
        }

        public function test_email_rules(): void {
                $this->assertSame( 'a@b.com', TaskFlow_Validation::email( ' a@b.com ' ) );
                $this->assertWPError( TaskFlow_Validation::email( 'not-an-email' ) );
        }

        public function test_color_fallback(): void {
                $this->assertSame( '#ff0000', TaskFlow_Validation::color( '#ff0000' ) );
                $this->assertSame( '#2271b1', TaskFlow_Validation::color( 'javascript:alert(1)' ) );
        }

        public function test_id_rules(): void {
                $this->assertSame( 7, TaskFlow_Validation::id( '7' ) );
                $this->assertWPError( TaskFlow_Validation::id( 0 ) );
                // absint() mirrors WordPress core: -3 becomes 3; the REST route
                // regex (\d+) never forwards negative ids in the first place.
                $this->assertSame( 3, TaskFlow_Validation::id( -3 ) );
        }
}
