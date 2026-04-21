<?php
/**
 * Plugin Name:       Content Ops
 * Plugin URI:        https://contentops.example
 * Description:       Bulk operations for WordPress and WooCommerce, designed for humans and AI agents.
 * Version:           0.1.0-alpha
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Jean Galea
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       content-ops
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONTENT_OPS_VERSION', '0.1.0-alpha' );
define( 'CONTENT_OPS_PLUGIN_FILE', __FILE__ );
define( 'CONTENT_OPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

$content_ops_autoload = CONTENT_OPS_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $content_ops_autoload ) ) {
	add_action( 'admin_notices', static function () {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'Content Ops: run `composer install` in the plugin directory before activating.', 'content-ops' );
		echo '</p></div>';
	} );
	return;
}
require $content_ops_autoload;

\ContentOps\Plugin::boot( __FILE__ );
