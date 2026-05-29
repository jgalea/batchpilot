<?php
namespace BatchPilot\Tests\Integration\REST;

use BatchPilot\Errors\BatchPilotError;
use BatchPilot\REST\RestController;
use BatchPilot\Tests\Integration\TestCase;
use WP_Error;
use WP_REST_Response;

final class RestControllerTest extends TestCase {

	public function test_error_response_has_canonical_shape(): void {
		$controller = new class() extends RestController {};
		$error      = new BatchPilotError( 'bp.filter.invalid', 'Bad filter.', [ 'key' => 'status' ] );

		$response = $controller->error_response( $error, 422 );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 422, $response->get_status() );
		$this->assertSame(
			[
				'code'    => 'bp.filter.invalid',
				'message' => 'Bad filter.',
				'context' => [ 'key' => 'status' ],
			],
			$response->get_data()
		);
	}

	public function test_capability_denies_subscriber(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$controller = new class() extends RestController {};
		$result     = $controller->require_capability( 'manage_options' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'bp.auth.forbidden', $result->get_error_code() );
	}

	public function test_capability_passes_for_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$controller = new class() extends RestController {};
		$this->assertTrue( $controller->require_capability( 'manage_options' ) );
	}
}
