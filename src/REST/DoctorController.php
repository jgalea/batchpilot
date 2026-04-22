<?php
namespace ContentOps\REST;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Database\Schema;
use WP_REST_Request;
use WP_REST_Response;

final class DoctorController extends RestController {

	private ActionSchedulerBridge $action_scheduler;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->action_scheduler = $action_scheduler;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function check_permission() {
		return $this->require_capability( 'manage_options' );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->collect_report() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect_report(): array {
		global $wpdb;

		$tables  = [
			$wpdb->prefix . 'co_operations',
			$wpdb->prefix . 'co_snapshots',
			$wpdb->prefix . 'co_schedules',
		];
		$missing = [];
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				$missing[] = $table;
			}
		}

		$hpos_class   = '\\Automattic\\WooCommerce\\Utilities\\OrderUtil';
		$hpos_enabled = class_exists( $hpos_class )
			? call_user_func( [ $hpos_class, 'custom_orders_table_usage_is_enabled' ] )
			: false;

		return [
			'schema_version'   => (string) get_option( Schema::VERSION_OPTION, '' ),
			'action_scheduler' => [
				'available' => $this->action_scheduler->is_available(),
			],
			'abilities_api'    => [
				'available' => function_exists( 'wp_register_ability' ),
			],
			'hpos'             => [
				'available' => class_exists( $hpos_class ),
				'enabled'   => (bool) $hpos_enabled,
			],
			'tables'           => [
				'expected' => $tables,
				'missing'  => $missing,
			],
			'cron'             => [
				'disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			],
		];
	}
}
