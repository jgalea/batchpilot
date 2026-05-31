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

		$args                     = QueryArgs::from_array( $row->filters() );
		$all_ids                  = $target->query( $args );
		$params                   = $row->params();
		$params['__operation_id'] = $operation_id;

		$total_processed = 0;
		$total_succeeded = 0;
		$total_failed    = 0;
		$item_errors     = [];
		$affected        = [];

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
