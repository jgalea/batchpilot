<?php
namespace BatchPilot\Tests\Integration\Rewrite;

use BatchPilot\Admin\Settings;
use BatchPilot\Execution\ExecutionService;
use BatchPilot\Operations\RepublishOperation;
use BatchPilot\Plugin;
use BatchPilot\Rewrite\RewriteLinks;
use BatchPilot\Tests\Integration\TestCase;

final class RewriteRepublishTest extends TestCase {

	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ): void {
		\BatchPilot\Database\Schema::install();
	}

	public static function wpTearDownAfterClass(): void {
		\BatchPilot\Database\Schema::drop_all();
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	private function execution(): ExecutionService {
		return Plugin::instance()->get( 'execution.service' );
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array{operation_id: int, result: \BatchPilot\Contracts\BatchResult}
	 */
	private function run_op( string $operation, int $id, array $params = [], string $post_type = 'post' ): array {
		$operation_id = $this->execution()->record( $post_type, $operation, get_current_user_id(), [ 'ids' => [ $id ] ], $params );
		return [
			'operation_id' => $operation_id,
			'result'       => $this->execution()->run_sync( $operation_id ),
		];
	}

	private function published_post(): int {
		$id = (int) self::factory()->post->create(
			[
				'post_title'   => 'Original',
				'post_content' => 'Old content',
				'post_excerpt' => 'Old excerpt',
				'post_status'  => 'publish',
			]
		);
		wp_set_object_terms( $id, [ 'alpha' ], 'post_tag' );
		update_post_meta( $id, 'custom', 'orig' );
		update_post_meta( $id, '_edit_lock', '123:1' );
		update_post_meta( $id, '_yoast_wpseo_linkdex', '40' );
		return $id;
	}

	public function test_rewrite_creates_linked_draft_without_excluded_meta(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id  = $this->published_post();
		$run = $this->run_op( 'rewrite', $id );

		$this->assertSame( 1, $run['result']->succeeded() );
		$copy_id = RewriteLinks::copy_of( $id );
		$this->assertGreaterThan( 0, $copy_id );
		$this->assertSame( $id, RewriteLinks::original_of( $copy_id ) );
		$this->assertSame( 'draft', get_post_status( $copy_id ) );
		$this->assertSame( 'orig', get_post_meta( $copy_id, 'custom', true ) );
		$this->assertSame( '', get_post_meta( $copy_id, '_edit_lock', true ) );
		$this->assertSame( '', get_post_meta( $copy_id, '_yoast_wpseo_linkdex', true ) );
		$this->assertSame( [ 'alpha' ], wp_get_object_terms( $copy_id, 'post_tag', [ 'fields' => 'names' ] ) );

		$row = Plugin::instance()->get( 'history.operations' )->find( $run['operation_id'] );
		$this->assertSame( [ $copy_id ], $row->affected_ids() );
	}

	public function test_second_rewrite_of_same_post_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		$this->run_op( 'rewrite', $id );
		$second = $this->run_op( 'rewrite', $id );

		$this->assertSame( 1, $second['result']->failed() );
		$this->assertStringContainsString( 'already exists', (string) $second['result']->item_errors()[ $id ] );
	}

	public function test_unpublished_post_cannot_be_rewritten(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$draft = (int) self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$run   = $this->run_op( 'rewrite', $draft );

		$this->assertSame( 1, $run['result']->failed() );
		$this->assertSame( 0, RewriteLinks::copy_of( $draft ) );
	}

	public function test_post_type_disabled_in_settings_cannot_be_rewritten(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		( new Settings() )->save( [ 'copy_post_types' => [ 'page' ] ] );

		$run = $this->run_op( 'rewrite', $id );
		$this->assertSame( 1, $run['result']->failed() );
	}

	public function test_republish_merges_copy_onto_original_and_keeps_revision(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		wp_save_post_revision( $id );
		$this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );

		$thumb = (int) self::factory()->attachment->create_object( 'img.jpg', 0, [ 'post_mime_type' => 'image/jpeg' ] );
		wp_update_post(
			[
				'ID'           => $copy_id,
				'post_title'   => 'Rewritten',
				'post_content' => 'New content',
				'post_excerpt' => 'New excerpt',
			]
		);
		wp_set_object_terms( $copy_id, [ 'beta' ], 'post_tag' );
		update_post_meta( $copy_id, 'custom', 'changed' );
		update_post_meta( $copy_id, '_thumbnail_id', $thumb );

		$run = $this->run_op( 'republish', $id );

		$this->assertSame( 1, $run['result']->succeeded() );
		$post = get_post( $id );
		$this->assertSame( 'Rewritten', $post->post_title );
		$this->assertSame( 'New content', $post->post_content );
		$this->assertSame( 'New excerpt', $post->post_excerpt );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( [ 'beta' ], wp_get_object_terms( $id, 'post_tag', [ 'fields' => 'names' ] ) );
		$this->assertSame( 'changed', get_post_meta( $id, 'custom', true ) );
		$this->assertSame( $thumb, (int) get_post_thumbnail_id( $id ) );
		$this->assertSame( '40', get_post_meta( $id, '_yoast_wpseo_linkdex', true ) );
		$this->assertNull( get_post( $copy_id ) );
		$this->assertSame( 0, RewriteLinks::copy_of( $id ) );
		$this->assertSame( '', get_post_meta( $id, RewriteLinks::META_COPY, true ) );

		$contents = array_map( static fn ( $rev ) => $rev->post_content, array_values( wp_get_post_revisions( $id ) ) );
		$this->assertContains( 'Old content', $contents );

		$row = Plugin::instance()->get( 'history.operations' )->find( $run['operation_id'] );
		$this->assertSame( [ $id ], $row->affected_ids() );
	}

	public function test_undo_republish_restores_original(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		$this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );
		wp_update_post(
			[
				'ID'           => $copy_id,
				'post_title'   => 'Rewritten',
				'post_content' => 'New content',
			]
		);
		wp_set_object_terms( $copy_id, [ 'beta' ], 'post_tag' );
		update_post_meta( $copy_id, 'custom', 'changed' );
		$run = $this->run_op( 'republish', $id );

		$undo = Plugin::instance()->get( 'operation.registry' )->get( 'republish' )->undo( $run['operation_id'] );

		$this->assertTrue( $undo->is_ok() );
		$post = get_post( $id );
		$this->assertSame( 'Original', $post->post_title );
		$this->assertSame( 'Old content', $post->post_content );
		$this->assertSame( [ 'alpha' ], wp_get_object_terms( $id, 'post_tag', [ 'fields' => 'names' ] ) );
		$this->assertSame( 'orig', get_post_meta( $id, 'custom', true ) );
	}

	public function test_undo_rewrite_deletes_copy_and_frees_link(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id      = $this->published_post();
		$run     = $this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );

		$undo = Plugin::instance()->get( 'operation.registry' )->get( 'rewrite' )->undo( $run['operation_id'] );

		$this->assertSame( 1, $undo->restored() );
		$this->assertNull( get_post( $copy_id ) );
		$this->assertSame( 0, RewriteLinks::copy_of( $id ) );
	}

	public function test_publishing_the_copy_merges_it_automatically(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		$this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );

		wp_update_post(
			[
				'ID'           => $copy_id,
				'post_title'   => 'Published rewrite',
				'post_content' => 'Published content',
				'post_status'  => 'publish',
			]
		);
		RepublishOperation::run_deferred_deletes();

		$this->assertSame( 'Published rewrite', get_post( $id )->post_title );
		$this->assertNull( get_post( $copy_id ) );
		$ops = Plugin::instance()->get( 'history.operations' )->list( 5, 0 );
		$this->assertSame( 'republish', $ops[0]->type() );
		$this->assertSame( get_current_user_id(), $ops[0]->user_id() );
	}

	public function test_scheduled_copy_is_merged_by_cron_without_a_user(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		$this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );
		wp_update_post(
			[
				'ID'           => $copy_id,
				'post_title'   => 'Scheduled rewrite',
				'post_content' => 'Scheduled content',
			]
		);
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			[
				'post_status'   => 'future',
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - 60 ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			],
			[ 'ID' => $copy_id ]
		);
		clean_post_cache( $copy_id );
		wp_set_current_user( 0 );

		do_action( 'publish_future_post', $copy_id );

		$this->assertSame( 'Scheduled rewrite', get_post( $id )->post_title );
		$this->assertNull( get_post( $copy_id ) );
		$ops = Plugin::instance()->get( 'history.operations' )->list( 5, 0 );
		$this->assertSame( 'republish', $ops[0]->type() );
		$this->assertSame( 0, $ops[0]->user_id() );
	}

	public function test_scheduled_copy_not_yet_due_is_left_alone(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		$this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );
		global $wpdb;
		$future = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$wpdb->update(
			$wpdb->posts,
			[
				'post_status'   => 'future',
				'post_date'     => $future,
				'post_date_gmt' => $future,
			],
			[ 'ID' => $copy_id ]
		);
		clean_post_cache( $copy_id );

		do_action( 'publish_future_post', $copy_id );

		$this->assertNotNull( get_post( $copy_id ) );
		$this->assertSame( 'Original', get_post( $id )->post_title );
	}

	public function test_user_who_cannot_edit_the_original_cannot_publish_the_copy(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		$this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );
		$author  = (int) self::factory()->user->create( [ 'role' => 'author' ] );
		wp_update_post(
			[
				'ID'          => $copy_id,
				'post_author' => $author,
			]
		);
		wp_set_current_user( $author );

		wp_update_post(
			[
				'ID'          => $copy_id,
				'post_status' => 'publish',
			]
		);

		$this->assertSame( 'draft', get_post_status( $copy_id ) );
		$this->assertSame( 'Original', get_post( $id )->post_title );
	}

	public function test_deleting_the_original_keeps_the_copy_as_a_normal_draft(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		$this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );

		wp_delete_post( $id, true );

		$this->assertNotNull( get_post( $copy_id ) );
		$this->assertSame( 0, RewriteLinks::original_of( $copy_id ) );
	}

	public function test_discarding_the_copy_frees_the_original(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id = $this->published_post();
		$this->run_op( 'rewrite', $id );
		$copy_id = RewriteLinks::copy_of( $id );

		wp_delete_post( $copy_id, true );
		$again = $this->run_op( 'rewrite', $id );

		$this->assertSame( 1, $again['result']->succeeded() );
	}

	public function test_user_without_batchpilot_caps_is_refused_over_rest(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => '' ] ) );
		$request = new \WP_REST_Request( 'POST', '/batchpilot/v1/preview' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				[
					'target'    => 'post',
					'operation' => 'rewrite',
					'filters'   => [ 'ids' => [ 1 ] ],
					'params'    => new \stdClass(),
				]
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}
}
