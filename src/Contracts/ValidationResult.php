<?php
namespace ContentOps\Contracts;

use ContentOps\Errors\ContentOpsError;

final class ValidationResult {

	private bool $ok;
	private ?ContentOpsError $error;

	private function __construct( bool $ok, ?ContentOpsError $error ) {
		$this->ok    = $ok;
		$this->error = $error;
	}

	public static function ok(): self {
		return new self( true, null );
	}

	public static function error( ContentOpsError $error ): self {
		return new self( false, $error );
	}

	public function is_ok(): bool {
		return $this->ok;
	}

	public function get_error(): ?ContentOpsError {
		return $this->error;
	}
}
