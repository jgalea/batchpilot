<?php
namespace BatchPilot\Tests\Integration\REST;

use BatchPilot\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class ExecuteRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		\BatchPilot\Database\Schema::install();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$role  = get_role( 'administrator' );
		foreach ( \BatchPilot\Capabilities\Capabilities::ALL as $cap ) {
			$role->add_cap( $cap );
		}
		wp_set_current_user( $admin );
	}

	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	private function preview( array $filters = [ 'status' => [ 'draft' ] ], array $params = [ 'permanent' => false ] ): array {
		$req = new WP_REST_Request( 'POST', '/batchpilot/v1/preview' );
		$req->set_body_params(
			[
				'target'    => 'post',
				'operation' => 'delete',
				'filters'   => $filters,
				'params'    => $params,
			]
		);
		return (array) $this->server->dispatch( $req )->get_data();
	}

	public function test_execute_runs_synchronously_below_threshold(): void {
		$ids = self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$preview = $this->preview();
		$token   = $preview['preview_token'];

		$req = new WP_REST_Request( 'POST', '/batchpilot/v1/execute' );
		$req->set_body_params(
			[
				'preview_token' => $token,
				'target'        => 'post',
				'operation'     => 'delete',
				'filters'       => [ 'status' => [ 'draft' ] ],
				'params'        => [ 'permanent' => false ],
			]
		);
		$response = $this->server->dispatch( $req );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'completed', $data['status'] );
		$this->assertGreaterThan( 0, $data['operation_id'] );
		$this->assertSame( 3, $data['batch']['succeeded'] );

		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}

	public function test_execute_rejects_invalid_token(): void {
		self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$req = new WP_REST_Request( 'POST', '/batchpilot/v1/execute' );
		$req->set_body_params(
			[
				'preview_token' => 'not-a-real-token',
				'target'        => 'post',
				'operation'     => 'delete',
				'filters'       => [ 'status' => [ 'draft' ] ],
				'params'        => [ 'permanent' => false ],
			]
		);
		$response = $this->server->dispatch( $req );
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'bp.preview.stale_token', $response->get_data()['code'] );
	}

	public function test_execute_queues_above_threshold(): void {
		add_filter( 'batchpilot_async_threshold', static fn () => 2 );
		self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$preview = $this->preview();
		$req     = new WP_REST_Request( 'POST', '/batchpilot/v1/execute' );
		$req->set_body_params(
			[
				'preview_token' => $preview['preview_token'],
				'target'        => 'post',
				'operation'     => 'delete',
				'filters'       => [ 'status' => [ 'draft' ] ],
				'params'        => [ 'permanent' => false ],
			]
		);
		$response = $this->server->dispatch( $req );

		$this->assertSame( 202, $response->get_status() );
		$this->assertSame( 'queued', $response->get_data()['status'] );
	}
}
