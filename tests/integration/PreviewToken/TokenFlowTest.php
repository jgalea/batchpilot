<?php
namespace ContentOps\Tests\Integration\PreviewToken;

use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\PreviewToken\TokenVerifier;
use ContentOps\Tests\Integration\TestCase;

final class TokenFlowTest extends TestCase {

	public function test_valid_token_verifies(): void {
		$generator = new TokenGenerator( 'salt' );
		$store     = new TokenStore( 60 );
		$verifier  = new TokenVerifier( $generator, $store );

		$payload = [
			'op'  => 'delete',
			'ids' => [ 1, 2, 3 ],
		];
		$token   = $generator->generate( $payload );
		$store->store( $token, $payload );

		$this->assertTrue( $verifier->verify( $token, $payload ) );
	}

	public function test_token_invalidates_when_payload_changes(): void {
		$generator = new TokenGenerator( 'salt' );
		$store     = new TokenStore( 60 );
		$verifier  = new TokenVerifier( $generator, $store );

		$original = [
			'op'  => 'delete',
			'ids' => [ 1, 2 ],
		];
		$token    = $generator->generate( $original );
		$store->store( $token, $original );

		$this->assertFalse(
			$verifier->verify(
				$token,
				[
					'op'  => 'delete',
					'ids' => [ 1, 2, 3 ],
				]
			)
		);
	}

	public function test_consume_invalidates(): void {
		$generator = new TokenGenerator( 'salt' );
		$store     = new TokenStore( 60 );
		$verifier  = new TokenVerifier( $generator, $store );

		$payload = [
			'op'  => 'delete',
			'ids' => [ 1 ],
		];
		$token   = $generator->generate( $payload );
		$store->store( $token, $payload );

		$verifier->consume( $token );

		$this->assertFalse( $verifier->verify( $token, $payload ) );
	}

	public function test_unknown_token_fails(): void {
		$generator = new TokenGenerator( 'salt' );
		$store     = new TokenStore( 60 );
		$verifier  = new TokenVerifier( $generator, $store );

		$this->assertFalse( $verifier->verify( 'bogus', [ 'op' => 'delete' ] ) );
	}
}
