<?php
namespace ContentOps\Contracts;

use ContentOps\Errors\ContentOpsError;

final class UndoResult {

	private bool $ok;
	private int $restored;
	private ?ContentOpsError $error;

	private function __construct( bool $ok, int $restored, ?ContentOpsError $error ) {
		$this->ok       = $ok;
		$this->restored = $restored;
		$this->error    = $error;
	}

	public static function of( int $restored ): self {
		return new self( true, $restored, null );
	}

	public static function error( ContentOpsError $error ): self {
		return new self( false, 0, $error );
	}

	public function is_ok(): bool {
		return $this->ok;
	}

	public function restored(): int {
		return $this->restored;
	}

	public function get_error(): ?ContentOpsError {
		return $this->error;
	}
}
