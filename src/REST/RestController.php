<?php
namespace BatchPilot\REST;

use BatchPilot\Errors\BatchPilotError;
use WP_Error;
use WP_REST_Response;

abstract class RestController {

	public function error_response( BatchPilotError $error, int $status ): WP_REST_Response {
		return new WP_REST_Response( $error->to_array(), $status );
	}

	/**
	 * @return true|WP_Error
	 */
	public function require_capability( string $capability ) {
		if ( current_user_can( $capability ) ) {
			return true;
		}

		return new WP_Error(
			'bp.auth.forbidden',
			__( 'You are not allowed to perform this action.', 'batchpilot' ),
			[
				'status'              => 403,
				'required_capability' => $capability,
			]
		);
	}
}
