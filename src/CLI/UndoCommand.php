<?php
namespace BatchPilot\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Errors\BatchPilotError;
use BatchPilot\History\OperationRepository;
use BatchPilot\Registry\OperationRegistry;

final class UndoCommand {

	private OperationRegistry $operations;
	private OperationRepository $repo;

	public function __construct( OperationRegistry $operations, OperationRepository $repo ) {
		$this->operations = $operations;
		$this->repo       = $repo;
	}

	/**
	 * Undo a previously executed BatchPilot operation.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Operation id.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json. Default: table.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args
	 * @param array<string, mixed> $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$assoc_args['id'] = (int) ( $args[0] ?? 0 );
		$result           = $this->run( $assoc_args );
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
		$id = (int) ( $assoc_args['id'] ?? 0 );

		$row = $this->repo->find( $id );
		if ( null === $row ) {
			$err = new BatchPilotError(
				'bp.operation.not_found',
				'Operation not found.',
				[ 'id' => $id ]
			);
			return [
				'exit_code' => 1,
				'output'    => (string) wp_json_encode( $err->to_array() ),
			];
		}

		// Mirrors UndoController::check_permission(): a user can only undo their own
		// operations unless they're an administrator. Skipped when WP-CLI runs without
		// --user (get_current_user_id() === 0), matching the existing shell-trust model
		// used elsewhere in this plugin (e.g. DeleteOperation's per-post capability check).
		if ( get_current_user_id() > 0 && ! current_user_can( 'manage_options' ) && get_current_user_id() !== $row->user_id() ) {
			$err = new BatchPilotError(
				'bp.auth.forbidden',
				'You can only undo operations you ran yourself.',
				[ 'id' => $id ]
			);
			return [
				'exit_code' => 1,
				'output'    => (string) wp_json_encode( $err->to_array() ),
			];
		}

		$op = $this->operations->get( $row->type() );
		if ( null === $op ) {
			$err = new BatchPilotError(
				'bp.operation.unknown',
				'Operation not registered.',
				[ 'type' => $row->type() ]
			);
			return [
				'exit_code' => 1,
				'output'    => (string) wp_json_encode( $err->to_array() ),
			];
		}

		$result = $op->undo( $id );
		if ( ! $result->is_ok() ) {
			$error   = $result->get_error();
			$payload = null === $error ? [] : $error->to_array();
			return [
				'exit_code' => 1,
				'output'    => (string) wp_json_encode( $payload ),
			];
		}

		return [
			'exit_code' => 0,
			'output'    => (string) wp_json_encode( [ 'restored' => $result->restored() ] ),
		];
	}
}
