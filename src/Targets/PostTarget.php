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
			new FilterDefinition(
				'post_type',
				__( 'Post type', 'content-ops' ),
				'enum',
				[
					'default' => $this->post_type,
					'options' => $this->post_type_options(),
				]
			),
			new FilterDefinition(
				'status',
				__( 'Status', 'content-ops' ),
				'enum',
				[
					'multiple' => true,
					'options'  => $this->status_options(),
				]
			),
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

	/**
	 * @return array<int, array{label: string, value: string}>
	 */
	private function status_options(): array {
		// The core statuses that apply to normal content. We explicitly
		// whitelist these rather than querying get_post_stati() so that
		// statuses registered for unrelated post types (Action Scheduler,
		// WooCommerce orders, etc.) don't leak into the Posts/Pages UI.
		$core_statuses = [ 'publish', 'draft', 'pending', 'private', 'future', 'trash' ];

		$options = [];
		foreach ( $core_statuses as $slug ) {
			$obj = get_post_status_object( $slug );
			if ( null !== $obj ) {
				$options[] = [
					'label' => (string) $obj->label,
					'value' => $slug,
				];
			}
		}

		return $options;
	}

	/**
	 * @return array<int, array{label: string, value: string}>
	 */
	private function post_type_options(): array {
		$obj = get_post_type_object( $this->post_type );
		if ( null === $obj ) {
			return [
				[
					'label' => $this->post_type,
					'value' => $this->post_type,
				],
			];
		}
		return [
			[
				'label' => (string) $obj->labels->name,
				'value' => $this->post_type,
			],
		];
	}

	public function query( QueryArgs $args, int $limit = 0, int $offset = 0 ): array {
		$query_args                     = $this->build_wp_query_args( $args );
		$query_args['fields']           = 'ids';
		$query_args['posts_per_page']   = $limit > 0 ? $limit : -1;
		$query_args['offset']           = $offset;
		$query_args['no_found_rows']    = true;
		$query_args['suppress_filters'] = false;

		$query = new \WP_Query( $query_args );

		return array_map( 'intval', (array) $query->posts );
	}

	public function count( QueryArgs $args ): int {
		$query_args                   = $this->build_wp_query_args( $args );
		$query_args['fields']         = 'ids';
		$query_args['posts_per_page'] = 1;
		$query_args['no_found_rows']  = false;

		$query = new \WP_Query( $query_args );

		return (int) $query->found_posts;
	}

	public function get_display( int $id ): array {
		$post = get_post( $id );
		if ( null === $post ) {
			return [
				'id'            => $id,
				'title'         => '',
				'status'        => 'missing',
				'date'          => '',
				'edit_url'      => '',
				'thumbnail_url' => null,
			];
		}

		$thumb_id  = (int) get_post_thumbnail_id( $post );
		$thumb_url = 0 === $thumb_id ? null : (string) wp_get_attachment_image_url( $thumb_id, 'thumbnail' );

		return [
			'id'            => (int) $post->ID,
			'title'         => (string) $post->post_title,
			'status'        => (string) $post->post_status,
			'date'          => (string) $post->post_date,
			'edit_url'      => (string) get_edit_post_link( $post->ID, 'raw' ),
			'thumbnail_url' => $thumb_url,
		];
	}

	public function supports_operation( string $operation_slug ): bool {
		return in_array( $operation_slug, self::SUPPORTED, true );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_wp_query_args( QueryArgs $args ): array {
		$query = [
			'post_type'   => $this->post_type,
			'post_status' => 'any',
		];

		if ( $args->has( 'status' ) ) {
			$query['post_status'] = $args->get( 'status' );
		}

		if ( $args->has( 'author' ) ) {
			$query['author'] = (int) $args->get( 'author' );
		}

		$date_query = [];
		if ( $args->has( 'published_before' ) ) {
			$date_query[] = [
				'before'    => (string) $args->get( 'published_before' ),
				'column'    => 'post_date',
				'inclusive' => true,
			];
		}
		if ( $args->has( 'published_after' ) ) {
			$date_query[] = [
				'after'     => (string) $args->get( 'published_after' ),
				'column'    => 'post_date',
				'inclusive' => true,
			];
		}
		if ( $args->has( 'modified_before' ) ) {
			$date_query[] = [
				'before'    => (string) $args->get( 'modified_before' ),
				'column'    => 'post_modified',
				'inclusive' => true,
			];
		}
		if ( $args->has( 'modified_after' ) ) {
			$date_query[] = [
				'after'     => (string) $args->get( 'modified_after' ),
				'column'    => 'post_modified',
				'inclusive' => true,
			];
		}
		if ( ! empty( $date_query ) ) {
			$query['date_query'] = $date_query;
		}

		$meta_query = [];
		if ( $args->has( 'has_featured_image' ) ) {
			$meta_query[] = true === $args->get( 'has_featured_image' )
				? [
					'key'     => '_thumbnail_id',
					'compare' => 'EXISTS',
				]
				: [
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				];
		}
		if ( ! empty( $meta_query ) ) {
			$query['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( $args->has( 'taxonomy' ) ) {
			$tax = $args->get( 'taxonomy' );
			if ( is_array( $tax ) && ! empty( $tax['taxonomy'] ) && ! empty( $tax['term_ids'] ) ) {
				$query['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => (string) $tax['taxonomy'],
						'field'    => 'term_id',
						'terms'    => array_map( 'intval', (array) $tax['term_ids'] ),
					],
				];
			}
		}

		if ( $args->has( 'post_parent' ) ) {
			$query['post_parent'] = (int) $args->get( 'post_parent' );
		}

		if ( $args->has( 'has_comments' ) ) {
			$query['comment_count'] = true === $args->get( 'has_comments' )
				? [
					'value'   => 0,
					'compare' => '>',
				]
				: 0;
		}

		if ( $args->has( 'has_children' ) ) {
			$query['co_has_children'] = (bool) $args->get( 'has_children' );
		}

		return $query;
	}
}
