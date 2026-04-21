<?php
namespace ContentOps\PreviewToken;

final class TokenStore {

	private const TRANSIENT_PREFIX = 'co_preview_token_';

	private int $ttl_seconds;

	public function __construct( int $ttl_seconds = 300 ) {
		$this->ttl_seconds = $ttl_seconds;
	}

	public function store( string $token, array $payload ): void {
		set_transient( self::TRANSIENT_PREFIX . $token, $payload, $this->ttl_seconds );
	}

	public function retrieve( string $token ): ?array {
		$payload = get_transient( self::TRANSIENT_PREFIX . $token );
		return is_array( $payload ) ? $payload : null;
	}

	public function invalidate( string $token ): void {
		delete_transient( self::TRANSIENT_PREFIX . $token );
	}
}
