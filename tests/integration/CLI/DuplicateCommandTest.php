<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\CLI\DuplicateCommand;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\DuplicateOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DuplicateCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	private function command(): DuplicateCommand {
		global $wpdb;
		$targets = new TargetRegistry();
		$ops     = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$ops->register( new DuplicateOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), new OperationRepository( $wpdb ) ) );

		$exec = new ExecutionService(
			$targets,
			$ops,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);

		return new DuplicateCommand( $exec );
	}

	public function test_duplicate_creates_new_posts_as_draft(): void {
		$src = (int) self::factory()->post->create(
			[
				'post_title'  => 'Hello',
				'post_status' => 'publish',
			]
		);

		$result = $this->command()->run(
			[
				'post-type'    => 'post',
				'status'       => 'publish',
				'format'       => 'json',
				'title-suffix' => ' (Copy)',
			]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 1, $data['succeeded'] );

		$drafts = get_posts(
			[
				'post_status' => 'draft',
				'numberposts' => -1,
			]
		);
		$this->assertCount( 1, $drafts );
		$this->assertSame( 'Hello (Copy)', $drafts[0]->post_title );
		$this->assertNotNull( get_post( $src ) );
	}

	public function test_dry_run_does_not_create_posts(): void {
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$result = $this->command()->run(
			[
				'post-type' => 'post',
				'status'    => 'publish',
				'dry-run'   => true,
				'format'    => 'json',
			]
		);

		$data = json_decode( $result['output'], true );
		$this->assertSame( 'preview', $data['status'] );
		$this->assertSame( 1, $data['count'] );

		$drafts = get_posts(
			[
				'post_status' => 'draft',
				'numberposts' => -1,
			]
		);
		$this->assertCount( 0, $drafts );
	}
}
