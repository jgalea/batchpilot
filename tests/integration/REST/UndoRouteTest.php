<?php
namespace BatchPilot\Tests\Integration\REST;

use BatchPilot\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class UndoRouteTest extends TestCase {

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

	public function test_undo_restores_trashed_posts(): void {
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );

		global $wpdb;
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$repo  = new \BatchPilot\History\OperationRepository( $wpdb );
		$saved = $repo->create( \BatchPilot\History\Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => false ] ) );
		$repo->mark_completed( $saved->id(), $ids );

		$response = $this->server->dispatch( new WP_REST_Request( 'POST', '/batchpilot/v1/operations/' . $saved->id() . '/undo' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_data()['restored'] );
		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}

	public function test_undo_missing_returns_404(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'POST', '/batchpilot/v1/operations/999999/undo' ) );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_undo_rejects_a_non_owning_user_with_the_same_capability(): void {
		// Holding batchpilot_delete only says you may undo delete operations, not that
		// you may undo *anyone's* delete operation. A second, equally-capable user must
		// not be able to undo the first user's operation.
		global $wpdb;
		$owner = self::factory()->user->create( [ 'role' => 'editor' ] );
		$role  = get_role( 'editor' );
		foreach ( \BatchPilot\Capabilities\Capabilities::ALL as $cap ) {
			$role->add_cap( $cap );
		}

		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$repo  = new \BatchPilot\History\OperationRepository( $wpdb );
		$saved = $repo->create( \BatchPilot\History\Operation::newly_created( 'delete', 'post', $owner, [], [ 'permanent' => false ] ) );
		$repo->mark_completed( $saved->id(), $ids );

		$other_editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $other_editor );

		$response = $this->server->dispatch( new WP_REST_Request( 'POST', '/batchpilot/v1/operations/' . $saved->id() . '/undo' ) );

		$this->assertSame( 403, $response->get_status() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ), 'A non-owning user must not be able to undo someone else\'s operation.' );
		}
	}

	public function test_undo_allows_admin_override_of_another_users_operation(): void {
		// manage_options retains the ability to undo any operation, matching the existing
		// admin-override convention used elsewhere (e.g. the "operation row not found"
		// branch already required manage_options).
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );

		global $wpdb;
		$owner = self::factory()->user->create( [ 'role' => 'editor' ] );

		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$repo  = new \BatchPilot\History\OperationRepository( $wpdb );
		$saved = $repo->create( \BatchPilot\History\Operation::newly_created( 'delete', 'post', $owner, [], [ 'permanent' => false ] ) );
		$repo->mark_completed( $saved->id(), $ids );

		// set_up() already left an administrator as the current user.
		$response = $this->server->dispatch( new WP_REST_Request( 'POST', '/batchpilot/v1/operations/' . $saved->id() . '/undo' ) );

		$this->assertSame( 200, $response->get_status() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}
}
