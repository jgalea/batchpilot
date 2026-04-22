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
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;

final class DeleteOperation implements OperationInterface {

	private const SAMPLE_SIZE = 20;

	private TokenGenerator $token_generator;
	private TokenStore $token_store;

	public function __construct( TokenGenerator $token_generator, TokenStore $token_store ) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
	}

	public function slug(): string {
		return 'delete';
	}

	public function label(): string {
		return __( 'Delete', 'content-ops' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'permanent' => [
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
		return UndoResult::error( new ContentOpsError( 'co.undo.not_implemented', 'Not implemented yet.' ) );
	}
}
