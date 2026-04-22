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
		return [
			new FilterDefinition( 'post_type', __( 'Post type', 'content-ops' ), 'enum', [ 'default' => $this->post_type ] ),
			new FilterDefinition( 'status', __( 'Status', 'content-ops' ), 'enum', [ 'multiple' => true ] ),
			new FilterDefinition( 'author', __( 'Author', 'content-ops' ), 'user' ),
			new FilterDefinition( 'modified_before', __( 'Modified before', 'content-ops' ), 'date' ),
			new FilterDefinition( 'modified_after', __( 'Modified after', 'content-ops' ), 'date' ),
			new FilterDefinition( 'published_before', __( 'Published before', 'content-ops' ), 'date' ),
			new FilterDefinition( 'published_after', __( 'Published after', 'content-ops' ), 'date' ),
			new FilterDefinition(
				'taxonomy',
				__( 'Taxonomy term', 'content-ops' ),
				'taxonomy',
				[
					'shape' => [
						'taxonomy' => 'string',
						'term_ids' => 'int[]',
					],
				]
			),
			new FilterDefinition( 'has_comments', __( 'Has comments', 'content-ops' ), 'bool' ),
			new FilterDefinition( 'has_featured_image', __( 'Has featured image', 'content-ops' ), 'bool' ),
			new FilterDefinition( 'post_parent', __( 'Post parent', 'content-ops' ), 'post' ),
			new FilterDefinition( 'has_children', __( 'Has children', 'content-ops' ), 'bool' ),
		];
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
