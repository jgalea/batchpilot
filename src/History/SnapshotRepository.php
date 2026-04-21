<?php
namespace ContentOps\History;

use wpdb;

final class SnapshotRepository {

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	private function table(): string {
		return $this->db->prefix . 'co_snapshots';
	}

	/** @param Snapshot[] $snapshots */
	public function bulk_insert( array $snapshots ): void {
		foreach ( $snapshots as $snapshot ) {
			$this->db->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table(),
				[
					'operation_id' => $snapshot->operation_id(),
					'object_type'  => $snapshot->object_type(),
					'object_id'    => $snapshot->object_id(),
					'field'        => $snapshot->field(),
					'old_value'    => $snapshot->old_value(),
				],
				[ '%d', '%s', '%d', '%s', '%s' ]
			);
		}
	}

	/** @return Snapshot[] */
	public function for_operation( int $operation_id ): array {
		$rows = $this->db->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->prepare( "SELECT * FROM {$this->table()} WHERE operation_id = %d", $operation_id ),
			ARRAY_A
		);

		return array_map(
			static fn ( array $row ) => new Snapshot(
				(int) $row['operation_id'],
				(string) $row['object_type'],
				(int) $row['object_id'],
				(string) $row['field'],
				isset( $row['old_value'] ) ? (string) $row['old_value'] : null
			),
			is_array( $rows ) ? $rows : []
		);
	}

	public function delete_for_operation( int $operation_id ): void {
		$this->db->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[ 'operation_id' => $operation_id ],
			[ '%d' ]
		);
	}
}
