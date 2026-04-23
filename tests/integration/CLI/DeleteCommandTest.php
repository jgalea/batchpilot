<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\CLI\DeleteCommand;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\DeleteOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DeleteCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	private function command(): DeleteCommand {
		global $wpdb;
		$targets = new TargetRegistry();
		$ops     = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$ops->register( new DeleteOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), new OperationRepository( $wpdb ) ) );

		$exec = new ExecutionService(
			$targets,
			$ops,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);

		return new DeleteCommand( $exec );
	}

	public function test_dry_run_returns_preview_and_does_not_delete(): void {
		$ids = self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$result = $this->command()->run(
			[
				'post-type' => 'post',
				'status'    => 'draft',
				'dry-run'   => true,
				'format'    => 'json',
			]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'preview', $data['status'] );
		$this->assertSame( 3, $data['count'] );

		foreach ( $ids as $id ) {
			$this->assertSame( 'draft', get_post_status( $id ) );
		}
	}

	public function test_execution_trashes_posts(): void {
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$result = $this->command()->run(
			[
				'post-type' => 'post',
				'status'    => 'draft',
				'format'    => 'json',
			]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'completed', $data['status'] );
		$this->assertSame( 2, $data['succeeded'] );

		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}

	public function test_older_than_parses_duration(): void {
		global $wpdb;

		$old     = (int) self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$old_gmt = gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => $old_gmt,
				'post_modified_gmt' => $old_gmt,
			],
			[ 'ID' => $old ]
		);
		clean_post_cache( $old );

		$recent = (int) self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->command()->run(
			[
				'post-type'  => 'post',
				'status'     => 'draft',
				'older-than' => '90d',
				'format'     => 'json',
			]
		);

		$this->assertSame( 'trash', get_post_status( $old ) );
		$this->assertSame( 'draft', get_post_status( $recent ) );
	}

	public function test_count_format_emits_just_integer(): void {
		self::factory()->post->create_many( 4, [ 'post_status' => 'draft' ] );

		$result = $this->command()->run(
			[
				'post-type' => 'post',
				'status'    => 'draft',
				'dry-run'   => true,
				'format'    => 'count',
			]
		);

		$this->assertSame( '4', trim( $result['output'] ) );
	}
}
