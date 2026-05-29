<?php
namespace BatchPilot\Admin;

use BatchPilot\Capabilities\Capabilities;

final class AssetLoader {

	public const HANDLE = 'batchpilot-admin';

	private string $plugin_file;

	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
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

		wp_enqueue_script(
			self::HANDLE,
			$plugin_url . 'assets/build/admin.js',
			(array) $asset['dependencies'],
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
