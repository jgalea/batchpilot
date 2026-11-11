<?php
namespace BatchPilot\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\CLI\DoctorCommand;
use BatchPilot\Errors\BatchPilotError;
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

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	public function register_category(): void {
		wp_register_ability_category(
			'batchpilot',
			[
				'label'       => __( 'BatchPilot', 'batchpilot' ),
				'description' => __( 'Bulk operations for WordPress and WooCommerce content.', 'batchpilot' ),
			]
		);
	}

	public function register_abilities(): void {
		$doctor = new DoctorCommand( $this->action_scheduler );

		wp_register_ability(
			'batchpilot/doctor',
			[
				'label'               => __( 'BatchPilot: doctor', 'batchpilot' ),
				'description'         => __( 'Report BatchPilot environment health.', 'batchpilot' ),
				'category'            => 'batchpilot',
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
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);

		$cap_map = [
			'delete'    => 'batchpilot_delete',
			'duplicate' => 'batchpilot_duplicate',
			'edit'      => 'batchpilot_edit',
			'rewrite'   => 'batchpilot_duplicate',
			'republish' => 'batchpilot_edit',
		];

		$execution = $this->execution;

		foreach ( $this->targets->all() as $target ) {
			foreach ( $this->operations->all() as $op ) {
				if ( ! $target->supports_operation( $op->slug() ) ) {
					continue;
				}

				$target_slug = $target->slug();
				$op_slug     = $op->slug();
				$name        = 'batchpilot/' . $target_slug . '-' . $op_slug;
				$cap         = $cap_map[ $op_slug ] ?? 'manage_options';

				wp_register_ability(
					$name,
					[
						'label'               => sprintf( '%s: %s', $target->label(), $op->label() ),
						/* translators: 1: target slug, 2: operation slug */
						'description'         => sprintf( __( 'Preview a %1$s %2$s bulk operation.', 'batchpilot' ), $target_slug, $op_slug ),
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
						'meta'                => [ 'show_in_rest' => true ],
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

		$this->register_run_abilities( $cap_map );
	}

	/**
	 * @param array<string, string> $cap_map
	 */
	private function register_run_abilities( array $cap_map ): void {
		$execution = $this->execution;
		$scheduler = $this->action_scheduler;

		wp_register_ability(
			'batchpilot/execute',
			[
				'label'               => __( 'BatchPilot: execute', 'batchpilot' ),
				'description'         => __( 'Run a bulk operation that was previewed first. Needs the preview_token returned by the matching preview ability, called with the same target, operation, filters and params.', 'batchpilot' ),
				'category'            => 'batchpilot',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'target', 'operation', 'preview_token' ],
					'properties' => [
						'target'        => [ 'type' => 'string' ],
						'operation'     => [ 'type' => 'string' ],
						'filters'       => [ 'type' => 'object' ],
						'params'        => [ 'type' => 'object' ],
						'preview_token' => [ 'type' => 'string' ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'operation_id' => [ 'type' => 'integer' ],
						'status'       => [ 'type' => 'string' ],
						'batch'        => [ 'type' => 'object' ],
					],
				],
				'permission_callback' => static fn ( $input = null ) => current_user_can( $cap_map[ (string) ( is_array( $input ) ? ( $input['operation'] ?? '' ) : '' ) ] ?? 'manage_options' ),
				'execute_callback'    => static function ( $input ) use ( $execution, $scheduler ) {
					$result = $execution->execute(
						(string) ( $input['target'] ?? '' ),
						(string) ( $input['operation'] ?? '' ),
						isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : [],
						isset( $input['params'] ) && is_array( $input['params'] ) ? $input['params'] : [],
						(string) ( $input['preview_token'] ?? '' ),
						$scheduler
					);
					return $result instanceof BatchPilotError ? $result->to_wp_error() : $result;
				},
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'destructive' => true ],
				],
			]
		);

		wp_register_ability(
			'batchpilot/undo',
			[
				'label'               => __( 'BatchPilot: undo', 'batchpilot' ),
				'description'         => __( 'Undo a completed operation by its history id, when its type supports undo.', 'batchpilot' ),
				'category'            => 'batchpilot',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'operation_id' ],
					'properties' => [
						'operation_id' => [ 'type' => 'integer' ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'restored' => [ 'type' => 'integer' ],
					],
				],
				'permission_callback' => static function ( $input = null ) use ( $execution, $cap_map ) {
					$row = $execution->find_operation( (int) ( is_array( $input ) ? ( $input['operation_id'] ?? 0 ) : 0 ) );
					if ( null === $row ) {
						return current_user_can( 'manage_options' );
					}
					return current_user_can( $cap_map[ $row->type() ] ?? 'manage_options' )
						&& ( current_user_can( 'manage_options' ) || get_current_user_id() === $row->user_id() );
				},
				'execute_callback'    => static function ( $input ) use ( $execution ) {
					$result = $execution->undo( (int) ( $input['operation_id'] ?? 0 ) );
					if ( ! $result->is_ok() ) {
						$error  = $result->get_error();
						$status = [
							'bp.operation.not_found' => 404,
							'bp.operation.unknown'   => 400,
							'bp.undo.already_undone' => 409,
						][ $error->code() ] ?? 422;
						return new \WP_Error( $error->code(), $error->message(), array_merge( $error->context(), [ 'status' => $status ] ) );
					}
					return [ 'restored' => $result->restored() ];
				},
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'destructive' => true ],
				],
			]
		);

		wp_register_ability(
			'batchpilot/history',
			[
				'label'               => __( 'BatchPilot: history', 'batchpilot' ),
				'description'         => __( 'List recent bulk operations, newest first.', 'batchpilot' ),
				'category'            => 'batchpilot',
				'input_schema'        => [
					'type'       => 'object',
					'default'    => [],
					'properties' => [
						'limit'  => [
							'type'    => 'integer',
							'default' => 20,
						],
						'offset' => [
							'type'    => 'integer',
							'default' => 0,
						],
					],
				],
				'output_schema'       => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
				'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				'execute_callback'    => static function ( $input ) use ( $execution ) {
					$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
					$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
					return array_map(
						static fn ( $row ) => [
							'id'             => $row->id(),
							'type'           => $row->type(),
							'target'         => $row->target(),
							'user_id'        => $row->user_id(),
							'status'         => $row->status(),
							'affected_count' => $row->affected_count(),
							'created_at'     => $row->created_at(),
							'completed_at'   => $row->completed_at(),
						],
						$execution->recent_operations( $limit, $offset )
					);
				},
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);
	}
}
