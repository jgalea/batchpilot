<?php
namespace BatchPilot\Tests\Integration\Abilities;

use BatchPilot\Abilities\AbilitiesBridge;
use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\Tests\Integration\TestCase;

final class AbilitiesBridgeTest extends TestCase {

	public function test_is_available_reflects_function_presence(): void {
		$bridge = $this->bridge();
		$this->assertSame( function_exists( 'wp_register_ability' ), $bridge->is_available() );
	}

	public function test_register_is_safe_when_abilities_missing(): void {
		$bridge = $this->bridge();
		$bridge->register();

		$this->assertTrue( true );
	}

	private function bridge(): AbilitiesBridge {
		global $wpdb;
		$targets = new \BatchPilot\Registry\TargetRegistry();
		$ops     = new \BatchPilot\Registry\OperationRegistry();
		$exec    = new \BatchPilot\Execution\ExecutionService(
			$targets,
			$ops,
			new \BatchPilot\History\OperationRepository( $wpdb ),
			new \BatchPilot\History\SnapshotRepository( $wpdb ),
			new \BatchPilot\PreviewToken\TokenGenerator( 'salt' ),
			new \BatchPilot\PreviewToken\TokenStore( 300 )
		);
		return new AbilitiesBridge( new ActionSchedulerBridge(), $exec, $targets, $ops );
	}
}
