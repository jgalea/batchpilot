<?php
namespace ContentOps\REST;

use ContentOps\Async\ActionSchedulerBridge;

final class RouteRegistrar {

	public const REST_NAMESPACE = 'content-ops/v1';

	private ActionSchedulerBridge $action_scheduler;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->action_scheduler = $action_scheduler;
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$doctor = new DoctorController( $this->action_scheduler );

		register_rest_route(
			self::REST_NAMESPACE,
			'/doctor',
			[
				'methods'             => 'GET',
				'callback'            => [ $doctor, 'handle' ],
				'permission_callback' => [ $doctor, 'check_permission' ],
			]
		);
	}
}
