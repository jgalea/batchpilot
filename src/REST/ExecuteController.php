<?php
namespace BatchPilot\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\Contracts\QueryArgs;
use BatchPilot\Errors\BatchPilotError;
use BatchPilot\Execution\ExecutionService;
use BatchPilot\Execution\OperationRunner;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\PreviewToken\TokenVerifier;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class ExecuteController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'batchpilot_delete',
		'duplicate' => 'batchpilot_duplicate',
		'edit'      => 'batchpilot_edit',
	];

	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;
	private TokenVerifier $verifier;
	private TokenStore $token_store;
	private ActionSchedulerBridge $scheduler;

	public function __construct(
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations,
		TokenVerifier $verifier,
		TokenStore $token_store,
		ActionSchedulerBridge $scheduler
	) {
		$this->execution   = $execution;
		$this->targets     = $targets;
		$this->operations  = $operations;
		$this->verifier    = $verifier;
		$this->token_store = $token_store;
		$this->scheduler   = $scheduler;
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
		$token     = (string) $request->get_param( 'preview_token' );
		$target    = (string) $request->get_param( 'target' );
		$operation = (string) $request->get_param( 'operation' );
		$filters   = (array) ( $request->get_param( 'filters' ) ?? [] );
		$params    = (array) ( $request->get_param( 'params' ) ?? [] );

		$target_obj = $this->targets->get( $target );
		$op_obj     = $this->operations->get( $operation );
		if ( null === $target_obj || null === $op_obj ) {
			return $this->error_response(
				new BatchPilotError(
					null === $target_obj ? 'bp.target.unknown' : 'bp.operation.unknown',
					'Unknown target or operation.'
				),
				400
			);
		}

		$args       = QueryArgs::from_array( $filters );
		$sample_ids = $target_obj->query( $args, 20, 0 );
		$count      = $target_obj->count( $args );
		$payload    = [
			'target'     => $target,
			'operation'  => $operation,
			'filters'    => $filters,
			'params'     => $params,
			'sample_ids' => $sample_ids,
			'count'      => $count,
		];
		if ( ! $this->verifier->verify( $token, $payload ) ) {
			return $this->error_response(
				new BatchPilotError( 'bp.preview.stale_token', 'Preview token invalid or expired. Re-preview before executing.' ),
				409
			);
		}
		$this->verifier->consume( $token );

		$user_id = (int) get_current_user_id();
		$op_id   = $this->execution->record( $target, $operation, $user_id, $filters, $params );

		$threshold = (int) apply_filters( 'batchpilot_async_threshold', 100 );
		if ( $count > $threshold && $this->scheduler->is_available() ) {
			$this->scheduler->schedule_single_action(
				time(),
				OperationRunner::HOOK,
				[ $op_id ],
				'batchpilot'
			);
			return new WP_REST_Response(
				[
					'operation_id' => $op_id,
					'status'       => 'queued',
				],
				202
			);
		}

		$result = $this->execution->run_sync( $op_id );
		if ( ! $result->is_ok() ) {
			$error = $result->get_error();
			if ( null === $error ) {
				$error = new BatchPilotError( 'bp.internal', 'Unknown execution failure.' );
			}
			return $this->error_response( $error, 500 );
		}

		return new WP_REST_Response(
			[
				'operation_id' => $op_id,
				'status'       => 'completed',
				'batch'        => [
					'processed' => $result->processed(),
					'succeeded' => $result->succeeded(),
					'failed'    => $result->failed(),
				],
			]
		);
	}
}
