<?php
namespace BatchPilot\Execution;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Contracts\BatchResult;
use BatchPilot\Contracts\PreviewResult;
use BatchPilot\Contracts\QueryArgs;
use BatchPilot\Errors\BatchPilotError;
use BatchPilot\History\Operation;
use BatchPilot\History\OperationRepository;
use BatchPilot\History\SnapshotRepository;
use BatchPilot\Operations\DuplicateOperation;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;

final class ExecutionService {

	private const BATCH_SIZE = 50;

	private TargetRegistry $targets;
	private OperationRegistry $operations_registry;
	private OperationRepository $operations_repo;
	private SnapshotRepository $snapshots_repo;
	private TokenGenerator $token_generator;
	private TokenStore $token_store;

	public function __construct(
		TargetRegistry $targets,
		OperationRegistry $operations_registry,
		OperationRepository $operations_repo,
		SnapshotRepository $snapshots_repo,
		TokenGenerator $token_generator,
		TokenStore $token_store
	) {
		$this->targets             = $targets;
		$this->operations_registry = $operations_registry;
		$this->operations_repo     = $operations_repo;
		$this->snapshots_repo      = $snapshots_repo;
		$this->token_generator     = $token_generator;
		$this->token_store         = $token_store;
	}

	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 */
	public function preview( string $target_slug, string $operation_slug, array $filters, array $params ): PreviewResult {
		$target = $this->targets->get( $target_slug );
		if ( null === $target ) {
			return PreviewResult::error( new BatchPilotError( 'bp.target.unknown', 'Unknown target.', [ 'target' => $target_slug ] ) );
		}
		$op = $this->operations_registry->get( $operation_slug );
		if ( null === $op ) {
			return PreviewResult::error( new BatchPilotError( 'bp.operation.unknown', 'Unknown operation.', [ 'operation' => $operation_slug ] ) );
		}
		if ( ! $target->supports_operation( $operation_slug ) ) {
			return PreviewResult::error(
				new BatchPilotError(
					'bp.target.unsupported_operation',
					'Target does not support this operation.',
					[
						'target'    => $target_slug,
						'operation' => $operation_slug,
					]
				)
			);
		}

		$args       = QueryArgs::from_array( $filters );
		$validation = $op->validate( $args, $params );
		if ( ! $validation->is_ok() ) {
			return PreviewResult::error( $validation->get_error() );
		}

		return $op->preview( $args, $params, $target );
	}

	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 */
	public function record( string $target_slug, string $operation_slug, int $user_id, array $filters, array $params ): int {
		$op = Operation::newly_created( $operation_slug, $target_slug, $user_id, $filters, $params );
		return $this->operations_repo->create( $op )->id();
	}

	/**
	 * Pins the exact matched ID set for an operation being deferred to async (Action
	 * Scheduler) execution. Call this before scheduling, with the full result of the
	 * same query that was just shown to the user — closes the window where content
	 * created or edited between accept time and actual cron execution could otherwise
	 * be silently swept into a batch the user never saw or approved.
	 *
	 * @param int[] $ids
	 */
	public function snapshot_for_queue( int $operation_id, array $ids ): void {
		$this->operations_repo->mark_queued( $operation_id, $ids );
	}

	public function run_sync( int $operation_id ): BatchResult {
		$row = $this->operations_repo->find( $operation_id );
		if ( null === $row ) {
			return BatchResult::error( new BatchPilotError( 'bp.run.not_found', 'Operation not found.', [ 'operation_id' => $operation_id ] ) );
		}

		$target = $this->targets->get( $row->target() );
		$op     = $this->operations_registry->get( $row->type() );
		if ( null === $target || null === $op ) {
			$this->operations_repo->mark_failed( $operation_id, 'Target or operation no longer registered.' );
			return BatchResult::error( new BatchPilotError( 'bp.run.unresolvable', 'Target or operation missing at run time.' ) );
		}

		$this->operations_repo->mark_running( $operation_id );

		// Prefer the ID set snapshotted at queue time (async/Action Scheduler path) over
		// re-deriving it from filters now — see snapshot_for_queue(). Sync runs and rows
		// predating this column have no snapshot, so they still re-query live.
		$queued_ids = $row->queued_ids();
		if ( null !== $queued_ids ) {
			$all_ids = $queued_ids;
		} else {
			$args    = QueryArgs::from_array( $row->filters() );
			$all_ids = $target->query( $args );
		}
		$params                   = $row->params();
		$params['__operation_id'] = $operation_id;

		$total_processed = 0;
		$total_succeeded = 0;
		$total_failed    = 0;
		$item_errors     = [];
		$affected        = [];

		/*
		 * Operations run synchronously inline with the submitting REST/CLI request, but
		 * large batches run later via Action Scheduler — a cron-driven context with no
		 * "current user" at all (get_current_user_id() === 0). execute_batch() uses the
		 * current user to perform a per-post current_user_can() check as defense in depth
		 * on top of the coarse batchpilot_* capability already checked when the operation
		 * was accepted. Without restoring the original submitter's identity here, that
		 * per-post check would either silently no-op (cron has no user) or, worse, run as
		 * whatever user happens to be set in that request context. Impersonate the
		 * recorded submitter for the duration of the batch and always restore afterwards.
		 */
		$restore_user_id = get_current_user_id();
		$impersonate     = $row->user_id() > 0 && $row->user_id() !== $restore_user_id;
		if ( $impersonate ) {
			wp_set_current_user( $row->user_id() );
		}

		try {
			foreach ( array_chunk( $all_ids, self::BATCH_SIZE ) as $chunk ) {
				$result = $op->execute_batch( $chunk, $params, $target );
				if ( ! $result->is_ok() ) {
					$this->operations_repo->mark_failed( $operation_id, $result->get_error()->message() );
					return $result;
				}
				$total_processed += $result->processed();
				$total_succeeded += $result->succeeded();
				$total_failed    += $result->failed();
				foreach ( $result->item_errors() as $k => $v ) {
					$item_errors[ $k ] = $v;
				}

				if ( $op instanceof DuplicateOperation ) {
					$affected = array_merge( $affected, $op->last_new_ids() );
				} else {
					foreach ( $chunk as $id ) {
						if ( ! isset( $result->item_errors()[ $id ] ) ) {
							$affected[] = (int) $id;
						}
					}
				}
			}
		} finally {
			if ( $impersonate ) {
				wp_set_current_user( $restore_user_id );
			}
		}

		$this->operations_repo->mark_completed( $operation_id, $affected );

		$result = BatchResult::of( $total_processed, $total_succeeded, $total_failed, $item_errors );

		/**
		 * Fires after a synchronous operation run is recorded as completed.
		 * Pro / third-party plugins use this to send notifications, webhooks,
		 * or trigger downstream workflows.
		 *
		 * @param int          $operation_id  History row id for this run.
		 * @param BatchResult  $result        Aggregated processed/succeeded/failed counts.
		 * @param Operation    $operation_row The persisted history row.
		 */
		\do_action( 'batchpilot_operation_completed', $operation_id, $result, $row );

		return $result;
	}
}
