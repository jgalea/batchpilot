<?php
namespace BatchPilot\History;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Admin\Settings;
use wpdb;

final class Pruner {

	public const HOOK = 'batchpilot_prune_history';

	public const STARTED_OPTION = 'batchpilot_pruning_started';

	private const BATCH_SIZE  = 200;
	private const TIME_BUDGET = 15;
	private const FINISHED    = [ 'completed', 'failed' ];

	private wpdb $db;

	private Settings $settings;

	public function __construct( wpdb $db, Settings $settings ) {
		$this->db       = $db;
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( self::HOOK, [ $this, 'prune' ] );
		add_action( 'init', [ self::class, 'ensure_scheduled' ] );
	}

	public static function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			add_option( self::STARTED_OPTION, time(), '', false );
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public function prune(): int {
		$days = max( 1, (int) ( $this->settings->get_all()['history_retention_days'] ?? 30 ) );

		$grace_start = get_option( self::STARTED_OPTION, false );
		if ( false === $grace_start ) {
			add_option( self::STARTED_OPTION, time(), '', false );
			return 0;
		}
		if ( time() < (int) $grace_start + $days * DAY_IN_SECONDS ) {
			return 0;
		}

		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$started = time();
		$removed = 0;

		$operations = $this->db->prefix . 'batchpilot_operations';
		$snapshots  = $this->db->prefix . 'batchpilot_snapshots';
		$statuses   = implode( ', ', array_fill( 0, count( self::FINISHED ), '%s' ) );

		do {
			$ids = $this->db->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->db->prepare(
					"SELECT id FROM {$operations} WHERE status IN ({$statuses}) AND created_at < %s ORDER BY id LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					...array_merge( self::FINISHED, [ $cutoff, self::BATCH_SIZE ] )
				)
			);
			if ( empty( $ids ) ) {
				return $removed;
			}

			$ids          = array_map( 'intval', $ids );
			$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
			$this->db->query( $this->db->prepare( "DELETE FROM {$snapshots} WHERE operation_id IN ({$placeholders})", ...$ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->db->query( $this->db->prepare( "DELETE FROM {$operations} WHERE id IN ({$placeholders})", ...$ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$removed += count( $ids );
		} while ( time() - $started < self::TIME_BUDGET );

		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
		return $removed;
	}
}
