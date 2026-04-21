<?php
namespace ContentOps\History;

final class Operation {

	private int $id;
	private string $type;
	private string $target;
	private int $user_id;
	private array $filters;
	private array $params;
	private int $affected_count;
	private array $affected_ids;
	private string $status;
	private ?string $error_message;
	private string $created_at;
	private ?string $completed_at;

	private function __construct(
		int $id,
		string $type,
		string $target,
		int $user_id,
		array $filters,
		array $params,
		int $affected_count,
		array $affected_ids,
		string $status,
		?string $error_message,
		string $created_at,
		?string $completed_at
	) {
		$this->id             = $id;
		$this->type           = $type;
		$this->target         = $target;
		$this->user_id        = $user_id;
		$this->filters        = $filters;
		$this->params         = $params;
		$this->affected_count = $affected_count;
		$this->affected_ids   = $affected_ids;
		$this->status         = $status;
		$this->error_message  = $error_message;
		$this->created_at     = $created_at;
		$this->completed_at   = $completed_at;
	}

	public static function newly_created( string $type, string $target, int $user_id, array $filters, array $params ): self {
		return new self( 0, $type, $target, $user_id, $filters, $params, 0, [], 'pending', null, gmdate( 'Y-m-d H:i:s' ), null );
	}

	public static function from_row( array $row ): self {
		return new self(
			(int) $row['id'],
			(string) $row['type'],
			(string) $row['target'],
			(int) $row['user_id'],
			self::decode_json( $row['filters_json'] ?? null ),
			self::decode_json( $row['params_json'] ?? null ),
			(int) $row['affected_count'],
			self::decode_json( $row['affected_ids_json'] ?? null ),
			(string) $row['status'],
			isset( $row['error_message'] ) ? (string) $row['error_message'] : null,
			(string) $row['created_at'],
			isset( $row['completed_at'] ) ? (string) $row['completed_at'] : null
		);
	}

	public function with_id( int $id ): self {
		$clone     = clone $this;
		$clone->id = $id;
		return $clone;
	}

	public function id(): int {
		return $this->id;
	}

	public function type(): string {
		return $this->type;
	}

	public function target(): string {
		return $this->target;
	}

	public function user_id(): int {
		return $this->user_id;
	}

	public function filters(): array {
		return $this->filters;
	}

	public function params(): array {
		return $this->params;
	}

	public function affected_count(): int {
		return $this->affected_count;
	}

	public function affected_ids(): array {
		return $this->affected_ids;
	}

	public function status(): string {
		return $this->status;
	}

	public function error_message(): ?string {
		return $this->error_message;
	}

	public function created_at(): string {
		return $this->created_at;
	}

	public function completed_at(): ?string {
		return $this->completed_at;
	}

	private static function decode_json( $value ): array {
		if ( null === $value || '' === $value ) {
			return [];
		}
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}
}
