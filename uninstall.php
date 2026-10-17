<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require __DIR__ . '/vendor/autoload.php';

if ( ! get_option( 'batchpilot_delete_data_on_uninstall', false ) ) {
	return;
}

\BatchPilot\Database\Schema::drop_all();

foreach ( [ '_bp_rewrite_of', '_bp_rewrite_copy', '_bp_rewrite_error' ] as $batchpilot_meta_key ) {
	delete_post_meta_by_key( $batchpilot_meta_key );
}

delete_option( 'batchpilot_delete_data_on_uninstall' );
