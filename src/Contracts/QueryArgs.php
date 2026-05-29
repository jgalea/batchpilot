<?php
namespace BatchPilot\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class QueryArgs {

	/** @var array<string, mixed> */
	private array $args;

	/**
	 * @param array<string, mixed> $args
	 */
	public function __construct( array $args = [] ) {
		$this->args = $args;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function from_array( array $args ): self {
		return new self( $args );
	}

	/**
	 * @param mixed $value
	 */
	public function with( string $key, $value ): self {
		$next         = $this->args;
		$next[ $key ] = $value;
		return new self( $next );
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- $default mirrors array_key_exists/get semantics.
	public function get( string $key, $default = null ) {
		return \array_key_exists( $key, $this->args ) ? $this->args[ $key ] : $default;
	}

	public function has( string $key ): bool {
		return \array_key_exists( $key, $this->args );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->args;
	}
}
