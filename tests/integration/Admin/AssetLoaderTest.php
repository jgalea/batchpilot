<?php
namespace ContentOps\Tests\Integration\Admin;

use ContentOps\Admin\AssetLoader;
use ContentOps\Tests\Integration\TestCase;

final class AssetLoaderTest extends TestCase {

	public function test_enqueues_admin_script_only_on_content_ops_pages(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$loader = new AssetLoader( CONTENT_OPS_PLUGIN_FILE );
		$loader->enqueue( 'toplevel_page_content-ops' );
		$this->assertTrue( wp_script_is( 'content-ops-admin', 'enqueued' ) );

		wp_dequeue_script( 'content-ops-admin' );
		wp_deregister_script( 'content-ops-admin' );

		$loader->enqueue( 'edit.php' );
		$this->assertFalse( wp_script_is( 'content-ops-admin', 'enqueued' ) );
	}

	public function test_localizes_bootstrap_payload_with_rest_url_nonce_and_caps(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$loader = new AssetLoader( CONTENT_OPS_PLUGIN_FILE );
		$loader->enqueue( 'toplevel_page_content-ops' );

		$before = wp_scripts()->get_data( 'content-ops-admin', 'before' );
		$this->assertIsArray( $before );
		$data = implode( "\n", array_filter( (array) $before ) );
		$this->assertStringContainsString( 'window.contentOpsAdmin', $data );
		$this->assertStringContainsString( '"namespace":"content-ops\/v1"', $data );
		$this->assertStringContainsString( '"nonce":"', $data );
		$this->assertStringContainsString( '"capabilities":{', $data );
		$this->assertStringContainsString( '"content_ops_delete"', $data );
	}
}
