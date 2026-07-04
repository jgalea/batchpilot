<?php
namespace BatchPilot\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminMenu {

	public const PARENT_SLUG = 'batchpilot';

	public const PAGES = [
		'dashboard'  => [
			'slug' => 'batchpilot',
		],
		'operations' => [
			'slug' => 'batchpilot-operations',
		],
		'history'    => [
			'slug' => 'batchpilot-history',
		],
		'settings'   => [
			'slug' => 'batchpilot-settings',
		],
	];

	private static function submenu_label( string $key ): string {
		switch ( $key ) {
			case 'operations':
				return __( 'Operations', 'batchpilot' );
			case 'history':
				return __( 'History', 'batchpilot' );
			case 'settings':
				return __( 'Settings', 'batchpilot' );
			case 'dashboard':
			default:
				return __( 'Dashboard', 'batchpilot' );
		}
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
	}

	public function register_menus(): void {
		add_menu_page(
			__( 'BatchPilot', 'batchpilot' ),
			__( 'BatchPilot', 'batchpilot' ),
			'manage_options',
			self::PARENT_SLUG,
			function () {
				$this->render_page( 'dashboard' );
			},
			'dashicons-list-view',
			58
		);

		foreach ( self::PAGES as $key => $info ) {
			$label = self::submenu_label( $key );
			add_submenu_page(
				self::PARENT_SLUG,
				$label,
				$label,
				'manage_options',
				$info['slug'],
				function () use ( $key ) {
					$this->render_page( $key );
				}
			);
		}
	}

	public function render_page( string $page_key ): void {
		$allowed = array_keys( self::PAGES );
		if ( ! in_array( $page_key, $allowed, true ) ) {
			return;
		}
		printf(
			'<div class="wrap"><div class="batchpilot-app" id="batchpilot-%1$s-root"></div></div>',
			esc_attr( $page_key )
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function page_hook_suffixes(): array {
		return [
			'dashboard'  => 'toplevel_page_batchpilot',
			'operations' => 'batchpilot_page_batchpilot-operations',
			'history'    => 'batchpilot_page_batchpilot-history',
			'settings'   => 'batchpilot_page_batchpilot-settings',
		];
	}
}
