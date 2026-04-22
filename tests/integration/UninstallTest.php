<?php
namespace ContentOps\Tests\Integration;

use ContentOps\Database\Schema;

final class UninstallTest extends TestCase {

	public function test_uninstall_with_opt_out_keeps_tables(): void {
		Schema::install();
		delete_option( 'content_ops_delete_data_on_uninstall' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'content-ops/content-ops.php' );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'co_operations' ) );
		$this->assertSame( $wpdb->prefix . 'co_operations', $found );
	}
}
