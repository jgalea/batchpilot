<?php
namespace BatchPilot\History;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Snapshot {

	private int $operation_id;
	private string $object_type;
	private int $object_id;
	private string $field;
	private ?string $old_value;

	public function __construct( int $operation_id, string $object_type, int $object_id, string $field, ?string $old_value ) {
		$this->operation_id = $operation_id;
		$this->object_type  = $object_type;
		$this->object_id    = $object_id;
		$this->field        = $field;
		$this->old_value    = $old_value;
	}

	public function operation_id(): int {
		return $this->operation_id;
	}

	public function object_type(): string {
		return $this->object_type;
	}

	public function object_id(): int {
		return $this->object_id;
	}

	public function field(): string {
		return $this->field;
	}

	public function old_value(): ?string {
		return $this->old_value;
	}
}
