<?php
namespace BatchPilot\Tests\Integration\Admin;

use BatchPilot\Admin\AssetLoader;
use BatchPilot\Tests\Integration\TestCase;

final class AssetLoaderTest extends TestCase {

	public function test_enqueues_admin_script_only_on_batchpilot_pages(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$loader = new AssetLoader( BATCHPILOT_PLUGIN_FILE );
		$loader->enqueue( 'toplevel_page_batchpilot' );
		$this->assertTrue( wp_script_is( 'batchpilot-admin', 'enqueued' ) );

		wp_dequeue_script( 'batchpilot-admin' );
		wp_deregister_script( 'batchpilot-admin' );

		$loader->enqueue( 'edit.php' );
		$this->assertFalse( wp_script_is( 'batchpilot-admin', 'enqueued' ) );
	}

	public function test_localizes_bootstrap_payload_with_rest_url_nonce_and_caps(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$loader = new AssetLoader( BATCHPILOT_PLUGIN_FILE );
		$loader->enqueue( 'toplevel_page_batchpilot' );

		$before = wp_scripts()->get_data( 'batchpilot-admin', 'before' );
		$this->assertIsArray( $before );
		$data = implode( "\n", array_filter( (array) $before ) );
		$this->assertStringContainsString( 'window.batchPilotAdmin', $data );
		$this->assertStringContainsString( '"namespace":"batchpilot\/v1"', $data );
		$this->assertStringContainsString( '"nonce":"', $data );
		$this->assertStringContainsString( '"capabilities":{', $data );
		$this->assertStringContainsString( '"batchpilot_delete"', $data );
	}
}
