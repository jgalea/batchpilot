<?php
namespace BatchPilot\Tests\Integration\Execution;

use BatchPilot\Execution\ExecutionService;
use BatchPilot\Execution\OperationRunner;
use BatchPilot\History\OperationRepository;
use BatchPilot\History\SnapshotRepository;
use BatchPilot\Operations\DeleteOperation;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;
use BatchPilot\Targets\PostTarget;
use BatchPilot\Tests\Integration\TestCase;

final class OperationRunnerTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\BatchPilot\Database\Schema::install();
	}

	public function test_handle_runs_operation_synchronously(): void {
		global $wpdb;
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$targets    = new TargetRegistry();
		$operations = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$operations->register( new DeleteOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), new OperationRepository( $wpdb ) ) );

		$svc = new ExecutionService(
			$targets,
			$operations,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);

		$op_id = $svc->record( 'post', 'delete', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );

		$runner = new OperationRunner( $svc );
		$runner->register();
		do_action( OperationRunner::HOOK, $op_id );

		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
		$row = ( new OperationRepository( $wpdb ) )->find( $op_id );
		$this->assertSame( 'completed', $row->status() );
	}
}
