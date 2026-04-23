<?php
namespace ContentOps\Tests\Integration\Admin;

use ContentOps\Admin\PostListIntegration;
use ContentOps\Tests\Integration\TestCase;

final class PostListIntegrationTest extends TestCase {

	public function test_injects_duplicate_row_action_for_administrator(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create();
		$integ   = new PostListIntegration( 'http://example.test/wp-admin/admin.php?page=content-ops-operations' );
		$actions = $integ->filter_row_actions( [ 'edit' => '<a>Edit</a>' ], get_post( $post_id ) );

		$this->assertArrayHasKey( 'content_ops_duplicate', $actions );
		$this->assertStringContainsString( 'page=content-ops-operations', $actions['content_ops_duplicate'] );
		$this->assertStringContainsString( 'operation=duplicate', $actions['content_ops_duplicate'] );
		$this->assertStringContainsString( 'target=post', $actions['content_ops_duplicate'] );
		$this->assertStringContainsString( 'filters%5Bids%5D%5B%5D=' . $post_id, $actions['content_ops_duplicate'] );
	}

	public function test_injects_bulk_actions(): void {
		$integ = new PostListIntegration( 'http://example.test/' );
		$bulk  = $integ->filter_bulk_actions( [ 'trash' => 'Trash' ] );
		$this->assertArrayHasKey( 'content_ops_delete', $bulk );
		$this->assertArrayHasKey( 'content_ops_duplicate', $bulk );
		$this->assertArrayHasKey( 'content_ops_edit', $bulk );
	}

	public function test_handle_bulk_action_returns_operations_builder_url_with_ids(): void {
		$url                   = 'http://example.test/edit.php';
		$integ                 = new PostListIntegration( 'http://example.test/wp-admin/admin.php?page=content-ops-operations' );
		$_REQUEST['post_type'] = 'post';
		$out                   = $integ->handle_bulk_action( $url, 'content_ops_delete', [ 1, 2, 3 ] );
		$this->assertStringContainsString( 'operation=delete', $out );
		$this->assertStringContainsString( 'filters%5Bids%5D%5B%5D=1', $out );
		$this->assertStringContainsString( 'filters%5Bids%5D%5B%5D=3', $out );
		unset( $_REQUEST['post_type'] );
	}
}
