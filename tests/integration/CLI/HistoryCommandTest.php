<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\CLI\HistoryCommand;
use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;
use ContentOps\Tests\Integration\TestCase;

final class HistoryCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	public function test_history_lists_recent_first(): void {
		global $wpdb;
		$repo = new OperationRepository( $wpdb );
		$a    = $repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );
		$b    = $repo->create( Operation::newly_created( 'duplicate', 'post', 1, [], [] ) );

		$command = new HistoryCommand( $repo );
		$result  = $command->run(
			[
				'limit'  => 10,
				'format' => 'json',
			]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$rows = json_decode( $result['output'], true );
		$this->assertSame( $b->id(), $rows[0]['id'] );
		$this->assertSame( $a->id(), $rows[1]['id'] );
	}

	public function test_history_table_format_emits_rows(): void {
		global $wpdb;
		$repo = new OperationRepository( $wpdb );
		$repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );

		$command = new HistoryCommand( $repo );
		$result  = $command->run( [ 'limit' => 5 ] );

		$this->assertSame( 0, $result['exit_code'] );
		$this->assertStringContainsString( 'delete', $result['output'] );
		$this->assertStringContainsString( 'post', $result['output'] );
	}

	public function test_history_limit_is_applied(): void {
		global $wpdb;
		$repo = new OperationRepository( $wpdb );
		for ( $i = 0; $i < 5; $i++ ) {
			$repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );
		}

		$command = new HistoryCommand( $repo );
		$result  = $command->run(
			[
				'limit'  => 2,
				'format' => 'json',
			]
		);

		$rows = json_decode( $result['output'], true );
		$this->assertCount( 2, $rows );
	}
}
