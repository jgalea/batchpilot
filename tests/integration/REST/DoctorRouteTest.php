<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class DoctorRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_doctor_returns_expected_shape(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/doctor' ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		foreach ( [ 'schema_version', 'action_scheduler', 'abilities_api', 'hpos', 'tables', 'cron' ] as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		$this->assertIsBool( $data['action_scheduler']['available'] );
	}

	public function test_doctor_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/doctor' ) );
		$this->assertSame( 403, $response->get_status() );
	}
}
