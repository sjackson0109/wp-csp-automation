<?php
/**
 * Smoke/regression coverage for includes/admin/views/page-baseline.php's
 * Drift tab -- no test previously rendered this view file at all.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

class PageBaselineTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_globals();
	}

	public function test_drift_tab_shows_the_no_baseline_notice_when_none_approved(): void {
		$_GET['tab']              = 'drift';
		$GLOBALS['_wpdb_get_row'] = null;

		ob_start();
		require WP_SAM_DIR . 'includes/admin/views/page-baseline.php';
		$output = (string) ob_get_clean();

		unset( $_GET['tab'] );

		$this->assertStringContainsString( 'No baseline has been approved yet', $output );
		$this->assertStringNotContainsString( 'wp-sam-drift-table', $output );
	}

	public function test_drift_tab_renders_the_dedicated_width_class_and_lists_a_drift_row(): void {
		$_GET['tab']                  = 'drift';
		$GLOBALS['_wpdb_get_row']     = array( 'id' => 1, 'is_current' => 1 );
		$GLOBALS['_wpdb_get_results'] = array( $this->drift_row() );

		ob_start();
		require WP_SAM_DIR . 'includes/admin/views/page-baseline.php';
		$output = (string) ob_get_clean();

		unset( $_GET['tab'] );

		$this->assertStringContainsString( 'wp-sam-drift-table', $output );
		$this->assertStringContainsString( 'wp-sam-drift-item', $output );
		$this->assertStringContainsString( 'frontend', $output );
		$this->assertStringContainsString( 'A site change was recorded 5 hours ago', $output );
	}

	public function test_drift_tab_empty_result_set_renders_without_fatal(): void {
		$_GET['tab']                  = 'drift';
		$GLOBALS['_wpdb_get_row']     = array( 'id' => 1, 'is_current' => 1 );
		$GLOBALS['_wpdb_get_results'] = array();

		ob_start();
		require WP_SAM_DIR . 'includes/admin/views/page-baseline.php';
		$output = (string) ob_get_clean();

		unset( $_GET['tab'] );

		$this->assertStringContainsString( 'No drift recorded for this filter.', $output );
	}

	/** @return array<string, mixed> */
	private function drift_row(): array {
		return array(
			'id'                => 7,
			'category'          => 'csp_header',
			'surface'           => 'frontend',
			'item_key'          => 'frontend',
			'risk_level'        => 'medium',
			'risk_reason'       => 'CSP header value changed',
			'correlated_change' => "A site change was recorded 5 hours ago -- see the Change Log to check whether it's related.",
			'disposition'       => 'unexplained',
			'old_value'         => "default-src 'self'",
			'new_value'         => "default-src 'self' https://example.test",
		);
	}
}
