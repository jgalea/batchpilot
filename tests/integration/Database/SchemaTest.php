<?php
namespace BatchPilot\Tests\Integration\Database;

use BatchPilot\Database\Schema;
use BatchPilot\Tests\Integration\TestCase;

final class SchemaTest extends TestCase {

	public function test_install_creates_all_tables(): void {
		global $wpdb;
		Schema::drop_all();

		Schema::install();

		$this->assertTableExists( $wpdb->prefix . 'batchpilot_operations' );
		$this->assertTableExists( $wpdb->prefix . 'batchpilot_snapshots' );
		$this->assertTableExists( $wpdb->prefix . 'batchpilot_schedules' );
		$this->assertSame( Schema::VERSION, get_option( Schema::VERSION_OPTION ) );
	}

	public function test_install_is_idempotent(): void {
		global $wpdb;
		Schema::install();
		Schema::install();

		$this->assertTableExists( $wpdb->prefix . 'batchpilot_operations' );
		$this->assertSame( Schema::VERSION, get_option( Schema::VERSION_OPTION ) );
	}

	public function test_drop_all_removes_tables_and_option(): void {
		global $wpdb;
		Schema::install();

		Schema::drop_all();

		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'batchpilot_operations' ) ) );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'batchpilot_snapshots' ) ) );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'batchpilot_schedules' ) ) );
		$this->assertFalse( get_option( Schema::VERSION_OPTION ) );
	}

	public function test_indices_exist_on_batchpilot_operations(): void {
		global $wpdb;
		Schema::install();

		$rows    = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->prefix}batchpilot_operations", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$indices = array_column( is_array( $rows ) ? $rows : [], 'Key_name' );
		$this->assertContains( 'user_created', $indices );
		$this->assertContains( 'status', $indices );
	}

	public function test_batchpilot_operations_has_expected_columns(): void {
		global $wpdb;
		Schema::install();
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}batchpilot_operations" );

		foreach ( [ 'id', 'type', 'target', 'user_id', 'filters_json', 'params_json', 'affected_count', 'affected_ids_json', 'status', 'error_message', 'created_at', 'completed_at' ] as $column ) {
			$this->assertContains( $column, $columns, "Missing column: {$column}" );
		}
	}

	private function assertTableExists( string $table ): void {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertSame( $table, $result );
	}
}
