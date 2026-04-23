<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class UndoRouteTest extends TestCase {

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

	public function test_undo_restores_trashed_posts(): void {
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );

		global $wpdb;
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$repo  = new \ContentOps\History\OperationRepository( $wpdb );
		$saved = $repo->create( \ContentOps\History\Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => false ] ) );
		$repo->mark_completed( $saved->id(), $ids );

		$response = $this->server->dispatch( new WP_REST_Request( 'POST', '/content-ops/v1/operations/' . $saved->id() . '/undo' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_data()['restored'] );
		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}

	public function test_undo_missing_returns_404(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'POST', '/content-ops/v1/operations/999999/undo' ) );
		$this->assertSame( 404, $response->get_status() );
	}
}
