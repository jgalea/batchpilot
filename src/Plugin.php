<?php
namespace ContentOps;

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
	}

	public function on_plugins_loaded(): void {
		\load_plugin_textdomain( 'content-ops', false, dirname( \plugin_basename( $this->plugin_file ) ) . '/languages' );
		$this->load_action_scheduler();

		global $wpdb;

		$action_scheduler_bridge = new \ContentOps\Async\ActionSchedulerBridge();
		$this->set( 'async.action_scheduler', $action_scheduler_bridge );

		$token_generator = new \ContentOps\PreviewToken\TokenGenerator( (string) wp_salt() );
		$token_store     = new \ContentOps\PreviewToken\TokenStore();
		$token_verifier  = new \ContentOps\PreviewToken\TokenVerifier( $token_generator, $token_store );
		$operations_repo = new \ContentOps\History\OperationRepository( $wpdb );
		$snapshots_repo  = new \ContentOps\History\SnapshotRepository( $wpdb );
		$this->set( 'preview.token_generator', $token_generator );
		$this->set( 'preview.token_store', $token_store );
		$this->set( 'preview.token_verifier', $token_verifier );
		$this->set( 'history.operations', $operations_repo );
		$this->set( 'history.snapshots', $snapshots_repo );

		$target_registry    = new \ContentOps\Registry\TargetRegistry();
		$operation_registry = new \ContentOps\Registry\OperationRegistry();

		$post_types = \apply_filters( 'content_ops_post_types', [ 'post', 'page' ] );
		foreach ( (array) $post_types as $post_type ) {
			$target_registry->register( new \ContentOps\Targets\PostTarget( (string) $post_type ) );
		}

		$operation_registry->register( new \ContentOps\Operations\DeleteOperation( $token_generator, $token_store, $operations_repo ) );
		$operation_registry->register( new \ContentOps\Operations\DuplicateOperation( $token_generator, $token_store, $operations_repo ) );
		$operation_registry->register( new \ContentOps\Operations\BulkEditOperation( $token_generator, $token_store, $operations_repo, $snapshots_repo ) );

		$this->set( 'target.registry', $target_registry );
		$this->set( 'operation.registry', $operation_registry );

		$execution = new \ContentOps\Execution\ExecutionService(
			$target_registry,
			$operation_registry,
			$operations_repo,
			$snapshots_repo,
			$token_generator,
			$token_store
		);
		$this->set( 'execution.service', $execution );

		$runner = new \ContentOps\Execution\OperationRunner( $execution );
		$runner->register();
		$this->set( 'execution.runner', $runner );

		$rest_registrar = new \ContentOps\REST\RouteRegistrar( $action_scheduler_bridge, $execution, $target_registry, $operation_registry, $operations_repo, $token_verifier, $token_store );
		$rest_registrar->register();
		$this->set( 'rest.registrar', $rest_registrar );

		$cli_registrar = new \ContentOps\CLI\CommandRegistrar( $action_scheduler_bridge, $execution, $target_registry, $operation_registry, $operations_repo );
		$cli_registrar->register();
		$this->set( 'cli.registrar', $cli_registrar );

		$abilities_bridge = new \ContentOps\Abilities\AbilitiesBridge( $action_scheduler_bridge, $execution, $target_registry, $operation_registry );
		$abilities_bridge->register();
		$this->set( 'abilities.bridge', $abilities_bridge );

		$preset_catalog = new \ContentOps\Presets\PresetCatalog();
		$this->set( 'preset.catalog', $preset_catalog );
		\add_filter(
			'content_ops_presets',
			static fn ( array $presets ) => array_merge( $presets, $preset_catalog->all() )
		);

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
