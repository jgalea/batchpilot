<?php
namespace ContentOps\Tests\Unit\Presets;

use ContentOps\Presets\PresetCatalog;
use ContentOps\Tests\Unit\TestCase;

final class PresetCatalogTest extends TestCase {

	public function test_all_returns_built_in_presets(): void {
		$catalog = new PresetCatalog();
		$slugs   = array_map( static fn ( $p ) => $p['slug'], $catalog->all() );

		$this->assertContains( 'trash-old-drafts', $slugs );
		$this->assertContains( 'trash-auto-drafts', $slugs );
	}

	public function test_preset_shape_has_required_keys(): void {
		foreach ( ( new PresetCatalog() )->all() as $preset ) {
			$this->assertArrayHasKey( 'slug', $preset );
			$this->assertArrayHasKey( 'label', $preset );
			$this->assertArrayHasKey( 'target', $preset );
			$this->assertArrayHasKey( 'operation', $preset );
			$this->assertArrayHasKey( 'filters', $preset );
			$this->assertArrayHasKey( 'params', $preset );
		}
	}
}
