<?php
namespace BatchPilot\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Execution\ExecutionService;

abstract class IdsOperationCommand {

	protected ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	abstract protected function operation(): string;

	/**
	 * @param string[]             $args
	 * @param array<string, mixed> $assoc_args
	 */
	protected function invoke( array $args, array $assoc_args ): void {
		if ( ! empty( $args ) && ! isset( $assoc_args['ids'] ) ) {
			$assoc_args['ids'] = implode( ',', $args );
		}
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
		$dry_run   = ! empty( $assoc_args['dry-run'] );
		$ids       = array_values( array_filter( array_map( 'intval', explode( ',', (string) ( $assoc_args['ids'] ?? '' ) ) ) ) );

		if ( empty( $ids ) ) {
			return $this->failure( 'bp.params.missing_ids', 'Pass one or more post IDs.' );
		}

		$filters = [ 'ids' => $ids ];
		$params  = [];
		if ( isset( $assoc_args['reassign-author'] ) ) {
			$params['reassign_author'] = (int) $assoc_args['reassign-author'];
		}

		$preview = $this->execution->preview( $post_type, $this->operation(), $filters, $params );
		if ( ! $preview->is_ok() ) {
			$error = $preview->get_error();
			return [
				'exit_code' => 1,
				'output'    => (string) wp_json_encode( null === $error ? [] : $error->to_array() ),
			];
		}

		if ( $dry_run ) {
			return [
				'exit_code' => 0,
				'output'    => (string) wp_json_encode(
					[
						'status'     => 'preview',
						'count'      => $preview->count(),
						'sample_ids' => $preview->sample_ids(),
					]
				),
			];
		}

		$op_id = $this->execution->record( $post_type, $this->operation(), (int) get_current_user_id(), $filters, $params );
		$batch = $this->execution->run_sync( $op_id );
		if ( ! $batch->is_ok() ) {
			$error = $batch->get_error();
			return [
				'exit_code' => 1,
				'output'    => (string) wp_json_encode( null === $error ? [] : $error->to_array() ),
			];
		}

		$payload = [
			'status'       => $batch->failed() > 0 && 0 === $batch->succeeded() ? 'failed' : 'completed',
			'operation_id' => $op_id,
			'processed'    => $batch->processed(),
			'succeeded'    => $batch->succeeded(),
			'failed'       => $batch->failed(),
		];
		if ( $batch->failed() > 0 ) {
			$payload['errors'] = $batch->item_errors();
		}

		return [
			'exit_code' => $batch->failed() > 0 ? 1 : 0,
			'output'    => (string) wp_json_encode( $payload ),
		];
	}

	/**
	 * @return array{exit_code: int, output: string}
	 */
	private function failure( string $code, string $message ): array {
		$err = new \BatchPilot\Errors\BatchPilotError( $code, $message );
		return [
			'exit_code' => 1,
			'output'    => (string) wp_json_encode( $err->to_array() ),
		];
	}
}
