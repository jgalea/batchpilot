<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require __DIR__ . '/vendor/autoload.php';

if ( ! get_option( 'content_ops_delete_data_on_uninstall', false ) ) {
	return;
}

\ContentOps\Database\Schema::drop_all();

delete_option( 'content_ops_delete_data_on_uninstall' );
