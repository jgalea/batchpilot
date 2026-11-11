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

	public function test_bulk_edit_password_is_never_stored_in_history_but_still_applied(): void {
		global $wpdb;
		$repo = new OperationRepository( $wpdb );
		$svc  = $this->service_with_edit();
		$ids  = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$op_id = $svc->record( 'post', 'edit', 0, [ 'ids' => $ids ], [ 'password' => 's3cret' ] );
		$svc->snapshot_for_queue( $op_id, $ids );

		$this->assertSame( '', $repo->find( $op_id )->params()['password'], 'A queued run must not keep the password in params_json.' );

		$result = $svc->run_sync( $op_id );

		$this->assertSame( 2, $result->succeeded() );
		$this->assertSame( '', $repo->find( $op_id )->params()['password'] );
		$this->assertSame( 's3cret', get_post( $ids[0] )->post_password );
		$this->assertFalse( get_transient( 'batchpilot_op_secrets_' . $op_id ) );
	}

	public function test_bulk_edit_password_is_not_stored_when_the_run_fails(): void {
		global $wpdb;
		$repo = new OperationRepository( $wpdb );
		$svc  = $this->service_with_edit();

		$op_id = $svc->record( 'bogus', 'edit', 0, [], [ 'password' => 's3cret' ] );
		$svc->run_sync( $op_id );

		$row = $repo->find( $op_id );
		$this->assertSame( 'failed', $row->status() );
		$this->assertSame( '', $row->params()['password'] );
	}

	private function service_with_edit(): ExecutionService {
		global $wpdb;
		$targets    = new TargetRegistry();
		$operations = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$operations->register(
			new \BatchPilot\Operations\BulkEditOperation(
				new TokenGenerator( 'salt' ),
				new TokenStore( 300 ),
				new OperationRepository( $wpdb ),
				new SnapshotRepository( $wpdb )
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

	public function test_a_second_undo_while_one_is_running_is_refused(): void {
		global $wpdb;
		$repo = new OperationRepository( $wpdb );
		$svc  = $this->service();
		$id   = $svc->record( 'post', 'delete', 0, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );
		$repo->mark_completed( $id, [] );

		add_option( 'batchpilot_undo_lock_' . $id, time(), '', false );
		$result = $svc->undo( $id );

		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'bp.undo.in_progress', $result->get_error()->code() );
		$this->assertSame( 'completed', $repo->find( $id )->status() );
	}

	public function test_undo_releases_its_lock(): void {
		global $wpdb;
		$repo = new OperationRepository( $wpdb );
		$svc  = $this->service();
		$id   = $svc->record( 'post', 'delete', 0, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );
		$repo->mark_completed( $id, [] );

		$this->assertTrue( $svc->undo( $id )->is_ok() );
		$this->assertFalse( get_option( 'batchpilot_undo_lock_' . $id ) );
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

	public function test_run_sync_re_checks_submitters_capability_with_no_current_user(): void {
		// Large/async batches run later via Action Scheduler's cron-driven worker, which
		// has no "current user" at all (get_current_user_id() === 0). run_sync() must
		// impersonate the original submitter (recorded on the operation row) so the
		// per-post capability re-check inside execute_batch() still has someone to check
		// against, rather than silently no-op'ing because nobody is "logged in".
		$other_author = self::factory()->user->create( [ 'role' => 'author' ] );
		$contributor  = self::factory()->user->create( [ 'role' => 'contributor' ] );
		$post_id      = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $other_author,
			]
		);

		$svc = $this->service();
		// Recorded as if the contributor had submitted this via REST while authenticated
		// (e.g. a future role_caps grant of batchpilot_delete to a non-admin role).
		$op_id = $svc->record( 'post', 'delete', $contributor, [ 'ids' => [ $post_id ] ], [ 'permanent' => false ] );

		$this->assertSame( 0, get_current_user_id(), 'Precondition: no current user, simulating an Action Scheduler cron run.' );
		$result = $svc->run_sync( $op_id );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 0, $result->succeeded() );
		$this->assertSame( 1, $result->failed() );
		$this->assertNotSame( 'trash', get_post_status( $post_id ) );
		$this->assertSame( 0, get_current_user_id(), 'The original (anonymous) cron context must be restored afterwards.' );
	}

	public function test_run_sync_uses_queued_snapshot_instead_of_re_querying_filters(): void {
		// snapshot_for_queue() pins the exact matched ID set at accept time. A post that
		// starts matching the same filter only *after* queuing (simulating content
		// created in the gap before a delayed Action Scheduler run actually fires) must
		// NOT be swept into the batch — only what was in the snapshot gets touched.
		$snapshotted_ids = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$svc   = $this->service();
		$op_id = $svc->record( 'post', 'delete', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );
		$svc->snapshot_for_queue( $op_id, $snapshotted_ids );

		// Created after queuing — matches the same `status=draft` filter, but was never
		// shown to the user and must be excluded from this run.
		$late_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$result = $svc->run_sync( $op_id );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->succeeded() );
		foreach ( $snapshotted_ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
		$this->assertNotSame( 'trash', get_post_status( $late_id ) );
	}

	public function test_run_sync_re_queries_filters_when_no_snapshot_was_taken(): void {
		// Sync (immediate) runs never call snapshot_for_queue() — confirms the fallback
		// path (queued_ids() === null) still works exactly as before this column existed.
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$svc    = $this->service();
		$op_id  = $svc->record( 'post', 'delete', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );
		$result = $svc->run_sync( $op_id );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->succeeded() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}
}
