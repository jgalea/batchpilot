<?php
namespace ContentOps\Tests\Integration\Abilities;

use ContentOps\Abilities\AbilitiesBridge;
use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Tests\Integration\TestCase;

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
		$targets = new \ContentOps\Registry\TargetRegistry();
		$ops     = new \ContentOps\Registry\OperationRegistry();
		$exec    = new \ContentOps\Execution\ExecutionService(
			$targets,
			$ops,
			new \ContentOps\History\OperationRepository( $wpdb ),
			new \ContentOps\History\SnapshotRepository( $wpdb ),
			new \ContentOps\PreviewToken\TokenGenerator( 'salt' ),
			new \ContentOps\PreviewToken\TokenStore( 300 )
		);
		return new AbilitiesBridge( new ActionSchedulerBridge(), $exec, $targets, $ops );
	}
}
