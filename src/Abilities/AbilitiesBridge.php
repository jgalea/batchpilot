<?php
namespace BatchPilot\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\CLI\DoctorCommand;
use BatchPilot\Execution\ExecutionService;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;

final class AbilitiesBridge {

	private ActionSchedulerBridge $action_scheduler;
	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;

	public function __construct(
		ActionSchedulerBridge $action_scheduler,
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations
	) {
		$this->action_scheduler = $action_scheduler;
		$this->execution        = $execution;
		$this->targets          = $targets;
		$this->operations       = $operations;
	}

	public function is_available(): bool {
		return function_exists( 'wp_register_ability' );
	}

	public function register(): void {
		if ( ! $this->is_available() ) {
			return;
		}

		if ( did_action( 'abilities_api_init' ) > 0 || did_action( 'init' ) > 0 ) {
			$this->register_abilities();
			return;
		}

		add_action( 'abilities_api_init', [ $this, 'register_abilities' ] );
		add_action( 'init', [ $this, 'register_abilities' ], 20 );
	}

	public function register_abilities(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$this->do_register_abilities();
	}

	private function do_register_abilities(): void {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'batchpilot',
				[
					'label'       => __( 'BatchPilot', 'batchpilot' ),
					'description' => __( 'Bulk operations for WordPress and WooCommerce content.', 'batchpilot' ),
				]
			);
		}

		$doctor = new DoctorCommand( $this->action_scheduler );

		wp_register_ability(
			'batchpilot/doctor',
			[
				'label'               => __( 'BatchPilot: doctor', 'batchpilot' ),
				'description'         => __( 'Report BatchPilot environment health.', 'batchpilot' ),
				'category'            => 'batchpilot',
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

		$cap_map = [
			'delete'    => 'batchpilot_delete',
			'duplicate' => 'batchpilot_duplicate',
			'edit'      => 'batchpilot_edit',
		];

		$execution = $this->execution;

		foreach ( $this->targets->all() as $target ) {
			foreach ( $this->operations->all() as $op ) {
				if ( ! $target->supports_operation( $op->slug() ) ) {
					continue;
				}

				$target_slug = $target->slug();
				$op_slug     = $op->slug();
				$name        = 'batchpilot/' . $target_slug . '_' . $op_slug;
				$cap         = $cap_map[ $op_slug ] ?? 'manage_options';

				wp_register_ability(
					$name,
					[
						'label'               => sprintf( '%s: %s', $target->label(), $op->label() ),
						'description'         => sprintf( 'Preview a %1$s %2$s bulk operation.', $target_slug, $op_slug ),
						'category'            => 'batchpilot',
						'input_schema'        => [
							'type'       => 'object',
							'properties' => [
								'filters' => [ 'type' => 'object' ],
								'params'  => $op->get_params_schema(),
							],
						],
						'output_schema'       => [
							'type'       => 'object',
							'properties' => [
								'count'         => [ 'type' => 'integer' ],
								'sample_ids'    => [
									'type'  => 'array',
									'items' => [ 'type' => 'integer' ],
								],
								'preview_token' => [ 'type' => 'string' ],
								'warnings'      => [
									'type'  => 'array',
									'items' => [ 'type' => 'string' ],
								],
							],
						],
						'permission_callback' => static fn () => current_user_can( $cap ),
						'execute_callback'    => static function ( $input ) use ( $execution, $target_slug, $op_slug ) {
							$filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : [];
							$params  = isset( $input['params'] ) && is_array( $input['params'] ) ? $input['params'] : [];
							$result  = $execution->preview( $target_slug, $op_slug, $filters, $params );
							if ( ! $result->is_ok() ) {
								return $result->get_error()->to_wp_error();
							}
							return [
								'count'         => $result->count(),
								'sample_ids'    => $result->sample_ids(),
								'preview_token' => $result->preview_token(),
								'warnings'      => $result->warnings(),
							];
						},
					]
				);
			}
		}
	}
}
