<?php
namespace BatchPilot\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Errors\BatchPilotError;

final class UndoResult {

	private bool $ok;
	private int $restored;
	private int $skipped;
	private ?BatchPilotError $error;

	private function __construct( bool $ok, int $restored, int $skipped, ?BatchPilotError $error ) {
		$this->ok       = $ok;
		$this->restored = $restored;
		$this->skipped  = $skipped;
		$this->error    = $error;
	}

	public static function of( int $restored, int $skipped = 0 ): self {
		return new self( true, $restored, $skipped, null );
	}

	public static function error( BatchPilotError $error ): self {
		return new self( false, 0, 0, $error );
	}

	public function is_ok(): bool {
		return $this->ok;
	}

	public function restored(): int {
		return $this->restored;
	}

	public function skipped(): int {
		return $this->skipped;
	}

	public function get_error(): ?BatchPilotError {
		return $this->error;
	}
}
