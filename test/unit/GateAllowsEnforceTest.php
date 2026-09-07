<?php
/**
 * Unit tests for Admin_UI::gate_allows_enforce() -- the private promotion
 * gate ajax_toggle_mode() consults before allowing a surface to switch from
 * report-only to enforce (GitHub issue #179). Reached via Reflection since
 * the method is private, matching this test suite's existing convention for
 * Admin_UI (see AdminUITest::test_plugin_page_hooks_use_new_top_level_title_
 * prefix()).
 *
 * Five gates, checked in order, each via $wpdb->get_var() -- tests queue
 * exactly as many values as the gate under test needs to reach, since a
 * gate that returns early never issues later gates' queries.
 *
 * ajax_toggle_mode()'s own new "reason is required" check (thin input
 * validation ahead of this gate) is not covered here: wp_send_json_error()/
 * wp_send_json_success() are not stubbed anywhere in this suite (the method
 * had no test coverage at all before this change), and adding an exit-
 * halting JSON-response stub is a larger, separate piece of test
 * infrastructure than this gate-logic change needs.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WP_SAM\Admin\Admin_UI;
use WP_SAM\Modules\Audit_Log;
use WP_SAM\Plugin;

class GateAllowsEnforceTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_globals();
	}

	public function test_allows_enforce_when_every_gate_passes(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array( 1, 0, 0, 0, 0, 0 );

		$this->assertTrue( $this->invoke_gate( 'frontend' ) );
	}

	public function test_gate1_blocks_with_no_approved_sources_or_hashes(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array( 0, 0 );

		$result = $this->invoke_gate( 'frontend' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'no approved sources or hashes', $result );
	}

	public function test_gate2_blocks_with_recent_violations(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array( 1, 0, 3 );

		$result = $this->invoke_gate( 'frontend' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '3 violation(s)', $result );
	}

	public function test_gate3_blocks_with_an_active_exception(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array( 1, 0, 0, 1 );

		$result = $this->invoke_gate( 'frontend' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'active exception', $result );
	}

	public function test_gate4_blocks_with_pending_sources(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array( 1, 0, 0, 0, 2 );

		$result = $this->invoke_gate( 'frontend' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '2 source candidates', $result );
	}

	public function test_gate4_singular_message_for_exactly_one_pending_source(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array( 1, 0, 0, 0, 1 );

		$result = $this->invoke_gate( 'frontend' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '1 source candidate ', $result );
		$this->assertStringNotContainsString( 'source candidates', $result );
	}

	public function test_gate5_blocks_with_a_recent_conflict(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array( 1, 0, 0, 0, 0, 1 );

		$result = $this->invoke_gate( 'frontend' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'competing Content-Security-Policy header', $result );
	}

	private function invoke_gate( string $surface ): bool|string {
		$reflection      = new ReflectionClass( Plugin::class );
		$plugin          = $reflection->newInstanceWithoutConstructor();
		$plugin->audit   = new Audit_Log();

		$ui     = new Admin_UI( $plugin );
		$method = new ReflectionMethod( Admin_UI::class, 'gate_allows_enforce' );
		$method->setAccessible( true );

		return $method->invoke( $ui, $surface );
	}
}
