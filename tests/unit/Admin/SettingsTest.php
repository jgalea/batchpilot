<?php
namespace BatchPilot\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use BatchPilot\Admin\Settings;
use BatchPilot\Tests\Unit\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'rest_sanitize_boolean' )->alias(
			static function ( $value ) {
				if ( is_string( $value ) && in_array( strtolower( $value ), [ 'false', '0' ], true ) ) {
					return false;
				}
				return (bool) $value;
			}
		);
	}

	/**
	 * @dataProvider falsy_form_values
	 */
	public function test_form_encoded_false_values_sanitize_to_false( $value ): void {
		$out = ( new Settings() )->sanitize(
			[
				'delete_data_on_uninstall' => $value,
				'delete_permanent_default' => $value,
			]
		);
		$this->assertFalse( $out['delete_data_on_uninstall'] );
		$this->assertFalse( $out['delete_permanent_default'] );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function falsy_form_values(): array {
		return [
			'string false' => [ 'false' ],
			'string zero'  => [ '0' ],
			'empty string' => [ '' ],
		];
	}

	public function test_truthy_values_sanitize_to_true(): void {
		$out = ( new Settings() )->sanitize( [ 'delete_data_on_uninstall' => '1' ] );
		$this->assertTrue( $out['delete_data_on_uninstall'] );
	}
}
