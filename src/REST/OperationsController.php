<?php
namespace BatchPilot\REST;

use BatchPilot\Errors\BatchPilotError;
use BatchPilot\History\Operation;
use BatchPilot\History\OperationRepository;
use WP_REST_Request;
use WP_REST_Response;

final class OperationsController extends RestController {

	private OperationRepository $operations;

	public function __construct( OperationRepository $operations ) {
		$this->operations = $operations;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function check_permission() {
		return $this->require_capability( 'manage_options' );
	}

	public function handle_list( WP_REST_Request $request ): WP_REST_Response {
		$limit  = max( 1, min( 100, (int) ( $request->get_param( 'limit' ) ?? 20 ) ) );
		$offset = max( 0, (int) ( $request->get_param( 'offset' ) ?? 0 ) );

		$rows = $this->operations->list( $limit, $offset );

		return new WP_REST_Response( array_map( [ $this, 'serialize' ], $rows ) );
	}

	public function handle_single( WP_REST_Request $request ): WP_REST_Response {
		$op = $this->operations->find( (int) $request['id'] );
		if ( null === $op ) {
			return $this->error_response(
				new BatchPilotError( 'bp.operation.not_found', 'Operation not found.', [ 'id' => (int) $request['id'] ] ),
				404
			);
		}
		return new WP_REST_Response( $this->serialize( $op ) );
	}

	/** @return array<string, mixed> */
	private function serialize( Operation $op ): array {
		return [
			'id'             => $op->id(),
			'type'           => $op->type(),
			'target'         => $op->target(),
			'user_id'        => $op->user_id(),
			'filters'        => $op->filters(),
			'params'         => $op->params(),
			'affected_count' => $op->affected_count(),
			'affected_ids'   => $op->affected_ids(),
			'status'         => $op->status(),
			'error_message'  => $op->error_message(),
			'created_at'     => $op->created_at(),
			'completed_at'   => $op->completed_at(),
		];
	}
}
