<?php
namespace BatchPilot\CLI;

use BatchPilot\Async\ActionSchedulerBridge;
use BatchPilot\REST\DoctorController;

final class DoctorCommand {

	private DoctorController $controller;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->controller = new DoctorController( $action_scheduler );
	}

	/**
	 * Report BatchPilot environment health.
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
	 *
	 * @param string[]             $args
	 * @param array<string, mixed> $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$format = $assoc_args['format'] ?? 'table';
		$report = $this->collect_report();

		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $report, JSON_PRETTY_PRINT ) );
			return;
		}

		$rows = [];
		/**
		 * @var callable(string, array<int|string, mixed>): void $flatten
		 */
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

	/**
	 * @return array<string, mixed>
	 */
	public function collect_report(): array {
		return $this->controller->collect_report();
	}
}
