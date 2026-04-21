<?php
namespace ContentOps\Tests\Integration\History;

use ContentOps\Database\Schema;
use ContentOps\History\Snapshot;
use ContentOps\History\SnapshotRepository;
use ContentOps\Tests\Integration\TestCase;

final class SnapshotRepositoryTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		Schema::install();
	}

	public function test_bulk_insert_and_retrieve_by_operation(): void {
		$repo      = new SnapshotRepository( $GLOBALS['wpdb'] );
		$snapshots = [
			new Snapshot( 42, 'post', 1, 'post_status', 'draft' ),
			new Snapshot( 42, 'post', 2, 'post_status', 'draft' ),
			new Snapshot( 42, 'post', 1, 'post_title', 'Old title' ),
		];

		$repo->bulk_insert( $snapshots );

		$this->assertCount( 3, $repo->for_operation( 42 ) );
	}

	public function test_delete_by_operation(): void {
		$repo = new SnapshotRepository( $GLOBALS['wpdb'] );
		$repo->bulk_insert( [ new Snapshot( 50, 'post', 1, 'post_status', 'publish' ) ] );
		$repo->bulk_insert( [ new Snapshot( 51, 'post', 1, 'post_status', 'publish' ) ] );

		$repo->delete_for_operation( 50 );

		$this->assertCount( 0, $repo->for_operation( 50 ) );
		$this->assertCount( 1, $repo->for_operation( 51 ) );
	}
}
