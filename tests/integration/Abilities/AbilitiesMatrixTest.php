<?php
namespace BatchPilot\Tests\Integration\Abilities;

use BatchPilot\Tests\Integration\TestCase;

final class AbilitiesMatrixTest extends TestCase {

	public function test_abilities_registered_for_target_operation_matrix(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not installed in test env.' );
		}

		do_action( 'abilities_api_init' );

		$this->assertNotNull( wp_get_ability( 'batchpilot/post_delete' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/post_duplicate' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/post_edit' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/page_delete' ) );
		$this->assertNotNull( wp_get_ability( 'batchpilot/doctor' ) );
	}
}
