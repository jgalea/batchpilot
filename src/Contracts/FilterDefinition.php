<?php
namespace BatchPilot\Contracts;

use InvalidArgumentException;

final class FilterDefinition {

	private string $key;
	private string $label;
	private string $type;
	/** @var array<string, mixed> */
	private array $schema;

	/**
	 * @param array<string, mixed> $schema
	 */
	public function __construct( string $key, string $label, string $type, array $schema = [] ) {
		if ( '' === $key ) {
			throw new InvalidArgumentException( 'FilterDefinition key must be non-empty.' );
		}

		$this->key    = $key;
		$this->label  = $label;
		$this->type   = $type;
		$this->schema = $schema;
	}

	public function key(): string {
		return $this->key;
	}

	public function label(): string {
		return $this->label;
	}

	public function type(): string {
		return $this->type;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function schema(): array {
		return $this->schema;
	}

	/**
	 * @return array{key: string, label: string, type: string, schema: array<string, mixed>}
	 */
	public function to_array(): array {
		return [
			'key'    => $this->key,
			'label'  => $this->label,
			'type'   => $this->type,
			'schema' => $this->schema,
		];
	}
}
