<?php
namespace ContentOps\Tests\Unit\Targets;

use Brain\Monkey\Functions;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Unit\TestCase;

final class PostTargetUnitTest extends TestCase {

	public function test_slug_is_post_type(): void {
		$target = new PostTarget( 'page' );
		$this->assertSame( 'page', $target->slug() );
	}

	public function test_label_falls_back_to_slug_when_post_type_missing(): void {
		Functions\when( 'get_post_type_object' )->justReturn( null );

		$target = new PostTarget( 'custom_thing' );
		$this->assertSame( 'custom_thing', $target->label() );
	}

	public function test_label_uses_plural_label_when_available(): void {
		$stub         = new \stdClass();
		$stub->labels = (object) [ 'name' => 'Products' ];
		Functions\when( 'get_post_type_object' )->justReturn( $stub );

		$target = new PostTarget( 'product' );
		$this->assertSame( 'Products', $target->label() );
	}

	public function test_supports_operation_allows_delete_duplicate_edit(): void {
		$target = new PostTarget( 'post' );
		$this->assertTrue( $target->supports_operation( 'delete' ) );
		$this->assertTrue( $target->supports_operation( 'duplicate' ) );
		$this->assertTrue( $target->supports_operation( 'edit' ) );
		$this->assertFalse( $target->supports_operation( 'move' ) );
	}
}
