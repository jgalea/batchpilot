<?php
namespace BatchPilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?self $instance = null;

	private string $plugin_file;

	/** @var array<string, object> */
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
		\add_action( 'admin_init', [ \BatchPilot\Capabilities\Capabilities::class, 'ensure_granted' ] );
	}

	public function on_plugins_loaded(): void {
		$this->load_action_scheduler();

		global $wpdb;

		$action_scheduler_bridge = new \BatchPilot\Async\ActionSchedulerBridge();
		$this->set( 'async.action_scheduler', $action_scheduler_bridge );

		$token_generator = new \BatchPilot\PreviewToken\TokenGenerator( (string) wp_salt() );
		$token_store     = new \BatchPilot\PreviewToken\TokenStore();
		$token_verifier  = new \BatchPilot\PreviewToken\TokenVerifier( $token_generator, $token_store );
		$operations_repo = new \BatchPilot\History\OperationRepository( $wpdb );
		$snapshots_repo  = new \BatchPilot\History\SnapshotRepository( $wpdb );
		$this->set( 'preview.token_generator', $token_generator );
		$this->set( 'preview.token_store', $token_store );
		$this->set( 'preview.token_verifier', $token_verifier );
		$this->set( 'history.operations', $operations_repo );
		$this->set( 'history.snapshots', $snapshots_repo );

		$target_registry    = new \BatchPilot\Registry\TargetRegistry();
		$operation_registry = new \BatchPilot\Registry\OperationRegistry();

		$post_types = \apply_filters( 'batchpilot_post_types', [ 'post', 'page' ] );
		foreach ( (array) $post_types as $post_type ) {
			$target_registry->register( new \BatchPilot\Targets\PostTarget( (string) $post_type ) );
		}

		$operation_registry->register( new \BatchPilot\Operations\DeleteOperation( $token_generator, $token_store, $operations_repo ) );
		$operation_registry->register( new \BatchPilot\Operations\DuplicateOperation( $token_generator, $token_store, $operations_repo ) );
		$operation_registry->register( new \BatchPilot\Operations\BulkEditOperation( $token_generator, $token_store, $operations_repo, $snapshots_repo ) );

		/**
		 * Fires after the built-in targets are registered, before ExecutionService is wired.
		 * Third-party plugins (incl. BatchPilot Pro) register additional Targets here.
		 *
		 * @param \BatchPilot\Registry\TargetRegistry        $target_registry
		 * @param \BatchPilot\PreviewToken\TokenGenerator    $token_generator
		 * @param \BatchPilot\PreviewToken\TokenStore        $token_store
		 * @param \BatchPilot\History\OperationRepository    $operations_repo
		 * @param \BatchPilot\History\SnapshotRepository     $snapshots_repo
		 */
		\do_action( 'batchpilot_register_targets', $target_registry, $token_generator, $token_store, $operations_repo, $snapshots_repo );

		/**
		 * Fires after the built-in operations are registered, before ExecutionService is wired.
		 * Third-party plugins register additional Operations here. The token generator/store
		 * and history repositories are passed so Pro operations can share the same plumbing.
		 *
		 * @param \BatchPilot\Registry\OperationRegistry     $operation_registry
		 * @param \BatchPilot\PreviewToken\TokenGenerator    $token_generator
		 * @param \BatchPilot\PreviewToken\TokenStore        $token_store
		 * @param \BatchPilot\History\OperationRepository    $operations_repo
		 * @param \BatchPilot\History\SnapshotRepository     $snapshots_repo
		 */
		\do_action( 'batchpilot_register_operations', $operation_registry, $token_generator, $token_store, $operations_repo, $snapshots_repo );

		$this->set( 'target.registry', $target_registry );
		$this->set( 'operation.registry', $operation_registry );

		$execution = new \BatchPilot\Execution\ExecutionService(
			$target_registry,
			$operation_registry,
			$operations_repo,
			$snapshots_repo,
			$token_generator,
			$token_store
		);
		$this->set( 'execution.service', $execution );

		$runner = new \BatchPilot\Execution\OperationRunner( $execution );
		$runner->register();
		$this->set( 'execution.runner', $runner );

		$settings = new \BatchPilot\Admin\Settings();
		$settings->register();
		$this->set( 'admin.settings', $settings );

		$rest_registrar = new \BatchPilot\REST\RouteRegistrar( $action_scheduler_bridge, $execution, $target_registry, $operation_registry, $operations_repo, $token_verifier, $token_store, $settings );
		$rest_registrar->register();
		$this->set( 'rest.registrar', $rest_registrar );

		$cli_registrar = new \BatchPilot\CLI\CommandRegistrar( $action_scheduler_bridge, $execution, $target_registry, $operation_registry, $operations_repo );
		$cli_registrar->register();
		$this->set( 'cli.registrar', $cli_registrar );

		$abilities_bridge = new \BatchPilot\Abilities\AbilitiesBridge( $action_scheduler_bridge, $execution, $target_registry, $operation_registry );
		$abilities_bridge->register();
		$this->set( 'abilities.bridge', $abilities_bridge );

		$preset_catalog = new \BatchPilot\Presets\PresetCatalog();
		$this->set( 'preset.catalog', $preset_catalog );
		\add_filter(
			'batchpilot_presets',
			static fn ( array $presets ) => array_merge( $presets, $preset_catalog->all() )
		);

		$admin_menu = new \BatchPilot\Admin\AdminMenu();
		$admin_menu->register();
		$this->set( 'admin.menu', $admin_menu );

		$asset_loader = new \BatchPilot\Admin\AssetLoader( $this->plugin_file );
		$asset_loader->register();
		$this->set( 'admin.assets', $asset_loader );

		$post_list_integration = new \BatchPilot\Admin\PostListIntegration(
			admin_url( 'admin.php?page=batchpilot-operations' )
		);
		$post_list_integration->register();
		$this->set( 'admin.post_list', $post_list_integration );

		\do_action( 'batchpilot_booted', $this );
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
