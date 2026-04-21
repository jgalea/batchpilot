<?php
namespace ContentOps\Tests\Integration\Capabilities;

use ContentOps\Capabilities\Capabilities;
use ContentOps\Tests\Integration\TestCase;

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
				'content_ops_delete',
				'content_ops_edit',
				'content_ops_duplicate',
				'content_ops_move',
				'content_ops_schedule',
			],
			Capabilities::ALL
		);
	}
}
