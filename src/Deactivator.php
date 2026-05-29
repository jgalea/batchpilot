<?php
namespace BatchPilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {
	public static function deactivate(): void {
		// Later phases unschedule recurring Action Scheduler actions here.
	}
}
