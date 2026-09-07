<?php
/**
 * Unit tests for WP_SAM\Intelligence\Evidence_Exporter.
 *
 * build() orchestrates roughly two dozen wpdb calls across Security_Health
 * and its own queries. Rather than sequencing all of them, most tests rely
 * on the wpdb stub's un-queued defaults (get_var/get_row/get_results all
 * return the same static "nothing here" value for every call when no
 * queue is set) -- a perfectly valid "quiet site" fixture that needs no
 * precise call-order bookkeeping. A couple of the more interesting private
 * methods are exercised in isolation via reflection instead.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WP_SAM\Intelligence\Evidence_Exporter;

class EvidenceExporterTest extends TestCase {

	private Evidence_Exporter $exporter;

	protected function setUp(): void {
		wp_test_reset_globals();
		$GLOBALS['_wpdb_get_var']     = 0;
		$GLOBALS['_wpdb_get_row']     = null;
		$GLOBALS['_wpdb_get_results'] = array();
		$this->exporter = new Evidence_Exporter();
	}

	private function invoke( string $method ) {
		$ref = new ReflectionMethod( Evidence_Exporter::class, $method );
		$ref->setAccessible( true );
		return $ref->invoke( $this->exporter );
	}

	public function test_build_includes_every_top_level_section(): void {
		$bundle = $this->exporter->build();

		foreach ( array( 'format_version', 'exported_at', 'reporting_period', 'site_url', 'plugin_version', 'disclaimer', 'framework_context', 'health_summary', 'controls', 'exceptions', 'certificates', 'baseline', 'drift_open_count', 'recent_change_log', 'audit_log_excerpt', 'checksum' ) as $key ) {
			$this->assertArrayHasKey( $key, $bundle );
		}
	}

	// ── Reporting period + checksum (GitHub issue #178) ─────────────────────────

	public function test_build_without_a_period_reports_null_bounds(): void {
		$bundle = $this->exporter->build();

		$this->assertNull( $bundle['reporting_period']['from'] );
		$this->assertNull( $bundle['reporting_period']['to'] );
	}

	public function test_build_echoes_back_a_supplied_period(): void {
		$bundle = $this->exporter->build(
			array(
				'from' => '2026-01-01',
				'to'   => '2026-01-31',
			)
		);

		$this->assertSame( '2026-01-01', $bundle['reporting_period']['from'] );
		$this->assertSame( '2026-01-31', $bundle['reporting_period']['to'] );
	}

	public function test_build_includes_a_verifiable_sha256_checksum(): void {
		$bundle = $this->exporter->build();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $bundle['checksum'] );

		$without_checksum = $bundle;
		unset( $without_checksum['checksum'] );
		$this->assertSame( hash( 'sha256', (string) wp_json_encode( $without_checksum ) ), $bundle['checksum'] );
	}

	public function test_build_framework_context_covers_all_five_named_frameworks(): void {
		$bundle = $this->exporter->build();

		$this->assertSame(
			array( 'Cyber Essentials', 'ISO/IEC 27001', 'PCI DSS', 'OWASP ASVS', 'CIS Controls' ),
			$bundle['framework_context']
		);
	}

	public function test_build_disclaims_certification(): void {
		$bundle = $this->exporter->build();

		$this->assertStringContainsString( 'not a certification', $bundle['disclaimer'] );
	}

	public function test_build_lists_frameworks_as_context_only_not_claims(): void {
		$bundle = $this->exporter->build();

		$this->assertContains( 'ISO/IEC 27001', $bundle['framework_context'] );
		$this->assertContains( 'PCI DSS', $bundle['framework_context'] );
	}

	public function test_build_controls_section_has_the_three_pillar_groups(): void {
		$bundle = $this->exporter->build();

		$this->assertArrayHasKey( 'csp', $bundle['controls'] );
		$this->assertArrayHasKey( 'pillars', $bundle['controls'] );
		$this->assertArrayHasKey( 'traffic_controls', $bundle['controls'] );
	}

	public function test_build_baseline_is_null_when_none_approved(): void {
		$bundle = $this->exporter->build();

		$this->assertNull( $bundle['baseline'] );
	}

	// ── baseline_detail() ────────────────────────────────────────────────────

	public function test_baseline_detail_returns_null_when_no_current_baseline(): void {
		$GLOBALS['_wpdb_get_row'] = null;

		$this->assertNull( $this->invoke( 'baseline_detail' ) );
	}

	public function test_baseline_detail_returns_version_and_note_when_present(): void {
		$GLOBALS['_wpdb_get_row'] = array(
			'version_number' => 3,
			'approved_at'    => '2026-09-02 00:00:00',
			'note'           => 'Post-launch baseline',
			'is_current'     => 1,
		);

		$detail = $this->invoke( 'baseline_detail' );

		$this->assertSame( 3, $detail['version_number'] );
		$this->assertSame( 'Post-launch baseline', $detail['note'] );
	}

	// ── exceptions_detail() ──────────────────────────────────────────────────

	public function test_exceptions_detail_has_the_five_expected_buckets(): void {
		$detail = $this->invoke( 'exceptions_detail' );

		$this->assertArrayHasKey( 'ip_allow_rules', $detail );
		$this->assertArrayHasKey( 'permanent_blocks', $detail );
		$this->assertArrayHasKey( 'dependency_exceptions', $detail );
		$this->assertArrayHasKey( 'csp_overrides', $detail );
		$this->assertArrayHasKey( 'formal_exceptions', $detail );
	}

	/**
	 * exceptions_detail() issues 5 get_results() calls in a fixed order:
	 * ip_allow, permanent_blocks, dependency_exceptions, csp_overrides,
	 * formal_exceptions (GitHub issue #178's new sam_exceptions read, real
	 * since #177) -- queued precisely to prove the last one lands in the
	 * right bucket, rather than relying on the shared un-queued default
	 * every other test in this file uses.
	 */
	public function test_exceptions_detail_formal_exceptions_reads_the_exceptions_table(): void {
		$exception_row = array(
			'control'                => 'csp_enforce',
			'surface'                => 'frontend',
			'business_justification' => 'Legacy embed cannot be updated before Q3.',
			'owner'                  => 'jane@example.test',
			'risk_classification'    => 'medium',
			'expiry_date'            => '2030-01-01 00:00:00',
		);
		$GLOBALS['_wpdb_get_results_queue'] = array(
			array(), // ip_allow_rules
			array(), // permanent_blocks
			array(), // dependency_exceptions
			array(), // csp_overrides
			array( $exception_row ), // formal_exceptions
		);

		$detail = $this->invoke( 'exceptions_detail' );

		$this->assertSame( array( $exception_row ), $detail['formal_exceptions'] );
	}
}
