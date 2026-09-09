<?php
/**
 * Unit tests for WP_SAM\Intelligence\Exception_Scheduler.
 *
 * Exception_Scheduler owns no decision logic of its own (Exception_Store::
 * expire_overdue()/due_for_notice() are tested directly in
 * ExceptionStoreTest.php) -- what's testable here is cron wiring/self-
 * healing (mirrors RenewalSchedulerTest.php's pattern) and that
 * run_daily_check() correctly wires expiry + notification together.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WP_SAM\Intelligence\Exception_Scheduler;
use WP_SAM\Intelligence\Exception_Store;
use WP_SAM\Modules\Audit_Log;

class ExceptionSchedulerTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_globals();
	}

	// ── Cron wiring ──────────────────────────────────────────────────────────

	public function test_register_wires_the_check_hook(): void {
		( new Exception_Scheduler() )->register();

		$this->assertArrayHasKey( Exception_Scheduler::CHECK_HOOK, $GLOBALS['_wp_actions'] );
	}

	public function test_register_schedules_the_daily_check_when_not_already_scheduled(): void {
		$this->assertArrayNotHasKey( Exception_Scheduler::CHECK_HOOK, $GLOBALS['_wp_cron'] );

		( new Exception_Scheduler() )->register();

		$this->assertArrayHasKey( Exception_Scheduler::CHECK_HOOK, $GLOBALS['_wp_cron'] );
	}

	public function test_register_does_not_reschedule_when_already_scheduled(): void {
		$GLOBALS['_wp_cron'][ Exception_Scheduler::CHECK_HOOK ] = 12345;

		( new Exception_Scheduler() )->register();

		$this->assertSame( 12345, $GLOBALS['_wp_cron'][ Exception_Scheduler::CHECK_HOOK ] );
	}

	public function test_register_heals_a_cleared_cron_table(): void {
		unset( $GLOBALS['_wp_cron'][ Exception_Scheduler::CHECK_HOOK ] );

		( new Exception_Scheduler() )->register();

		$this->assertArrayHasKey( Exception_Scheduler::CHECK_HOOK, $GLOBALS['_wp_cron'] );
	}

	// ── run_daily_check() ─────────────────────────────────────────────────────

	public function test_run_daily_check_expires_overdue_exceptions(): void {
		$GLOBALS['_wpdb_get_results_queue'] = array(
			array( array( 'id' => 1 ) ), // expire_overdue()'s own query.
			array(),                      // due_for_notice()'s own query.
		);

		$store = new Exception_Store( $this->createMock( Audit_Log::class ) );
		( new Exception_Scheduler( $store ) )->run_daily_check();

		$this->assertSame( 'expired', $GLOBALS['_wpdb_updated_rows'][0]['data']['review_status'] );
	}

	public function test_run_daily_check_sends_no_email_when_nothing_is_due(): void {
		$GLOBALS['_wpdb_get_results_queue'] = array(
			array(), // expire_overdue()
			array(), // due_for_notice()
		);
		$GLOBALS['_wp_options']['admin_email'] = 'admin@example.test';

		$store = new Exception_Store( $this->createMock( Audit_Log::class ) );
		( new Exception_Scheduler( $store ) )->run_daily_check();

		$this->assertSame( array(), $GLOBALS['_wp_mail_calls'] );
	}

	public function test_run_daily_check_emails_the_configured_admin_when_something_is_due(): void {
		$GLOBALS['_wpdb_get_results_queue'] = array(
			array(), // expire_overdue()
			array(
				array(
					'control'     => 'csp_enforce',
					'surface'     => 'frontend',
					'expiry_date' => '2030-01-01 00:00:00',
					'owner'       => 'jane@example.test',
				),
			),
		);
		$GLOBALS['_wp_options']['admin_email'] = 'admin@example.test';

		$store = new Exception_Store( $this->createMock( Audit_Log::class ) );
		( new Exception_Scheduler( $store ) )->run_daily_check();

		$this->assertCount( 1, $GLOBALS['_wp_mail_calls'] );
		$this->assertSame( 'admin@example.test', $GLOBALS['_wp_mail_calls'][0]['to'] );
		$this->assertStringContainsString( 'csp_enforce', $GLOBALS['_wp_mail_calls'][0]['message'] );
	}

	public function test_run_daily_check_sends_no_email_without_a_valid_admin_address(): void {
		$GLOBALS['_wpdb_get_results_queue'] = array(
			array(), // expire_overdue()
			array( array( 'control' => 'csp_enforce', 'surface' => '', 'expiry_date' => '2030-01-01 00:00:00', 'owner' => 'x' ) ),
		);
		$GLOBALS['_wp_options']['admin_email'] = 'not-an-email';

		$store = new Exception_Store( $this->createMock( Audit_Log::class ) );
		( new Exception_Scheduler( $store ) )->run_daily_check();

		$this->assertSame( array(), $GLOBALS['_wp_mail_calls'] );
	}

	public function test_run_daily_check_prefers_the_dedicated_notify_email_option(): void {
		$GLOBALS['_wpdb_get_results_queue'] = array(
			array(), // expire_overdue()
			array( array( 'control' => 'csp_enforce', 'surface' => '', 'expiry_date' => '2030-01-01 00:00:00', 'owner' => 'x' ) ),
		);
		$GLOBALS['_wp_options']['admin_email']       = 'admin@example.test';
		$GLOBALS['_wp_options']['wp_sam_notify_email'] = 'security-team@example.test';

		$store = new Exception_Store( $this->createMock( Audit_Log::class ) );
		( new Exception_Scheduler( $store ) )->run_daily_check();

		$this->assertSame( 'security-team@example.test', $GLOBALS['_wp_mail_calls'][0]['to'] );
	}
}
