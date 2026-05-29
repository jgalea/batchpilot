<?php
namespace BatchPilot\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Execution\ExecutionService;

final class EditCommand {

	private ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	/**
	 * Bulk-edit posts.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<slug>]
	 * : Post type slug. Default: post.
	 *
	 * [--status=<status>]
	 * : Source post status filter (comma separated).
	 *
	 * [--set-status=<status>]
	 * : New post status.
	 *
	 * [--reassign-author=<id>]
	 * : New author user ID.
	 *
	 * [--shift-dates=<days>]
	 * : Shift post_date by N days.
	 *
	 * [--comment-status=<status>]
	 * : open or closed.
	 *
	 * [--menu-order=<int>]
	 * : Set menu_order.
	 *
	 * [--dry-run]
	 * : Preview only.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * [--format=<format>]
	 * : table, json, count, ids. Default: table.
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args
	 * @param array<string, mixed> $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$result = $this->run( $assoc_args );
		if ( '' !== $result['output'] ) {
			\WP_CLI::line( $result['output'] );
		}
		if ( 0 !== $result['exit_code'] ) {
			\WP_CLI::halt( $result['exit_code'] );
		}
	}

	/**
	 * @param array<string, mixed> $assoc_args
	 * @return array{exit_code: int, output: string}
	 */
	public function run( array $assoc_args ): array {
		$post_type = (string) ( $assoc_args['post-type'] ?? 'post' );
		$format    = (string) ( $assoc_args['format'] ?? 'table' );
		$dry_run   = ! empty( $assoc_args['dry-run'] );

		$filters = [];
		if ( isset( $assoc_args['status'] ) ) {
			$filters['status'] = array_map( 'trim', explode( ',', (string) $assoc_args['status'] ) );
		}

		$params = [];
		if ( isset( $assoc_args['set-status'] ) ) {
			$params['set_status'] = (string) $assoc_args['set-status'];
		}
		if ( isset( $assoc_args['reassign-author'] ) ) {
			$params['reassign_author'] = (int) $assoc_args['reassign-author'];
		}
		if ( isset( $assoc_args['shift-dates'] ) ) {
			$params['shift_dates_days'] = (int) $assoc_args['shift-dates'];
		}
		if ( isset( $assoc_args['comment-status'] ) ) {
			$params['comment_status'] = (string) $assoc_args['comment-status'];
		}
		if ( isset( $assoc_args['menu-order'] ) ) {
			$params['menu_order'] = (int) $assoc_args['menu-order'];
		}

		$preview = $this->execution->preview( $post_type, 'edit', $filters, $params );
		if ( ! $preview->is_ok() ) {
			$error   = $preview->get_error();
			$payload = null === $error ? [] : $error->to_array();
			return [
				'exit_code' => 2,
				'output'    => (string) wp_json_encode( $payload ),
			];
		}

		if ( $dry_run ) {
			return [
				'exit_code' => 0,
				'output'    => $this->format_preview( $preview->count(), $preview->sample_ids(), $format, 'preview' ),
			];
		}

		$op_id = $this->execution->record( $post_type, 'edit', (int) get_current_user_id(), $filters, $params );
		$batch = $this->execution->run_sync( $op_id );
		if ( ! $batch->is_ok() ) {
			$error   = $batch->get_error();
			$payload = null === $error ? [] : $error->to_array();
			return [
				'exit_code' => 1,
				'output'    => (string) wp_json_encode( $payload ),
			];
		}

		return [
			'exit_code' => 0,
			'output'    => $this->format_batch( $op_id, $batch->processed(), $batch->succeeded(), $batch->failed(), $format ),
		];
	}

	/** @param int[] $sample_ids */
	private function format_preview( int $count, array $sample_ids, string $format, string $status ): string {
		if ( 'count' === $format ) {
			return (string) $count;
		}
		if ( 'ids' === $format ) {
			return implode( "\n", array_map( 'strval', $sample_ids ) );
		}
		$payload = [
			'status'     => $status,
			'count'      => $count,
			'sample_ids' => $sample_ids,
		];
		return (string) wp_json_encode( $payload );
	}

	private function format_batch( int $op_id, int $processed, int $succeeded, int $failed, string $format ): string {
		if ( 'count' === $format ) {
			return (string) $succeeded;
		}
		$payload = [
			'status'       => 'completed',
			'operation_id' => $op_id,
			'processed'    => $processed,
			'succeeded'    => $succeeded,
			'failed'       => $failed,
		];
		return (string) wp_json_encode( $payload );
	}
}
