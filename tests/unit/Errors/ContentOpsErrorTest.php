<?php
namespace ContentOps\Tests\Unit\Errors;

use ContentOps\Errors\ContentOpsError;
use ContentOps\Tests\Unit\TestCase;

final class ContentOpsErrorTest extends TestCase {

	public function test_error_exposes_code_message_and_context(): void {
		$error = new ContentOpsError( 'co.filter.invalid_post_type', 'Unknown post type.', [ 'post_type' => 'widget' ] );

		$this->assertSame( 'co.filter.invalid_post_type', $error->code() );
		$this->assertSame( 'Unknown post type.', $error->message() );
		$this->assertSame( [ 'post_type' => 'widget' ], $error->context() );
	}

	public function test_context_defaults_to_empty_array(): void {
		$error = new ContentOpsError( 'co.generic', 'Something failed.' );

		$this->assertSame( [], $error->context() );
	}

	public function test_to_array_returns_canonical_shape(): void {
		$error = new ContentOpsError( 'co.preview.stale_token', 'Preview token has expired.', [ 'ttl' => 300 ] );

		$this->assertSame(
			[
				'code'    => 'co.preview.stale_token',
				'message' => 'Preview token has expired.',
				'context' => [ 'ttl' => 300 ],
			],
			$error->to_array()
		);
	}

	public function test_code_must_be_non_empty(): void {
		$this->expectException( \InvalidArgumentException::class );
		new ContentOpsError( '', 'Boom.' );
	}
}
