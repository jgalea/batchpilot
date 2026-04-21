<?php
namespace ContentOps\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'__',
			'_e',
			'esc_html__',
			'esc_html_e',
			'esc_attr__',
			'wp_parse_args',
		] );

		Monkey\Functions\when( 'wp_json_encode' )->alias(
			static fn ( $value, $flags = 0 ) => json_encode( $value, $flags | JSON_UNESCAPED_SLASHES )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
