<?php
/**
 * Schema migration runner.
 *
 * v1.0.0 is a single install step via Schema::install().
 * Future versions will register per-version migration callbacks here.
 */

namespace ContentOps\Database;

final class Migrations {

	public static function maybe_migrate(): void {
		$current = (string) get_option( Schema::VERSION_OPTION, '' );

		if ( Schema::VERSION === $current ) {
			return;
		}

		Schema::install();
	}
}
