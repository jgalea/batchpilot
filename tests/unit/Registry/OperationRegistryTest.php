<?php
namespace ContentOps\Tests\Unit\Registry;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\OperationInterface;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Contracts\UndoResult;
use ContentOps\Contracts\ValidationResult;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Tests\Unit\TestCase;

final class OperationRegistryTest extends TestCase {

	public function test_register_and_retrieve(): void {
		$registry  = new OperationRegistry();
		$operation = $this->fake_operation( 'delete' );

		$registry->register( $operation );

		$this->assertSame( $operation, $registry->get( 'delete' ) );
	}

	public function test_duplicate_throws(): void {
		$registry = new OperationRegistry();
		$registry->register( $this->fake_operation( 'delete' ) );

		$this->expectException( \RuntimeException::class );
		$registry->register( $this->fake_operation( 'delete' ) );
	}

	public function test_all_returns_registered(): void {
		$registry = new OperationRegistry();
		$registry->register( $this->fake_operation( 'delete' ) );
		$registry->register( $this->fake_operation( 'duplicate' ) );

		$this->assertCount( 2, $registry->all() );
	}

	private function fake_operation( string $slug ): OperationInterface {
		return new class( $slug ) implements OperationInterface {
			private string $slug;
			public function __construct( string $slug ) {
				$this->slug = $slug; }
			public function slug(): string {
				return $this->slug; }
			public function label(): string {
				return ucfirst( $this->slug ); }
			public function get_params_schema(): array {
				return []; }
			public function validate( QueryArgs $args, array $params ): ValidationResult {
				return ValidationResult::ok(); }
			public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult {
				return PreviewResult::of( 0, [], '' );
			}
			public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
				return BatchResult::of( 0, 0, 0 );
			}
			public function supports_undo(): bool {
				return false; }
			public function undo( int $operation_id ): UndoResult {
				return UndoResult::of( 0 ); }
		};
	}
}
