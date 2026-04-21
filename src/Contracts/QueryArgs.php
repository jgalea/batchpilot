<?php
namespace ContentOps\Contracts;

final class QueryArgs {

	private array $args;

	public function __construct( array $args = [] ) {
		$this->args = $args;
	}

	public static function from_array( array $args ): self {
		return new self( $args );
	}

	public function with( string $key, $value ): self {
		$next         = $this->args;
		$next[ $key ] = $value;
		return new self( $next );
	}

	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- $default mirrors array_key_exists/get semantics.
	public function get( string $key, $default = null ) {
		return \array_key_exists( $key, $this->args ) ? $this->args[ $key ] : $default;
	}

	public function has( string $key ): bool {
		return \array_key_exists( $key, $this->args );
	}

	public function to_array(): array {
		return $this->args;
	}
}
