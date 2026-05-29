<?php
namespace BatchPilot\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Errors\BatchPilotError;

final class ValidationResult {

	private bool $ok;
	private ?BatchPilotError $error;

	private function __construct( bool $ok, ?BatchPilotError $error ) {
		$this->ok    = $ok;
		$this->error = $error;
	}

	public static function ok(): self {
		return new self( true, null );
	}

	public static function error( BatchPilotError $error ): self {
		return new self( false, $error );
	}

	public function is_ok(): bool {
		return $this->ok;
	}

	public function get_error(): ?BatchPilotError {
		return $this->error;
	}
}
