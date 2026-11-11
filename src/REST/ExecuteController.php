<?php
namespace BatchPilot\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\Errors\BatchPilotError;
use BatchPilot\Execution\ExecutionService;
use WP_REST_Request;
use WP_REST_Response;

final class ExecuteController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'batchpilot_delete',
		'duplicate' => 'batchpilot_duplicate',
		'edit'      => 'batchpilot_edit',
		'rewrite'   => 'batchpilot_duplicate',
		'republish' => 'batchpilot_edit',
	];

	private const STATUS_MAP = [
		'bp.target.unknown'      => 400,
		'bp.operation.unknown'   => 400,
		'bp.preview.stale_token' => 409,
	];

	private ExecutionService $execution;
	private ActionSchedulerBridge $scheduler;

	public function __construct( ExecutionService $execution, ActionSchedulerBridge $scheduler ) {
		$this->execution = $execution;
		$this->scheduler = $scheduler;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		$op  = (string) $request->get_param( 'operation' );
		$cap = self::CAP_MAP[ $op ] ?? 'manage_options';
		return $this->require_capability( $cap );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->execution->execute(
			(string) $request->get_param( 'target' ),
			(string) $request->get_param( 'operation' ),
			(array) ( $request->get_param( 'filters' ) ?? [] ),
			(array) ( $request->get_param( 'params' ) ?? [] ),
			(string) $request->get_param( 'preview_token' ),
			$this->scheduler
		);

		if ( $result instanceof BatchPilotError ) {
			return $this->error_response( $result, self::STATUS_MAP[ $result->code() ] ?? 500 );
		}

		return new WP_REST_Response( $result, 'queued' === $result['status'] ? 202 : 200 );
	}
}
