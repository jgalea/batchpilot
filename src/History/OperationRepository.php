<?php
namespace BatchPilot\History;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use wpdb;

final class OperationRepository {

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	private function table(): string {
		return $this->db->prefix . 'batchpilot_operations';
	}

	public function create( Operation $op ): Operation {
		$this->db->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[
				'type'              => $op->type(),
				'target'            => $op->target(),
				'user_id'           => $op->user_id(),
				'filters_json'      => wp_json_encode( $op->filters() ),
				'params_json'       => wp_json_encode( $op->params() ),
				'affected_count'    => $op->affected_count(),
				'affected_ids_json' => wp_json_encode( $op->affected_ids() ),
				'status'            => $op->status(),
				'error_message'     => $op->error_message(),
				'created_at'        => $op->created_at(),
				'completed_at'      => $op->completed_at(),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $op->with_id( (int) $this->db->insert_id );
	}

	/**
	 * Pins the exact set of matched object IDs for an operation deferred to async
	 * execution, so the Action Scheduler run later executes against what was actually
	 * shown at accept time rather than re-evaluating the filters against whatever
	 * content matches when the cron job happens to fire.
	 *
	 * @param int[] $ids
	 */
	public function mark_queued( int $id, array $ids ): void {
		$this->db->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[ 'queued_ids_json' => wp_json_encode( array_values( array_map( 'intval', $ids ) ) ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public function mark_running( int $id ): void {
		$this->db->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[ 'status' => 'running' ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * @param int[] $affected_ids
	 */
	public function mark_completed( int $id, array $affected_ids ): void {
		$this->db->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[
				'status'            => 'completed',
				'affected_count'    => count( $affected_ids ),
				'affected_ids_json' => wp_json_encode( array_values( $affected_ids ) ),
				'completed_at'      => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function mark_failed( int $id, string $error_message ): void {
		$this->db->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[
				'status'        => 'failed',
				'error_message' => $error_message,
				'completed_at'  => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function find( int $id ): ?Operation {
		$row = $this->db->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? Operation::from_row( $row ) : null;
	}

	/** @return Operation[] */
	public function list( int $limit, int $offset ): array {
		$rows = $this->db->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->prepare( "SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ),
			ARRAY_A
		);

		return array_map( [ Operation::class, 'from_row' ], is_array( $rows ) ? $rows : [] );
	}
}
