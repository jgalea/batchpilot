<?php
namespace BatchPilot\Tests\Integration;

use BatchPilot\Activator;
use BatchPilot\Database\Schema;

final class ActivationTest extends TestCase {

	public function test_activate_runs_migrations(): void {
		Schema::drop_all();

		Activator::activate();

		$this->assertSame( Schema::VERSION, get_option( Schema::VERSION_OPTION ) );
	}
}
