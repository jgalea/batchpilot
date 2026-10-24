<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require __DIR__ . '/vendor/autoload.php';

$batchpilot_settings = get_option( \BatchPilot\Admin\Settings::OPTION, [] );
if ( ! is_array( $batchpilot_settings ) || empty( $batchpilot_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

\BatchPilot\Database\Schema::drop_all();

foreach ( [ '_bp_rewrite_of', '_bp_rewrite_copy', '_bp_rewrite_error' ] as $batchpilot_meta_key ) {
	delete_post_meta_by_key( $batchpilot_meta_key );
}

$batchpilot_hooks = [ 'batchpilot_run_operation', 'batchpilot_pro_run_schedule' ];
foreach ( $batchpilot_hooks as $batchpilot_hook ) {
	wp_clear_scheduled_hook( $batchpilot_hook );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( $batchpilot_hook );
	}
}
$batchpilot_as_table = $wpdb->prefix . 'actionscheduler_actions';
if ( $batchpilot_as_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $batchpilot_as_table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$batchpilot_as_table} WHERE hook IN (%s, %s)", ...$batchpilot_hooks ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_batchpilot_' ) . '%', $wpdb->esc_like( '_transient_timeout_batchpilot_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

foreach ( [ \BatchPilot\Admin\Settings::OPTION, 'batchpilot_caps_version' ] as $batchpilot_option ) {
	delete_option( $batchpilot_option );
}

$batchpilot_admin = get_role( 'administrator' );
if ( null !== $batchpilot_admin ) {
	foreach ( \BatchPilot\Capabilities\Capabilities::ALL as $batchpilot_cap ) {
		$batchpilot_admin->remove_cap( $batchpilot_cap );
	}
}
