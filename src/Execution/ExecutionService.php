<?php
namespace BatchPilot\Execution;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Contracts\BatchResult;
use BatchPilot\Contracts\PreviewResult;
use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\Contracts\QueryArgs;
use BatchPilot\Contracts\UndoResult;
use BatchPilot\Contracts\TracksAffectedIds;
use BatchPilot\Errors\BatchPilotError;
use BatchPilot\History\Operation;
use BatchPilot\History\OperationRepository;
use BatchPilot\History\SnapshotRepository;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\PreviewToken\TokenVerifier;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;

final class ExecutionService {

	private const SECRET_PARAMS = [ 'password' ];

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
	 * Checks that the target and operation exist, are compatible, and that the params validate.
	 *
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 */
	public function validate( string $target_slug, string $operation_slug, array $filters, array $params ): ?BatchPilotError {
		$target = $this->targets->get( $target_slug );
		if ( null === $target ) {
			return new BatchPilotError( 'bp.target.unknown', 'Unknown target.', [ 'target' => $target_slug ] );
		}
		$op = $this->operations_registry->get( $operation_slug );
		if ( null === $op ) {
			return new BatchPilotError( 'bp.operation.unknown', 'Unknown operation.', [ 'operation' => $operation_slug ] );
		}
		if ( ! $target->supports_operation( $operation_slug ) ) {
			return new BatchPilotError(
				'bp.target.unsupported_operation',
				'Target does not support this operation.',
				[
					'target'    => $target_slug,
					'operation' => $operation_slug,
				]
			);
		}

		$validation = $op->validate( QueryArgs::from_array( $filters ), $params );
		return $validation->is_ok() ? null : $validation->get_error();
	}

	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 */
	public function preview( string $target_slug, string $operation_slug, array $filters, array $params ): PreviewResult {
		$error = $this->validate( $target_slug, $operation_slug, $filters, $params );
		if ( null !== $error ) {
			return PreviewResult::error( $error );
		}

		return $this->operations_registry->get( $operation_slug )->preview(
			QueryArgs::from_array( $filters ),
			$params,
			$this->targets->get( $target_slug )
		);
	}

	/**
	 * Runs a previewed operation: verifies the preview token, records the run and
	 * either executes it inline or queues it when it exceeds the async threshold.
	 *
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 * @return array{operation_id: int, status: string, batch?: array{processed: int, succeeded: int, failed: int}}|BatchPilotError
	 */
	public function execute( string $target_slug, string $operation_slug, array $filters, array $params, string $token, ActionSchedulerBridge $scheduler ) {
		$target = $this->targets->get( $target_slug );
		$op     = $this->operations_registry->get( $operation_slug );
		if ( null === $target || null === $op ) {
			return new BatchPilotError(
				null === $target ? 'bp.target.unknown' : 'bp.operation.unknown',
				'Unknown target or operation.'
			);
		}

		$args    = QueryArgs::from_array( $filters );
		$count   = $target->count( $args );
		$payload = [
			'target'     => $target_slug,
			'operation'  => $operation_slug,
			'filters'    => $filters,
			'params'     => $params,
			'sample_ids' => $target->query( $args, 20, 0 ),
			'count'      => $count,
			// Must match the previewing user recorded in Operations/*::preview(): a token
			// previewed by one user cannot be executed by another, even if both hold the
			// same coarse batchpilot_* capability.
			'user_id'    => get_current_user_id(),
		];

		$verifier = new TokenVerifier( $this->token_generator, $this->token_store );
		if ( ! $verifier->verify( $token, $payload ) ) {
			return new BatchPilotError( 'bp.preview.stale_token', __( 'Preview token invalid or expired. Re-preview before executing.', 'batchpilot' ) );
		}
		$verifier->consume( $token );

		$op_id = $this->record( $target_slug, $operation_slug, (int) get_current_user_id(), $filters, $params );

		$threshold = (int) apply_filters( 'batchpilot_async_threshold', 100 );
		if ( $count > $threshold && $scheduler->is_available() ) {
			// Pin the exact matched ID set now, at accept time, so content created or edited
			// before Action Scheduler picks the run up can't be swept into a batch the user
			// never previewed.
			$this->snapshot_for_queue( $op_id, $target->query( $args ) );
			$scheduler->schedule_single_action( time(), OperationRunner::HOOK, [ $op_id ], 'batchpilot' );

			return [
				'operation_id' => $op_id,
				'status'       => 'queued',
			];
		}

		$result = $this->run_sync( $op_id );
		if ( ! $result->is_ok() ) {
			return $result->get_error() ?? new BatchPilotError( 'bp.internal', 'Unknown execution failure.' );
		}

		return [
			'operation_id' => $op_id,
			'status'       => 'completed',
			'batch'        => [
				'processed' => $result->processed(),
				'succeeded' => $result->succeeded(),
				'failed'    => $result->failed(),
			],
		];
	}

	public function find_operation( int $operation_id ): ?Operation {
		return $this->operations_repo->find( $operation_id );
	}

	public function undo( int $operation_id ): UndoResult {
		$row = $this->operations_repo->find( $operation_id );
		if ( null === $row ) {
			return UndoResult::error( new BatchPilotError( 'bp.operation.not_found', 'Operation not found.', [ 'id' => $operation_id ] ) );
		}
		if ( 'undone' === $row->status() ) {
			return UndoResult::error( new BatchPilotError( 'bp.undo.already_undone', __( 'This operation has already been undone.', 'batchpilot' ), [ 'id' => $operation_id ] ) );
		}

		$op = $this->operations_registry->get( $row->type() );
		if ( null === $op ) {
			return UndoResult::error( new BatchPilotError( 'bp.operation.unknown', 'Operation type no longer registered.', [ 'type' => $row->type() ] ) );
		}

		// add_option() is atomic on the unique option name, so only one request wins; a lock
		// older than five minutes belongs to a request that died and is taken over.
		$lock = 'batchpilot_undo_lock_' . $operation_id;
		if ( ! \add_option( $lock, time(), '', false ) ) {
			if ( (int) \get_option( $lock ) > time() - 300 ) {
				return UndoResult::error( new BatchPilotError( 'bp.undo.in_progress', __( 'This operation is already being undone.', 'batchpilot' ), [ 'id' => $operation_id ] ) );
			}
			\update_option( $lock, time(), false );
		}

		try {
			$result = $op->undo( $operation_id );
			if ( $result->is_ok() ) {
				$this->operations_repo->mark_undone( $operation_id );
			}
		} finally {
			\delete_option( $lock );
		}

		return $result;
	}

	/**
	 * @return Operation[]
	 */
	public function recent_operations( int $limit, int $offset ): array {
		return $this->operations_repo->list( $limit, $offset );
	}

	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 */
	public function record( string $target_slug, string $operation_slug, int $user_id, array $filters, array $params ): int {
		// Secrets never reach the history table: they wait in a short-lived transient
		// that run_sync() consumes, so queued, failed and completed rows are all clean.
		$secrets = [];
		foreach ( self::SECRET_PARAMS as $key ) {
			if ( isset( $params[ $key ] ) && '' !== $params[ $key ] ) {
				$secrets[ $key ] = $params[ $key ];
				$params[ $key ]  = '';
			}
		}

		$op      = Operation::newly_created( $operation_slug, $target_slug, $user_id, $filters, $params );
		$next_id = $this->operations_repo->create( $op )->id();
		if ( ! empty( $secrets ) ) {
			\set_transient( 'batchpilot_op_secrets_' . $next_id, $secrets, DAY_IN_SECONDS );
		}

		return $next_id;
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

		if ( ! $target->supports_operation( $row->type() ) ) {
			$this->operations_repo->mark_failed( $operation_id, 'Target does not support this operation.' );
			return BatchResult::error( new BatchPilotError( 'bp.target.unsupported_operation', 'Target does not support this operation.' ) );
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
		$params  = $row->params();
		$secrets = \get_transient( 'batchpilot_op_secrets_' . $operation_id );
		\delete_transient( 'batchpilot_op_secrets_' . $operation_id );
		if ( is_array( $secrets ) ) {
			$params = array_merge( $params, $secrets );
		}
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
			$batch_size = max( 1, (int) \apply_filters( 'batchpilot_batch_size', 50 ) );
			foreach ( array_chunk( $all_ids, $batch_size ) as $chunk ) {
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

				if ( $op instanceof TracksAffectedIds ) {
					$affected = array_merge( $affected, $op->last_affected_ids() );
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
