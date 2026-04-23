<?php
namespace ContentOps\Tests\Integration\Admin;

use ContentOps\Admin\Settings;
use ContentOps\Tests\Integration\TestCase;

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
				'role_caps'                => [],
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
				'role_caps'                => [ 'editor' => [ 'content_ops_delete' => true ] ],
				'unknown_key'              => 'ignored',
			]
		);
		$saved = $settings->get_all();
		$this->assertSame( 250, $saved['async_threshold'] );
		$this->assertSame( 75, $saved['batch_size'] );
		$this->assertTrue( $saved['delete_permanent_default'] );
		$this->assertSame( 45, $saved['history_retention_days'] );
		$this->assertArrayHasKey( 'editor', $saved['role_caps'] );
		$this->assertArrayNotHasKey( 'unknown_key', $saved );
	}

	public function test_async_threshold_filter_consumes_setting(): void {
		$settings = new Settings();
		$settings->register();
		$settings->save( [ 'async_threshold' => 500 ] );
		$this->assertSame( 500, (int) apply_filters( 'content_ops_async_threshold', 100 ) );
	}
}
