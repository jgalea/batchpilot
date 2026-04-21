<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\CLI\DoctorCommand;
use ContentOps\Tests\Integration\TestCase;

final class DoctorCommandTest extends TestCase {

	public function test_collect_report_returns_expected_keys(): void {
		$command = new DoctorCommand( new ActionSchedulerBridge() );

		$result = $command->collect_report();

		foreach ( [ 'schema_version', 'action_scheduler', 'abilities_api', 'hpos', 'tables', 'cron' ] as $key ) {
			$this->assertArrayHasKey( $key, $result );
		}
		$this->assertIsBool( $result['action_scheduler']['available'] );
	}
}
