<?php
namespace ContentOps\Contracts;

use ContentOps\Errors\ContentOpsError;

final class PreviewResult {

	private bool $ok;
	private int $count;
	/** @var int[] */
	private array $sample_ids;
	private string $preview_token;
	/** @var string[] */
	private array $warnings;
	private ?ContentOpsError $error;

	/**
	 * @param int[]    $sample_ids
	 * @param string[] $warnings
	 */
	private function __construct(
		bool $ok,
		int $count,
		array $sample_ids,
		string $preview_token,
		array $warnings,
		?ContentOpsError $error
	) {
		$this->ok            = $ok;
		$this->count         = $count;
		$this->sample_ids    = $sample_ids;
		$this->preview_token = $preview_token;
		$this->warnings      = $warnings;
		$this->error         = $error;
	}

	/**
	 * @param int[]    $sample_ids
	 * @param string[] $warnings
	 */
	public static function of( int $count, array $sample_ids, string $preview_token, array $warnings = [] ): self {
		return new self( true, $count, $sample_ids, $preview_token, $warnings, null );
	}

	public static function error( ContentOpsError $error ): self {
		return new self( false, 0, [], '', [], $error );
	}

	public function is_ok(): bool {
		return $this->ok;
	}

	public function count(): int {
		return $this->count;
	}

	/**
	 * @return int[]
	 */
	public function sample_ids(): array {
		return $this->sample_ids;
	}

	public function preview_token(): string {
		return $this->preview_token;
	}

	/**
	 * @return string[]
	 */
	public function warnings(): array {
		return $this->warnings;
	}

	public function get_error(): ?ContentOpsError {
		return $this->error;
	}
}
