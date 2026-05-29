<?php
namespace BatchPilot\Tests\Integration\REST;

use BatchPilot\Admin\Settings;
use BatchPilot\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class SettingsRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_get_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/batchpilot/v1/settings' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_returns_defaults(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/batchpilot/v1/settings' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 100, $data['async_threshold'] );
	}

	public function test_post_persists_changes(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request = new WP_REST_Request( 'POST', '/batchpilot/v1/settings' );
		$request->set_body_params( [ 'async_threshold' => 300 ] );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 300, $response->get_data()['async_threshold'] );
	}

	public function test_post_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$request = new WP_REST_Request( 'POST', '/batchpilot/v1/settings' );
		$request->set_body_params( [ 'async_threshold' => 300 ] );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}
}
