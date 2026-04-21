<?php
namespace ContentOps\REST;

use ContentOps\Errors\ContentOpsError;
use WP_Error;
use WP_REST_Response;

abstract class RestController {

	public function error_response( ContentOpsError $error, int $status ): WP_REST_Response {
		return new WP_REST_Response( $error->to_array(), $status );
	}

	public function require_capability( string $capability ) {
		if ( current_user_can( $capability ) ) {
			return true;
		}

		return new WP_Error(
			'co.auth.forbidden',
			__( 'You are not allowed to perform this action.', 'content-ops' ),
			[
				'status'              => 403,
				'required_capability' => $capability,
			]
		);
	}
}
