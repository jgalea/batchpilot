<?php
namespace ContentOps\Tests\Integration\Operations;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Operations\DeleteOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DeleteOperationTest extends TestCase {

	private function op(): DeleteOperation {
		return new DeleteOperation( new TokenGenerator( 'test-salt' ), new TokenStore( 300 ) );
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
}
