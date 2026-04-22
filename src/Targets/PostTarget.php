<?php
namespace ContentOps\Targets;

use ContentOps\Contracts\FilterDefinition;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;

final class PostTarget implements TargetInterface {

	private const SUPPORTED = [ 'delete', 'duplicate', 'edit' ];

	private string $post_type;

	public function __construct( string $post_type ) {
		$this->post_type = $post_type;
	}

	public function slug(): string {
		return $this->post_type;
	}

	public function label(): string {
		$object = get_post_type_object( $this->post_type );
		if ( null === $object || ! isset( $object->labels->name ) || '' === $object->labels->name ) {
			return $this->post_type;
		}
		return (string) $object->labels->name;
	}

	public function get_filters(): array {
		return [];
	}

	public function query( QueryArgs $args, int $limit = 0, int $offset = 0 ): array {
		return [];
	}

	public function count( QueryArgs $args ): int {
		return 0;
	}

	public function get_display( int $id ): array {
		return [];
	}

	public function supports_operation( string $operation_slug ): bool {
		return in_array( $operation_slug, self::SUPPORTED, true );
	}
}
