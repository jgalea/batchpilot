<?php
namespace BatchPilot\History;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use wpdb;

final class SnapshotRepository {

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	private function table(): string {
		return $this->db->prefix . 'batchpilot_snapshots';
	}

	/** @param Snapshot[] $snapshots */
	public function bulk_insert( array $snapshots ): void {
		if ( empty( $snapshots ) ) {
			return;
		}

		foreach ( array_chunk( $snapshots, 500 ) as $chunk ) {
			$placeholders = [];
			$values       = [];
			foreach ( $chunk as $snapshot ) {
				$old_value       = $snapshot->old_value();
				$old_placeholder = null === $old_value ? 'NULL' : '%s';
				$placeholders[]  = "(%d, %s, %d, %s, {$old_placeholder})";
				$values[]        = $snapshot->operation_id();
				$values[]        = $snapshot->object_type();
				$values[]        = $snapshot->object_id();
				$values[]        = $snapshot->field();
				if ( null !== $old_value ) {
					$values[] = $old_value;
				}
			}

			$sql = "INSERT INTO {$this->table()} (operation_id, object_type, object_id, field, old_value) VALUES " . implode( ', ', $placeholders );

			$this->db->query( $this->db->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
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
