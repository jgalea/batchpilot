<?php
namespace BatchPilot\Tests\Integration;

use BatchPilot\Plugin;

final class PluginBootsTest extends TestCase {

	public function test_plugin_instance_is_available(): void {
		$this->assertInstanceOf( Plugin::class, Plugin::instance() );
	}

	public function test_batchpilot_booted_action_fires(): void {
		$fired    = 0;
		$callback = static function () use ( &$fired ): void {
			++$fired;
		};

		add_action( 'batchpilot_booted', $callback );
		Plugin::instance()->on_plugins_loaded();
		remove_action( 'batchpilot_booted', $callback );

		$this->assertSame( 1, $fired );
	}
}
