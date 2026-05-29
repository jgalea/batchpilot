<?php
namespace BatchPilot\Tests\Integration\History;

use BatchPilot\Database\Schema;
use BatchPilot\History\Snapshot;
use BatchPilot\History\SnapshotRepository;
use BatchPilot\Tests\Integration\TestCase;

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

	public function test_null_old_value_round_trips_as_null(): void {
		$repo = new SnapshotRepository( $GLOBALS['wpdb'] );
		$repo->bulk_insert( [ new Snapshot( 99, 'post', 1, 'post_excerpt', null ) ] );

		$snapshots = $repo->for_operation( 99 );
		$this->assertCount( 1, $snapshots );
		$this->assertNull( $snapshots[0]->old_value() );
	}

	public function test_fields_round_trip_by_value(): void {
		$repo = new SnapshotRepository( $GLOBALS['wpdb'] );
		$repo->bulk_insert( [ new Snapshot( 77, 'post', 42, 'post_title', 'Old title' ) ] );

		$s = $repo->for_operation( 77 )[0];
		$this->assertSame( 77, $s->operation_id() );
		$this->assertSame( 'post', $s->object_type() );
		$this->assertSame( 42, $s->object_id() );
		$this->assertSame( 'post_title', $s->field() );
		$this->assertSame( 'Old title', $s->old_value() );
	}
}
