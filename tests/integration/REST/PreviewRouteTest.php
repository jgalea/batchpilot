<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class PreviewRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$role  = get_role( 'administrator' );
		foreach ( \ContentOps\Capabilities\Capabilities::ALL as $cap ) {
			$role->add_cap( $cap );
		}
		wp_set_current_user( $admin );
	}

	public function test_preview_returns_count_and_token(): void {
		self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$req = new WP_REST_Request( 'POST', '/content-ops/v1/preview' );
		$req->set_body_params(
			[
				'target'    => 'post',
				'operation' => 'delete',
				'filters'   => [ 'status' => [ 'draft' ] ],
				'params'    => [ 'permanent' => false ],
			]
		);
		$response = $this->server->dispatch( $req );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 3, $data['count'] );
		$this->assertCount( 3, $data['sample_ids'] );
		$this->assertNotSame( '', $data['preview_token'] );
	}

	public function test_preview_unknown_operation_returns_400(): void {
		$req = new WP_REST_Request( 'POST', '/content-ops/v1/preview' );
		$req->set_body_params(
			[
				'target'    => 'post',
				'operation' => 'bogus',
				'filters'   => [],
				'params'    => [],
			]
		);
		$response = $this->server->dispatch( $req );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'co.operation.unknown', $response->get_data()['code'] );
	}

	public function test_preview_rejects_user_without_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$req = new WP_REST_Request( 'POST', '/content-ops/v1/preview' );
		$req->set_body_params(
			[
				'target'    => 'post',
				'operation' => 'delete',
				'filters'   => [],
				'params'    => [],
			]
		);
		$response = $this->server->dispatch( $req );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_preview_includes_display_rows_with_title_status_and_edit_url(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => 'A draft to delete',
			]
		);

		$request = new \WP_REST_Request( 'POST', '/content-ops/v1/preview' );
		$request->set_body_params(
			[
				'target'    => 'post',
				'operation' => 'delete',
				'filters'   => [ 'status' => 'draft' ],
				'params'    => [],
			]
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'display_rows', $data );
		$this->assertNotEmpty( $data['display_rows'] );
		$first = $data['display_rows'][0];
		$this->assertSame( $post_id, $first['id'] );
		$this->assertSame( 'A draft to delete', $first['title'] );
		$this->assertSame( 'draft', $first['status'] );
		$this->assertArrayHasKey( 'edit_url', $first );
		$this->assertArrayHasKey( 'thumbnail_url', $first );
	}
}
