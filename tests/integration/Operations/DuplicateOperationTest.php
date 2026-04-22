<?php
namespace ContentOps\Tests\Integration\Operations;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Operations\DuplicateOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DuplicateOperationTest extends TestCase {

	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ): void {
		\ContentOps\Database\Schema::install();
	}

	public static function wpTearDownAfterClass(): void {
		\ContentOps\Database\Schema::drop_all();
	}

	private function op(): DuplicateOperation {
		global $wpdb;
		return new DuplicateOperation(
			new TokenGenerator( 'test-salt' ),
			new TokenStore( 300 ),
			new \ContentOps\History\OperationRepository( $wpdb )
		);
	}

	public function test_slug_and_label(): void {
		$op = $this->op();
		$this->assertSame( 'duplicate', $op->slug() );
		$this->assertNotSame( '', $op->label() );
	}

	public function test_validate_rejects_unknown_status(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[ 'target_status' => 'banana' ]
		);
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_status', $result->get_error()->code() );
	}

	public function test_validate_rejects_missing_author(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[ 'reassign_author' => 999999 ]
		);
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_author', $result->get_error()->code() );
	}

	public function test_validate_accepts_valid_params(): void {
		$user_id = self::factory()->user->create();
		$result  = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[
				'target_status'   => 'draft',
				'reassign_author' => $user_id,
				'title_suffix'    => ' (Copy)',
			]
		);
		$this->assertTrue( $result->is_ok() );
	}

	public function test_preview_returns_count_and_token(): void {
		self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );

		$preview = $this->op()->preview(
			QueryArgs::from_array( [ 'status' => [ 'publish' ] ] ),
			[],
			new PostTarget( 'post' )
		);

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 2, $preview->count() );
		$this->assertNotSame( '', $preview->preview_token() );
	}

	public function test_params_schema_lists_all_params(): void {
		$schema = $this->op()->get_params_schema();
		$this->assertArrayHasKey( 'target_status', $schema['properties'] );
		$this->assertArrayHasKey( 'reassign_author', $schema['properties'] );
		$this->assertArrayHasKey( 'title_suffix', $schema['properties'] );
		$this->assertArrayHasKey( 'include_children', $schema['properties'] );
	}
}
