<?php
namespace ContentOps\REST;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Errors\ContentOpsError;
use ContentOps\Execution\ExecutionService;
use ContentOps\Execution\OperationRunner;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\PreviewToken\TokenVerifier;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class ExecuteController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'content_ops_delete',
		'duplicate' => 'content_ops_duplicate',
		'edit'      => 'content_ops_edit',
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
				new ContentOpsError(
					null === $target_obj ? 'co.target.unknown' : 'co.operation.unknown',
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
				new ContentOpsError( 'co.preview.stale_token', 'Preview token invalid or expired. Re-preview before executing.' ),
				409
			);
		}
		$this->verifier->consume( $token );

		$user_id = (int) get_current_user_id();
		$op_id   = $this->execution->record( $target, $operation, $user_id, $filters, $params );

		$threshold = (int) apply_filters( 'content_ops_async_threshold', 100 );
		if ( $count > $threshold && $this->scheduler->is_available() ) {
			$this->scheduler->schedule_single_action(
				time(),
				OperationRunner::HOOK,
				[ $op_id ],
				'content-ops'
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
				$error = new ContentOpsError( 'co.internal', 'Unknown execution failure.' );
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
