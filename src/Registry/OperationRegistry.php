<?php
namespace ContentOps\Registry;

use ContentOps\Contracts\OperationInterface;
use RuntimeException;

final class OperationRegistry {

	/** @var array<string, OperationInterface> */
	private array $operations = [];

	public function register( OperationInterface $operation ): void {
		$slug = $operation->slug();
		if ( isset( $this->operations[ $slug ] ) ) {
			throw new RuntimeException( sprintf( 'Operation "%s" already registered.', $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing exception; slug is an internal identifier.
		}

		$this->operations[ $slug ] = $operation;
	}

	public function has( string $slug ): bool {
		return isset( $this->operations[ $slug ] );
	}

	public function get( string $slug ): ?OperationInterface {
		return $this->operations[ $slug ] ?? null;
	}

	/** @return array<string, OperationInterface> */
	public function all(): array {
		return $this->operations;
	}
}
