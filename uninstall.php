<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require __DIR__ . '/vendor/autoload.php';

$batchpilot_uninstall_site = static function (): void {
	global $wpdb;

	$settings = get_option( \BatchPilot\Admin\Settings::OPTION, [] );
	$enabled  = ( is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] ) )
		|| rest_sanitize_boolean( get_option( 'batchpilot_delete_data_on_uninstall', false ) );
	if ( ! $enabled ) {
		return;
	}

	\BatchPilot\Database\Schema::drop_all();

	foreach ( [ '_bp_rewrite_of', '_bp_rewrite_copy', '_bp_rewrite_error' ] as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}

	$hooks = [ 'batchpilot_run_operation', 'batchpilot_pro_run_schedule', 'batchpilot_prune_history' ];
	foreach ( $hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook );
		}
	}
	$as_table = $wpdb->prefix . 'actionscheduler_actions';
	if ( $as_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $as_table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$as_table} WHERE hook IN (%s, %s, %s)", $hooks[0], $hooks[1], $hooks[2] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_batchpilot_' ) . '%', $wpdb->esc_like( '_transient_timeout_batchpilot_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	foreach ( [ \BatchPilot\Admin\Settings::OPTION, 'batchpilot_delete_data_on_uninstall', 'batchpilot_caps_version' ] as $option ) {
		delete_option( $option );
	}

	$admin = get_role( 'administrator' );
	if ( null !== $admin ) {
		foreach ( \BatchPilot\Capabilities\Capabilities::ALL as $cap ) {
			$admin->remove_cap( $cap );
		}
	}
};

if ( is_multisite() ) {
	foreach ( get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) as $batchpilot_site_id ) {
		switch_to_blog( $batchpilot_site_id );
		$batchpilot_uninstall_site();
		restore_current_blog();
	}
} else {
	$batchpilot_uninstall_site();
}
