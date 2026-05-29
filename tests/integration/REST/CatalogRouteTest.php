<?php
namespace BatchPilot\Tests\Integration\REST;

use BatchPilot\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class CatalogRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_catalog_returns_targets_and_operations(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/batchpilot/v1/catalog' ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'targets', $data );
		$this->assertArrayHasKey( 'operations', $data );
		$this->assertArrayHasKey( 'presets', $data );

		$target_slugs = array_column( $data['targets'], 'slug' );
		$this->assertContains( 'post', $target_slugs );
		$this->assertContains( 'page', $target_slugs );

		$op_slugs = array_column( $data['operations'], 'slug' );
		$this->assertContains( 'delete', $op_slugs );
		$this->assertContains( 'duplicate', $op_slugs );
		$this->assertContains( 'edit', $op_slugs );

		$post_row = null;
		foreach ( $data['targets'] as $t ) {
			if ( 'post' === $t['slug'] ) {
				$post_row = $t;
				break;
			}
		}
		$this->assertNotNull( $post_row );
		$this->assertNotEmpty( $post_row['filters'] );
	}

	public function test_catalog_rejects_non_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/batchpilot/v1/catalog' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_catalog_exposes_built_in_presets(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/batchpilot/v1/catalog' ) );
		$data     = $response->get_data();
		$slugs    = array_map( static fn ( $p ) => $p['slug'], $data['presets'] );
		$this->assertContains( 'trash-old-drafts', $slugs );
		$this->assertContains( 'trash-auto-drafts', $slugs );
	}
}
