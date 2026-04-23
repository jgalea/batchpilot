<?php
namespace ContentOps\REST;

use ContentOps\Errors\ContentOpsError;
use ContentOps\History\OperationRepository;
use ContentOps\Registry\OperationRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class UndoController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'content_ops_delete',
		'duplicate' => 'content_ops_duplicate',
		'edit'      => 'content_ops_edit',
	];

	private OperationRegistry $operations;
	private OperationRepository $repo;

	public function __construct( OperationRegistry $operations, OperationRepository $repo ) {
		$this->operations = $operations;
		$this->repo       = $repo;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		$op = $this->repo->find( (int) $request['id'] );
		if ( null === $op ) {
			return $this->require_capability( 'manage_options' );
		}
		$cap = self::CAP_MAP[ $op->type() ] ?? 'manage_options';
		return $this->require_capability( $cap );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request['id'];
		$op = $this->repo->find( $id );
		if ( null === $op ) {
			return $this->error_response(
				new ContentOpsError( 'co.operation.not_found', 'Operation not found.', [ 'id' => $id ] ),
				404
			);
		}

		$runner = $this->operations->get( $op->type() );
		if ( null === $runner ) {
			return $this->error_response(
				new ContentOpsError( 'co.operation.unknown', 'Operation type no longer registered.', [ 'type' => $op->type() ] ),
				400
			);
		}

		$result = $runner->undo( $id );
		if ( ! $result->is_ok() ) {
			return $this->error_response( $result->get_error(), 422 );
		}
		return new WP_REST_Response( [ 'restored' => $result->restored() ] );
	}
}
