<?php
namespace ContentOps\Tests\Integration\Async;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Tests\Integration\TestCase;

final class ActionSchedulerBridgeTest extends TestCase {

	public function test_is_available(): void {
		$this->assertTrue( ( new ActionSchedulerBridge() )->is_available() );
	}

	public function test_schedule_single_action_returns_id(): void {
		$bridge = new ActionSchedulerBridge();

		$id = $bridge->schedule_single_action( time() + 60, 'content_ops_test_hook', [ 'arg' => 'value' ], 'content-ops' );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	public function test_cancel_action(): void {
		$bridge = new ActionSchedulerBridge();
		$id     = $bridge->schedule_single_action( time() + 60, 'content_ops_test_hook', [], 'content-ops' );

		$bridge->cancel_action( $id );

		$this->assertFalse( $bridge->action_exists( $id ) );
	}
}
