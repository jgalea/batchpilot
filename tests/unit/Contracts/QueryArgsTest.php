<?php
namespace ContentOps\Tests\Unit\Contracts;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Tests\Unit\TestCase;

final class QueryArgsTest extends TestCase {

	public function test_empty_args_return_empty_array(): void {
		$this->assertSame( [], ( new QueryArgs() )->to_array() );
	}

	public function test_with_returns_new_instance(): void {
		$args    = new QueryArgs();
		$updated = $args->with( 'post_type', 'post' );

		$this->assertNotSame( $args, $updated );
		$this->assertSame( [], $args->to_array() );
		$this->assertSame( [ 'post_type' => 'post' ], $updated->to_array() );
	}

	public function test_get_returns_default_when_missing(): void {
		$args = ( new QueryArgs() )->with( 'status', 'draft' );

		$this->assertSame( 'draft', $args->get( 'status' ) );
		$this->assertNull( $args->get( 'post_type' ) );
		$this->assertSame( 'fallback', $args->get( 'post_type', 'fallback' ) );
	}

	public function test_from_array_creates_instance(): void {
		$args = QueryArgs::from_array(
			[
				'a' => 1,
				'b' => 2,
			]
		);
		$this->assertSame(
			[
				'a' => 1,
				'b' => 2,
			],
			$args->to_array()
		);
	}

	public function test_has_reports_presence_even_for_null(): void {
		$args = ( new QueryArgs() )->with( 'key', null );

		$this->assertTrue( $args->has( 'key' ) );
		$this->assertFalse( $args->has( 'other' ) );
	}
}
