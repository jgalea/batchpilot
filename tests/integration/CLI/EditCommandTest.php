<?php
namespace BatchPilot\Tests\Integration\CLI;

use BatchPilot\CLI\EditCommand;
use BatchPilot\Execution\ExecutionService;
use BatchPilot\History\OperationRepository;
use BatchPilot\History\SnapshotRepository;
use BatchPilot\Operations\BulkEditOperation;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;
use BatchPilot\Targets\PostTarget;
use BatchPilot\Tests\Integration\TestCase;

final class EditCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\BatchPilot\Database\Schema::install();
	}

	private function command(): EditCommand {
		global $wpdb;
		$targets = new TargetRegistry();
		$ops     = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$ops->register( new BulkEditOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), new OperationRepository( $wpdb ), new SnapshotRepository( $wpdb ) ) );

		$exec = new ExecutionService(
			$targets,
			$ops,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);
		return new EditCommand( $exec );
	}

	public function test_set_status_updates_posts(): void {
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );

		$result = $this->command()->run(
			[
				'post-type'  => 'post',
				'status'     => 'publish',
				'set-status' => 'draft',
				'format'     => 'json',
			]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 2, $data['succeeded'] );

		foreach ( $ids as $id ) {
			$this->assertSame( 'draft', get_post_status( $id ) );
		}
	}

	public function test_validation_error_returns_nonzero_exit(): void {
		$result = $this->command()->run(
			[
				'post-type'  => 'post',
				'set-status' => 'banana',
				'format'     => 'json',
			]
		);

		$this->assertNotSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'bp.params.invalid_status', $data['code'] );
	}
}
