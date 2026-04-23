<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class OperationsRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_list_returns_recent_operations(): void {
		global $wpdb;
		$repo = new \ContentOps\History\OperationRepository( $wpdb );
		$a    = $repo->create( \ContentOps\History\Operation::newly_created( 'delete', 'post', 1, [], [] ) );
		$b    = $repo->create( \ContentOps\History\Operation::newly_created( 'duplicate', 'post', 1, [], [] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/operations' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $b->id(), $data[0]['id'] );
		$this->assertSame( $a->id(), $data[1]['id'] );
	}

	public function test_single_returns_operation(): void {
		global $wpdb;
		$repo  = new \ContentOps\History\OperationRepository( $wpdb );
		$saved = $repo->create( \ContentOps\History\Operation::newly_created( 'delete', 'post', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/operations/' . $saved->id() ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $saved->id(), $data['id'] );
		$this->assertSame( 'delete', $data['type'] );
		$this->assertSame( [ 'status' => [ 'draft' ] ], $data['filters'] );
	}

	public function test_single_404_when_missing(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/operations/999999' ) );
		$this->assertSame( 404, $response->get_status() );
	}
}
