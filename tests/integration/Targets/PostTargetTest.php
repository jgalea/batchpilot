<?php
namespace ContentOps\Tests\Integration\Targets;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class PostTargetTest extends TestCase {

	public function test_query_filters_by_status(): void {
		$draft_a = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$draft_b = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$target = new PostTarget( 'post' );
		$ids    = $target->query( QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ) );

		sort( $ids );
		$this->assertSame( [ $draft_a, $draft_b ], array_map( 'intval', $ids ) );
	}

	public function test_count_matches_query_size(): void {
		self::factory()->post->create_many( 4, [ 'post_status' => 'draft' ] );

		$target = new PostTarget( 'post' );
		$this->assertSame( 4, $target->count( QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ) ) );
	}

	public function test_modified_before_filters_by_date(): void {
		global $wpdb;

		$old = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => '2024-01-01 00:00:00',
				'post_modified_gmt' => '2024-01-01 00:00:00',
			],
			[ 'ID' => $old ]
		);
		clean_post_cache( $old );

		self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$target = new PostTarget( 'post' );
		$ids    = $target->query(
			QueryArgs::from_array(
				[
					'status'          => [ 'draft' ],
					'modified_before' => '2024-06-01',
				]
			)
		);

		$this->assertSame( [ (int) $old ], array_map( 'intval', $ids ) );
	}

	public function test_author_filter(): void {
		$user_id = self::factory()->user->create();
		$mine    = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $user_id,
			]
		);
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$target = new PostTarget( 'post' );
		$ids    = $target->query( QueryArgs::from_array( [ 'author' => $user_id ] ) );

		$this->assertSame( [ (int) $mine ], array_map( 'intval', $ids ) );
	}

	public function test_has_featured_image_filter(): void {
		$with    = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$without = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $with, '_thumbnail_id', 999 );

		$target = new PostTarget( 'post' );
		$ids    = $target->query(
			QueryArgs::from_array(
				[
					'status'             => [ 'publish' ],
					'has_featured_image' => true,
				]
			)
		);

		$this->assertSame( [ (int) $with ], array_map( 'intval', $ids ) );
		$this->assertNotContains( (int) $without, array_map( 'intval', $ids ) );
	}

	public function test_limit_and_offset_paginate(): void {
		$ids = self::factory()->post->create_many( 5, [ 'post_status' => 'draft' ] );
		sort( $ids );

		$target = new PostTarget( 'post' );
		$page_1 = $target->query( QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ), 2, 0 );
		$page_2 = $target->query( QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ), 2, 2 );

		$this->assertCount( 2, $page_1 );
		$this->assertCount( 2, $page_2 );
		$this->assertSame( [], array_intersect( $page_1, $page_2 ) );
	}
}
