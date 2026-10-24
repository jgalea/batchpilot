<?php
namespace BatchPilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {
	public static function deactivate(): void {
		\BatchPilot\History\Pruner::unschedule();
	}
}
