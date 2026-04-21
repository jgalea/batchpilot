<?php
namespace ContentOps\Tests\Unit\PreviewToken;

use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\Tests\Unit\TestCase;

final class TokenGeneratorTest extends TestCase {

	public function test_same_payload_produces_deterministic_token(): void {
		$generator = new TokenGenerator( 'site-salt' );

		$a = $generator->generate(
			[
				'op'  => 'delete',
				'ids' => [ 1, 2, 3 ],
			]
		);
		$b = $generator->generate(
			[
				'op'  => 'delete',
				'ids' => [ 1, 2, 3 ],
			]
		);

		$this->assertSame( $a, $b );
		$this->assertNotEmpty( $a );
	}

	public function test_different_payload_produces_different_token(): void {
		$generator = new TokenGenerator( 'site-salt' );

		$this->assertNotSame(
			$generator->generate(
				[
					'op'  => 'delete',
					'ids' => [ 1 ],
				]
			),
			$generator->generate(
				[
					'op'  => 'delete',
					'ids' => [ 2 ],
				]
			)
		);
	}

	public function test_payload_key_order_does_not_affect_token(): void {
		$generator = new TokenGenerator( 'site-salt' );

		$this->assertSame(
			$generator->generate(
				[
					'op'  => 'delete',
					'ids' => [ 1, 2 ],
				]
			),
			$generator->generate(
				[
					'ids' => [ 1, 2 ],
					'op'  => 'delete',
				]
			)
		);
	}

	public function test_different_salt_produces_different_token(): void {
		$this->assertNotSame(
			( new TokenGenerator( 'salt-a' ) )->generate( [ 'op' => 'delete' ] ),
			( new TokenGenerator( 'salt-b' ) )->generate( [ 'op' => 'delete' ] )
		);
	}
}
