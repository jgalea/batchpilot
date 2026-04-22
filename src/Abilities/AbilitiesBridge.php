<?php
namespace ContentOps\Abilities;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\CLI\DoctorCommand;

final class AbilitiesBridge {

	private ActionSchedulerBridge $action_scheduler;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->action_scheduler = $action_scheduler;
	}

	public function is_available(): bool {
		return function_exists( 'wp_register_ability' );
	}

	public function register(): void {
		if ( ! $this->is_available() ) {
			return;
		}

		add_action( 'abilities_api_init', [ $this, 'register_abilities' ] );
	}

	public function register_abilities(): void {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'content-ops',
				[
					'label'       => __( 'Content Ops', 'content-ops' ),
					'description' => __( 'Bulk operations for WordPress and WooCommerce content.', 'content-ops' ),
				]
			);
		}

		$doctor = new DoctorCommand( $this->action_scheduler );

		wp_register_ability(
			'content-ops/doctor',
			[
				'label'               => __( 'Content Ops: doctor', 'content-ops' ),
				'description'         => __( 'Report Content Ops environment health.', 'content-ops' ),
				'category'            => 'content-ops',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'schema_version'   => [ 'type' => 'string' ],
						'action_scheduler' => [ 'type' => 'object' ],
						'abilities_api'    => [ 'type' => 'object' ],
						'hpos'             => [ 'type' => 'object' ],
						'tables'           => [ 'type' => 'object' ],
						'cron'             => [ 'type' => 'object' ],
					],
				],
				'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				'execute_callback'    => static fn () => $doctor->collect_report(),
			]
		);
	}
}
