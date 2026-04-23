<?php
namespace ContentOps\REST;

use ContentOps\Execution\ExecutionService;
use ContentOps\Registry\TargetRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class PreviewController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'content_ops_delete',
		'duplicate' => 'content_ops_duplicate',
		'edit'      => 'content_ops_edit',
	];

	private ExecutionService $execution;
	private TargetRegistry $targets;

	public function __construct( ExecutionService $execution, TargetRegistry $targets ) {
		$this->execution = $execution;
		$this->targets   = $targets;
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
		$target    = (string) $request->get_param( 'target' );
		$operation = (string) $request->get_param( 'operation' );
		$filters   = (array) ( $request->get_param( 'filters' ) ?? [] );
		$params    = (array) ( $request->get_param( 'params' ) ?? [] );

		$result = $this->execution->preview( $target, $operation, $filters, $params );

		if ( ! $result->is_ok() ) {
			$error = $result->get_error();
			if ( null === $error ) {
				return new WP_REST_Response(
					[
						'code'    => 'co.internal',
						'message' => 'Unknown preview failure.',
					],
					500
				);
			}
			$code   = $error->code();
			$status = 0 === strpos( $code, 'co.target.' ) || 0 === strpos( $code, 'co.operation.' ) ? 400 : 422;
			return $this->error_response( $error, $status );
		}

		$target_obj   = $this->targets->get( $target );
		$display_rows = [];
		if ( null !== $target_obj ) {
			foreach ( $result->sample_ids() as $id ) {
				$display_rows[] = $target_obj->get_display( (int) $id );
			}
		}

		return new WP_REST_Response(
			[
				'count'         => $result->count(),
				'sample_ids'    => $result->sample_ids(),
				'preview_token' => $result->preview_token(),
				'warnings'      => $result->warnings(),
				'display_rows'  => $display_rows,
			]
		);
	}
}
