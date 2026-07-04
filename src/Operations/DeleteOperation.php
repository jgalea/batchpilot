<?php
namespace BatchPilot\Operations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Contracts\BatchResult;
use BatchPilot\Contracts\OperationInterface;
use BatchPilot\Contracts\PreviewResult;
use BatchPilot\Contracts\QueryArgs;
use BatchPilot\Contracts\TargetInterface;
use BatchPilot\Contracts\UndoResult;
use BatchPilot\Contracts\ValidationResult;
use BatchPilot\Errors\BatchPilotError;
use BatchPilot\History\OperationRepository;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;

final class DeleteOperation implements OperationInterface {

	private const SAMPLE_SIZE = 20;

	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	private OperationRepository $operations;

	public function __construct(
		TokenGenerator $token_generator,
		TokenStore $token_store,
		OperationRepository $operations
	) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
		$this->operations      = $operations;
	}

	public function slug(): string {
		return 'delete';
	}

	public function label(): string {
		return __( 'Delete', 'batchpilot' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'permanent' => [
					'type'        => 'boolean',
					'default'     => false,
					'label'       => __( 'Permanently delete (skip trash)', 'batchpilot' ),
					'description' => __( 'When enabled, items are hard-deleted and cannot be restored via Undo. Off by default — items go to the Trash and can be restored.', 'batchpilot' ),
				],
			],
		];
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function validate( QueryArgs $args, array $params ): ValidationResult {
		return ValidationResult::ok();
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult {
		$count      = $target->count( $args );
		$sample_ids = $target->query( $args, self::SAMPLE_SIZE, 0 );

		$payload = [
			'target'     => $target->slug(),
			'operation'  => $this->slug(),
			'filters'    => $args->to_array(),
			'params'     => $params,
			'sample_ids' => $sample_ids,
			'count'      => $count,
			// Binds the issued token to whoever previewed it, so a token (or its
			// underlying transient key) leaking or being handed to another equally-
			// capable user can't be replayed to execute on their behalf. See
			// ExecuteController::handle() for the matching re-check.
			'user_id'    => get_current_user_id(),
		];

		$token = $this->token_generator->generate( $payload );
		$this->token_store->store( $token, $payload );

		return PreviewResult::of( $count, $sample_ids, $token );
	}

	/**
	 * @param int[]                $ids
	 * @param array<string, mixed> $params
	 */
	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
		$permanent   = ! empty( $params['permanent'] );
		$succeeded   = 0;
		$failed      = 0;
		$item_errors = [];

		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$post = get_post( $id );

			if ( null === $post ) {
				++$failed;
				$item_errors[ $id ] = 'Post not found.';
				continue;
			}

			// Defense in depth: the batchpilot_delete capability gates *whether* this
			// operation may run at all, but it is a single coarse capability and does not
			// imply WP's native per-post delete rights (e.g. delete_others_posts, or a
			// custom post type's own capability_type). Re-check per post so that a future
			// non-administrator grant of batchpilot_delete can't bypass WordPress's own
			// authorization model. Skipped only when there is no authenticated user to
			// check against (WP-CLI run without --user), matching that context's existing
			// trust model. See ExecutionService::run_sync() for how the submitting user's
			// identity is restored for async/cron-driven runs.
			if ( get_current_user_id() > 0 && ! current_user_can( 'delete_post', $id ) ) {
				++$failed;
				$item_errors[ $id ] = 'Insufficient permissions to delete this item.';
				continue;
			}

			$result = $permanent ? wp_delete_post( $id, true ) : wp_trash_post( $id );

			if ( false === $result || null === $result ) {
				++$failed;
				$item_errors[ $id ] = $permanent ? 'wp_delete_post returned false.' : 'wp_trash_post returned false.';
				continue;
			}

			++$succeeded;
		}

		return BatchResult::of( count( $ids ), $succeeded, $failed, $item_errors );
	}

	public function supports_undo(): bool {
		return true;
	}

	public function undo( int $operation_id ): UndoResult {
		$op = $this->operations->find( $operation_id );
		if ( null === $op ) {
			return UndoResult::error(
				new BatchPilotError(
					'bp.undo.not_found',
					'Operation not found.',
					[ 'operation_id' => $operation_id ]
				)
			);
		}

		if ( ! empty( $op->params()['permanent'] ) ) {
			return UndoResult::error(
				new BatchPilotError(
					'bp.undo.permanent_delete',
					'Permanent deletions cannot be undone.',
					[ 'operation_id' => $operation_id ]
				)
			);
		}

		$restored = 0;
		foreach ( $op->affected_ids() as $id ) {
			$id = (int) $id;
			if ( get_current_user_id() > 0 && ! current_user_can( 'delete_post', $id ) ) {
				continue;
			}
			if ( false !== wp_untrash_post( $id ) ) {
				++$restored;
			}
		}

		return UndoResult::of( $restored );
	}
}
