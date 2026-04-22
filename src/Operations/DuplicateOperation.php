<?php
namespace ContentOps\Operations;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\OperationInterface;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Contracts\UndoResult;
use ContentOps\Contracts\ValidationResult;
use ContentOps\Errors\ContentOpsError;
use ContentOps\History\OperationRepository;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;

final class DuplicateOperation implements OperationInterface {

	private const SAMPLE_SIZE    = 20;
	private const DEFAULT_SUFFIX = ' (Copy)';

	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	/** @phpstan-ignore-next-line property.onlyWritten (wired for undo() impl in Task 10) */
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
		return 'duplicate';
	}

	public function label(): string {
		return __( 'Duplicate', 'content-ops' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'target_status'    => [
					'type'    => 'string',
					'default' => 'draft',
				],
				'reassign_author'  => [
					'type' => 'integer',
				],
				'title_suffix'     => [
					'type'    => 'string',
					'default' => self::DEFAULT_SUFFIX,
				],
				'include_children' => [
					'type'    => 'boolean',
					'default' => false,
				],
			],
		];
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function validate( QueryArgs $args, array $params ): ValidationResult {
		if ( isset( $params['target_status'] ) ) {
			$status = (string) $params['target_status'];
			if ( null === get_post_status_object( $status ) ) {
				return ValidationResult::error(
					new ContentOpsError(
						'co.params.invalid_status',
						'Unknown post status.',
						[ 'target_status' => $status ]
					)
				);
			}
		}

		if ( isset( $params['reassign_author'] ) ) {
			$user_id = (int) $params['reassign_author'];
			if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
				return ValidationResult::error(
					new ContentOpsError(
						'co.params.invalid_author',
						'User not found.',
						[ 'reassign_author' => $user_id ]
					)
				);
			}
		}

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
		return BatchResult::of( 0, 0, 0 );
	}

	public function supports_undo(): bool {
		return true;
	}

	public function undo( int $operation_id ): UndoResult {
		return UndoResult::error(
			new ContentOpsError( 'co.undo.not_implemented', 'Not implemented yet.' )
		);
	}
}
