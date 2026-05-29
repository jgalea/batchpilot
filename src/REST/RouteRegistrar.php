<?php
namespace BatchPilot\REST;

use BatchPilot\Admin\Settings;
use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\Execution\ExecutionService;
use BatchPilot\History\OperationRepository;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\PreviewToken\TokenVerifier;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;

final class RouteRegistrar {

	public const REST_NAMESPACE = 'batchpilot/v1';

	private ActionSchedulerBridge $action_scheduler;
	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;
	private OperationRepository $operations_repo;
	private TokenVerifier $verifier;
	private TokenStore $token_store;
	private Settings $settings;

	public function __construct(
		ActionSchedulerBridge $action_scheduler,
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations,
		OperationRepository $operations_repo,
		TokenVerifier $verifier,
		TokenStore $token_store,
		Settings $settings
	) {
		$this->action_scheduler = $action_scheduler;
		$this->execution        = $execution;
		$this->targets          = $targets;
		$this->operations       = $operations;
		$this->operations_repo  = $operations_repo;
		$this->verifier         = $verifier;
		$this->token_store      = $token_store;
		$this->settings         = $settings;
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

		$catalog = new CatalogController( $this->targets, $this->operations );
		register_rest_route(
			self::REST_NAMESPACE,
			'/catalog',
			[
				'methods'             => 'GET',
				'callback'            => [ $catalog, 'handle' ],
				'permission_callback' => [ $catalog, 'check_permission' ],
			]
		);

		$preview = new PreviewController( $this->execution, $this->targets );
		register_rest_route(
			self::REST_NAMESPACE,
			'/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $preview, 'handle' ],
				'permission_callback' => [ $preview, 'check_permission' ],
				'args'                => [
					'target'    => [
						'type'     => 'string',
						'required' => true,
					],
					'operation' => [
						'type'     => 'string',
						'required' => true,
					],
					'filters'   => [
						'type'    => 'object',
						'default' => new \stdClass(),
					],
					'params'    => [
						'type'    => 'object',
						'default' => new \stdClass(),
					],
				],
			]
		);

		$execute = new ExecuteController(
			$this->execution,
			$this->targets,
			$this->operations,
			$this->verifier,
			$this->token_store,
			$this->action_scheduler
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/execute',
			[
				'methods'             => 'POST',
				'callback'            => [ $execute, 'handle' ],
				'permission_callback' => [ $execute, 'check_permission' ],
				'args'                => [
					'preview_token' => [
						'type'     => 'string',
						'required' => true,
					],
					'target'        => [
						'type'     => 'string',
						'required' => true,
					],
					'operation'     => [
						'type'     => 'string',
						'required' => true,
					],
					'filters'       => [
						'type'    => 'object',
						'default' => new \stdClass(),
					],
					'params'        => [
						'type'    => 'object',
						'default' => new \stdClass(),
					],
				],
			]
		);

		$operations_ctrl = new OperationsController( $this->operations_repo );
		register_rest_route(
			self::REST_NAMESPACE,
			'/operations',
			[
				'methods'             => 'GET',
				'callback'            => [ $operations_ctrl, 'handle_list' ],
				'permission_callback' => [ $operations_ctrl, 'check_permission' ],
				'args'                => [
					'limit'  => [
						'type'    => 'integer',
						'default' => 20,
					],
					'offset' => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
			]
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/operations/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $operations_ctrl, 'handle_single' ],
				'permission_callback' => [ $operations_ctrl, 'check_permission' ],
			]
		);

		$undo = new UndoController( $this->operations, $this->operations_repo );
		register_rest_route(
			self::REST_NAMESPACE,
			'/operations/(?P<id>\d+)/undo',
			[
				'methods'             => 'POST',
				'callback'            => [ $undo, 'handle' ],
				'permission_callback' => [ $undo, 'check_permission' ],
			]
		);

		$settings_ctrl = new SettingsController( $this->settings );
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $settings_ctrl, 'handle_get' ],
					'permission_callback' => [ $settings_ctrl, 'check_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $settings_ctrl, 'handle_post' ],
					'permission_callback' => [ $settings_ctrl, 'check_permission' ],
				],
			]
		);
	}
}
