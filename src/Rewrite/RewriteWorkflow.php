<?php
namespace BatchPilot\Rewrite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Execution\ExecutionService;
use WP_Post;

final class RewriteWorkflow {

	public const META_ERROR = '_bp_rewrite_error';

	private ExecutionService $execution;

	/** @var array<int, int> Merged copy ID => original ID, for this request. */
	private array $merged = [];

	/** @var array<int, true> */
	private array $published_copies = [];

	private bool $shutdown_registered = false;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	public function register(): void {
		add_filter( 'wp_insert_post_data', [ $this, 'guard_publish' ], 10, 2 );
		add_action( 'transition_post_status', [ $this, 'track_publish' ], 10, 3 );
		add_action( 'wp_after_insert_post', [ $this, 'on_after_insert' ], 10, 2 );
		add_action( 'publish_future_post', [ $this, 'on_scheduled' ], 1 );
		add_action( 'before_delete_post', [ $this, 'on_before_delete' ] );
		add_filter( 'redirect_post_location', [ $this, 'filter_redirect' ], 10, 2 );
		add_filter( 'wp_robots', [ $this, 'filter_robots' ] );
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public function guard_publish( array $data, array $postarr ): array {
		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( 0 === $post_id || ! in_array( $data['post_status'] ?? '', [ 'publish', 'future' ], true ) ) {
			return $data;
		}
		$original_id = RewriteLinks::original_of( $post_id );
		if ( 0 !== $original_id && get_current_user_id() > 0 && ! current_user_can( 'edit_post', $original_id ) ) {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	public function track_publish( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status || 0 === RewriteLinks::original_of( (int) $post->ID ) ) {
			return;
		}
		$this->published_copies[ (int) $post->ID ] = true;
		if ( ! $this->shutdown_registered ) {
			$this->shutdown_registered = true;
			add_action( 'shutdown', [ $this, 'finish_published_copies' ], 4 );
		}
	}

	public function on_after_insert( int $post_id, WP_Post $post ): void {
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		$this->merge_copy( $post_id, true );
	}

	public function finish_published_copies(): void {
		foreach ( array_keys( $this->published_copies ) as $copy_id ) {
			$this->merge_copy( (int) $copy_id, false );
		}
		$this->published_copies = [];
	}

	public function on_scheduled( int $post_id ): void {
		$copy = get_post( $post_id );
		if ( null === $copy || 'future' !== $copy->post_status || 0 === RewriteLinks::original_of( $post_id ) ) {
			return;
		}
		if ( strtotime( $copy->post_date_gmt . ' GMT' ) > time() ) {
			return;
		}
		$this->merge_copy( $post_id, false );
	}

	public function on_before_delete( int $post_id ): void {
		$original_id = RewriteLinks::original_of( $post_id );
		if ( 0 !== $original_id ) {
			RewriteLinks::unlink( $original_id, $post_id );
			return;
		}
		$copy_id = (int) get_post_meta( $post_id, RewriteLinks::META_COPY, true );
		if ( 0 !== $copy_id ) {
			RewriteLinks::unlink( $post_id, $copy_id );
		}
	}

	public function filter_redirect( string $location, int $post_id ): string {
		if ( ! isset( $this->merged[ $post_id ] ) ) {
			return $location;
		}
		return add_query_arg( 'message', 1, (string) get_edit_post_link( $this->merged[ $post_id ], 'raw' ) );
	}

	/**
	 * @param array<string, bool|string> $robots
	 * @return array<string, bool|string>
	 */
	public function filter_robots( array $robots ): array {
		if ( is_singular() && 0 !== RewriteLinks::original_of( (int) get_queried_object_id() ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	private function merge_copy( int $copy_id, bool $defer_delete ): void {
		$original_id = RewriteLinks::original_of( $copy_id );
		if ( 0 === $original_id ) {
			return;
		}
		$copy     = get_post( $copy_id );
		$original = get_post( $original_id );
		if ( null === $copy || ! in_array( $copy->post_status, [ 'publish', 'future' ], true ) ) {
			return;
		}
		if ( null === $original ) {
			delete_post_meta( $copy_id, RewriteLinks::META_ORIGINAL );
			return;
		}

		$user_id = (int) get_current_user_id();
		$params  = [ 'defer_copy_delete' => $defer_delete ];
		if ( 0 === $user_id ) {
			$params['scheduled_by'] = (int) $copy->post_author;
		}

		$operation_id = $this->execution->record( $original->post_type, 'republish', $user_id, [ 'ids' => [ $original_id ] ], $params );
		$result       = $this->execution->run_sync( $operation_id );

		if ( $result->is_ok() && 0 === $result->failed() && $result->succeeded() > 0 ) {
			$this->merged[ $copy_id ] = $original_id;
			return;
		}

		$errors  = $result->is_ok() ? $result->item_errors() : [];
		$message = ! empty( $errors ) ? (string) reset( $errors ) : 'The rewrite could not be merged into the original.';
		update_post_meta( $copy_id, self::META_ERROR, $message );
		$this->revert_to_draft( $copy_id );
	}

	private function revert_to_draft( int $copy_id ): void {
		wp_update_post(
			[
				'ID'          => $copy_id,
				'post_status' => 'draft',
			]
		);
	}
}
