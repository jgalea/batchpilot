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
use BatchPilot\History\Snapshot;
use BatchPilot\History\SnapshotRepository;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\Rewrite\RewriteLinks;

final class RepublishOperation implements OperationInterface, TracksAffectedIds {

	private const SAMPLE_SIZE = 20;

	private const POST_FIELDS = [
		'post_title',
		'post_content',
		'post_excerpt',
		'menu_order',
		'comment_status',
		'ping_status',
		'post_parent',
	];

	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	private OperationRepository $operations;
	private SnapshotRepository $snapshots;
	private PostCopier $copier;

	/** @var int[] */
	private array $last_affected = [];

	/** @var int[] */
	private static array $deferred_deletes = [];

	public function __construct(
		TokenGenerator $token_generator,
		TokenStore $token_store,
		OperationRepository $operations,
		SnapshotRepository $snapshots,
		PostCopier $copier
	) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
		$this->operations      = $operations;
		$this->snapshots       = $snapshots;
		$this->copier          = $copier;
	}

	/** @return int[] */
	public function last_affected_ids(): array {
		return $this->last_affected;
	}

	public function slug(): string {
		return 'republish';
	}

	public function label(): string {
		return __( 'Rewrite & Republish: merge copy into original', 'batchpilot' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => new \stdClass(),
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
			'user_id'    => get_current_user_id(),
		];

		$token = $this->token_generator->generate( $payload );
		$this->token_store->store( $token, $payload );

		return PreviewResult::of( $count, $sample_ids, $token );
	}

	/**
	 * Accepts either the original or its rewrite copy as an ID. When
	 * $params['defer_copy_delete'] is set the copy is deleted at shutdown instead of
	 * inline, so a REST save response built after the merge can still describe it.
	 *
	 * @param int[]                $ids
	 * @param array<string, mixed> $params
	 */
	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
		$this->last_affected = [];
		$operation_id        = isset( $params['__operation_id'] ) ? (int) $params['__operation_id'] : 0;
		$defer_delete        = ! empty( $params['defer_copy_delete'] );
		$succeeded           = 0;
		$failed              = 0;
		$item_errors         = [];

		foreach ( $ids as $id ) {
			$id          = (int) $id;
			$original_id = RewriteLinks::original_of( $id );
			$copy_id     = $id;
			if ( 0 === $original_id ) {
				$original_id = $id;
				$copy_id     = RewriteLinks::copy_of( $id );
			}

			$original = get_post( $original_id );
			$copy     = 0 === $copy_id ? null : get_post( $copy_id );
			if ( null === $original || null === $copy ) {
				++$failed;
				$item_errors[ $id ] = 'No rewrite copy found for this post.';
				continue;
			}
			if ( (int) get_post_meta( $original_id, RewriteLinks::META_COPY, true ) !== $copy_id || $original->post_type !== $copy->post_type ) {
				++$failed;
				$item_errors[ $id ] = 'The rewrite copy is not linked to this post.';
				continue;
			}
			if ( get_current_user_id() > 0 && ! current_user_can( 'edit_post', $original_id ) ) {
				++$failed;
				$item_errors[ $id ] = 'Insufficient permissions to edit the original post.';
				continue;
			}

			$error = $this->merge( $original, $copy, $operation_id, $defer_delete );
			if ( null !== $error ) {
				++$failed;
				$item_errors[ $id ] = $error;
				continue;
			}

			$this->last_affected[] = $original_id;
			++$succeeded;
		}

		return BatchResult::of( count( $ids ), $succeeded, $failed, $item_errors );
	}

	private function merge( \WP_Post $original, \WP_Post $copy, int $operation_id, bool $defer_delete ): ?string {
		$original_id = (int) $original->ID;
		$copy_id     = (int) $copy->ID;

		$snapshots = $this->capture( $operation_id, $original );

		wp_save_post_revision( $original_id );

		$update = [ 'ID' => $original_id ];
		foreach ( self::POST_FIELDS as $field ) {
			$update[ $field ] = $copy->$field;
		}

		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) || 0 === (int) $result ) {
			return is_wp_error( $result ) ? $result->get_error_message() : 'wp_update_post failed.';
		}

		$this->copier->sync_taxonomies( $copy_id, $original_id, $original->post_type );
		$this->copier->sync_meta( $copy_id, $original_id );

		if ( $operation_id > 0 && ! empty( $snapshots ) ) {
			$this->snapshots->bulk_insert( $snapshots );
		}

		RewriteLinks::unlink( $original_id, $copy_id );

		if ( $defer_delete ) {
			$this->defer_delete( $copy_id );
		} else {
			wp_delete_post( $copy_id, true );
		}

		/**
		 * Fires after a Rewrite & Republish copy has been merged onto its original.
		 *
		 * @param int $original_id The original post, which keeps its ID and URL.
		 * @param int $copy_id     The copy that was merged and is being deleted.
		 */
		do_action( 'batchpilot_rewrite_republished', $original_id, $copy_id );

		return null;
	}

	/**
	 * @return Snapshot[]
	 */
	private function capture( int $operation_id, \WP_Post $original ): array {
		$id        = (int) $original->ID;
		$snapshots = [];
		foreach ( self::POST_FIELDS as $field ) {
			$snapshots[] = new Snapshot( $operation_id, 'post', $id, $field, (string) $original->$field );
		}
		foreach ( get_object_taxonomies( $original->post_type ) as $taxonomy ) {
			$terms       = wp_get_object_terms( $id, $taxonomy, [ 'fields' => 'ids' ] );
			$snapshots[] = new Snapshot(
				$operation_id,
				'post',
				$id,
				'taxonomy:' . $taxonomy,
				(string) wp_json_encode( array_map( 'intval', is_array( $terms ) ? $terms : [] ) )
			);
		}

		$meta = [];
		foreach ( $this->copier->copyable_meta( $id, $this->copier->excluded_meta_patterns( $original ) ) as $key => $values ) {
			$meta[ $key ] = array_map( 'base64_encode', $values );
		}
		$snapshots[] = new Snapshot( $operation_id, 'post', $id, 'meta', (string) wp_json_encode( $meta ) );

		return $snapshots;
	}

	private function defer_delete( int $copy_id ): void {
		if ( empty( self::$deferred_deletes ) ) {
			add_action( 'shutdown', [ self::class, 'run_deferred_deletes' ], 5 );
		}
		self::$deferred_deletes[] = $copy_id;
	}

	public static function run_deferred_deletes(): void {
		$ids                    = self::$deferred_deletes;
		self::$deferred_deletes = [];
		foreach ( $ids as $copy_id ) {
			wp_delete_post( $copy_id, true );
		}
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

		$by_id = [];
		foreach ( $this->snapshots->for_operation( $operation_id ) as $snap ) {
			$by_id[ $snap->object_id() ][ $snap->field() ] = $snap->old_value();
		}

		$restored = 0;
		foreach ( $by_id as $post_id => $fields ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( null === $post ) {
				continue;
			}
			if ( get_current_user_id() > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$update = [ 'ID' => $post_id ];
			foreach ( self::POST_FIELDS as $field ) {
				if ( array_key_exists( $field, $fields ) ) {
					$update[ $field ] = (string) $fields[ $field ];
				}
			}
			if ( count( $update ) > 1 ) {
				$result = wp_update_post( wp_slash( $update ), true );
				if ( is_wp_error( $result ) || 0 === (int) $result ) {
					continue;
				}
			}

			foreach ( $fields as $field => $old_value ) {
				if ( 0 === strpos( $field, 'taxonomy:' ) ) {
					$term_ids = json_decode( (string) $old_value, true );
					wp_set_object_terms( $post_id, is_array( $term_ids ) ? array_map( 'intval', $term_ids ) : [], substr( $field, strlen( 'taxonomy:' ) ) );
				}
			}

			if ( isset( $fields['meta'] ) ) {
				$decoded = json_decode( (string) $fields['meta'], true );
				$meta    = [];
				foreach ( is_array( $decoded ) ? $decoded : [] as $key => $values ) {
					$meta[ (string) $key ] = array_map( 'base64_decode', (array) $values );
				}
				$this->copier->replace_meta( $post_id, $meta );
			}

			++$restored;
		}

		return UndoResult::of( $restored );
	}
}
