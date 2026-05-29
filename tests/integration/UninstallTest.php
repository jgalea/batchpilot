<?php
namespace BatchPilot\Tests\Integration;

use BatchPilot\Database\Schema;

final class UninstallTest extends TestCase {

	public function test_uninstall_with_opt_out_keeps_tables(): void {
		Schema::install();
		delete_option( 'batchpilot_delete_data_on_uninstall' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'batchpilot/batchpilot.php' );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'batchpilot_operations' ) );
		$this->assertSame( $wpdb->prefix . 'batchpilot_operations', $found );
	}
}
