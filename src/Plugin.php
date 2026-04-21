<?php
namespace ContentOps;

final class Plugin {

	private static ?self $instance = null;

	private string $plugin_file;

	private array $services = [];

	private function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	public static function boot( string $plugin_file ): self {
		if ( self::$instance instanceof self ) {
			return self::$instance;
		}

		self::$instance = new self( $plugin_file );
		self::$instance->register_hooks();

		return self::$instance;
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	public function plugin_file(): string {
		return $this->plugin_file;
	}

	public function plugin_dir(): string {
		return \plugin_dir_path( $this->plugin_file );
	}

	public function set( string $id, object $service ): void {
		$this->services[ $id ] = $service;
	}

	public function get( string $id ): ?object {
		return $this->services[ $id ] ?? null;
	}

	private function register_hooks(): void {
		\register_activation_hook( $this->plugin_file, [ Activator::class, 'activate' ] );
		\register_deactivation_hook( $this->plugin_file, [ Deactivator::class, 'deactivate' ] );
		\add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ], -1 );
	}

	public function on_plugins_loaded(): void {
		\load_plugin_textdomain( 'content-ops', false, dirname( \plugin_basename( $this->plugin_file ) ) . '/languages' );
		$this->load_action_scheduler();

		$action_scheduler_bridge = new \ContentOps\Async\ActionSchedulerBridge();
		$this->set( 'async.action_scheduler', $action_scheduler_bridge );

		$rest_registrar = new \ContentOps\REST\RouteRegistrar( $action_scheduler_bridge );
		$rest_registrar->register();
		$this->set( 'rest.registrar', $rest_registrar );

		\do_action( 'content_ops_booted', $this );
	}

	private function load_action_scheduler(): void {
		if ( \function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$bundled = $this->plugin_dir() . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
		if ( \file_exists( $bundled ) ) {
			require_once $bundled;
		}
	}

	public static function reset_for_tests(): void {
		self::$instance = null;
	}
}
