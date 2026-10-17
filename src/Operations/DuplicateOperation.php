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
use BatchPilot\Duplication\PostCopier;
use BatchPilot\Errors\BatchPilotError;
use BatchPilot\History\OperationRepository;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;

final class DuplicateOperation implements OperationInterface, TracksAffectedIds {

	private const SAMPLE_SIZE    = 20;
	private const DEFAULT_SUFFIX = ' (Copy)';

	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	private OperationRepository $operations;
	private PostCopier $copier;

	/** @var int[] */
	private array $last_new_ids = [];

	/** @return int[] */
	public function last_affected_ids(): array {
		return $this->last_new_ids;
	}

	/** @return int[] */
	public function last_new_ids(): array {
		return $this->last_new_ids;
	}

	public function __construct(
		TokenGenerator $token_generator,
		TokenStore $token_store,
		OperationRepository $operations,
		?PostCopier $copier = null
	) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
		$this->operations      = $operations;
		$this->copier          = $copier ?? new PostCopier();
	}

	public function slug(): string {
		return 'duplicate';
	}

	public function label(): string {
		return __( 'Duplicate', 'batchpilot' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'target_status'    => [
					'type'        => 'string',
					'widget'      => 'post_status',
					'default'     => 'draft',
					'label'       => __( 'Status of duplicates', 'batchpilot' ),
					'description' => __( 'Status applied to newly created copies.', 'batchpilot' ),
				],
				'reassign_author'  => [
					'type'        => 'integer',
					'widget'      => 'user',
					'label'       => __( 'Author of duplicates', 'batchpilot' ),
					'description' => __( 'If set, the new copies will be assigned to this user. Leave empty to keep the original author.', 'batchpilot' ),
				],
				'title_suffix'     => [
					'type'        => 'string',
					'default'     => self::DEFAULT_SUFFIX,
					'label'       => __( 'Title suffix', 'batchpilot' ),
					'description' => __( 'Appended to each copied title so duplicates are easy to spot.', 'batchpilot' ),
				],
				'include_children' => [
					'type'        => 'boolean',
					'default'     => false,
					'label'       => __( 'Also duplicate child posts', 'batchpilot' ),
					'description' => __( 'Recursively duplicate hierarchical children (pages, attachments) along with each matched post.', 'batchpilot' ),
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
					new BatchPilotError(
						'bp.params.invalid_status',
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
					new BatchPilotError(
						'bp.params.invalid_author',
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
		$this->last_new_ids = [];
		$target_status      = isset( $params['target_status'] ) ? (string) $params['target_status'] : 'draft';
		$reassign_author    = isset( $params['reassign_author'] ) ? (int) $params['reassign_author'] : 0;
		$title_suffix       = array_key_exists( 'title_suffix', $params ) ? (string) $params['title_suffix'] : self::DEFAULT_SUFFIX;

		$succeeded   = 0;
		$failed      = 0;
		$item_errors = [];

		// Defense in depth: see the matching comment in DeleteOperation::execute_batch().
		// batchpilot_duplicate gates whether this operation may run at all; this also
		// re-checks the native "create new post of this type" capability once up front
		// (it does not vary per row, since a Target is bound to a single post type).
		$has_create_cap = true;
		if ( get_current_user_id() > 0 ) {
			$post_type_object = get_post_type_object( $target->slug() );
			$create_cap       = ( null !== $post_type_object && isset( $post_type_object->cap->create_posts ) )
				? (string) $post_type_object->cap->create_posts
				: 'edit_posts';
			$has_create_cap   = current_user_can( $create_cap );
		}

		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$source = get_post( $id );
			if ( null === $source ) {
				++$failed;
				$item_errors[ $id ] = 'Post not found.';
				continue;
			}

			// Per-post read check on the source, plus the one-time create check above.
			if ( get_current_user_id() > 0 && ( ! $has_create_cap || ! current_user_can( 'read_post', $id ) ) ) {
				++$failed;
				$item_errors[ $id ] = 'Insufficient permissions to duplicate this item.';
				continue;
			}

			$new_id = $this->copier->create_copy(
				$source,
				[
					'post_title'  => $source->post_title . $title_suffix,
					'post_status' => $target_status,
					'post_author' => $reassign_author > 0 ? $reassign_author : (int) $source->post_author,
				]
			);
			if ( is_wp_error( $new_id ) ) {
				++$failed;
				$item_errors[ $id ] = $new_id->get_error_message();
				continue;
			}

			$this->last_new_ids[] = (int) $new_id;
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

		$restored = 0;
		foreach ( $op->affected_ids() as $id ) {
			$id = (int) $id;
			if ( get_current_user_id() > 0 && ! current_user_can( 'delete_post', $id ) ) {
				continue;
			}
			if ( wp_delete_post( $id, true ) ) {
				++$restored;
			}
		}

		return UndoResult::of( $restored );
	}
}
