<?php
namespace ContentOps\Tests\Integration\Operations;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Operations\BulkEditOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class BulkEditOperationTest extends TestCase {

	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ): void {
		\ContentOps\Database\Schema::install();
	}

	public static function wpTearDownAfterClass(): void {
		\ContentOps\Database\Schema::drop_all();
	}

	private function op(): BulkEditOperation {
		global $wpdb;
		return new BulkEditOperation(
			new TokenGenerator( 'test-salt' ),
			new TokenStore( 300 ),
			new \ContentOps\History\OperationRepository( $wpdb ),
			new \ContentOps\History\SnapshotRepository( $wpdb )
		);
	}

	public function test_slug_and_label(): void {
		$op = $this->op();
		$this->assertSame( 'edit', $op->slug() );
		$this->assertNotSame( '', $op->label() );
	}

	public function test_validate_rejects_unknown_status(): void {
		$result = $this->op()->validate( QueryArgs::from_array( [] ), [ 'set_status' => 'banana' ] );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_status', $result->get_error()->code() );
	}

	public function test_validate_rejects_non_integer_shift(): void {
		$result = $this->op()->validate( QueryArgs::from_array( [] ), [ 'shift_dates_days' => 'lots' ] );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_shift', $result->get_error()->code() );
	}

	public function test_validate_rejects_bad_comment_status(): void {
		$result = $this->op()->validate( QueryArgs::from_array( [] ), [ 'comment_status' => 'maybe' ] );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_comment_status', $result->get_error()->code() );
	}

	public function test_validate_rejects_unknown_taxonomy(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[
				'taxonomy_add' => [
					'taxonomy' => 'fake_tax',
					'term_ids' => [ 1 ],
				],
			]
		);
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_taxonomy', $result->get_error()->code() );
	}

	public function test_validate_accepts_combined_params(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[
				'set_status'       => 'draft',
				'shift_dates_days' => 7,
				'comment_status'   => 'closed',
				'taxonomy_add'     => [
					'taxonomy' => 'category',
					'term_ids' => [ 1 ],
				],
			]
		);
		$this->assertTrue( $result->is_ok() );
	}

	public function test_preview_returns_expected_shape(): void {
		self::factory()->post->create_many( 4, [ 'post_status' => 'publish' ] );
		$preview = $this->op()->preview(
			QueryArgs::from_array( [ 'status' => [ 'publish' ] ] ),
			[ 'set_status' => 'draft' ],
			new PostTarget( 'post' )
		);

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 4, $preview->count() );
	}

	public function test_execute_batch_changes_status_and_snapshots_old_value(): void {
		global $wpdb;
		$repo   = new \ContentOps\History\OperationRepository( $wpdb );
		$op_row = $repo->create( \ContentOps\History\Operation::newly_created( 'edit', 'post', 0, [], [] ) );
		$ids    = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );

		$result = $this->op()->execute_batch(
			$ids,
			[
				'set_status'     => 'draft',
				'__operation_id' => $op_row->id(),
			],
			new PostTarget( 'post' )
		);

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->succeeded() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'draft', get_post_status( $id ) );
		}

		$snapshots = ( new \ContentOps\History\SnapshotRepository( $wpdb ) )->for_operation( $op_row->id() );
		$this->assertCount( 2, $snapshots );
		foreach ( $snapshots as $snap ) {
			$this->assertSame( 'post_status', $snap->field() );
			$this->assertSame( 'publish', $snap->old_value() );
		}
	}

	public function test_execute_batch_shifts_dates(): void {
		global $wpdb;
		$repo   = new \ContentOps\History\OperationRepository( $wpdb );
		$op_row = $repo->create( \ContentOps\History\Operation::newly_created( 'edit', 'post', 0, [], [] ) );
		$id     = (int) self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_date'     => '2024-06-01 10:00:00',
				'post_date_gmt' => '2024-06-01 10:00:00',
			]
		);

		$this->op()->execute_batch(
			[ $id ],
			[
				'shift_dates_days' => 7,
				'__operation_id'   => $op_row->id(),
			],
			new PostTarget( 'post' )
		);

		$this->assertSame( '2024-06-08 10:00:00', get_post( $id )->post_date );
	}

	public function test_execute_batch_adds_taxonomy_terms(): void {
		global $wpdb;
		$repo    = new \ContentOps\History\OperationRepository( $wpdb );
		$op_row  = $repo->create( \ContentOps\History\Operation::newly_created( 'edit', 'post', 0, [], [] ) );
		$id      = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = (int) self::factory()->term->create( [ 'taxonomy' => 'category' ] );

		$this->op()->execute_batch(
			[ $id ],
			[
				'taxonomy_add'   => [
					'taxonomy' => 'category',
					'term_ids' => [ $term_id ],
				],
				'__operation_id' => $op_row->id(),
			],
			new PostTarget( 'post' )
		);

		$terms = wp_get_post_terms( $id, 'category', [ 'fields' => 'ids' ] );
		$this->assertContains( $term_id, array_map( 'intval', $terms ) );
	}
}
