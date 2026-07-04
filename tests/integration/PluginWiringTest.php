<?php
namespace BatchPilot\Tests\Integration;

use BatchPilot\Plugin;

final class PluginWiringTest extends TestCase {

	public function test_registries_register_expected_entries(): void {
		$plugin = Plugin::instance();

		$targets    = $plugin->get( 'target.registry' );
		$operations = $plugin->get( 'operation.registry' );

		$this->assertInstanceOf( \BatchPilot\Registry\TargetRegistry::class, $targets );
		$this->assertInstanceOf( \BatchPilot\Registry\OperationRegistry::class, $operations );

		$this->assertTrue( $targets->has( 'post' ) );
		$this->assertTrue( $targets->has( 'page' ) );
		$this->assertTrue( $operations->has( 'delete' ) );
		$this->assertTrue( $operations->has( 'duplicate' ) );
		$this->assertTrue( $operations->has( 'edit' ) );
	}

	public function test_execution_service_is_registered(): void {
		$this->assertInstanceOf( \BatchPilot\Execution\ExecutionService::class, Plugin::instance()->get( 'execution.service' ) );
	}

	public function test_register_targets_hook_fired_during_boot(): void {
		$this->assertGreaterThan(
			0,
			did_action( 'batchpilot_register_targets' ),
			'Pro / third-party plugins rely on this hook to add Targets. It must fire during plugin boot.'
		);
	}

	public function test_register_operations_hook_fired_during_boot(): void {
		$this->assertGreaterThan(
			0,
			did_action( 'batchpilot_register_operations' ),
			'Pro / third-party plugins rely on this hook to add Operations. It must fire during plugin boot.'
		);
	}

	public function test_schema_migration_self_heals_on_admin_init(): void {
		// register_activation_hook()'s callback never fires on an in-place update (auto-
		// update, or the dashboard "Update" button while the plugin stays active) — only
		// on an explicit deactivate-then-reactivate. Without this admin_init hook, a
		// schema change shipped in a later version would never reach an already-running
		// site that simply auto-updates.
		$this->assertNotFalse(
			has_action( 'admin_init', [ \BatchPilot\Database\Migrations::class, 'maybe_migrate' ] ),
			'Migrations::maybe_migrate() must be hooked on admin_init, not only on plugin activation.'
		);
	}

	public function test_register_targets_hook_passes_the_live_registry(): void {
		$captured = null;
		add_action(
			'batchpilot_register_targets',
			static function ( $registry ) use ( &$captured ): void {
				$captured = $registry;
			},
			20
		);

		// Re-trigger on the same singleton — Plugin::on_plugins_loaded() rebuilds
		// the registries each call, so the hook fires with the fresh instance.
		Plugin::instance()->on_plugins_loaded();

		$this->assertInstanceOf( \BatchPilot\Registry\TargetRegistry::class, $captured );
		$this->assertSame(
			Plugin::instance()->get( 'target.registry' ),
			$captured,
			'The hook payload must be the same registry instance the plugin stores, otherwise Pro registrations would be lost.'
		);
	}
}
