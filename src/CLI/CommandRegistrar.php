<?php
namespace BatchPilot\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\Execution\ExecutionService;
use BatchPilot\History\OperationRepository;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;

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
			'batchpilot doctor',
			new DoctorCommand( $this->action_scheduler ),
			[ 'shortdesc' => __( 'Check BatchPilot environment health.', 'batchpilot' ) ]
		);

		\WP_CLI::add_command(
			'batchpilot delete',
			new DeleteCommand( $this->execution ),
			[ 'shortdesc' => __( 'Trash or permanently delete posts in bulk.', 'batchpilot' ) ]
		);

		\WP_CLI::add_command(
			'batchpilot duplicate',
			new DuplicateCommand( $this->execution ),
			[ 'shortdesc' => __( 'Duplicate posts in bulk.', 'batchpilot' ) ]
		);

		\WP_CLI::add_command(
			'batchpilot edit',
			new EditCommand( $this->execution ),
			[ 'shortdesc' => __( 'Bulk-edit posts.', 'batchpilot' ) ]
		);

		\WP_CLI::add_command(
			'batchpilot history',
			new HistoryCommand( $this->operations_repo ),
			[ 'shortdesc' => __( 'List BatchPilot operations.', 'batchpilot' ) ]
		);

		\WP_CLI::add_command(
			'batchpilot undo',
			new UndoCommand( $this->operations, $this->operations_repo ),
			[ 'shortdesc' => __( 'Undo a BatchPilot operation.', 'batchpilot' ) ]
		);
	}
}
