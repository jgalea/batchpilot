<?php
namespace BatchPilot\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Contracts\TargetInterface;
use LogicException;

final class TargetRegistry {

	/** @var array<string, TargetInterface> */
	private array $targets = [];

	public function register( TargetInterface $target ): void {
		$slug = $target->slug();
		if ( isset( $this->targets[ $slug ] ) ) {
			throw new LogicException( sprintf( 'Target "%s" already registered.', $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing exception; slug is an internal identifier.
		}

		$this->targets[ $slug ] = $target;
	}

	public function has( string $slug ): bool {
		return isset( $this->targets[ $slug ] );
	}

	public function get( string $slug ): ?TargetInterface {
		return $this->targets[ $slug ] ?? null;
	}

	/** @return array<string, TargetInterface> */
	public function all(): array {
		return $this->targets;
	}
}
