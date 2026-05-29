<?php
require __DIR__ . '/../../vendor/autoload.php';

$wp_phpunit_dir = getenv( 'WP_TESTS_DIR' );
if ( false === $wp_phpunit_dir ) {
	$wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );
}
if ( false === $wp_phpunit_dir ) {
	$wp_phpunit_dir = __DIR__ . '/../../vendor/wp-phpunit/wp-phpunit';
}

$_tests_dir = rtrim( $wp_phpunit_dir, '/\\' );

$GLOBALS['wp_tests_options'] = [
	'active_plugins' => [ 'batchpilot/batchpilot.php' ],
];

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/batchpilot.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
