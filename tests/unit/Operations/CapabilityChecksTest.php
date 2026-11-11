<?php
namespace BatchPilot\Tests\Unit\Operations;

use BatchPilot\Contracts\TargetInterface;
use BatchPilot\Duplication\PostCopier;
use BatchPilot\History\OperationRepository;
use BatchPilot\History\SnapshotRepository;
use BatchPilot\Operations\BulkEditOperation;
use BatchPilot\Operations\DuplicateOperation;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use Mockery;

final class CapabilityChecksTest extends TestCase {

	/** @var string[] */
	private array $caps = [];

	protected function setUp(): void {
		parent::setUp();
		$this->caps = [ 'edit_post', 'edit_posts' ];

		Functions\when( 'get_current_user_id' )->justReturn( 5 );
		Functions\when( 'get_post' )->justReturn(
			(object) [
				'ID'            => 10,
				'post_type'     => 'post',
				'post_status'   => 'draft',
				'post_author'   => 5,
				'post_title'    => 'Hello',
				'post_password' => '',
				'post_date'     => '2026-01-01 00:00:00',
				'post_date_gmt' => '0000-00-00 00:00:00',
			]
		);
		Functions\when( 'get_post_type_object' )->justReturn(
			(object) [
				'cap' => (object) [
					'publish_posts'     => 'publish_posts',
					'edit_others_posts' => 'edit_others_posts',
					'create_posts'      => 'edit_posts',
				],
			]
		);
		Functions\when( 'get_taxonomy' )->justReturn( (object) [ 'cap' => (object) [ 'assign_terms' => 'assign_categories' ] ] );
		Functions\when( 'current_user_can' )->alias( fn ( $cap ) => in_array( $cap, $this->caps, true ) );
		Functions\when( 'wp_slash' )->alias( static fn ( $v ) => is_array( $v ) ? array_map( 'addslashes', $v ) : addslashes( $v ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
	}

	private function bulk_edit(): BulkEditOperation {
		$make = static fn ( string $c ) => ( new \ReflectionClass( $c ) )->newInstanceWithoutConstructor();
		return new BulkEditOperation(
			$make( TokenGenerator::class ),
			$make( TokenStore::class ),
			$make( OperationRepository::class ),
			$make( SnapshotRepository::class )
		);
	}

	/**
	 * @return TargetInterface&\Mockery\MockInterface
	 */
	private function target() {
		$target = Mockery::mock( TargetInterface::class );
		$target->allows( 'slug' )->andReturn( 'post' );
		return $target;
	}

	public function test_bulk_edit_cannot_publish_without_publish_posts(): void {
		Functions\expect( 'wp_update_post' )->never();

		$result = $this->bulk_edit()->execute_batch( [ 10 ], [ 'set_status' => 'publish' ], $this->target() );

		$this->assertSame( 1, $result->failed() );
	}

	public function test_bulk_edit_cannot_hand_posts_to_another_author_without_edit_others_posts(): void {
		Functions\expect( 'wp_update_post' )->never();

		$result = $this->bulk_edit()->execute_batch( [ 10 ], [ 'reassign_author' => 9 ], $this->target() );

		$this->assertSame( 1, $result->failed() );
	}

	public function test_bulk_edit_cannot_assign_terms_without_assign_terms(): void {
		Functions\expect( 'wp_update_post' )->never();
		Functions\expect( 'wp_set_object_terms' )->never();

		$result = $this->bulk_edit()->execute_batch(
			[ 10 ],
			[
				'taxonomy_add' => [
					'taxonomy' => 'category',
					'term_ids' => [ 3 ],
				],
			],
			$this->target()
		);

		$this->assertSame( 1, $result->failed() );
	}

	public function test_bulk_edit_still_works_when_the_rights_are_held(): void {
		$this->caps = [ 'edit_post', 'publish_posts' ];
		Functions\expect( 'wp_update_post' )->once()->andReturn( 10 );

		$result = $this->bulk_edit()->execute_batch( [ 10 ], [ 'set_status' => 'publish' ], $this->target() );

		$this->assertSame( 1, $result->succeeded() );
	}

	public function test_bulk_edit_slashes_values_before_wp_update_post(): void {
		$captured = null;
		Functions\when( 'wp_update_post' )->alias(
			static function ( $data ) use ( &$captured ) {
				$captured = $data;
				return 10;
			}
		);

		$this->bulk_edit()->execute_batch( [ 10 ], [ 'password' => 'a\\b' ], $this->target() );

		$this->assertSame( 'a\\\\b', $captured['post_password'] );
	}

	public function test_duplicate_cannot_publish_copies_without_publish_posts(): void {
		$op = new DuplicateOperation(
			( new \ReflectionClass( TokenGenerator::class ) )->newInstanceWithoutConstructor(),
			( new \ReflectionClass( TokenStore::class ) )->newInstanceWithoutConstructor(),
			( new \ReflectionClass( OperationRepository::class ) )->newInstanceWithoutConstructor(),
			new PostCopier()
		);
		Functions\expect( 'wp_insert_post' )->never();

		$result = $op->execute_batch( [ 10 ], [ 'target_status' => 'publish' ], $this->target() );

		$this->assertSame( 1, $result->failed() );
	}
}
