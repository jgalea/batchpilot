<?php
namespace ContentOps\Tests\Integration\Operations;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Operations\DeleteOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DeleteOperationTest extends TestCase {

	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ): void {
		\ContentOps\Database\Schema::install();
	}

	public static function wpTearDownAfterClass(): void {
		\ContentOps\Database\Schema::drop_all();
	}

	private function op(): DeleteOperation {
		global $wpdb;
		return new DeleteOperation(
			new TokenGenerator( 'test-salt' ),
			new TokenStore( 300 ),
			new \ContentOps\History\OperationRepository( $wpdb )
		);
	}

	public function test_slug_and_label(): void {
		$op = $this->op();
		$this->assertSame( 'delete', $op->slug() );
		$this->assertNotSame( '', $op->label() );
	}

	public function test_params_schema_exposes_permanent_flag(): void {
		$schema = $this->op()->get_params_schema();
		$this->assertArrayHasKey( 'permanent', $schema['properties'] );
		$this->assertSame( 'boolean', $schema['properties']['permanent']['type'] );
	}

	public function test_validate_returns_ok(): void {
		$result = $this->op()->validate( QueryArgs::from_array( [] ), [] );
		$this->assertTrue( $result->is_ok() );
	}

	public function test_preview_returns_count_sample_and_token(): void {
		self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$target = new PostTarget( 'post' );
		$args   = QueryArgs::from_array( [ 'status' => [ 'draft' ] ] );

		$preview = $this->op()->preview( $args, [ 'permanent' => false ], $target );

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 3, $preview->count() );
		$this->assertCount( 3, $preview->sample_ids() );
		$this->assertNotSame( '', $preview->preview_token() );
	}

	public function test_preview_caps_sample_at_twenty(): void {
		self::factory()->post->create_many( 25, [ 'post_status' => 'draft' ] );

		$preview = $this->op()->preview(
			QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ),
			[ 'permanent' => false ],
			new PostTarget( 'post' )
		);

		$this->assertSame( 25, $preview->count() );
		$this->assertCount( 20, $preview->sample_ids() );
	}

	public function test_supports_undo_is_true(): void {
		$this->assertTrue( $this->op()->supports_undo() );
	}

	public function test_execute_batch_trashes_by_default(): void {
		$ids    = self::factory()->post->create_many( 3, [ 'post_status' => 'publish' ] );
		$target = new PostTarget( 'post' );

		$result = $this->op()->execute_batch( $ids, [ 'permanent' => false ], $target );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 3, $result->processed() );
		$this->assertSame( 3, $result->succeeded() );
		$this->assertSame( 0, $result->failed() );

		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}

	public function test_execute_batch_hard_deletes_when_permanent(): void {
		$ids    = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		$target = new PostTarget( 'post' );

		$result = $this->op()->execute_batch( $ids, [ 'permanent' => true ], $target );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->succeeded() );

		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ) );
		}
	}

	public function test_execute_batch_records_failures_for_missing_ids(): void {
		$ok_id  = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$target = new PostTarget( 'post' );

		$result = $this->op()->execute_batch( [ $ok_id, 999999 ], [ 'permanent' => false ], $target );

		$this->assertSame( 2, $result->processed() );
		$this->assertSame( 1, $result->succeeded() );
		$this->assertSame( 1, $result->failed() );
		$this->assertArrayHasKey( 999999, $result->item_errors() );
	}

	public function test_undo_restores_trashed_posts(): void {
		global $wpdb;
		$repo = new \ContentOps\History\OperationRepository( $wpdb );

		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$saved = $repo->create(
			\ContentOps\History\Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => false ] )
		);
		$repo->mark_completed( $saved->id(), $ids );

		$op     = new DeleteOperation( new TokenGenerator( 'test-salt' ), new TokenStore( 300 ), $repo );
		$result = $op->undo( $saved->id() );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->restored() );
		foreach ( $ids as $id ) {
			$this->assertNotSame( 'trash', get_post_status( $id ) );
		}
	}

	public function test_undo_rejects_permanent_deletes(): void {
		global $wpdb;
		$repo = new \ContentOps\History\OperationRepository( $wpdb );

		$saved = $repo->create(
			\ContentOps\History\Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => true ] )
		);
		$repo->mark_completed( $saved->id(), [ 123 ] );

		$op     = new DeleteOperation( new TokenGenerator( 'test-salt' ), new TokenStore( 300 ), $repo );
		$result = $op->undo( $saved->id() );

		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.undo.permanent_delete', $result->get_error()->code() );
	}

	public function test_undo_rejects_missing_operation(): void {
		global $wpdb;
		$repo = new \ContentOps\History\OperationRepository( $wpdb );

		$op     = new DeleteOperation( new TokenGenerator( 'test-salt' ), new TokenStore( 300 ), $repo );
		$result = $op->undo( 999999 );

		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.undo.not_found', $result->get_error()->code() );
	}
}
