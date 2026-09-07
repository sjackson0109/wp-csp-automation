<?php
/**
 * Unit tests for WP_SAM\Intelligence\Exception_Store.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WP_SAM\Intelligence\Exception_Store;
use WP_SAM\Modules\Audit_Log;

class ExceptionStoreTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_globals();
	}

	// ── create() validation ───────────────────────────────────────────────────

	public function test_create_rejects_missing_required_fields(): void {
		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->create( array() );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	public function test_create_requires_an_expiry_date_unless_privileged_override(): void {
		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->create( $this->valid_input( array( 'expiry_date' => '' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertContains( 'An expiry date is required unless a privileged override is used.', $result['errors'] );
	}

	public function test_create_allows_a_missing_expiry_date_with_a_privileged_override(): void {
		$audit = $this->createMock( Audit_Log::class );
		$audit->expects( $this->once() )->method( 'log' );

		$store  = new Exception_Store( $audit );
		$result = $store->create(
			$this->valid_input(
				array(
					'expiry_date'            => '',
					'is_privileged_override' => true,
				)
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['id'] );
	}

	public function test_create_rejects_an_unparseable_expiry_date(): void {
		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->create( $this->valid_input( array( 'expiry_date' => 'not a date' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertContains( 'Expiry date is not a valid date.', $result['errors'] );
	}

	public function test_create_inserts_a_valid_exception_and_logs_it(): void {
		$audit = $this->createMock( Audit_Log::class );
		$audit->expects( $this->once() )
			->method( 'log' )
			->with( 'exceptions', 'exception_created', $this->stringContains( 'Widget Corp' ) );

		$store  = new Exception_Store( $audit );
		$result = $store->create( $this->valid_input( array( 'owner' => 'Widget Corp' ) ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['id'] );
		$this->assertSame( array(), $result['errors'] );
	}

	public function test_create_falls_back_to_medium_risk_for_an_invalid_value(): void {
		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->create( $this->valid_input( array( 'risk_classification' => 'catastrophic' ) ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'medium', $GLOBALS['_wpdb_inserted_rows'][0]['data']['risk_classification'] );
	}

	// ── has_active_for() ─────────────────────────────────────────────────────

	public function test_has_active_for_reflects_the_query_result(): void {
		$store = new Exception_Store( $this->createMock( Audit_Log::class ) );

		$GLOBALS['_wpdb_get_var'] = 1;
		$this->assertTrue( $store->has_active_for( 'csp_enforce', 'frontend' ) );

		$GLOBALS['_wpdb_get_var'] = 0;
		$this->assertFalse( $store->has_active_for( 'csp_enforce', 'frontend' ) );
	}

	// ── extend() ──────────────────────────────────────────────────────────────

	public function test_extend_requires_a_reason(): void {
		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->extend( 1, '2030-01-01', '' );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	public function test_extend_rejects_an_unparseable_new_expiry_date(): void {
		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->extend( 1, 'not a date', 'still needed' );

		$this->assertFalse( $result['success'] );
	}

	public function test_extend_rejects_a_non_active_exception(): void {
		$GLOBALS['_wpdb_get_row'] = array( 'id' => 1, 'review_status' => 'expired' );

		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->extend( 1, '2030-01-01', 'still needed' );

		$this->assertFalse( $result['success'] );
	}

	public function test_extend_updates_expiry_and_logs_the_reason(): void {
		$GLOBALS['_wpdb_get_row'] = array( 'id' => 1, 'review_status' => 'active' );

		$audit = $this->createMock( Audit_Log::class );
		$audit->expects( $this->once() )
			->method( 'log' )
			->with( 'exceptions', 'exception_extended', $this->stringContains( 'still needed' ) );

		$store  = new Exception_Store( $audit );
		$result = $store->extend( 1, '2030-06-15', 'still needed' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '2030-06-15 00:00:00', $GLOBALS['_wpdb_updated_rows'][0]['data']['expiry_date'] );
	}

	// ── revoke() ──────────────────────────────────────────────────────────────

	public function test_revoke_requires_a_reason(): void {
		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->revoke( 1, '', 'admin' );

		$this->assertFalse( $result['success'] );
	}

	public function test_revoke_rejects_a_non_active_exception(): void {
		$GLOBALS['_wpdb_get_row'] = array( 'id' => 1, 'review_status' => 'revoked' );

		$store  = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$result = $store->revoke( 1, 'no longer needed', 'admin' );

		$this->assertFalse( $result['success'] );
	}

	public function test_revoke_sets_revoked_fields_and_logs_the_reason(): void {
		$GLOBALS['_wpdb_get_row'] = array( 'id' => 1, 'review_status' => 'active' );

		$audit = $this->createMock( Audit_Log::class );
		$audit->expects( $this->once() )
			->method( 'log' )
			->with( 'exceptions', 'exception_revoked', $this->stringContains( 'no longer needed' ) );

		$store  = new Exception_Store( $audit );
		$result = $store->revoke( 1, 'no longer needed', 'admin' );

		$this->assertTrue( $result['success'] );
		$updated = $GLOBALS['_wpdb_updated_rows'][0]['data'];
		$this->assertSame( 'revoked', $updated['review_status'] );
		$this->assertSame( 'admin', $updated['revoked_by'] );
		$this->assertNotEmpty( $updated['revoked_at'] );
	}

	// ── expire_overdue() ──────────────────────────────────────────────────────

	public function test_expire_overdue_does_nothing_when_none_are_overdue(): void {
		$GLOBALS['_wpdb_get_results_queue'] = array( array() );

		$audit = $this->createMock( Audit_Log::class );
		$audit->expects( $this->never() )->method( 'log' );

		$store = new Exception_Store( $audit );
		$this->assertSame( 0, $store->expire_overdue() );
	}

	public function test_expire_overdue_flips_each_overdue_row_and_logs_it(): void {
		$GLOBALS['_wpdb_get_results_queue'] = array(
			array( array( 'id' => 5 ), array( 'id' => 9 ) ),
		);

		$audit = $this->createMock( Audit_Log::class );
		$audit->expects( $this->exactly( 2 ) )->method( 'log' );

		$store = new Exception_Store( $audit );
		$count = $store->expire_overdue();

		$this->assertSame( 2, $count );
		$this->assertSame( 'expired', $GLOBALS['_wpdb_updated_rows'][0]['data']['review_status'] );
		$this->assertSame( 'expired', $GLOBALS['_wpdb_updated_rows'][1]['data']['review_status'] );
	}

	// ── due_for_notice() ──────────────────────────────────────────────────────

	public function test_due_for_notice_returns_the_query_result(): void {
		$rows = array( array( 'id' => 1, 'control' => 'csp_enforce' ) );
		$GLOBALS['_wpdb_get_results_queue'] = array( $rows );

		$store = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$this->assertSame( $rows, $store->due_for_notice( 7 ) );
	}

	public function test_due_for_notice_returns_an_empty_array_when_none_are_due(): void {
		$GLOBALS['_wpdb_get_results_queue'] = array( array() );

		$store = new Exception_Store( $this->createMock( Audit_Log::class ) );
		$this->assertSame( array(), $store->due_for_notice( 7 ) );
	}

	// ── Fixtures ─────────────────────────────────────────────────────────────────

	/** @return array<string,mixed> */
	private function valid_input( array $overrides = array() ): array {
		return array_merge(
			array(
				'control'                => 'csp_enforce',
				'surface'                => 'frontend',
				'weaker_value'           => "allow 'unsafe-inline'",
				'business_justification' => 'Legacy embed cannot be updated before Q3.',
				'owner'                  => 'jane@example.test',
				'risk_classification'    => 'medium',
				'expiry_date'            => '2030-01-01',
			),
			$overrides
		);
	}
}
