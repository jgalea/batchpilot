<?php
namespace BatchPilot\Tests\Unit\Targets;

use Brain\Monkey\Functions;
use BatchPilot\Contracts\QueryArgs;
use BatchPilot\Targets\PostTarget;
use BatchPilot\Tests\Unit\TestCase;

final class PostTargetUnitTest extends TestCase {

	public function test_slug_is_post_type(): void {
		$target = new PostTarget( 'page' );
		$this->assertSame( 'page', $target->slug() );
	}

	public function test_label_falls_back_to_slug_when_post_type_missing(): void {
		Functions\when( 'get_post_type_object' )->justReturn( null );

		$target = new PostTarget( 'custom_thing' );
		$this->assertSame( 'custom_thing', $target->label() );
	}

	public function test_label_uses_plural_label_when_available(): void {
		$stub         = new \stdClass();
		$stub->labels = (object) [ 'name' => 'Products' ];
		Functions\when( 'get_post_type_object' )->justReturn( $stub );

		$target = new PostTarget( 'product' );
		$this->assertSame( 'Products', $target->label() );
	}

	public function test_supports_operation_allows_delete_duplicate_edit(): void {
		$target = new PostTarget( 'post' );
		$this->assertTrue( $target->supports_operation( 'delete' ) );
		$this->assertTrue( $target->supports_operation( 'duplicate' ) );
		$this->assertTrue( $target->supports_operation( 'edit' ) );
		$this->assertFalse( $target->supports_operation( 'move' ) );
	}

	public function test_get_filters_returns_expected_keys(): void {
		$this->stubFilterVocab();

		$target  = new PostTarget( 'post' );
		$filters = $target->get_filters();

		$keys = array_map( static fn ( $f ) => $f->key(), $filters );

		$this->assertSame(
			[
				'ids',
				'post_type',
				'status',
				'author',
				'modified_before',
				'modified_after',
				'published_before',
				'published_after',
				'taxonomy',
				'has_comments',
				'has_featured_image',
				'post_parent',
				'has_children',
				'search',
				'meta',
			],
			$keys
		);
	}

	public function test_get_filters_types_are_declared(): void {
		$this->stubFilterVocab();

		$target = new PostTarget( 'post' );
		$by_key = [];
		foreach ( $target->get_filters() as $filter ) {
			$by_key[ $filter->key() ] = $filter->type();
		}

		$this->assertSame( 'ids', $by_key['ids'] );
		$this->assertSame( 'enum', $by_key['status'] );
		$this->assertSame( 'user', $by_key['author'] );
		$this->assertSame( 'date', $by_key['modified_before'] );
		$this->assertSame( 'taxonomy', $by_key['taxonomy'] );
		$this->assertSame( 'bool', $by_key['has_comments'] );
		$this->assertSame( 'post', $by_key['post_parent'] );
	}

	public function test_has_children_restricts_the_query_to_parents(): void {
		$query = $this->build_query( [ 'has_children' => true ], [ '12', '15' ] );
		$this->assertSame( [ 12, 15 ], $query['post__in'] );

		$query = $this->build_query( [ 'has_children' => true ], [] );
		$this->assertSame( [ 0 ], $query['post__in'] );
	}

	public function test_no_children_excludes_parents(): void {
		$query = $this->build_query( [ 'has_children' => false ], [ '12', '15' ] );
		$this->assertSame( [ 12, 15 ], $query['post__not_in'] );
		$this->assertArrayNotHasKey( 'post__in', $query );
	}

	public function test_has_children_intersects_with_specific_ids(): void {
		$query = $this->build_query(
			[
				'ids'          => [ 12, 99 ],
				'has_children' => true,
			],
			[ '12', '15' ]
		);
		$this->assertSame( [ 12 ], $query['post__in'] );
	}

	public function test_custom_field_filter_builds_a_meta_clause(): void {
		$query = $this->build_query(
			[
				'meta' => [
					'key'     => 'color',
					'compare' => 'LIKE',
					'value'   => 'red',
				],
			],
			[]
		);
		$this->assertSame(
			[
				[
					'key'     => 'color',
					'compare' => 'LIKE',
					'value'   => 'red',
				],
			],
			$query['meta_query']
		);

		$query = $this->build_query(
			[
				'meta' => [
					'key'     => 'color',
					'compare' => 'NOT EXISTS',
				],
			],
			[]
		);
		$this->assertArrayNotHasKey( 'value', $query['meta_query'][0] );

		$query = $this->build_query(
			[
				'meta' => [
					'key'     => 'color',
					'compare' => 'DROP TABLE',
					'value'   => 'x',
				],
			],
			[]
		);
		$this->assertSame( '=', $query['meta_query'][0]['compare'] );
	}

	public function test_search_filter_matches_title_and_content(): void {
		$query = $this->build_query( [ 'search' => ' sale ' ], [] );
		$this->assertSame( 'sale', $query['s'] );
		$this->assertSame( [ 'post_title', 'post_content' ], $query['search_columns'] );
	}

	/**
	 * @param array<string, mixed> $filters
	 * @param string[]             $parent_ids
	 * @return array<string, mixed>
	 */
	private function build_query( array $filters, array $parent_ids ): array {
		global $wpdb;
		$wpdb        = \Mockery::mock(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb->posts = 'wp_posts';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_col' )->andReturn( $parent_ids );

		$args  = QueryArgs::from_array( $filters );
		$build = \Closure::bind(
			fn () => $this->build_wp_query_args( $args ),
			new PostTarget( 'page' ),
			PostTarget::class
		);
		return $build();
	}

	private function stubFilterVocab(): void {
		$post_type         = new \stdClass();
		$post_type->labels = (object) [ 'name' => 'Posts' ];
		Functions\when( 'get_post_type_object' )->justReturn( $post_type );

		Functions\when( 'get_post_status_object' )->alias(
			static function ( string $slug ) {
				$labels = [
					'publish' => 'Published',
					'draft'   => 'Draft',
					'pending' => 'Pending',
					'private' => 'Private',
					'future'  => 'Scheduled',
					'trash'   => 'Trash',
				];
				if ( ! isset( $labels[ $slug ] ) ) {
					return null;
				}
				return (object) [ 'label' => $labels[ $slug ] ];
			}
		);
	}
}
