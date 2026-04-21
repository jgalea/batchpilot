<?php
namespace ContentOps\Tests\Unit\Registry;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Tests\Unit\TestCase;

final class TargetRegistryTest extends TestCase {

	public function test_register_and_retrieve(): void {
		$registry = new TargetRegistry();
		$target   = $this->fake_target( 'post' );

		$registry->register( $target );

		$this->assertSame( $target, $registry->get( 'post' ) );
		$this->assertTrue( $registry->has( 'post' ) );
	}

	public function test_duplicate_throws(): void {
		$registry = new TargetRegistry();
		$registry->register( $this->fake_target( 'post' ) );

		$this->expectException( \LogicException::class );
		$registry->register( $this->fake_target( 'post' ) );
	}

	public function test_missing_returns_null(): void {
		$registry = new TargetRegistry();
		$this->assertNull( $registry->get( 'missing' ) );
		$this->assertFalse( $registry->has( 'missing' ) );
	}

	public function test_all_preserves_insertion_order(): void {
		$registry = new TargetRegistry();
		$registry->register( $this->fake_target( 'post' ) );
		$registry->register( $this->fake_target( 'page' ) );

		$this->assertSame( [ 'post', 'page' ], array_keys( $registry->all() ) );
	}

	private function fake_target( string $slug ): TargetInterface {
		return new class( $slug ) implements TargetInterface {
			private string $slug;
			public function __construct( string $slug ) {
				$this->slug = $slug; }
			public function slug(): string {
				return $this->slug; }
			public function label(): string {
				return ucfirst( $this->slug ); }
			public function get_filters(): array {
				return []; }
			public function query( QueryArgs $args, int $limit = 0, int $offset = 0 ): array {
				return []; }
			public function count( QueryArgs $args ): int {
				return 0; }
			public function get_display( int $id ): array {
				return []; }
			public function supports_operation( string $operation_slug ): bool {
				return true; }
		};
	}
}
