<?php
namespace BatchPilot\Tests\Integration\Abilities;

use BatchPilot\Tests\Integration\TestCase;

final class AbilitiesMatrixTest extends TestCase {

	public function test_abilities_registered_for_target_operation_matrix(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not installed in test env.' );
		}

				$this->assertNotNull( wp_get_ability( 'batchpilot/post-delete' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/post-duplicate' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/post-edit' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/page-delete' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/post-rewrite' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/post-republish' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/doctor' ) );
	}
}
