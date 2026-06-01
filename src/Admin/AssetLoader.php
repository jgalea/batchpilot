<?php
namespace BatchPilot\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Capabilities\Capabilities;

final class AssetLoader {

	public const HANDLE         = 'batchpilot-admin';
	public const WIDGETS_HANDLE = 'batchpilot-widgets';

	private string $plugin_file;

	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	public function register(): void {
		// Register the shared widget bundle early so other plugins (BatchPilot Pro,
		// third parties) can list it as a script dependency. Registration happens
		// on every admin page; actual enqueue only happens for batchpilot pages or
		// when downstream scripts depending on batchpilot-widgets are enqueued.
		add_action( 'admin_enqueue_scripts', [ $this, 'register_widget_bundle' ], 5 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ], 10 );
	}

	public function register_widget_bundle(): void {
		$plugin_dir = plugin_dir_path( $this->plugin_file );
		$plugin_url = plugin_dir_url( $this->plugin_file );

		$asset_path = $plugin_dir . 'assets/build/widgets.asset.php';
		$asset      = file_exists( $asset_path )
			? require $asset_path
			: [
				'dependencies' => [ 'wp-element', 'wp-components', 'wp-i18n' ],
				'version'      => BATCHPILOT_VERSION,
			];

		wp_register_script(
			self::WIDGETS_HANDLE,
			$plugin_url . 'assets/build/widgets.js',
			(array) $asset['dependencies'],
			(string) $asset['version'],
			true
		);
	}

	public function enqueue( string $hook_suffix ): void {
		if ( ! $this->is_batchpilot_page( $hook_suffix ) ) {
			return;
		}

		$plugin_dir = plugin_dir_path( $this->plugin_file );
		$plugin_url = plugin_dir_url( $this->plugin_file );
		$asset_path = $plugin_dir . 'assets/build/admin.asset.php';
		$asset      = file_exists( $asset_path )
			? require $asset_path
			: [
				'dependencies' => [ 'wp-element' ],
				'version'      => BATCHPILOT_VERSION,
			];

		// admin.js consumes the widget bundle at runtime via window.batchPilot.widgets,
		// so it must load AFTER widgets.js. Append the widgets handle to admin's
		// dependency list; WP enqueues dependencies in order.
		$dependencies   = (array) $asset['dependencies'];
		$dependencies[] = self::WIDGETS_HANDLE;

		wp_enqueue_script(
			self::HANDLE,
			$plugin_url . 'assets/build/admin.js',
			$dependencies,
			(string) $asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style(
			self::HANDLE,
			$plugin_url . 'assets/build/admin.css',
			[ 'wp-components' ],
			(string) $asset['version']
		);

		wp_set_script_translations( self::HANDLE, 'batchpilot' );

		wp_add_inline_script(
			self::HANDLE,
			'window.batchPilotAdmin = ' . wp_json_encode( $this->bootstrap_payload() ) . ';',
			'before'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function bootstrap_payload(): array {
		$caps = [];
		foreach ( Capabilities::ALL as $cap ) {
			$caps[ $cap ] = current_user_can( $cap );
		}
		$caps['manage_options'] = current_user_can( 'manage_options' );

		return [
			'namespace'    => 'batchpilot/v1',
			'restUrl'      => esc_url_raw( rest_url( 'batchpilot/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'capabilities' => $caps,
			'adminUrl'     => admin_url(),
			'pluginUrl'    => plugin_dir_url( $this->plugin_file ),
			'version'      => BATCHPILOT_VERSION,
			'pages'        => [
				'operations' => admin_url( 'admin.php?page=batchpilot-operations' ),
				'history'    => admin_url( 'admin.php?page=batchpilot-history' ),
				'dashboard'  => admin_url( 'admin.php?page=batchpilot' ),
				'settings'   => admin_url( 'admin.php?page=batchpilot-settings' ),
			],
		];
	}

	private function is_batchpilot_page( string $hook_suffix ): bool {
		return in_array( $hook_suffix, AdminMenu::page_hook_suffixes(), true );
	}
}
