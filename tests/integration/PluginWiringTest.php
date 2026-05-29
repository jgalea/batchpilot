<?php
namespace BatchPilot\Tests\Integration;

use BatchPilot\Plugin;

final class PluginWiringTest extends TestCase {

	public function test_registries_register_expected_entries(): void {
		$plugin = Plugin::instance();

		$targets    = $plugin->get( 'target.registry' );
		$operations = $plugin->get( 'operation.registry' );

		$this->assertInstanceOf( \BatchPilot\Registry\TargetRegistry::class, $targets );
		$this->assertInstanceOf( \BatchPilot\Registry\OperationRegistry::class, $operations );

		$this->assertTrue( $targets->has( 'post' ) );
		$this->assertTrue( $targets->has( 'page' ) );
		$this->assertTrue( $operations->has( 'delete' ) );
		$this->assertTrue( $operations->has( 'duplicate' ) );
		$this->assertTrue( $operations->has( 'edit' ) );
	}

	public function test_execution_service_is_registered(): void {
		$this->assertInstanceOf( \BatchPilot\Execution\ExecutionService::class, Plugin::instance()->get( 'execution.service' ) );
	}
}
