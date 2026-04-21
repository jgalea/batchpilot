<?php
namespace ContentOps\Errors;

use InvalidArgumentException;

final class ContentOpsError {

	private string $code;
	private string $message;
	private array $context;

	public function __construct( string $code, string $message, array $context = [] ) {
		if ( '' === $code ) {
			throw new InvalidArgumentException( 'ContentOpsError code must be a non-empty string.' );
		}

		$this->code    = $code;
		$this->message = $message;
		$this->context = $context;
	}

	public function code(): string {
		return $this->code;
	}

	public function message(): string {
		return $this->message;
	}

	public function context(): array {
		return $this->context;
	}

	public function to_array(): array {
		return [
			'code'    => $this->code,
			'message' => $this->message,
			'context' => $this->context,
		];
	}

	public function to_wp_error(): \WP_Error {
		return new \WP_Error( $this->code, $this->message, $this->context );
	}
}
