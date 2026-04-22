<?php
namespace ContentOps\Tests\Integration\Operations;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Operations\DuplicateOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DuplicateOperationTest extends TestCase {

	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ): void {
		\ContentOps\Database\Schema::install();
	}

	public static function wpTearDownAfterClass(): void {
		\ContentOps\Database\Schema::drop_all();
	}

	private function op(): DuplicateOperation {
		global $wpdb;
		return new DuplicateOperation(
			new TokenGenerator( 'test-salt' ),
			new TokenStore( 300 ),
			new \ContentOps\History\OperationRepository( $wpdb )
		);
	}

	public function test_slug_and_label(): void {
		$op = $this->op();
		$this->assertSame( 'duplicate', $op->slug() );
		$this->assertNotSame( '', $op->label() );
	}

	public function test_validate_rejects_unknown_status(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[ 'target_status' => 'banana' ]
		);
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_status', $result->get_error()->code() );
	}

	public function test_validate_rejects_missing_author(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[ 'reassign_author' => 999999 ]
		);
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_author', $result->get_error()->code() );
	}

	public function test_validate_accepts_valid_params(): void {
		$user_id = self::factory()->user->create();
		$result  = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[
				'target_status'   => 'draft',
				'reassign_author' => $user_id,
				'title_suffix'    => ' (Copy)',
			]
		);
		$this->assertTrue( $result->is_ok() );
	}

	public function test_preview_returns_count_and_token(): void {
		self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );

		$preview = $this->op()->preview(
			QueryArgs::from_array( [ 'status' => [ 'publish' ] ] ),
			[],
			new PostTarget( 'post' )
		);

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 2, $preview->count() );
		$this->assertNotSame( '', $preview->preview_token() );
	}

	public function test_params_schema_lists_all_params(): void {
		$schema = $this->op()->get_params_schema();
		$this->assertArrayHasKey( 'target_status', $schema['properties'] );
		$this->assertArrayHasKey( 'reassign_author', $schema['properties'] );
		$this->assertArrayHasKey( 'title_suffix', $schema['properties'] );
		$this->assertArrayHasKey( 'include_children', $schema['properties'] );
	}

	public function test_execute_batch_duplicates_posts_with_suffix_and_draft(): void {
		$source = (int) self::factory()->post->create(
			[
				'post_title'   => 'Hello',
				'post_content' => 'Body',
				'post_status'  => 'publish',
			]
		);

		$op     = $this->op();
		$target = new PostTarget( 'post' );

		$result = $op->execute_batch( [ $source ], [], $target );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 1, $result->succeeded() );
		$this->assertSame( 0, $result->failed() );

		$new_ids = $op->last_new_ids();
		$this->assertCount( 1, $new_ids );
		$copy = get_post( $new_ids[0] );
		$this->assertSame( 'Hello (Copy)', $copy->post_title );
		$this->assertSame( 'Body', $copy->post_content );
		$this->assertSame( 'draft', $copy->post_status );
	}

	public function test_execute_batch_copies_meta_and_thumbnail_but_skips_edit_lock(): void {
		$source = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $source, 'custom_field', 'value' );
		update_post_meta( $source, '_thumbnail_id', 42 );
		update_post_meta( $source, '_edit_lock', '1234567890:1' );

		$op = $this->op();
		$op->execute_batch( [ $source ], [], new PostTarget( 'post' ) );
		$new_id = $op->last_new_ids()[0];

		$this->assertSame( 'value', get_post_meta( $new_id, 'custom_field', true ) );
		$this->assertSame( '42', (string) get_post_meta( $new_id, '_thumbnail_id', true ) );
		$this->assertSame( '', get_post_meta( $new_id, '_edit_lock', true ) );
	}

	public function test_execute_batch_copies_taxonomies(): void {
		$source  = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = (int) self::factory()->term->create( [ 'taxonomy' => 'category' ] );
		wp_set_post_terms( $source, [ $term_id ], 'category' );

		$op = $this->op();
		$op->execute_batch( [ $source ], [], new PostTarget( 'post' ) );
		$new_id = $op->last_new_ids()[0];

		$terms = wp_get_post_terms( $new_id, 'category', [ 'fields' => 'ids' ] );
		$this->assertSame( [ $term_id ], array_map( 'intval', $terms ) );
	}

	public function test_execute_batch_uses_target_status_param(): void {
		$source = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$op = $this->op();
		$op->execute_batch(
			[ $source ],
			[
				'target_status' => 'pending',
				'title_suffix'  => '',
			],
			new PostTarget( 'post' )
		);
		$new_id = $op->last_new_ids()[0];

		$this->assertSame( 'pending', get_post_status( $new_id ) );
		$this->assertSame( get_post( $source )->post_title, get_post( $new_id )->post_title );
	}

	public function test_undo_deletes_the_duplicate_posts(): void {
		global $wpdb;
		$repo = new \ContentOps\History\OperationRepository( $wpdb );

		$source = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$op     = $this->op();
		$op->execute_batch( [ $source ], [], new PostTarget( 'post' ) );
		$new_ids = $op->last_new_ids();

		$saved = $repo->create(
			\ContentOps\History\Operation::newly_created( 'duplicate', 'post', 0, [], [] )
		);
		$repo->mark_completed( $saved->id(), $new_ids );

		$result = $op->undo( $saved->id() );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( count( $new_ids ), $result->restored() );
		foreach ( $new_ids as $id ) {
			$this->assertNull( get_post( $id ) );
		}
		$this->assertNotNull( get_post( $source ) );
	}

	public function test_undo_missing_operation_returns_error(): void {
		$op     = $this->op();
		$result = $op->undo( 999999 );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.undo.not_found', $result->get_error()->code() );
	}
}
