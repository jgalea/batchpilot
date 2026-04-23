<?php
namespace ContentOps\Tests\Integration\Admin;

use ContentOps\Admin\AdminMenu;
use ContentOps\Tests\Integration\TestCase;

final class AdminMenuTest extends TestCase {

	public function test_registers_top_level_menu_and_four_submenus(): void {
		global $menu, $submenu;
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$menu    = [];
		$submenu = [];
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$admin = new AdminMenu();
		$admin->register_menus();

		$top = array_column( $menu, 2 );
		$this->assertContains( 'content-ops', $top );
		$this->assertArrayHasKey( 'content-ops', $submenu );

		$slugs = array_column( $submenu['content-ops'], 2 );
		$this->assertSame(
			[ 'content-ops', 'content-ops-operations', 'content-ops-history', 'content-ops-settings' ],
			$slugs
		);
	}

	public function test_render_outputs_page_slug_root_div(): void {
		$admin = new AdminMenu();
		ob_start();
		$admin->render_page( 'dashboard' );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'id="content-ops-dashboard-root"', $html );
		$this->assertStringContainsString( 'class="content-ops-app"', $html );
	}
}
