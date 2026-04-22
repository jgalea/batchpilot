<?php
namespace ContentOps\Tests\Integration\History;

use ContentOps\Database\Schema;
use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;
use ContentOps\Tests\Integration\TestCase;

final class OperationRepositoryTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		Schema::install();
	}

	public function test_create_persists_and_assigns_id(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$op   = Operation::newly_created( 'delete', 'post', 1, [ 'status' => 'draft' ], [ 'permanent' => false ] );

		$saved = $repo->create( $op );

		$this->assertGreaterThan( 0, $saved->id() );
		$this->assertSame( 'delete', $saved->type() );
		$this->assertSame( 'post', $saved->target() );
		$this->assertSame( 'pending', $saved->status() );
	}

	public function test_mark_completed_records_affected(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$op   = $repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );

		$repo->mark_completed( $op->id(), [ 10, 11, 12 ] );

		$reloaded = $repo->find( $op->id() );
		$this->assertSame( 'completed', $reloaded->status() );
		$this->assertSame( 3, $reloaded->affected_count() );
		$this->assertSame( [ 10, 11, 12 ], $reloaded->affected_ids() );
	}

	public function test_list_returns_most_recent_first(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$a    = $repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );
		$b    = $repo->create( Operation::newly_created( 'duplicate', 'post', 1, [], [] ) );

		$list = $repo->list( 10, 0 );

		$this->assertSame( $b->id(), $list[0]->id() );
		$this->assertSame( $a->id(), $list[1]->id() );
	}

	public function test_find_missing_returns_null(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$this->assertNull( $repo->find( 999999 ) );
	}

	public function test_mark_running_transitions_status(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$op   = $repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );

		$repo->mark_running( $op->id() );

		$this->assertSame( 'running', $repo->find( $op->id() )->status() );
	}

	public function test_mark_failed_records_error_and_completes_at(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$op   = $repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );

		$repo->mark_failed( $op->id(), 'Something broke.' );

		$reloaded = $repo->find( $op->id() );
		$this->assertSame( 'failed', $reloaded->status() );
		$this->assertSame( 'Something broke.', $reloaded->error_message() );
		$this->assertNotNull( $reloaded->completed_at() );
	}
}
