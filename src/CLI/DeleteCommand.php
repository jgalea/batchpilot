<?php
namespace ContentOps\CLI;

use ContentOps\Execution\ExecutionService;

final class DeleteCommand {

	private ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	/**
	 * Trash or permanently delete posts in bulk.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<slug>]
	 * : Post type slug. Default: post.
	 *
	 * [--status=<status>]
	 * : Post status or comma-separated list.
	 *
	 * [--older-than=<duration>]
	 * : Modified-before cutoff, e.g. 90d.
	 *
	 * [--permanent]
	 * : Hard-delete instead of trashing.
	 *
	 * [--dry-run]
	 * : Preview only.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, count, ids. Default: table.
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
		$permanent = ! empty( $assoc_args['permanent'] );

		$filters = [];
		if ( isset( $assoc_args['status'] ) ) {
			$filters['status'] = array_map( 'trim', explode( ',', (string) $assoc_args['status'] ) );
		}
		if ( isset( $assoc_args['older-than'] ) ) {
			$filters['modified_before'] = $this->duration_to_date( (string) $assoc_args['older-than'] );
		}

		$params = [ 'permanent' => $permanent ];

		$preview = $this->execution->preview( $post_type, 'delete', $filters, $params );
		if ( ! $preview->is_ok() ) {
			$error   = $preview->get_error();
			$payload = null === $error ? [] : $error->to_array();
			return [
				'exit_code' => 1,
				'output'    => (string) wp_json_encode( $payload ),
			];
		}

		if ( $dry_run ) {
			return [
				'exit_code' => 0,
				'output'    => $this->format_preview( $preview->count(), $preview->sample_ids(), $format, 'preview' ),
			];
		}

		$op_id = $this->execution->record( $post_type, 'delete', (int) get_current_user_id(), $filters, $params );
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

	private function duration_to_date( string $duration ): string {
		if ( preg_match( '/^(\d+)d$/', $duration, $m ) ) {
			return gmdate( 'Y-m-d H:i:s', time() - ( (int) $m[1] ) * DAY_IN_SECONDS );
		}
		return $duration;
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
