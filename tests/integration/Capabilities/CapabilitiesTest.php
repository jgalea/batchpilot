<?php
namespace BatchPilot\Tests\Integration\Capabilities;

use BatchPilot\Capabilities\Capabilities;
use BatchPilot\Tests\Integration\TestCase;

final class CapabilitiesTest extends TestCase {

	public function test_admin_gets_all_caps(): void {
		Capabilities::grant_to_admins();

		$admin = get_role( 'administrator' );
		foreach ( Capabilities::ALL as $cap ) {
			$this->assertTrue( $admin->has_cap( $cap ), "Missing cap: {$cap}" );
		}
	}

	public function test_all_constant_is_stable(): void {
		$this->assertSame(
			[
				'batchpilot_delete',
				'batchpilot_edit',
				'batchpilot_duplicate',
				'batchpilot_move',
				'batchpilot_schedule',
			],
			Capabilities::ALL
		);
	}
}
