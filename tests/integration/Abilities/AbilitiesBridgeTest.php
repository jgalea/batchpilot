<?php
namespace BatchPilot\Tests\Integration\Abilities;

use BatchPilot\Abilities\AbilitiesBridge;
use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\Tests\Integration\TestCase;

final class AbilitiesBridgeTest extends TestCase {

	public function test_is_available_reflects_function_presence(): void {
		$bridge = $this->bridge();
		$this->assertSame( function_exists( 'wp_register_ability' ), $bridge->is_available() );
	}

	public function test_register_is_safe_when_abilities_missing(): void {
		$bridge = $this->bridge();
		$bridge->register();

		$this->assertTrue( true );
	}

	public function test_history_ability_runs_without_input(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API unavailable.' );
		}
		\BatchPilot\Database\Schema::install();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$ability = wp_get_ability( 'batchpilot/history' );
		$this->assertNotNull( $ability );
		$this->assertIsArray( $ability->execute() );
	}

	public function test_undoing_twice_is_a_conflict_not_a_server_error(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API unavailable.' );
		}
		global $wpdb;
		\BatchPilot\Database\Schema::install();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		wp_get_current_user()->add_cap( 'batchpilot_edit' );

		$repo = new \BatchPilot\History\OperationRepository( $wpdb );
		$op   = $repo->create( \BatchPilot\History\Operation::newly_created( 'edit', 'post', get_current_user_id(), [], [] ) );
		$repo->mark_undone( $op->id() );

		$result = wp_get_ability( 'batchpilot/undo' )->execute( [ 'operation_id' => $op->id() ] );

		$this->assertWPError( $result );
		$this->assertSame( 'bp.undo.already_undone', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
	}

	private function bridge(): AbilitiesBridge {
		global $wpdb;
		$targets = new \BatchPilot\Registry\TargetRegistry();
		$ops     = new \BatchPilot\Registry\OperationRegistry();
		$exec    = new \BatchPilot\Execution\ExecutionService(
			$targets,
			$ops,
			new \BatchPilot\History\OperationRepository( $wpdb ),
			new \BatchPilot\History\SnapshotRepository( $wpdb ),
			new \BatchPilot\PreviewToken\TokenGenerator( 'salt' ),
			new \BatchPilot\PreviewToken\TokenStore( 300 )
		);
		return new AbilitiesBridge( new ActionSchedulerBridge(), $exec, $targets, $ops );
	}
}
