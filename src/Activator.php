<?php
namespace ContentOps;

use ContentOps\Database\Migrations;

final class Activator {

	public static function activate(): void {
		Migrations::maybe_migrate();
	}
}
