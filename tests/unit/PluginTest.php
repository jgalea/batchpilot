<?php
namespace BatchPilot\Tests\Unit;

use Brain\Monkey\Functions;
use BatchPilot\Plugin;

final class PluginTest extends TestCase {

	protected function tearDown(): void {
		Plugin::reset_for_tests();
		parent::tearDown();
	}

	public function test_boot_returns_same_instance_on_second_call(): void {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		$first  = Plugin::boot( __FILE__ );
		$second = Plugin::boot( '/some/other/file.php' );

		$this->assertSame( $first, $second );
		$this->assertSame( __FILE__, $first->plugin_file() );
	}

	public function test_services_can_be_registered_and_retrieved(): void {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		$plugin  = Plugin::boot( __FILE__ );
		$service = new \stdClass();

		$plugin->set( 'test.service', $service );

		$this->assertSame( $service, $plugin->get( 'test.service' ) );
		$this->assertNull( $plugin->get( 'missing' ) );
	}
}
