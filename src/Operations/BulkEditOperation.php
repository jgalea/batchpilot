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
use ContentOps\History\SnapshotRepository;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;

final class BulkEditOperation implements OperationInterface {

	private const SAMPLE_SIZE = 20;

	private TokenGenerator $token_generator;
	private TokenStore $token_store;

	/** @phpstan-ignore-next-line property.onlyWritten */
	private OperationRepository $operations;

	/** @phpstan-ignore-next-line property.onlyWritten */
	private SnapshotRepository $snapshots;

	public function __construct(
		TokenGenerator $token_generator,
		TokenStore $token_store,
		OperationRepository $operations,
		SnapshotRepository $snapshots
	) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
		$this->operations      = $operations;
		$this->snapshots       = $snapshots;
	}

	public function slug(): string {
		return 'edit';
	}

	public function label(): string {
		return __( 'Bulk edit', 'content-ops' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'set_status'       => [ 'type' => 'string' ],
				'reassign_author'  => [ 'type' => 'integer' ],
				'shift_dates_days' => [ 'type' => 'integer' ],
				'taxonomy_add'     => [ 'type' => 'object' ],
				'taxonomy_remove'  => [ 'type' => 'object' ],
				'password'         => [ 'type' => 'string' ],
				'comment_status'   => [
					'type' => 'string',
					'enum' => [ 'open', 'closed' ],
				],
				'menu_order'       => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function validate( QueryArgs $args, array $params ): ValidationResult {
		if ( isset( $params['set_status'] ) && null === get_post_status_object( (string) $params['set_status'] ) ) {
			return ValidationResult::error(
				new ContentOpsError(
					'co.params.invalid_status',
					'Unknown post status.',
					[ 'set_status' => $params['set_status'] ]
				)
			);
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

		if ( isset( $params['shift_dates_days'] ) && ! is_int( $params['shift_dates_days'] ) ) {
			return ValidationResult::error(
				new ContentOpsError( 'co.params.invalid_shift', 'shift_dates_days must be an integer.' )
			);
		}

		if ( isset( $params['menu_order'] ) && ! is_int( $params['menu_order'] ) ) {
			return ValidationResult::error(
				new ContentOpsError( 'co.params.invalid_menu_order', 'menu_order must be an integer.' )
			);
		}

		if ( isset( $params['comment_status'] ) && ! in_array( $params['comment_status'], [ 'open', 'closed' ], true ) ) {
			return ValidationResult::error(
				new ContentOpsError( 'co.params.invalid_comment_status', 'comment_status must be open or closed.' )
			);
		}

		foreach ( [ 'taxonomy_add', 'taxonomy_remove' ] as $key ) {
			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}
			$spec = $params[ $key ];
			if ( ! is_array( $spec ) || empty( $spec['taxonomy'] ) || ! taxonomy_exists( (string) $spec['taxonomy'] ) ) {
				return ValidationResult::error(
					new ContentOpsError( 'co.params.invalid_taxonomy', 'Unknown taxonomy.', [ 'param' => $key ] )
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
		return UndoResult::error( new ContentOpsError( 'co.undo.not_implemented', 'Not implemented yet.' ) );
	}
}
