<?php
namespace BatchPilot\Tests\Unit\Contracts;

use BatchPilot\Contracts\FilterDefinition;
use BatchPilot\Tests\Unit\TestCase;

final class FilterDefinitionTest extends TestCase {

	public function test_exposes_schema_fields(): void {
		$def = new FilterDefinition( 'status', 'Status', 'enum', [ 'options' => [ 'draft', 'publish' ] ] );

		$this->assertSame( 'status', $def->key() );
		$this->assertSame( 'Status', $def->label() );
		$this->assertSame( 'enum', $def->type() );
		$this->assertSame( [ 'draft', 'publish' ], $def->schema()['options'] );
	}

	public function test_to_array_is_serializable(): void {
		$def = new FilterDefinition( 'author', 'Author', 'user_id' );

		$this->assertSame(
			[
				'key'    => 'author',
				'label'  => 'Author',
				'type'   => 'user_id',
				'schema' => [],
			],
			$def->to_array()
		);
	}

	public function test_empty_key_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new FilterDefinition( '', 'Empty', 'string' );
	}
}
