<?php
namespace ContentOps\Admin;

final class AdminMenu {

	public const PARENT_SLUG = 'content-ops';

	public const PAGES = [
		'dashboard'  => [
			'slug'      => 'content-ops',
			'title_key' => 'Dashboard',
		],
		'operations' => [
			'slug'      => 'content-ops-operations',
			'title_key' => 'Operations',
		],
		'history'    => [
			'slug'      => 'content-ops-history',
			'title_key' => 'History',
		],
		'settings'   => [
			'slug'      => 'content-ops-settings',
			'title_key' => 'Settings',
		],
	];

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
	}

	public function register_menus(): void {
		add_menu_page(
			__( 'Content Ops', 'content-ops' ),
			__( 'Content Ops', 'content-ops' ),
			'manage_options',
			self::PARENT_SLUG,
			function () {
				$this->render_page( 'dashboard' );
			},
			'dashicons-list-view',
			58
		);

		foreach ( self::PAGES as $key => $info ) {
			add_submenu_page(
				self::PARENT_SLUG,
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
				__( $info['title_key'], 'content-ops' ),
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
				__( $info['title_key'], 'content-ops' ),
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
			'<div class="wrap"><div class="content-ops-app" id="content-ops-%1$s-root"></div></div>',
			esc_attr( $page_key )
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function page_hook_suffixes(): array {
		return [
			'dashboard'  => 'toplevel_page_content-ops',
			'operations' => 'content-ops_page_content-ops-operations',
			'history'    => 'content-ops_page_content-ops-history',
			'settings'   => 'content-ops_page_content-ops-settings',
		];
	}
}
