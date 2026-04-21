<?php
namespace ContentOps\Tests\Integration\Database;

use ContentOps\Database\Schema;
use ContentOps\Tests\Integration\TestCase;

final class SchemaTest extends TestCase {

	public function test_install_creates_all_tables(): void {
		global $wpdb;
		Schema::drop_all();

		Schema::install();

		$this->assertTableExists( $wpdb->prefix . 'co_operations' );
		$this->assertTableExists( $wpdb->prefix . 'co_snapshots' );
		$this->assertTableExists( $wpdb->prefix . 'co_schedules' );
		$this->assertSame( Schema::VERSION, get_option( Schema::VERSION_OPTION ) );
	}

	public function test_install_is_idempotent(): void {
		Schema::install();
		Schema::install();

		$this->assertTrue( true );
	}

	public function test_co_operations_has_expected_columns(): void {
		global $wpdb;
		Schema::install();
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}co_operations" );

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
