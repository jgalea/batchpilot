<?php
namespace ContentOps\Tests\Integration;

use ContentOps\Plugin;
use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		if ( null === Plugin::instance() ) {
			Plugin::boot( dirname( __DIR__, 2 ) . '/content-ops.php' );
		}
	}
}
