<?php
namespace ContentOps\CLI;

use ContentOps\Async\ActionSchedulerBridge;

final class CommandRegistrar {

	private ActionSchedulerBridge $action_scheduler;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->action_scheduler = $action_scheduler;
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
	}
}
