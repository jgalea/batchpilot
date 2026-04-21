<?php
namespace ContentOps\Contracts;

interface OperationInterface {

	public function slug(): string;

	public function label(): string;

	public function get_params_schema(): array;

	public function validate( QueryArgs $args, array $params ): ValidationResult;

	public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult;

	/** @param int[] $ids */
	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult;

	public function supports_undo(): bool;

	public function undo( int $operation_id ): UndoResult;
}
