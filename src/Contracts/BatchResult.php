<?php
namespace ContentOps\Contracts;

use ContentOps\Errors\ContentOpsError;

final class BatchResult {

	private bool $ok;
	private int $processed;
	private int $succeeded;
	private int $failed;
	/** @var array<int|string, string> */
	private array $item_errors;
	private ?ContentOpsError $error;

	/**
	 * @param array<int|string, string> $item_errors
	 */
	private function __construct(
		bool $ok,
		int $processed,
		int $succeeded,
		int $failed,
		array $item_errors,
		?ContentOpsError $error
	) {
		$this->ok          = $ok;
		$this->processed   = $processed;
		$this->succeeded   = $succeeded;
		$this->failed      = $failed;
		$this->item_errors = $item_errors;
		$this->error       = $error;
	}

	/**
	 * @param array<int|string, string> $item_errors
	 */
	public static function of( int $processed, int $succeeded, int $failed, array $item_errors = [] ): self {
		if ( $processed !== $succeeded + $failed ) {
			throw new \InvalidArgumentException( 'BatchResult: processed must equal succeeded + failed.' );
		}
		if ( count( $item_errors ) > $failed ) {
			throw new \InvalidArgumentException( 'BatchResult: item_errors count cannot exceed failed count.' );
		}
		return new self( true, $processed, $succeeded, $failed, $item_errors, null );
	}

	public static function error( ContentOpsError $error ): self {
		return new self( false, 0, 0, 0, [], $error );
	}

	public function is_ok(): bool {
		return $this->ok;
	}

	public function processed(): int {
		return $this->processed;
	}

	public function succeeded(): int {
		return $this->succeeded;
	}

	public function failed(): int {
		return $this->failed;
	}

	/**
	 * @return array<int|string, string>
	 */
	public function item_errors(): array {
		return $this->item_errors;
	}

	public function get_error(): ?ContentOpsError {
		return $this->error;
	}
}
