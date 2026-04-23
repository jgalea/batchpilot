<?php
namespace ContentOps\Tests\Integration\Abilities;

use ContentOps\Tests\Integration\TestCase;

final class AbilitiesMatrixTest extends TestCase {

	public function test_abilities_registered_for_target_operation_matrix(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not installed in test env.' );
		}

		do_action( 'abilities_api_init' );

		$this->assertNotNull( wp_get_ability( 'content-ops/post_delete' ) );
		$this->assertNotNull( wp_get_ability( 'content-ops/post_duplicate' ) );
		$this->assertNotNull( wp_get_ability( 'content-ops/post_edit' ) );
		$this->assertNotNull( wp_get_ability( 'content-ops/page_delete' ) );
		$this->assertNotNull( wp_get_ability( 'content-ops/doctor' ) );
	}
}
