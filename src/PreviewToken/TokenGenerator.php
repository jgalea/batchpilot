<?php
namespace BatchPilot\PreviewToken;

final class TokenGenerator {

	private string $salt;

	public function __construct( string $salt ) {
		$this->salt = $salt;
	}

	/**
	 * @param array<int|string, mixed> $payload
	 */
	public function generate( array $payload ): string {
		return hash_hmac( 'sha256', self::canonicalize( $payload ), $this->salt );
	}

	/**
	 * @param array<int|string, mixed> $payload
	 */
	public static function canonicalize( array $payload ): string {
		$sort = static function ( &$value ) use ( &$sort ): void {
			if ( is_array( $value ) ) {
				ksort( $value );
				foreach ( $value as &$item ) {
					$sort( $item );
				}
			}
		};
		$sort( $payload );

		return (string) wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
	}
}
