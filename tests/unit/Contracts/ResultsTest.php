<?php
namespace BatchPilot\Tests\Unit\Contracts;

use BatchPilot\Contracts\BatchResult;
use BatchPilot\Contracts\PreviewResult;
use BatchPilot\Contracts\UndoResult;
use BatchPilot\Contracts\ValidationResult;
use BatchPilot\Errors\BatchPilotError;
use BatchPilot\Tests\Unit\TestCase;

final class ResultsTest extends TestCase {

	public function test_validation_ok(): void {
		$result = ValidationResult::ok();
		$this->assertTrue( $result->is_ok() );
		$this->assertNull( $result->get_error() );
	}

	public function test_validation_error(): void {
		$error  = new BatchPilotError( 'bp.filter.invalid', 'Invalid filter.' );
		$result = ValidationResult::error( $error );

		$this->assertFalse( $result->is_ok() );
		$this->assertSame( $error, $result->get_error() );
	}

	public function test_preview_carries_count_and_sample(): void {
		$preview = PreviewResult::of( 1247, [ 1, 2, 3 ], 'token-abc', [ 'warning' ] );

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 1247, $preview->count() );
		$this->assertSame( [ 1, 2, 3 ], $preview->sample_ids() );
		$this->assertSame( 'token-abc', $preview->preview_token() );
		$this->assertSame( [ 'warning' ], $preview->warnings() );
	}

	public function test_preview_error(): void {
		$preview = PreviewResult::error( new BatchPilotError( 'bp.query.too_many', 'Too many matches.' ) );
		$this->assertFalse( $preview->is_ok() );
	}

	public function test_batch_aggregates_counts(): void {
		$batch = BatchResult::of(
			50,
			48,
			2,
			[
				17 => 'missing',
				22 => 'permission',
			]
		);

		$this->assertTrue( $batch->is_ok() );
		$this->assertSame( 50, $batch->processed() );
		$this->assertSame( 48, $batch->succeeded() );
		$this->assertSame( 2, $batch->failed() );
		$this->assertSame(
			[
				17 => 'missing',
				22 => 'permission',
			],
			$batch->item_errors()
		);
	}

	public function test_undo_reports_restored_count(): void {
		$undo = UndoResult::of( 10 );
		$this->assertTrue( $undo->is_ok() );
		$this->assertSame( 10, $undo->restored() );
	}

	public function test_batch_error_exposes_zero_state(): void {
		$error = new BatchPilotError( 'bp.batch.failed', 'Batch failed.' );
		$batch = BatchResult::error( $error );

		$this->assertFalse( $batch->is_ok() );
		$this->assertSame( $error, $batch->get_error() );
		$this->assertSame( 0, $batch->processed() );
		$this->assertSame( 0, $batch->succeeded() );
		$this->assertSame( 0, $batch->failed() );
		$this->assertSame( [], $batch->item_errors() );
	}

	public function test_undo_error_exposes_zero_state(): void {
		$error = new BatchPilotError( 'bp.undo.failed', 'Undo failed.' );
		$undo  = UndoResult::error( $error );

		$this->assertFalse( $undo->is_ok() );
		$this->assertSame( $error, $undo->get_error() );
		$this->assertSame( 0, $undo->restored() );
	}
}
