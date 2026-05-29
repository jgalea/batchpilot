<?php
/**
 * Plugin Name:       BatchPilot
 * Plugin URI:        https://github.com/jgalea/batchpilot
 * Description:       Bulk delete, bulk edit, and bulk duplicate WordPress content with preview, undo, and full audit history. Driveable via admin UI, WP-CLI, REST API, and the Abilities API.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Jean Galea
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       batchpilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BATCHPILOT_VERSION', '1.0.0' );
define( 'BATCHPILOT_PLUGIN_FILE', __FILE__ );
define( 'BATCHPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

$batchpilot_autoload = BATCHPILOT_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $batchpilot_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'BatchPilot: run `composer install` in the plugin directory before activating.', 'batchpilot' );
			echo '</p></div>';
		}
	);
	return;
}
require $batchpilot_autoload;

\BatchPilot\Plugin::boot( __FILE__ );
