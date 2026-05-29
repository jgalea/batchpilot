<?php
namespace BatchPilot\Execution;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OperationRunner {

	public const HOOK = 'batchpilot_run_operation';

	private ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	public function register(): void {
		add_action( self::HOOK, [ $this, 'handle' ], 10, 1 );
	}

	public function handle( int $operation_id ): void {
		$this->execution->run_sync( (int) $operation_id );
	}
}
