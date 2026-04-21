<?php
namespace ContentOps\Tests\Integration\Abilities;

use ContentOps\Abilities\AbilitiesBridge;
use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Tests\Integration\TestCase;

final class AbilitiesBridgeTest extends TestCase {

	public function test_is_available_reflects_function_presence(): void {
		$bridge = new AbilitiesBridge( new ActionSchedulerBridge() );
		$this->assertSame( function_exists( 'wp_register_ability' ), $bridge->is_available() );
	}

	public function test_register_is_safe_when_abilities_missing(): void {
		$bridge = new AbilitiesBridge( new ActionSchedulerBridge() );
		$bridge->register();

		$this->assertTrue( true );
	}
}
