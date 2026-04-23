<?php
namespace ContentOps\REST;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\PreviewToken\TokenVerifier;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;

final class RouteRegistrar {

	public const REST_NAMESPACE = 'content-ops/v1';

	private ActionSchedulerBridge $action_scheduler;
	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;
	private OperationRepository $operations_repo;
	private TokenVerifier $verifier;
	private TokenStore $token_store;

	public function __construct(
		ActionSchedulerBridge $action_scheduler,
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations,
		OperationRepository $operations_repo,
		TokenVerifier $verifier,
		TokenStore $token_store
	) {
		$this->action_scheduler = $action_scheduler;
		$this->execution        = $execution;
		$this->targets          = $targets;
		$this->operations       = $operations;
		$this->operations_repo  = $operations_repo;
		$this->verifier         = $verifier;
		$this->token_store      = $token_store;
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

		$preview = new PreviewController( $this->execution );
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
	}
}
