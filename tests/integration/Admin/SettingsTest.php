<?php
namespace BatchPilot\Tests\Integration\Admin;

use BatchPilot\Admin\Settings;
use BatchPilot\Tests\Integration\TestCase;

final class SettingsTest extends TestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_defaults_when_option_missing(): void {
		$settings = new Settings();
		$this->assertSame(
			[
				'async_threshold'          => 100,
				'batch_size'               => 50,
				'delete_permanent_default' => false,
				'history_retention_days'   => 30,
				'delete_data_on_uninstall' => false,
				'copy_post_types'          => [ 'post', 'page' ],
				'copy_roles'               => [ 'administrator', 'editor' ],
			],
			$settings->get_all()
		);
	}

	public function test_save_sanitizes_and_persists(): void {
		$settings = new Settings();
		$settings->save(
			[
				'async_threshold'          => '250',
				'batch_size'               => '75',
				'delete_permanent_default' => '1',
				'history_retention_days'   => '45',
				'delete_data_on_uninstall' => '1',
				'unknown_key'              => 'ignored',
			]
		);
		$saved = $settings->get_all();
		$this->assertSame( 250, $saved['async_threshold'] );
		$this->assertSame( 75, $saved['batch_size'] );
		$this->assertTrue( $saved['delete_permanent_default'] );
		$this->assertSame( 45, $saved['history_retention_days'] );
		$this->assertTrue( $saved['delete_data_on_uninstall'] );
		$this->assertArrayNotHasKey( 'unknown_key', $saved );
	}

	public function test_async_threshold_filter_consumes_setting(): void {
		$settings = new Settings();
		$settings->register();
		$settings->save( [ 'async_threshold' => 500 ] );
		$this->assertSame( 500, (int) apply_filters( 'batchpilot_async_threshold', 100 ) );
	}

	public function test_copy_settings_drop_unknown_post_types_and_roles(): void {
		$settings = new Settings();
		$settings->save(
			[
				'copy_post_types' => [ 'post', 'no-such-type', 'attachment' ],
				'copy_roles'      => [ 'author', 'no-such-role' ],
			]
		);
		$saved = $settings->get_all();
		$this->assertSame( [ 'post' ], $saved['copy_post_types'] );
		$this->assertSame( [ 'author' ], $saved['copy_roles'] );
	}
}
