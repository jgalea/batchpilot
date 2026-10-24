<?php
namespace BatchPilot\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION = 'batchpilot_settings';

	public const DEFAULTS = [
		'async_threshold'          => 100,
		'batch_size'               => 50,
		'delete_permanent_default' => false,
		'history_retention_days'   => 30,
		'delete_data_on_uninstall' => false,
		'copy_post_types'          => [ 'post', 'page' ],
		'copy_roles'               => [ 'administrator', 'editor' ],
	];

	public function register(): void {
		add_filter( 'batchpilot_async_threshold', [ $this, 'filter_async_threshold' ] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_all(): array {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		return array_merge( self::DEFAULTS, $stored );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function save( array $input ): array {
		$clean  = $this->sanitize( $input );
		$merged = array_merge( $this->get_all(), $clean );
		update_option( self::OPTION, $merged );
		return $merged;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$out = [];
		if ( array_key_exists( 'async_threshold', $input ) ) {
			$out['async_threshold'] = max( 1, (int) $input['async_threshold'] );
		}
		if ( array_key_exists( 'batch_size', $input ) ) {
			$out['batch_size'] = max( 1, min( 500, (int) $input['batch_size'] ) );
		}
		if ( array_key_exists( 'delete_permanent_default', $input ) ) {
			$out['delete_permanent_default'] = (bool) $input['delete_permanent_default'];
		}
		if ( array_key_exists( 'history_retention_days', $input ) ) {
			$out['history_retention_days'] = max( 1, (int) $input['history_retention_days'] );
		}
		if ( array_key_exists( 'delete_data_on_uninstall', $input ) ) {
			$out['delete_data_on_uninstall'] = (bool) $input['delete_data_on_uninstall'];
		}
		if ( array_key_exists( 'copy_post_types', $input ) ) {
			$out['copy_post_types'] = $this->sanitize_post_types( $input['copy_post_types'] );
		}
		if ( array_key_exists( 'copy_roles', $input ) ) {
			$out['copy_roles'] = $this->sanitize_roles( $input['copy_roles'] );
		}
		return $out;
	}

	/**
	 * @param mixed $value
	 * @return string[]
	 */
	private function sanitize_post_types( $value ): array {
		$clean = [];
		foreach ( (array) $value as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug && post_type_exists( $slug ) && ! in_array( $slug, [ 'attachment', 'revision', 'nav_menu_item' ], true ) ) {
				$clean[] = $slug;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * @param mixed $value
	 * @return string[]
	 */
	private function sanitize_roles( $value ): array {
		$known = array_keys( wp_roles()->roles );
		$clean = [];
		foreach ( (array) $value as $role ) {
			$role = sanitize_key( (string) $role );
			if ( in_array( $role, $known, true ) ) {
				$clean[] = $role;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * @param mixed $default_value
	 */
	public function filter_async_threshold( $default_value ): int {
		$all = $this->get_all();
		return (int) ( $all['async_threshold'] ?? $default_value );
	}
}
