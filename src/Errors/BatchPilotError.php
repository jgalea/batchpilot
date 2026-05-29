<?php
namespace BatchPilot\Errors;

use InvalidArgumentException;

final class BatchPilotError {

	private string $code;
	private string $message;
	/** @var array<string, mixed> */
	private array $context;

	/**
	 * @param array<string, mixed> $context
	 */
	public function __construct( string $code, string $message, array $context = [] ) {
		if ( '' === $code ) {
			throw new InvalidArgumentException( 'BatchPilotError code must be a non-empty string.' );
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

	/**
	 * @return array<string, mixed>
	 */
	public function context(): array {
		return $this->context;
	}

	/**
	 * @return array{code: string, message: string, context: array<string, mixed>}
	 */
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
