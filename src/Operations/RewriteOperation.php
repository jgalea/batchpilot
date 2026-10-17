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
use BatchPilot\Contracts\TracksAffectedIds;
use BatchPilot\Contracts\UndoResult;
use BatchPilot\Contracts\ValidationResult;
use BatchPilot\Duplication\Access;
use BatchPilot\Duplication\PostCopier;
use BatchPilot\Errors\BatchPilotError;
use BatchPilot\History\OperationRepository;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\Rewrite\RewriteLinks;

final class RewriteOperation implements OperationInterface, TracksAffectedIds {

	private const SAMPLE_SIZE = 20;

	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	private OperationRepository $operations;
	private PostCopier $copier;
	private Access $access;

	/** @var int[] */
	private array $last_new_ids = [];

	public function __construct(
		TokenGenerator $token_generator,
		TokenStore $token_store,
		OperationRepository $operations,
		PostCopier $copier,
		Access $access
	) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
		$this->operations      = $operations;
		$this->copier          = $copier;
		$this->access          = $access;
	}

	/** @return int[] */
	public function last_affected_ids(): array {
		return $this->last_new_ids;
	}

	public function slug(): string {
		return 'rewrite';
	}

	public function label(): string {
		return __( 'Rewrite & Republish: create draft copy', 'batchpilot' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'reassign_author' => [
					'type'        => 'integer',
					'widget'      => 'user',
					'label'       => __( 'Author of the draft copy', 'batchpilot' ),
					'description' => __( 'Defaults to the user creating the copy. The original keeps its author when the copy is republished.', 'batchpilot' ),
				],
			],
		];
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function validate( QueryArgs $args, array $params ): ValidationResult {
		if ( isset( $params['reassign_author'] ) ) {
			$user_id = (int) $params['reassign_author'];
			if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
				return ValidationResult::error(
					new BatchPilotError( 'bp.params.invalid_author', 'User not found.', [ 'reassign_author' => $user_id ] )
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
		$this->last_new_ids = [];
		$succeeded          = 0;
		$failed             = 0;
		$item_errors        = [];
		$has_user           = get_current_user_id() > 0;
		$author             = isset( $params['reassign_author'] ) ? (int) $params['reassign_author'] : get_current_user_id();

		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$source = get_post( $id );
			if ( null === $source ) {
				++$failed;
				$item_errors[ $id ] = 'Post not found.';
				continue;
			}
			if ( ! $this->access->post_type_enabled( $source->post_type ) ) {
				++$failed;
				$item_errors[ $id ] = 'Rewrite & Republish is not enabled for this post type.';
				continue;
			}
			if ( ! in_array( $source->post_status, [ 'publish', 'private' ], true ) ) {
				++$failed;
				$item_errors[ $id ] = 'Only published posts can be rewritten.';
				continue;
			}
			if ( 0 !== RewriteLinks::original_of( $id ) ) {
				++$failed;
				$item_errors[ $id ] = 'This post is itself a rewrite copy.';
				continue;
			}
			if ( $has_user ) {
				$type = get_post_type_object( $source->post_type );
				if ( ! current_user_can( 'edit_post', $id ) || null === $type || ! current_user_can( $type->cap->create_posts ) ) {
					++$failed;
					$item_errors[ $id ] = 'Insufficient permissions to rewrite this item.';
					continue;
				}
			}

			$existing = RewriteLinks::copy_of( $id );
			if ( 0 !== $existing ) {
				++$failed;
				$item_errors[ $id ] = sprintf( 'A rewrite of this post already exists (#%d).', $existing );
				continue;
			}

			$overrides = $author > 0 ? [ 'post_author' => $author ] : [];
			$copy_id   = $this->copier->create_copy( $source, $overrides );
			if ( is_wp_error( $copy_id ) ) {
				++$failed;
				$item_errors[ $id ] = $copy_id->get_error_message();
				continue;
			}

			if ( ! RewriteLinks::link( $id, $copy_id ) ) {
				wp_delete_post( $copy_id, true );
				++$failed;
				$item_errors[ $id ] = 'A rewrite of this post was created at the same time by someone else.';
				continue;
			}

			$this->last_new_ids[] = $copy_id;
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
				new BatchPilotError( 'bp.undo.not_found', 'Operation not found.', [ 'operation_id' => $operation_id ] )
			);
		}

		$restored = 0;
		foreach ( $op->affected_ids() as $copy_id ) {
			$copy_id = (int) $copy_id;
			$copy    = get_post( $copy_id );
			if ( null === $copy || 0 === RewriteLinks::original_of( $copy_id ) ) {
				continue;
			}
			if ( get_current_user_id() > 0 && ! current_user_can( 'delete_post', $copy_id ) ) {
				continue;
			}
			if ( wp_delete_post( $copy_id, true ) ) {
				++$restored;
			}
		}

		return UndoResult::of( $restored );
	}
}
