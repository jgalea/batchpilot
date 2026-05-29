<?php
namespace BatchPilot\Tests\Integration\CLI;

use BatchPilot\CLI\UndoCommand;
use BatchPilot\History\Operation;
use BatchPilot\History\OperationRepository;
use BatchPilot\Operations\DeleteOperation;
use BatchPilot\PreviewToken\TokenGenerator;
use BatchPilot\PreviewToken\TokenStore;
use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Tests\Integration\TestCase;

final class UndoCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\BatchPilot\Database\Schema::install();
	}

	public function test_undo_restores_trashed_posts(): void {
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );

		global $wpdb;
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$repo  = new OperationRepository( $wpdb );
		$saved = $repo->create( Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => false ] ) );
		$repo->mark_completed( $saved->id(), $ids );

		$ops = new OperationRegistry();
		$ops->register( new DeleteOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), $repo ) );

		$cmd    = new UndoCommand( $ops, $repo );
		$result = $cmd->run(
			[
				'id'     => $saved->id(),
				'format' => 'json',
			]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 2, $data['restored'] );
		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}

	public function test_undo_missing_operation_returns_error(): void {
		global $wpdb;
		$ops = new OperationRegistry();
		$cmd = new UndoCommand( $ops, new OperationRepository( $wpdb ) );

		$result = $cmd->run(
			[
				'id'     => 999999,
				'format' => 'json',
			]
		);

		$this->assertNotSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'bp.operation.not_found', $data['code'] );
	}

	public function test_undo_unknown_operation_type_returns_error(): void {
		global $wpdb;
		$repo  = new OperationRepository( $wpdb );
		$saved = $repo->create( Operation::newly_created( 'custom_op', 'post', 0, [], [] ) );

		$ops = new OperationRegistry();
		$cmd = new UndoCommand( $ops, $repo );

		$result = $cmd->run(
			[
				'id'     => $saved->id(),
				'format' => 'json',
			]
		);

		$this->assertNotSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'bp.operation.unknown', $data['code'] );
	}
}
