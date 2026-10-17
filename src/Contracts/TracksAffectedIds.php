<?php
namespace BatchPilot\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TracksAffectedIds {

	/**
	 * IDs to record in history as affected by the last execute_batch() call, for
	 * operations whose result is not the same set of IDs they were handed.
	 *
	 * @return int[]
	 */
	public function last_affected_ids(): array;
}
