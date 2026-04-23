<?php
namespace ContentOps\Abilities;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\CLI\DoctorCommand;
use ContentOps\Execution\ExecutionService;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;

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

		$cap_map = [
			'delete'    => 'content_ops_delete',
			'duplicate' => 'content_ops_duplicate',
			'edit'      => 'content_ops_edit',
		];

		$execution = $this->execution;

		foreach ( $this->targets->all() as $target ) {
			foreach ( $this->operations->all() as $op ) {
				if ( ! $target->supports_operation( $op->slug() ) ) {
					continue;
				}

				$target_slug = $target->slug();
				$op_slug     = $op->slug();
				$name        = 'content-ops/' . $target_slug . '_' . $op_slug;
				$cap         = $cap_map[ $op_slug ] ?? 'manage_options';

				wp_register_ability(
					$name,
					[
						'label'               => sprintf( '%s: %s', $target->label(), $op->label() ),
						'description'         => sprintf( 'Preview a %1$s %2$s bulk operation.', $target_slug, $op_slug ),
						'category'            => 'content-ops',
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
