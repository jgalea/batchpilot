<?php
namespace BatchPilot\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Errors\BatchPilotError;
use BatchPilot\History\OperationRepository;
use BatchPilot\Registry\OperationRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class UndoController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'batchpilot_delete',
		'duplicate' => 'batchpilot_duplicate',
		'edit'      => 'batchpilot_edit',
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
		$cap     = self::CAP_MAP[ $op->type() ] ?? 'manage_options';
		$has_cap = $this->require_capability( $cap );
		if ( true !== $has_cap ) {
			return $has_cap;
		}

		// The coarse batchpilot_* capability says whether this user may undo operations
		// of this type at all, not whose. Without an ownership check, any user holding
		// that capability could undo any other user's operation by guessing/enumerating
		// IDs — including reversing a delete another user did on purpose. Site admins
		// (manage_options) retain override, matching the "no row found" branch above.
		if ( current_user_can( 'manage_options' ) || get_current_user_id() === $op->user_id() ) {
			return true;
		}

		return new \WP_Error(
			'bp.auth.forbidden',
			__( 'You can only undo operations you ran yourself.', 'batchpilot' ),
			[ 'status' => 403 ]
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request['id'];
		$op = $this->repo->find( $id );
		if ( null === $op ) {
			return $this->error_response(
				new BatchPilotError( 'bp.operation.not_found', 'Operation not found.', [ 'id' => $id ] ),
				404
			);
		}

		$runner = $this->operations->get( $op->type() );
		if ( null === $runner ) {
			return $this->error_response(
				new BatchPilotError( 'bp.operation.unknown', 'Operation type no longer registered.', [ 'type' => $op->type() ] ),
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
