<?php
namespace ContentOps\Contracts;

interface TargetInterface {

	public function slug(): string;

	public function label(): string;

	/** @return FilterDefinition[] */
	public function get_filters(): array;

	/** @return int[] */
	public function query( QueryArgs $args, int $limit = 0, int $offset = 0 ): array;

	public function count( QueryArgs $args ): int;

	public function get_display( int $id ): array;

	public function supports_operation( string $operation_slug ): bool;
}
