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
}
