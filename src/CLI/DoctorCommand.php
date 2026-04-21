<?php
namespace ContentOps\CLI;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\REST\DoctorController;

final class DoctorCommand {

	private DoctorController $controller;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->controller = new DoctorController( $action_scheduler );
	}

	/**
	 * Report Content Ops environment health.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$format = $assoc_args['format'] ?? 'table';
		$report = $this->collect_report();

		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $report, JSON_PRETTY_PRINT ) );
			return;
		}

		$rows    = [];
		$flatten = static function ( string $prefix, array $value ) use ( &$flatten, &$rows ): void {
			foreach ( $value as $k => $v ) {
				$key = '' === $prefix ? (string) $k : $prefix . '.' . $k;
				if ( is_array( $v ) ) {
					$flatten( $key, $v );
				} else {
					$rows[] = [
						'check' => $key,
						'value' => var_export( $v, true ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					];
				}
			}
		};
		$flatten( '', $report );

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'check', 'value' ] );
	}

	public function collect_report(): array {
		return $this->controller->collect_report();
	}
}
