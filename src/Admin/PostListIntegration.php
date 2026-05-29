<?php
namespace BatchPilot\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

final class PostListIntegration {

	private string $operations_url;

	public function __construct( string $operations_url ) {
		$this->operations_url = $operations_url;
	}

	public function register(): void {
		foreach ( get_post_types( [ 'public' => true ] ) as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", [ $this, 'filter_bulk_actions' ] );
			add_filter( "handle_bulk_actions-edit-{$post_type}", [ $this, 'handle_bulk_action' ], 10, 3 );
		}
		add_filter( 'post_row_actions', [ $this, 'filter_row_actions' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'filter_row_actions' ], 10, 2 );
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function filter_row_actions( array $actions, WP_Post $post ): array {
		if ( ! current_user_can( 'batchpilot_duplicate' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return $actions;
		}
		$url                             = $this->build_url( 'duplicate', $post->post_type, [ 'ids' => [ (int) $post->ID ] ] );
		$actions['batchpilot_duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Duplicate with BatchPilot', 'batchpilot' )
		);
		return $actions;
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function filter_bulk_actions( array $actions ): array {
		return array_merge(
			$actions,
			[
				'batchpilot_delete'    => __( 'BatchPilot: Delete', 'batchpilot' ),
				'batchpilot_duplicate' => __( 'BatchPilot: Duplicate', 'batchpilot' ),
				'batchpilot_edit'      => __( 'BatchPilot: Bulk edit', 'batchpilot' ),
			]
		);
	}

	/**
	 * @param int[] $post_ids
	 */
	public function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( 0 !== strpos( $action, 'batchpilot_' ) ) {
			return $redirect_to;
		}
		$op        = substr( $action, strlen( 'batchpilot_' ) );
		$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $this->build_url( $op, $post_type, [ 'ids' => array_map( 'intval', $post_ids ) ] );
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	private function build_url( string $operation, string $target, array $filters ): string {
		$base = add_query_arg(
			[
				'target'    => $target,
				'operation' => $operation,
			],
			$this->operations_url
		);

		$pairs = [];
		foreach ( $filters as $filter_key => $filter_value ) {
			if ( is_array( $filter_value ) ) {
				foreach ( $filter_value as $item ) {
					$pairs[] = rawurlencode( 'filters[' . $filter_key . '][]' ) . '=' . rawurlencode( (string) $item );
				}
			} else {
				$pairs[] = rawurlencode( 'filters[' . $filter_key . ']' ) . '=' . rawurlencode( (string) $filter_value );
			}
		}

		if ( empty( $pairs ) ) {
			return $base;
		}

		$separator = ( false === strpos( $base, '?' ) ) ? '?' : '&';
		return $base . $separator . implode( '&', $pairs );
	}
}
