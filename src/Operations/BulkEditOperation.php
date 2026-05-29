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
use BatchPilot\History\SnapshotRepository;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;

final class BulkEditOperation implements OperationInterface {

	private const SAMPLE_SIZE = 20;

	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	private OperationRepository $operations;
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
		return __( 'Bulk edit', 'batchpilot' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'set_status'       => [
					'type'        => 'string',
					'widget'      => 'post_status',
					'label'       => __( 'Set status to', 'batchpilot' ),
					'description' => __( 'Change the publication status of every matched item.', 'batchpilot' ),
				],
				'reassign_author'  => [
					'type'        => 'integer',
					'widget'      => 'user',
					'label'       => __( 'Reassign author to', 'batchpilot' ),
					'description' => __( 'Every matched item will be reassigned to this user.', 'batchpilot' ),
				],
				'shift_dates_days' => [
					'type'        => 'integer',
					'label'       => __( 'Shift publish dates by (days)', 'batchpilot' ),
					'description' => __( 'Positive to push forward, negative to pull back. Example: −30 shifts posts a month earlier.', 'batchpilot' ),
				],
				'taxonomy_add'     => [
					'type'        => 'object',
					'widget'      => 'taxonomy_terms',
					'label'       => __( 'Add taxonomy terms', 'batchpilot' ),
					'description' => __( 'Pick a taxonomy and one or more terms. They will be added to every matched item (existing terms are preserved).', 'batchpilot' ),
				],
				'taxonomy_remove'  => [
					'type'        => 'object',
					'widget'      => 'taxonomy_terms',
					'label'       => __( 'Remove taxonomy terms', 'batchpilot' ),
					'description' => __( 'Pick a taxonomy and the terms to remove from every matched item.', 'batchpilot' ),
				],
				'password'         => [
					'type'        => 'string',
					'widget'      => 'password',
					'label'       => __( 'Set password', 'batchpilot' ),
					'description' => __( 'Password-protect every matched item. Leave empty to clear an existing password.', 'batchpilot' ),
				],
				'comment_status'   => [
					'type'        => 'string',
					'enum'        => [ 'open', 'closed' ],
					'label'       => __( 'Comments', 'batchpilot' ),
					'description' => __( 'Allow or disallow comments on all matched items.', 'batchpilot' ),
				],
				'menu_order'       => [
					'type'        => 'integer',
					'label'       => __( 'Menu order', 'batchpilot' ),
					'description' => __( 'Numeric order used by themes to sort posts/pages.', 'batchpilot' ),
				],
			],
		];
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function validate( QueryArgs $args, array $params ): ValidationResult {
		if ( isset( $params['set_status'] ) && null === get_post_status_object( (string) $params['set_status'] ) ) {
			return ValidationResult::error(
				new BatchPilotError(
					'bp.params.invalid_status',
					'Unknown post status.',
					[ 'set_status' => $params['set_status'] ]
				)
			);
		}

		if ( isset( $params['reassign_author'] ) ) {
			$user_id = (int) $params['reassign_author'];
			if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
				return ValidationResult::error(
					new BatchPilotError(
						'bp.params.invalid_author',
						'User not found.',
						[ 'reassign_author' => $user_id ]
					)
				);
			}
		}

		if ( isset( $params['shift_dates_days'] ) && ! is_int( $params['shift_dates_days'] ) ) {
			return ValidationResult::error(
				new BatchPilotError( 'bp.params.invalid_shift', 'shift_dates_days must be an integer.' )
			);
		}

		if ( isset( $params['menu_order'] ) && ! is_int( $params['menu_order'] ) ) {
			return ValidationResult::error(
				new BatchPilotError( 'bp.params.invalid_menu_order', 'menu_order must be an integer.' )
			);
		}

		if ( isset( $params['comment_status'] ) && ! in_array( $params['comment_status'], [ 'open', 'closed' ], true ) ) {
			return ValidationResult::error(
				new BatchPilotError( 'bp.params.invalid_comment_status', 'comment_status must be open or closed.' )
			);
		}

		foreach ( [ 'taxonomy_add', 'taxonomy_remove' ] as $key ) {
			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}
			$spec = $params[ $key ];
			if ( ! is_array( $spec ) || empty( $spec['taxonomy'] ) || ! taxonomy_exists( (string) $spec['taxonomy'] ) ) {
				return ValidationResult::error(
					new BatchPilotError( 'bp.params.invalid_taxonomy', 'Unknown taxonomy.', [ 'param' => $key ] )
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
		$operation_id = isset( $params['__operation_id'] ) ? (int) $params['__operation_id'] : 0;
		$succeeded    = 0;
		$failed       = 0;
		$item_errors  = [];
		$snapshots    = [];

		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$post = get_post( $id );
			if ( null === $post ) {
				++$failed;
				$item_errors[ $id ] = 'Post not found.';
				continue;
			}

			$update = [ 'ID' => $id ];

			if ( isset( $params['set_status'] ) ) {
				$snapshots[]           = new \BatchPilot\History\Snapshot( $operation_id, 'post', $id, 'post_status', (string) $post->post_status );
				$update['post_status'] = (string) $params['set_status'];
			}
			if ( isset( $params['reassign_author'] ) ) {
				$snapshots[]           = new \BatchPilot\History\Snapshot( $operation_id, 'post', $id, 'post_author', (string) $post->post_author );
				$update['post_author'] = (int) $params['reassign_author'];
			}
			if ( isset( $params['shift_dates_days'] ) ) {
				$snapshots[]             = new \BatchPilot\History\Snapshot( $operation_id, 'post', $id, 'post_date', (string) $post->post_date );
				$snapshots[]             = new \BatchPilot\History\Snapshot( $operation_id, 'post', $id, 'post_date_gmt', (string) $post->post_date_gmt );
				$shifted                 = gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date ) + ( (int) $params['shift_dates_days'] * DAY_IN_SECONDS ) );
				$shifted_gmt             = gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date_gmt ) + ( (int) $params['shift_dates_days'] * DAY_IN_SECONDS ) );
				$update['post_date']     = $shifted;
				$update['post_date_gmt'] = $shifted_gmt;
			}
			if ( isset( $params['password'] ) ) {
				$snapshots[]             = new \BatchPilot\History\Snapshot( $operation_id, 'post', $id, 'post_password', (string) $post->post_password );
				$update['post_password'] = (string) $params['password'];
			}
			if ( isset( $params['comment_status'] ) ) {
				$snapshots[]              = new \BatchPilot\History\Snapshot( $operation_id, 'post', $id, 'comment_status', (string) $post->comment_status );
				$update['comment_status'] = (string) $params['comment_status'];
			}
			if ( isset( $params['menu_order'] ) ) {
				$snapshots[]          = new \BatchPilot\History\Snapshot( $operation_id, 'post', $id, 'menu_order', (string) $post->menu_order );
				$update['menu_order'] = (int) $params['menu_order'];
			}

			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) || 0 === (int) $result ) {
				++$failed;
				$item_errors[ $id ] = is_wp_error( $result ) ? $result->get_error_message() : 'wp_update_post failed.';
				continue;
			}

			if ( isset( $params['taxonomy_add'] ) ) {
				$tax         = $params['taxonomy_add'];
				$existing    = wp_get_object_terms( $id, (string) $tax['taxonomy'], [ 'fields' => 'ids' ] );
				$snapshots[] = new \BatchPilot\History\Snapshot(
					$operation_id,
					'post',
					$id,
					'taxonomy:' . (string) $tax['taxonomy'],
					(string) wp_json_encode( array_map( 'intval', is_array( $existing ) ? $existing : [] ) )
				);
				wp_set_object_terms( $id, array_map( 'intval', (array) $tax['term_ids'] ), (string) $tax['taxonomy'], true );
			}
			if ( isset( $params['taxonomy_remove'] ) ) {
				$tax         = $params['taxonomy_remove'];
				$existing    = wp_get_object_terms( $id, (string) $tax['taxonomy'], [ 'fields' => 'ids' ] );
				$snapshots[] = new \BatchPilot\History\Snapshot(
					$operation_id,
					'post',
					$id,
					'taxonomy:' . (string) $tax['taxonomy'],
					(string) wp_json_encode( array_map( 'intval', is_array( $existing ) ? $existing : [] ) )
				);
				wp_remove_object_terms( $id, array_map( 'intval', (array) $tax['term_ids'] ), (string) $tax['taxonomy'] );
			}

			++$succeeded;
		}

		if ( $operation_id > 0 && ! empty( $snapshots ) ) {
			$this->snapshots->bulk_insert( $snapshots );
		}

		return BatchResult::of( count( $ids ), $succeeded, $failed, $item_errors );
	}

	public function supports_undo(): bool {
		return true;
	}

	public function undo( int $operation_id ): UndoResult {
		$op = $this->operations->find( $operation_id );
		if ( null === $op ) {
			return UndoResult::error( new BatchPilotError( 'bp.undo.not_found', 'Operation not found.', [ 'operation_id' => $operation_id ] ) );
		}

		$snaps = $this->snapshots->for_operation( $operation_id );
		$by_id = [];
		foreach ( $snaps as $snap ) {
			$by_id[ $snap->object_id() ][ $snap->field() ] = $snap->old_value();
		}

		$restored = 0;
		foreach ( $by_id as $post_id => $fields ) {
			$update = [ 'ID' => (int) $post_id ];
			foreach ( $fields as $field => $old_value ) {
				if ( 0 === strpos( $field, 'taxonomy:' ) ) {
					$taxonomy = substr( $field, strlen( 'taxonomy:' ) );
					$term_ids = json_decode( (string) $old_value, true );
					wp_set_object_terms( (int) $post_id, is_array( $term_ids ) ? array_map( 'intval', $term_ids ) : [], $taxonomy );
					continue;
				}
				if ( in_array( $field, [ 'menu_order', 'post_author' ], true ) ) {
					$update[ $field ] = (int) $old_value;
				} else {
					$update[ $field ] = (string) $old_value;
				}
			}
			if ( count( $update ) > 1 ) {
				/** @phpstan-ignore-next-line argument.type */
				$result = wp_update_post( $update, true );
				if ( ! is_wp_error( $result ) && 0 !== (int) $result ) {
					++$restored;
				}
			} else {
				++$restored;
			}
		}

		return UndoResult::of( $restored );
	}
}
