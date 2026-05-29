<?php
namespace BatchPilot\REST;

use BatchPilot\Admin\Settings;
use WP_REST_Request;
use WP_REST_Response;

final class SettingsController extends RestController {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function check_permission() {
		return $this->require_capability( 'manage_options' );
	}

	public function handle_get( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->settings->get_all() );
	}

	public function handle_post( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		$saved = $this->settings->save( is_array( $body ) ? $body : [] );
		return new WP_REST_Response( $saved );
	}
}
