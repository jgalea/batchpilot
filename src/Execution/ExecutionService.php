<?php
namespace ContentOps\Execution;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Errors\ContentOpsError;
use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\DuplicateOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;

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
			return PreviewResult::error( new ContentOpsError( 'co.target.unknown', 'Unknown target.', [ 'target' => $target_slug ] ) );
		}
		$op = $this->operations_registry->get( $operation_slug );
		if ( null === $op ) {
			return PreviewResult::error( new ContentOpsError( 'co.operation.unknown', 'Unknown operation.', [ 'operation' => $operation_slug ] ) );
		}
		if ( ! $target->supports_operation( $operation_slug ) ) {
			return PreviewResult::error(
				new ContentOpsError(
					'co.target.unsupported_operation',
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
			return BatchResult::error( new ContentOpsError( 'co.run.not_found', 'Operation not found.', [ 'operation_id' => $operation_id ] ) );
		}

		$target = $this->targets->get( $row->target() );
		$op     = $this->operations_registry->get( $row->type() );
		if ( null === $target || null === $op ) {
			$this->operations_repo->mark_failed( $operation_id, 'Target or operation no longer registered.' );
			return BatchResult::error( new ContentOpsError( 'co.run.unresolvable', 'Target or operation missing at run time.' ) );
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

		return BatchResult::of( $total_processed, $total_succeeded, $total_failed, $item_errors );
	}
}
