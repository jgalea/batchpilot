<?php
namespace ContentOps\CLI;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;

final class CommandRegistrar {

	private ActionSchedulerBridge $action_scheduler;
	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;
	private OperationRepository $operations_repo;

	public function __construct(
		ActionSchedulerBridge $action_scheduler,
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations,
		OperationRepository $operations_repo
	) {
		$this->action_scheduler = $action_scheduler;
		$this->execution        = $execution;
		$this->targets          = $targets;
		$this->operations       = $operations;
		$this->operations_repo  = $operations_repo;
	}

	public function register(): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		\WP_CLI::add_command(
			'content-ops doctor',
			new DoctorCommand( $this->action_scheduler ),
			[ 'shortdesc' => __( 'Check Content Ops environment health.', 'content-ops' ) ]
		);

		\WP_CLI::add_command(
			'content-ops delete',
			new DeleteCommand( $this->execution ),
			[ 'shortdesc' => __( 'Trash or permanently delete posts in bulk.', 'content-ops' ) ]
		);

		\WP_CLI::add_command(
			'content-ops duplicate',
			new DuplicateCommand( $this->execution ),
			[ 'shortdesc' => __( 'Duplicate posts in bulk.', 'content-ops' ) ]
		);
	}
}
