<?php
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
