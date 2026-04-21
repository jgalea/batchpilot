<?php
namespace ContentOps\Tests\Unit\Contracts;

use ContentOps\Contracts\OperationInterface;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Tests\Unit\TestCase;
use ReflectionClass;

final class ContractsShapeTest extends TestCase {

	public function test_target_interface_exposes_expected_methods(): void {
		$methods = array_map(
			static fn ( \ReflectionMethod $m ) => $m->getName(),
			( new ReflectionClass( TargetInterface::class ) )->getMethods()
		);

		foreach ( [ 'slug', 'label', 'get_filters', 'query', 'count', 'get_display', 'supports_operation' ] as $expected ) {
			$this->assertContains( $expected, $methods );
		}
	}

	public function test_operation_interface_exposes_expected_methods(): void {
		$methods = array_map(
			static fn ( \ReflectionMethod $m ) => $m->getName(),
			( new ReflectionClass( OperationInterface::class ) )->getMethods()
		);

		foreach ( [ 'slug', 'label', 'get_params_schema', 'validate', 'preview', 'execute_batch', 'supports_undo', 'undo' ] as $expected ) {
			$this->assertContains( $expected, $methods );
		}
	}
}
