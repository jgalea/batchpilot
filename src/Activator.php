<?php
namespace ContentOps;

use ContentOps\Capabilities\Capabilities;
use ContentOps\Database\Migrations;

final class Activator {

	public static function activate(): void {
		Migrations::maybe_migrate();
		Capabilities::grant_to_admins();
	}
}
