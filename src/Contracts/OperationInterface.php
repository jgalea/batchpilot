<?php
namespace BatchPilot\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface OperationInterface {

	public function slug(): string;

	public function label(): string;

	/**
	 * @return array<string, mixed>
	 */
	public function get_params_schema(): array;

	/**
	 * @param array<string, mixed> $params
	 */
	public function validate( QueryArgs $args, array $params ): ValidationResult;

	/**
	 * @param array<string, mixed> $params
	 */
	public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult;

	/**
	 * @param int[]                $ids
	 * @param array<string, mixed> $params
	 */
	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult;

	/** Self-declared capability: can this Operation be undone via undo()? */
	public function supports_undo(): bool;

	public function undo( int $operation_id ): UndoResult;
}
