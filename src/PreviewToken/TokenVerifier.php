<?php
namespace ContentOps\PreviewToken;

final class TokenVerifier {

	private TokenGenerator $generator;
	private TokenStore $store;

	public function __construct( TokenGenerator $generator, TokenStore $store ) {
		$this->generator = $generator;
		$this->store     = $store;
	}

	/**
	 * @param array<int|string, mixed> $current_payload
	 */
	public function verify( string $token, array $current_payload ): bool {
		$stored = $this->store->retrieve( $token );
		if ( null === $stored ) {
			return false;
		}

		$expected = $this->generator->generate( $current_payload );

		return hash_equals( $expected, $token ) && $this->payloads_match( $stored, $current_payload );
	}

	public function consume( string $token ): void {
		$this->store->invalidate( $token );
	}

	/**
	 * @param array<int|string, mixed> $a
	 * @param array<int|string, mixed> $b
	 */
	private function payloads_match( array $a, array $b ): bool {
		return TokenGenerator::canonicalize( $a ) === TokenGenerator::canonicalize( $b );
	}
}
