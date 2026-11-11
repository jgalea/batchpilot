<?php
namespace BatchPilot\Targets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Contracts\FilterDefinition;
use BatchPilot\Contracts\QueryArgs;
use BatchPilot\Contracts\TargetInterface;

final class RevisionTarget implements TargetInterface {

	public function slug(): string {
		return 'revision';
	}

	public function label(): string {
		return __( 'Revisions', 'batchpilot' );
	}

	public function get_filters(): array {
		return [
			new FilterDefinition( 'ids', __( 'Specific IDs', 'batchpilot' ), 'ids', [ 'multiple' => true ] ),
			new FilterDefinition( 'author', __( 'Author', 'batchpilot' ), 'user' ),
			new FilterDefinition( 'post_parent', __( 'Revisions of post', 'batchpilot' ), 'post' ),
			new FilterDefinition( 'modified_before', __( 'Modified before', 'batchpilot' ), 'date' ),
			new FilterDefinition( 'modified_after', __( 'Modified after', 'batchpilot' ), 'date' ),
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
		$revision = get_post( $id );
		if ( null === $revision ) {
			return [
				'id'            => $id,
				'title'         => '',
				'status'        => 'missing',
				'date'          => '',
				'edit_url'      => '',
				'thumbnail_url' => null,
			];
		}

		$parent = get_post( (int) $revision->post_parent );

		return [
			'id'            => (int) $revision->ID,
			'title'         => null === $parent ? (string) $revision->post_title : (string) $parent->post_title,
			'status'        => (string) $revision->post_status,
			'date'          => (string) $revision->post_modified,
			'edit_url'      => (string) get_edit_post_link( (int) $revision->post_parent, 'raw' ),
			'thumbnail_url' => null,
		];
	}

	public function supports_operation( string $operation_slug ): bool {
		return 'delete' === $operation_slug;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_wp_query_args( QueryArgs $args ): array {
		$query = [
			'post_type'   => 'revision',
			'post_status' => 'inherit',
		];

		if ( $args->has( 'ids' ) ) {
			$ids = array_values( array_filter( array_map( 'intval', (array) $args->get( 'ids' ) ) ) );
			if ( ! empty( $ids ) ) {
				$query['post__in'] = $ids;
			}
		}
		if ( $args->has( 'author' ) ) {
			$query['author'] = (int) $args->get( 'author' );
		}
		if ( $args->has( 'post_parent' ) ) {
			$query['post_parent'] = (int) $args->get( 'post_parent' );
		}

		$date_query = [];
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

		return $query;
	}
}
