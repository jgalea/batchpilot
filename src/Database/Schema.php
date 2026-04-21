<?php
namespace ContentOps\Database;

final class Schema {

	public const VERSION        = '1.0.0';
	public const VERSION_OPTION = 'content_ops_schema_version';

	public static function install(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$operations = "CREATE TABLE {$wpdb->prefix}co_operations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(32) NOT NULL,
			target VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			filters_json LONGTEXT NULL,
			params_json LONGTEXT NULL,
			affected_count INT UNSIGNED NOT NULL DEFAULT 0,
			affected_ids_json LONGTEXT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'pending',
			error_message TEXT NULL,
			created_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY user_created (user_id, created_at),
			KEY status (status)
		) {$charset_collate};";

		$snapshots = "CREATE TABLE {$wpdb->prefix}co_snapshots (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			operation_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(64) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			field VARCHAR(64) NOT NULL,
			old_value LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY operation_id (operation_id),
			KEY object (object_type, object_id)
		) {$charset_collate};";

		$schedules = "CREATE TABLE {$wpdb->prefix}co_schedules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			operation_type VARCHAR(32) NOT NULL,
			target_type VARCHAR(64) NOT NULL,
			filters_json LONGTEXT NULL,
			params_json LONGTEXT NULL,
			recurrence_json LONGTEXT NULL,
			action_scheduler_id BIGINT UNSIGNED NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			last_run_at DATETIME NULL,
			next_run_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY enabled_next_run (enabled, next_run_at)
		) {$charset_collate};";

		dbDelta( $operations );
		dbDelta( $snapshots );
		dbDelta( $schedules );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	public static function drop_all(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}co_snapshots" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}co_operations" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}co_schedules" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_option( self::VERSION_OPTION );
	}
}
