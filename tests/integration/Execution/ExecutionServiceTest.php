<?php
namespace BatchPilot\Tests\Integration\Execution;

use BatchPilot\Execution\ExecutionService;
use BatchPilot\History\OperationRepository;
use BatchPilot\History\SnapshotRepository;
use BatchPilot\Operations\DeleteOperation;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;
use BatchPilot\Targets\PostTarget;
use BatchPilot\Tests\Integration\TestCase;

final class ExecutionServiceTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\BatchPilot\Database\Schema::install();
	}

	private function service(): ExecutionService {
		global $wpdb;
		$targets    = new TargetRegistry();
		$operations = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$operations->register(
			new DeleteOperation(
				new TokenGenerator( 'salt' ),
				new TokenStore( 300 ),
				new OperationRepository( $wpdb )
			)
		);

		return new ExecutionService(
			$targets,
			$operations,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);
	}

	public function test_preview_returns_preview_result(): void {
		self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$preview = $this->service()->preview( 'post', 'delete', [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 2, $preview->count() );
	}

	public function test_preview_unknown_target_returns_error(): void {
		$preview = $this->service()->preview( 'bogus', 'delete', [], [] );
		$this->assertFalse( $preview->is_ok() );
		$this->assertSame( 'bp.target.unknown', $preview->get_error()->code() );
	}

	public function test_preview_unknown_operation_returns_error(): void {
		$preview = $this->service()->preview( 'post', 'bogus', [], [] );
		$this->assertFalse( $preview->is_ok() );
		$this->assertSame( 'bp.operation.unknown', $preview->get_error()->code() );
	}

	public function test_preview_target_rejects_operation(): void {
		global $wpdb;
		$targets = new TargetRegistry();
		$ops     = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );

		$rejecting = new class() implements \BatchPilot\Contracts\OperationInterface {
			public function slug(): string {
				return 'move';
			}
			public function label(): string {
				return 'Move';
			}
			public function get_params_schema(): array {
				return [
					'type'       => 'object',
					'properties' => [],
				];
			}
			public function validate( \BatchPilot\Contracts\QueryArgs $a, array $p ): \BatchPilot\Contracts\ValidationResult {
				return \BatchPilot\Contracts\ValidationResult::ok();
			}
			public function preview( \BatchPilot\Contracts\QueryArgs $a, array $p, \BatchPilot\Contracts\TargetInterface $t ): \BatchPilot\Contracts\PreviewResult {
				return \BatchPilot\Contracts\PreviewResult::of( 0, [], '' );
			}
			public function execute_batch( array $ids, array $p, \BatchPilot\Contracts\TargetInterface $t ): \BatchPilot\Contracts\BatchResult {
				return \BatchPilot\Contracts\BatchResult::of( 0, 0, 0 );
			}
			public function supports_undo(): bool {
				return false;
			}
			public function undo( int $id ): \BatchPilot\Contracts\UndoResult {
				return \BatchPilot\Contracts\UndoResult::of( 0 );
			}
		};
		$ops->register( $rejecting );

		$svc     = new ExecutionService(
			$targets,
			$ops,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);
		$preview = $svc->preview( 'post', 'move', [], [] );
		$this->assertFalse( $preview->is_ok() );
		$this->assertSame( 'bp.target.unsupported_operation', $preview->get_error()->code() );
	}

	public function test_record_creates_history_row_and_returns_id(): void {
		$id = $this->service()->record( 'post', 'delete', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );
		$this->assertGreaterThan( 0, $id );
	}

	public function test_run_sync_executes_delete_and_marks_completed(): void {
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$svc    = $this->service();
		$op_id  = $svc->record( 'post', 'delete', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );
		$result = $svc->run_sync( $op_id );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->succeeded() );

		global $wpdb;
		$row = ( new OperationRepository( $wpdb ) )->find( $op_id );
		$this->assertSame( 'completed', $row->status() );
		$this->assertSame( 2, $row->affected_count() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}
}
