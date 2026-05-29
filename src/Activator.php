<?php
namespace BatchPilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Capabilities\Capabilities;
use BatchPilot\Database\Migrations;

final class Activator {

	public static function activate(): void {
		Migrations::maybe_migrate();
		Capabilities::grant_to_admins();
	}
}
