<?php
namespace BatchPilot\Tests\Integration\Async;

use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\Tests\Integration\TestCase;

final class ActionSchedulerBridgeTest extends TestCase {

	public function test_is_available(): void {
		$this->assertTrue( ( new ActionSchedulerBridge() )->is_available() );
	}

	public function test_schedule_single_action_returns_id(): void {
		$bridge = new ActionSchedulerBridge();

		$id = $bridge->schedule_single_action( time() + 60, 'batchpilot_test_hook', [ 'arg' => 'value' ], 'batchpilot' );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	public function test_cancel_action(): void {
		$bridge = new ActionSchedulerBridge();
		$id     = $bridge->schedule_single_action( time() + 60, 'batchpilot_test_hook', [], 'batchpilot' );

		$bridge->cancel_action( $id );

		$this->assertFalse( $bridge->action_exists( $id ) );
	}
}
