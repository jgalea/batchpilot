# Phase 1b — Admin UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a polished React-based admin UI on top of the Phase 1a backend so an administrator can run Delete / Duplicate / BulkEdit via a Content Ops admin menu without touching WP-CLI or raw REST.

**Architecture:** PHP registers a top-level "Content Ops" menu with four submenus (Dashboard, Operations, History, Settings), each rendering a page-slug-specific root `<div>` and enqueueing a single webpack bundle. The bundle's entry point detects which root is mounted and renders the corresponding React page, talking to the existing `content-ops/v1` REST namespace via `@wordpress/api-fetch`. Settings persist through a new `GET`/`POST /settings` endpoint (single `content_ops_settings` option). Preview responses are extended with a `display_rows` array so sample UI can render titles, statuses, dates, and edit links without a second round-trip. Row actions + bulk actions on native post list tables link (query-string-prefilled) into the Operations Builder.

**Tech Stack:** PHP 7.4+, WordPress 6.3+, `@wordpress/scripts` 30.x, `@wordpress/element` (React 18), `@wordpress/components`, `@wordpress/api-fetch`, `@wordpress/i18n`, Jest + React Testing Library via wp-scripts, `@playwright/test` + `@wordpress/e2e-test-utils-playwright` for E2E.

---

## Resolved architectural decisions

1. **Single webpack entry.** `assets/src/admin/index.js` detects which `#content-ops-{slug}-root` is present in the DOM and mounts the corresponding page component. One bundle, one build artifact.
2. **Settings storage.** Single WP option `content_ops_settings` (serialized associative array). REST read at `GET /content-ops/v1/settings` and write at `POST /content-ops/v1/settings`, both requiring `manage_options`. Defaults merged server-side; unknown keys rejected.
3. **Live preview debounce.** 300 ms debounce on filter/params changes. In-flight previews are aborted via `AbortController` when a newer request supersedes them.
4. **Preview sample display.** `POST /preview` response is extended with a `display_rows` field — an array the same length as `sample_ids` where each entry is the `get_display()` result (id, title, status, date, edit_url, thumbnail_url). No second endpoint.
5. **Operations Builder match mode.** Phase 1b is "All filters must match" only. "Any" is Phase 2.
6. **E2E testing framework.** `@wordpress/e2e-test-utils-playwright` with `@playwright/test`. Tests live under `tests/e2e/`.
7. **Menu icon.** `dashicons-list-view`. Custom SVG is Phase 2 polish.
8. **Row actions / bulk actions on post lists.** Phase 1b ships as query-string-prefilled deep links into Operations Builder in a new tab. No in-place modals — those are Phase 2.

---

## File structure

### New PHP files
- `src/Admin/AdminMenu.php` — registers top-level menu + submenus, renders root `<div>`s.
- `src/Admin/AssetLoader.php` — enqueues `assets/build/admin.js` + localizes bootstrap data on Content Ops pages.
- `src/Admin/Settings.php` — schema, defaults, read/write, sanitization of `content_ops_settings`.
- `src/REST/SettingsController.php` — `GET`/`POST /content-ops/v1/settings`.
- `src/Admin/PostListIntegration.php` — hooks into `{post_type}_row_actions` and `bulk_actions-{screen}` to add deep-link entries.

### Modified PHP files
- `src/Plugin.php` — wire `AdminMenu`, `AssetLoader`, `Settings`, `SettingsController`, `PostListIntegration`.
- `src/REST/RouteRegistrar.php` — register the settings route; inject targets into PreviewController.
- `src/REST/PreviewController.php` — append `display_rows` from target.

### New JS files (under `assets/src/admin/`)
- `api.js` — thin wrapper around `apiFetch` with typed helpers + AbortController plumbing + normalized errors.
- `bootstrap.js` — reads `window.contentOpsAdmin` and exposes getters.
- `router.js` — detects the mounted root id, returns `{ page, mount }`.
- `pages/Dashboard.jsx`, `pages/OperationsBuilder.jsx`, `pages/History.jsx`, `pages/Settings.jsx`
- `components/HealthPanel.jsx`, `components/StatsCard.jsx`, `components/RecentOperationsList.jsx`, `components/PresetCards.jsx`, `components/TargetPicker.jsx`, `components/FilterRow.jsx`, `components/FilterList.jsx`, `components/OperationPicker.jsx`, `components/OperationParamsForm.jsx`, `components/PreviewPanel.jsx`, `components/ExecuteButton.jsx`, `components/ExecutionResult.jsx`, `components/HistoryTable.jsx`, `components/OperationDetailsModal.jsx`, `components/SettingsForm.jsx`
- `hooks/useDebouncedPreview.js`, `hooks/useCatalog.js`
- `state/builderReducer.js`, `state/builderContext.js`
- `styles.scss`
- `__tests__/*.test.js` — Jest + RTL tests per component.

### E2E
- `tests/e2e/playwright.config.ts`
- `tests/e2e/operations-builder.spec.ts`
- `tests/e2e/history-undo.spec.ts`
- `tests/e2e/fixtures.ts`

---

## Tasks

### Task 1: Admin menu + page shells (PHP)

**Files:**
- Create: `src/Admin/AdminMenu.php`
- Modify: `src/Plugin.php`
- Test: `tests/integration/Admin/AdminMenuTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace ContentOps\Tests\Integration\Admin;

use ContentOps\Admin\AdminMenu;
use ContentOps\Tests\Integration\TestCase;

final class AdminMenuTest extends TestCase {

	public function test_registers_top_level_menu_and_four_submenus(): void {
		global $menu, $submenu;
		$menu    = [];
		$submenu = [];

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test:integration -- --filter=AdminMenuTest`
Expected: FAIL with `Class "ContentOps\Admin\AdminMenu" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
namespace ContentOps\Admin;

final class AdminMenu {

	public const PARENT_SLUG = 'content-ops';

	public const PAGES = [
		'dashboard'  => [ 'slug' => 'content-ops',            'title_key' => 'Dashboard' ],
		'operations' => [ 'slug' => 'content-ops-operations', 'title_key' => 'Operations' ],
		'history'    => [ 'slug' => 'content-ops-history',    'title_key' => 'History' ],
		'settings'   => [ 'slug' => 'content-ops-settings',   'title_key' => 'Settings' ],
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
				__( $info['title_key'], 'content-ops' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				__( $info['title_key'], 'content-ops' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
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
			'<div class="wrap content-ops-app"><div id="content-ops-%1$s-root"></div></div>',
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
```

Modify `src/Plugin.php` inside `on_plugins_loaded()`, add before `do_action( 'content_ops_booted', ... )`:

```php
$admin_menu = new \ContentOps\Admin\AdminMenu();
$admin_menu->register();
$this->set( 'admin.menu', $admin_menu );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test:integration -- --filter=AdminMenuTest`
Expected: PASS (2 tests / ≥4 assertions).

- [ ] **Step 5: Commit**

```bash
git add src/Admin/AdminMenu.php src/Plugin.php tests/integration/Admin/AdminMenuTest.php
git commit -m "feat: register Content Ops admin menu + submenus"
```

---

### Task 2: Enqueue admin bundle + bootstrap localized data

**Files:**
- Create: `src/Admin/AssetLoader.php`
- Modify: `src/Plugin.php`
- Test: `tests/integration/Admin/AssetLoaderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace ContentOps\Tests\Integration\Admin;

use ContentOps\Admin\AssetLoader;
use ContentOps\Tests\Integration\TestCase;

final class AssetLoaderTest extends TestCase {

	public function test_enqueues_admin_script_only_on_content_ops_pages(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$loader = new AssetLoader( CONTENT_OPS_PLUGIN_FILE );
		$loader->enqueue( 'toplevel_page_content-ops' );
		$this->assertTrue( wp_script_is( 'content-ops-admin', 'enqueued' ) );

		wp_dequeue_script( 'content-ops-admin' );
		wp_deregister_script( 'content-ops-admin' );

		$loader->enqueue( 'edit.php' );
		$this->assertFalse( wp_script_is( 'content-ops-admin', 'enqueued' ) );
	}

	public function test_localizes_bootstrap_payload_with_rest_url_nonce_and_caps(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$loader = new AssetLoader( CONTENT_OPS_PLUGIN_FILE );
		$loader->enqueue( 'toplevel_page_content-ops' );

		$data = wp_scripts()->get_data( 'content-ops-admin', 'data' );
		$this->assertIsString( $data );
		$this->assertStringContainsString( 'window.contentOpsAdmin', (string) $data );
		$this->assertStringContainsString( '"namespace":"content-ops/v1"', (string) $data );
		$this->assertStringContainsString( '"nonce":"', (string) $data );
		$this->assertStringContainsString( '"capabilities":{', (string) $data );
		$this->assertStringContainsString( '"content_ops_delete"', (string) $data );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test:integration -- --filter=AssetLoaderTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
namespace ContentOps\Admin;

use ContentOps\Capabilities\Capabilities;

final class AssetLoader {

	public const HANDLE = 'content-ops-admin';

	private string $plugin_file;

	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue( string $hook_suffix ): void {
		if ( ! $this->is_content_ops_page( $hook_suffix ) ) {
			return;
		}

		$plugin_dir = plugin_dir_path( $this->plugin_file );
		$plugin_url = plugin_dir_url( $this->plugin_file );
		$asset_path = $plugin_dir . 'assets/build/admin.asset.php';
		$asset      = file_exists( $asset_path )
			? require $asset_path
			: [ 'dependencies' => [ 'wp-element' ], 'version' => CONTENT_OPS_VERSION ];

		wp_enqueue_script(
			self::HANDLE,
			$plugin_url . 'assets/build/admin.js',
			(array) $asset['dependencies'],
			(string) $asset['version'],
			true
		);

		wp_set_script_translations( self::HANDLE, 'content-ops' );

		wp_add_inline_script(
			self::HANDLE,
			'window.contentOpsAdmin = ' . wp_json_encode( $this->bootstrap_payload() ) . ';',
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
			'namespace'    => 'content-ops/v1',
			'restUrl'      => esc_url_raw( rest_url( 'content-ops/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'capabilities' => $caps,
			'adminUrl'     => admin_url(),
			'pluginUrl'    => plugin_dir_url( $this->plugin_file ),
			'version'      => CONTENT_OPS_VERSION,
			'pages'        => [
				'operations' => admin_url( 'admin.php?page=content-ops-operations' ),
				'history'    => admin_url( 'admin.php?page=content-ops-history' ),
				'dashboard'  => admin_url( 'admin.php?page=content-ops' ),
				'settings'   => admin_url( 'admin.php?page=content-ops-settings' ),
			],
		];
	}

	private function is_content_ops_page( string $hook_suffix ): bool {
		return in_array( $hook_suffix, AdminMenu::page_hook_suffixes(), true );
	}
}
```

In `src/Plugin.php` (after `AdminMenu` wiring):

```php
$asset_loader = new \ContentOps\Admin\AssetLoader( $this->plugin_file );
$asset_loader->register();
$this->set( 'admin.assets', $asset_loader );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test:integration -- --filter=AssetLoaderTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Admin/AssetLoader.php src/Plugin.php tests/integration/Admin/AssetLoaderTest.php
git commit -m "feat: enqueue admin bundle with REST bootstrap payload"
```

---

### Task 3: React entry point + page router

**Files:**
- Modify: `assets/src/admin/index.js`
- Create: `assets/src/admin/bootstrap.js`
- Create: `assets/src/admin/router.js`
- Create: `assets/src/admin/__tests__/router.test.js`
- Create: `assets/src/admin/__tests__/bootstrap.test.js`
- Create: `assets/src/admin/pages/Dashboard.jsx`
- Create: `assets/src/admin/pages/OperationsBuilder.jsx`
- Create: `assets/src/admin/pages/History.jsx`
- Create: `assets/src/admin/pages/Settings.jsx`
- Create: `assets/src/admin/styles.scss`

- [ ] **Step 1: Write the failing tests**

```js
// assets/src/admin/__tests__/router.test.js
import { detectPage } from '../router';

const clearBody = () => {
	while ( document.body.firstChild ) {
		document.body.removeChild( document.body.firstChild );
	}
};

const mountRoot = ( id ) => {
	const div = document.createElement( 'div' );
	div.id = id;
	document.body.appendChild( div );
};

describe( 'detectPage', () => {
	beforeEach( clearBody );

	it( 'returns dashboard page when dashboard root is present', () => {
		mountRoot( 'content-ops-dashboard-root' );
		const result = detectPage( document );
		expect( result.page ).toBe( 'dashboard' );
		expect( result.mount ).toBeInstanceOf( HTMLElement );
	} );

	it( 'returns operations page when operations root is present', () => {
		mountRoot( 'content-ops-operations-root' );
		expect( detectPage( document ).page ).toBe( 'operations' );
	} );

	it( 'returns history page when history root is present', () => {
		mountRoot( 'content-ops-history-root' );
		expect( detectPage( document ).page ).toBe( 'history' );
	} );

	it( 'returns settings page when settings root is present', () => {
		mountRoot( 'content-ops-settings-root' );
		expect( detectPage( document ).page ).toBe( 'settings' );
	} );

	it( 'returns null when no root is present', () => {
		expect( detectPage( document ) ).toBeNull();
	} );
} );
```

```js
// assets/src/admin/__tests__/bootstrap.test.js
import { getBootstrap } from '../bootstrap';

describe( 'getBootstrap', () => {
	afterEach( () => {
		delete global.window.contentOpsAdmin;
	} );

	it( 'returns window.contentOpsAdmin when present', () => {
		global.window.contentOpsAdmin = {
			namespace: 'content-ops/v1',
			restUrl: 'http://example.test/wp-json/content-ops/v1/',
			nonce: 'abc',
			capabilities: { manage_options: true, content_ops_delete: true },
			pages: {},
		};
		expect( getBootstrap().nonce ).toBe( 'abc' );
	} );

	it( 'throws when bootstrap missing', () => {
		expect( () => getBootstrap() ).toThrow( /contentOpsAdmin/ );
	} );
} );
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm run test:js -- --testPathPattern=admin/__tests__`
Expected: FAIL — modules not found.

- [ ] **Step 3: Write minimal implementation**

```js
// assets/src/admin/bootstrap.js
export const getBootstrap = () => {
	const data = ( typeof window !== 'undefined' ) ? window.contentOpsAdmin : undefined;
	if ( ! data || ! data.restUrl || ! data.nonce ) {
		throw new Error( 'contentOpsAdmin bootstrap payload missing' );
	}
	return data;
};
```

```js
// assets/src/admin/router.js
const PAGE_IDS = {
	dashboard:  'content-ops-dashboard-root',
	operations: 'content-ops-operations-root',
	history:    'content-ops-history-root',
	settings:   'content-ops-settings-root',
};

export const detectPage = ( doc = document ) => {
	for ( const [ page, id ] of Object.entries( PAGE_IDS ) ) {
		const mount = doc.getElementById( id );
		if ( mount ) {
			return { page, mount };
		}
	}
	return null;
};
```

```js
// assets/src/admin/index.js
import { createElement, render } from '@wordpress/element';
import { detectPage } from './router';
import Dashboard from './pages/Dashboard';
import OperationsBuilder from './pages/OperationsBuilder';
import History from './pages/History';
import Settings from './pages/Settings';
import './styles.scss';

const PAGES = {
	dashboard:  Dashboard,
	operations: OperationsBuilder,
	history:    History,
	settings:   Settings,
};

const mount = () => {
	const hit = detectPage( document );
	if ( ! hit ) {
		return;
	}
	const Component = PAGES[ hit.page ];
	if ( ! Component ) {
		return;
	}
	render( createElement( Component ), hit.mount );
};

if ( document.readyState !== 'loading' ) {
	mount();
} else {
	document.addEventListener( 'DOMContentLoaded', mount );
}
```

Create placeholder page components so the entry compiles:

```jsx
// assets/src/admin/pages/Dashboard.jsx
import { __ } from '@wordpress/i18n';
const Dashboard = () => <div><h1>{ __( 'Dashboard', 'content-ops' ) }</h1></div>;
export default Dashboard;
```

```jsx
// assets/src/admin/pages/OperationsBuilder.jsx
import { __ } from '@wordpress/i18n';
const OperationsBuilder = () => <div><h1>{ __( 'Operations', 'content-ops' ) }</h1></div>;
export default OperationsBuilder;
```

```jsx
// assets/src/admin/pages/History.jsx
import { __ } from '@wordpress/i18n';
const History = () => <div><h1>{ __( 'History', 'content-ops' ) }</h1></div>;
export default History;
```

```jsx
// assets/src/admin/pages/Settings.jsx
import { __ } from '@wordpress/i18n';
const Settings = () => <div><h1>{ __( 'Settings', 'content-ops' ) }</h1></div>;
export default Settings;
```

Create `assets/src/admin/styles.scss`:

```scss
.content-ops-app {
	max-width: 1200px;
}
```

- [ ] **Step 4: Run tests + build to verify they pass**

Run: `npm run test:js -- --testPathPattern=admin/__tests__`
Expected: PASS.

Run: `npm run build`
Expected: webpack succeeds; `assets/build/admin.js` updates.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/
git commit -m "feat: admin bundle entry with page router + placeholder pages"
```

---

### Task 4: apiFetch wrapper with AbortController + normalized errors

**Files:**
- Create: `assets/src/admin/api.js`
- Create: `assets/src/admin/__tests__/api.test.js`

- [ ] **Step 1: Write the failing test**

```js
import apiFetch from '@wordpress/api-fetch';
import { createApi, normalizeError } from '../api';

jest.mock( '@wordpress/api-fetch' );

describe( 'api', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it( 'fetches catalog via GET /catalog', async () => {
		apiFetch.mockResolvedValue( { targets: [], operations: [], presets: [] } );
		const api = createApi();
		const result = await api.fetchCatalog();
		expect( apiFetch ).toHaveBeenCalledWith( expect.objectContaining( {
			path: '/content-ops/v1/catalog',
			method: 'GET',
		} ) );
		expect( result.targets ).toEqual( [] );
	} );

	it( 'sends preview body and forwards signal for aborting', async () => {
		apiFetch.mockResolvedValue( { count: 3, sample_ids: [ 1, 2, 3 ], preview_token: 'tok', warnings: [], display_rows: [] } );
		const api = createApi();
		const controller = new AbortController();
		await api.preview( {
			target: 'post',
			operation: 'delete',
			filters: { status: [ 'draft' ] },
			params: {},
		}, controller.signal );

		expect( apiFetch ).toHaveBeenCalledWith( expect.objectContaining( {
			path: '/content-ops/v1/preview',
			method: 'POST',
			signal: controller.signal,
			data: {
				target: 'post',
				operation: 'delete',
				filters: { status: [ 'draft' ] },
				params: {},
			},
		} ) );
	} );

	it( 'normalizes errors with a code, message, and context', () => {
		const err = normalizeError( {
			code: 'co.preview.stale_token',
			message: 'Preview token invalid or expired.',
			data: { status: 409 },
		} );
		expect( err ).toEqual( {
			code:    'co.preview.stale_token',
			message: 'Preview token invalid or expired.',
			status:  409,
			context: {},
		} );
	} );

	it( 'handles DOMException AbortError as aborted=true', () => {
		const abortErr = new DOMException( 'aborted', 'AbortError' );
		expect( normalizeError( abortErr ).aborted ).toBe( true );
	} );
} );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:js -- --testPathPattern=admin/__tests__/api`
Expected: FAIL — module missing.

- [ ] **Step 3: Write minimal implementation**

```js
// assets/src/admin/api.js
import apiFetch from '@wordpress/api-fetch';

export const normalizeError = ( err ) => {
	if ( err && err.name === 'AbortError' ) {
		return { code: 'co.client.aborted', message: 'Request aborted.', status: 0, context: {}, aborted: true };
	}
	if ( err && err.code ) {
		return {
			code:    err.code,
			message: err.message || '',
			status:  ( err.data && err.data.status ) || 0,
			context: err.data && err.data.context ? err.data.context : {},
		};
	}
	return { code: 'co.client.unknown', message: ( err && err.message ) || String( err ), status: 0, context: {} };
};

export const createApi = ( fetchFn = apiFetch ) => {
	const call = ( path, method, data, signal ) =>
		fetchFn( {
			path: `/content-ops/v1${ path }`,
			method,
			...( data !== undefined ? { data } : {} ),
			...( signal ? { signal } : {} ),
		} );

	return {
		fetchCatalog: ( signal ) => call( '/catalog', 'GET', undefined, signal ),
		preview:      ( body, signal ) => call( '/preview', 'POST', body, signal ),
		execute:      ( body, signal ) => call( '/execute', 'POST', body, signal ),
		listOperations: ( { limit = 20, offset = 0 } = {}, signal ) =>
			call( `/operations?limit=${ limit }&offset=${ offset }`, 'GET', undefined, signal ),
		getOperation: ( id, signal ) => call( `/operations/${ id }`, 'GET', undefined, signal ),
		undoOperation: ( id, signal ) => call( `/operations/${ id }/undo`, 'POST', undefined, signal ),
		fetchDoctor:  ( signal ) => call( '/doctor', 'GET', undefined, signal ),
		getSettings:  ( signal ) => call( '/settings', 'GET', undefined, signal ),
		saveSettings: ( body, signal ) => call( '/settings', 'POST', body, signal ),
	};
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:js -- --testPathPattern=admin/__tests__/api`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/api.js assets/src/admin/__tests__/api.test.js
git commit -m "feat: apiFetch wrapper with AbortController + normalized errors"
```

---

### Task 5: Settings — PHP option store + REST endpoints

**Files:**
- Create: `src/Admin/Settings.php`
- Create: `src/REST/SettingsController.php`
- Modify: `src/REST/RouteRegistrar.php`
- Modify: `src/Plugin.php`
- Test: `tests/integration/Admin/SettingsTest.php`
- Test: `tests/integration/REST/SettingsRouteTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/integration/Admin/SettingsTest.php
namespace ContentOps\Tests\Integration\Admin;

use ContentOps\Admin\Settings;
use ContentOps\Tests\Integration\TestCase;

final class SettingsTest extends TestCase {

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	public function test_defaults_when_option_missing(): void {
		$settings = new Settings();
		$this->assertSame(
			[
				'async_threshold'          => 100,
				'batch_size'               => 50,
				'delete_permanent_default' => false,
				'history_retention_days'   => 30,
				'role_caps'                => [],
			],
			$settings->get_all()
		);
	}

	public function test_save_sanitizes_and_persists(): void {
		$settings = new Settings();
		$settings->save(
			[
				'async_threshold'          => '250',
				'batch_size'               => '75',
				'delete_permanent_default' => '1',
				'history_retention_days'   => '45',
				'role_caps'                => [ 'editor' => [ 'content_ops_delete' => true ] ],
				'unknown_key'              => 'ignored',
			]
		);
		$saved = $settings->get_all();
		$this->assertSame( 250, $saved['async_threshold'] );
		$this->assertSame( 75, $saved['batch_size'] );
		$this->assertTrue( $saved['delete_permanent_default'] );
		$this->assertSame( 45, $saved['history_retention_days'] );
		$this->assertArrayHasKey( 'editor', $saved['role_caps'] );
		$this->assertArrayNotHasKey( 'unknown_key', $saved );
	}

	public function test_async_threshold_filter_consumes_setting(): void {
		$settings = new Settings();
		$settings->register();
		$settings->save( [ 'async_threshold' => 500 ] );
		$this->assertSame( 500, (int) apply_filters( 'content_ops_async_threshold', 100 ) );
	}
}
```

```php
<?php
// tests/integration/REST/SettingsRouteTest.php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Admin\Settings;
use ContentOps\Tests\Integration\TestCase;

final class SettingsRouteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		do_action( 'rest_api_init' );
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	public function test_get_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/content-ops/v1/settings' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_returns_defaults(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/content-ops/v1/settings' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 100, $response->get_data()['async_threshold'] );
	}

	public function test_post_persists_changes(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request = new \WP_REST_Request( 'POST', '/content-ops/v1/settings' );
		$request->set_body_params( [ 'async_threshold' => 300 ] );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 300, $response->get_data()['async_threshold'] );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test:integration -- --filter=Settings`
Expected: FAIL — classes missing.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// src/Admin/Settings.php
namespace ContentOps\Admin;

final class Settings {

	public const OPTION = 'content_ops_settings';

	public const DEFAULTS = [
		'async_threshold'          => 100,
		'batch_size'               => 50,
		'delete_permanent_default' => false,
		'history_retention_days'   => 30,
		'role_caps'                => [],
	];

	public function register(): void {
		add_filter( 'content_ops_async_threshold', [ $this, 'filter_async_threshold' ] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_all(): array {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		return array_merge( self::DEFAULTS, $stored );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function save( array $input ): array {
		$clean  = $this->sanitize( $input );
		$merged = array_merge( $this->get_all(), $clean );
		update_option( self::OPTION, $merged );
		return $merged;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$out = [];
		if ( array_key_exists( 'async_threshold', $input ) ) {
			$out['async_threshold'] = max( 1, (int) $input['async_threshold'] );
		}
		if ( array_key_exists( 'batch_size', $input ) ) {
			$out['batch_size'] = max( 1, min( 500, (int) $input['batch_size'] ) );
		}
		if ( array_key_exists( 'delete_permanent_default', $input ) ) {
			$out['delete_permanent_default'] = (bool) $input['delete_permanent_default'];
		}
		if ( array_key_exists( 'history_retention_days', $input ) ) {
			$out['history_retention_days'] = max( 1, (int) $input['history_retention_days'] );
		}
		if ( array_key_exists( 'role_caps', $input ) && is_array( $input['role_caps'] ) ) {
			$caps = [];
			foreach ( $input['role_caps'] as $role => $cap_map ) {
				if ( ! is_array( $cap_map ) ) {
					continue;
				}
				$caps[ sanitize_key( (string) $role ) ] = array_map( 'boolval', $cap_map );
			}
			$out['role_caps'] = $caps;
		}
		return $out;
	}

	/**
	 * @param mixed $default
	 */
	public function filter_async_threshold( $default ): int {
		$all = $this->get_all();
		return (int) ( $all['async_threshold'] ?? $default );
	}
}
```

```php
<?php
// src/REST/SettingsController.php
namespace ContentOps\REST;

use ContentOps\Admin\Settings;
use WP_REST_Request;
use WP_REST_Response;

final class SettingsController extends RestController {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function check_permission() {
		return $this->require_capability( 'manage_options' );
	}

	public function handle_get( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->settings->get_all() );
	}

	public function handle_post( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		$saved = $this->settings->save( is_array( $body ) ? $body : [] );
		return new WP_REST_Response( $saved );
	}
}
```

In `src/REST/RouteRegistrar.php` add `use ContentOps\Admin\Settings;` at the top, add `private Settings $settings;` member, accept `Settings $settings` as the final constructor arg, assign it, and inside `register_routes()` add:

```php
$settings_ctrl = new SettingsController( $this->settings );
register_rest_route(
	self::REST_NAMESPACE,
	'/settings',
	[
		[
			'methods'             => 'GET',
			'callback'            => [ $settings_ctrl, 'handle_get' ],
			'permission_callback' => [ $settings_ctrl, 'check_permission' ],
		],
		[
			'methods'             => 'POST',
			'callback'            => [ $settings_ctrl, 'handle_post' ],
			'permission_callback' => [ $settings_ctrl, 'check_permission' ],
		],
	]
);
```

In `src/Plugin.php` before the `RouteRegistrar` instantiation:

```php
$settings = new \ContentOps\Admin\Settings();
$settings->register();
$this->set( 'admin.settings', $settings );
```

Pass `$settings` as the final argument to `new \ContentOps\REST\RouteRegistrar( ... )`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test:integration -- --filter=Settings`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Admin/Settings.php src/REST/SettingsController.php src/REST/RouteRegistrar.php src/Plugin.php tests/integration/
git commit -m "feat: settings option store + REST /settings endpoints"
```

---

### Task 6: Extend `POST /preview` with `display_rows`

**Files:**
- Modify: `src/REST/PreviewController.php`
- Modify: `src/REST/RouteRegistrar.php`
- Test: `tests/integration/REST/PreviewRouteTest.php` (extend)

- [ ] **Step 1: Add failing test**

Append to `tests/integration/REST/PreviewRouteTest.php`:

```php
public function test_preview_includes_display_rows_with_title_status_and_edit_url(): void {
	$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	wp_set_current_user( $user_id );

	$post_id = self::factory()->post->create(
		[
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => 'A draft to delete',
		]
	);

	$request = new \WP_REST_Request( 'POST', '/content-ops/v1/preview' );
	$request->set_body_params(
		[
			'target'    => 'post',
			'operation' => 'delete',
			'filters'   => [ 'status' => 'draft' ],
			'params'    => [],
		]
	);
	$response = rest_do_request( $request );
	$this->assertSame( 200, $response->get_status() );
	$data = $response->get_data();
	$this->assertArrayHasKey( 'display_rows', $data );
	$this->assertNotEmpty( $data['display_rows'] );
	$first = $data['display_rows'][0];
	$this->assertSame( $post_id, $first['id'] );
	$this->assertSame( 'A draft to delete', $first['title'] );
	$this->assertSame( 'draft', $first['status'] );
	$this->assertArrayHasKey( 'edit_url', $first );
	$this->assertArrayHasKey( 'thumbnail_url', $first );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test:integration -- --filter=PreviewRouteTest::test_preview_includes_display_rows_with_title_status_and_edit_url`
Expected: FAIL — `display_rows` missing.

- [ ] **Step 3: Modify `PreviewController`**

Change constructor to accept `TargetRegistry`:

```php
use ContentOps\Registry\TargetRegistry;

private ExecutionService $execution;
private TargetRegistry $targets;

public function __construct( ExecutionService $execution, TargetRegistry $targets ) {
	$this->execution = $execution;
	$this->targets   = $targets;
}
```

Replace the success `WP_REST_Response` return in `handle()`:

```php
$target_obj   = $this->targets->get( $target );
$display_rows = [];
if ( null !== $target_obj ) {
	foreach ( $result->sample_ids() as $id ) {
		$display_rows[] = $target_obj->get_display( (int) $id );
	}
}

return new WP_REST_Response(
	[
		'count'         => $result->count(),
		'sample_ids'    => $result->sample_ids(),
		'preview_token' => $result->preview_token(),
		'warnings'      => $result->warnings(),
		'display_rows'  => $display_rows,
	]
);
```

In `src/REST/RouteRegistrar.php::register_routes()`, update:

```php
$preview = new PreviewController( $this->execution, $this->targets );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test:integration -- --filter=PreviewRouteTest`
Expected: all preview tests PASS including the new one.

- [ ] **Step 5: Commit**

```bash
git add src/REST/PreviewController.php src/REST/RouteRegistrar.php tests/integration/REST/PreviewRouteTest.php
git commit -m "feat: include display_rows in /preview response"
```

---

### Task 7: Dashboard page — HealthPanel

**Files:**
- Create: `assets/src/admin/components/HealthPanel.jsx`
- Create: `assets/src/admin/__tests__/HealthPanel.test.js`
- Modify: `assets/src/admin/pages/Dashboard.jsx`

- [ ] **Step 1: Write the failing test**

```js
// assets/src/admin/__tests__/HealthPanel.test.js
import { render, screen, waitFor } from '@testing-library/react';
import HealthPanel from '../components/HealthPanel';

const makeApi = ( doctor ) => ( {
	fetchDoctor: jest.fn().mockResolvedValue( doctor ),
} );

describe( 'HealthPanel', () => {
	it( 'renders green checks for available systems', async () => {
		const api = makeApi( {
			action_scheduler: { available: true },
			abilities_api:    { available: false },
			hpos:             { available: false, enabled: false },
			tables:           { expected: [ 'x' ], missing: [] },
			cron:             { disabled: false },
			schema_version:   '1',
		} );
		render( <HealthPanel api={ api } /> );
		await waitFor( () => expect( screen.getByTestId( 'health-action_scheduler' ) ).toHaveAttribute( 'data-status', 'ok' ) );
		expect( screen.getByTestId( 'health-abilities_api' ) ).toHaveAttribute( 'data-status', 'warn' );
		expect( screen.getByTestId( 'health-hpos' ) ).toHaveAttribute( 'data-status', 'warn' );
		expect( screen.getByTestId( 'health-tables' ) ).toHaveAttribute( 'data-status', 'ok' );
		expect( screen.getByTestId( 'health-cron' ) ).toHaveAttribute( 'data-status', 'ok' );
	} );

	it( 'shows error when doctor fetch fails', async () => {
		const api = { fetchDoctor: jest.fn().mockRejectedValue( { code: 'co.internal', message: 'boom' } ) };
		render( <HealthPanel api={ api } /> );
		await waitFor( () => expect( screen.getByRole( 'alert' ) ).toHaveTextContent( /boom/ ) );
	} );
} );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:js -- --testPathPattern=HealthPanel`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

```jsx
// assets/src/admin/components/HealthPanel.jsx
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader, Spinner, Notice } from '@wordpress/components';
import { normalizeError } from '../api';

const Row = ( { id, label, status, detail } ) => (
	<li data-testid={ `health-${ id }` } data-status={ status }>
		<span className={ `content-ops-health-dot content-ops-health-dot--${ status }` } aria-hidden="true" />
		<strong>{ label }</strong>
		{ detail && <span className="content-ops-health-detail"> { detail }</span> }
	</li>
);

const HealthPanel = ( { api } ) => {
	const [ report, setReport ] = useState( null );
	const [ error, setError ]   = useState( null );

	useEffect( () => {
		let cancelled = false;
		api.fetchDoctor()
			.then( ( data ) => { if ( ! cancelled ) setReport( data ); } )
			.catch( ( e ) => { if ( ! cancelled ) setError( normalizeError( e ) ); } );
		return () => { cancelled = true; };
	}, [ api ] );

	if ( error ) {
		return <Notice status="error" isDismissible={ false } role="alert">{ error.message }</Notice>;
	}
	if ( ! report ) {
		return <Spinner />;
	}

	const items = [
		{ id: 'action_scheduler', label: __( 'Action Scheduler', 'content-ops' ),
		  status: report.action_scheduler.available ? 'ok' : 'warn' },
		{ id: 'abilities_api', label: __( 'Abilities API', 'content-ops' ),
		  status: report.abilities_api.available ? 'ok' : 'warn',
		  detail: report.abilities_api.available ? '' : __( 'Optional — install to expose MCP tools.', 'content-ops' ) },
		{ id: 'hpos', label: __( 'HPOS (WooCommerce)', 'content-ops' ),
		  status: report.hpos.enabled ? 'ok' : 'warn',
		  detail: report.hpos.available ? ( report.hpos.enabled ? '' : __( 'Available but disabled.', 'content-ops' ) ) : __( 'Not applicable.', 'content-ops' ) },
		{ id: 'tables', label: __( 'Database tables', 'content-ops' ),
		  status: report.tables.missing.length === 0 ? 'ok' : 'error',
		  detail: report.tables.missing.length === 0 ? '' : report.tables.missing.join( ', ' ) },
		{ id: 'cron', label: __( 'WP-Cron', 'content-ops' ),
		  status: report.cron.disabled ? 'warn' : 'ok',
		  detail: report.cron.disabled ? __( 'DISABLE_WP_CRON is set.', 'content-ops' ) : '' },
	];

	return (
		<Card>
			<CardHeader>{ __( 'Environment health', 'content-ops' ) }</CardHeader>
			<CardBody>
				<ul className="content-ops-health-list">
					{ items.map( ( item ) => <Row key={ item.id } { ...item } /> ) }
				</ul>
			</CardBody>
		</Card>
	);
};

export default HealthPanel;
```

Update `assets/src/admin/pages/Dashboard.jsx`:

```jsx
import { __ } from '@wordpress/i18n';
import HealthPanel from '../components/HealthPanel';
import { createApi } from '../api';

const Dashboard = ( { api = createApi() } ) => (
	<div>
		<h1>{ __( 'Dashboard', 'content-ops' ) }</h1>
		<HealthPanel api={ api } />
	</div>
);

export default Dashboard;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:js -- --testPathPattern=HealthPanel`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/HealthPanel.jsx assets/src/admin/__tests__/HealthPanel.test.js assets/src/admin/pages/Dashboard.jsx
git commit -m "feat: dashboard health panel fed by /doctor"
```

---

### Task 8: Dashboard — StatsCard + RecentOperationsList

**Files:**
- Create: `assets/src/admin/components/StatsCard.jsx`
- Create: `assets/src/admin/components/RecentOperationsList.jsx`
- Create: `assets/src/admin/__tests__/StatsCard.test.js`
- Create: `assets/src/admin/__tests__/RecentOperationsList.test.js`
- Modify: `assets/src/admin/pages/Dashboard.jsx`

- [ ] **Step 1: Write failing tests**

```js
// assets/src/admin/__tests__/StatsCard.test.js
import { render, screen, waitFor } from '@testing-library/react';
import StatsCard from '../components/StatsCard';

describe( 'StatsCard', () => {
	it( 'shows counts derived from operations list', async () => {
		const api = {
			listOperations: jest.fn().mockResolvedValue( [
				{ id: 1, affected_count: 5, status: 'completed', created_at: new Date().toISOString() },
				{ id: 2, affected_count: 3, status: 'completed', created_at: new Date().toISOString() },
			] ),
		};
		render( <StatsCard api={ api } /> );
		await waitFor( () => expect( screen.getByTestId( 'stats-ops-this-week' ) ).toHaveTextContent( '2' ) );
		expect( screen.getByTestId( 'stats-items-affected' ) ).toHaveTextContent( '8' );
	} );
} );
```

```js
// assets/src/admin/__tests__/RecentOperationsList.test.js
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import RecentOperationsList from '../components/RecentOperationsList';

describe( 'RecentOperationsList', () => {
	it( 'renders last five operations with an undo action for delete', async () => {
		const api = {
			listOperations: jest.fn().mockResolvedValue( [
				{ id: 10, type: 'delete', target: 'post', affected_count: 3, status: 'completed', created_at: '2026-04-20 10:00:00' },
			] ),
			undoOperation: jest.fn().mockResolvedValue( { restored: 3 } ),
		};
		render( <RecentOperationsList api={ api } /> );
		await waitFor( () => expect( screen.getByText( /delete/i ) ).toBeInTheDocument() );

		await userEvent.click( screen.getByRole( 'button', { name: /undo/i } ) );
		expect( api.undoOperation ).toHaveBeenCalledWith( 10 );
	} );
} );
```

- [ ] **Step 2: Run tests to verify failure**

Run: `npm run test:js -- --testPathPattern="StatsCard|RecentOperationsList"`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```jsx
// assets/src/admin/components/StatsCard.jsx
import { useEffect, useState } from '@wordpress/element';
import { Card, CardBody, CardHeader, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const StatsCard = ( { api } ) => {
	const [ ops, setOps ] = useState( null );
	useEffect( () => {
		api.listOperations( { limit: 100, offset: 0 } ).then( setOps ).catch( () => setOps( [] ) );
	}, [ api ] );

	if ( ops === null ) return <Spinner />;

	const weekAgo  = Date.now() - 7 * 24 * 60 * 60 * 1000;
	const thisWeek = ops.filter( ( op ) => Date.parse( op.created_at + 'Z' ) >= weekAgo );
	const items    = thisWeek.reduce( ( sum, op ) => sum + ( op.affected_count || 0 ), 0 );

	return (
		<Card>
			<CardHeader>{ __( 'This week', 'content-ops' ) }</CardHeader>
			<CardBody>
				<p><span data-testid="stats-ops-this-week">{ thisWeek.length }</span> { __( 'operations', 'content-ops' ) }</p>
				<p><span data-testid="stats-items-affected">{ items }</span> { __( 'items affected', 'content-ops' ) }</p>
				<p data-testid="stats-next-run">{ __( 'Next scheduled run: N/A', 'content-ops' ) }</p>
			</CardBody>
		</Card>
	);
};

export default StatsCard;
```

```jsx
// assets/src/admin/components/RecentOperationsList.jsx
import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { normalizeError } from '../api';

const UNDOABLE = [ 'delete', 'duplicate', 'edit' ];

const RecentOperationsList = ( { api } ) => {
	const [ ops, setOps ]       = useState( null );
	const [ notice, setNotice ] = useState( null );

	const reload = () => api.listOperations( { limit: 5, offset: 0 } ).then( setOps ).catch( () => setOps( [] ) );
	useEffect( () => { reload(); }, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const undo = async ( id ) => {
		try {
			const r = await api.undoOperation( id );
			setNotice( { status: 'success', text: __( 'Restored ', 'content-ops' ) + r.restored } );
			reload();
		} catch ( err ) {
			setNotice( { status: 'error', text: normalizeError( err ).message } );
		}
	};

	if ( ops === null ) return <Spinner />;

	return (
		<div>
			<h2>{ __( 'Recent operations', 'content-ops' ) }</h2>
			{ notice && <Notice status={ notice.status } onRemove={ () => setNotice( null ) }>{ notice.text }</Notice> }
			{ ops.length === 0 && <p>{ __( 'No operations yet.', 'content-ops' ) }</p> }
			<ul>
				{ ops.map( ( op ) => (
					<li key={ op.id }>
						<strong>{ op.type }</strong> — { op.target } — { op.affected_count } { __( 'items', 'content-ops' ) } — { op.status }
						{ UNDOABLE.includes( op.type ) && op.status === 'completed' && (
							<Button variant="secondary" onClick={ () => undo( op.id ) }>{ __( 'Undo', 'content-ops' ) }</Button>
						) }
					</li>
				) ) }
			</ul>
		</div>
	);
};

export default RecentOperationsList;
```

Update `Dashboard.jsx`:

```jsx
import { __ } from '@wordpress/i18n';
import HealthPanel from '../components/HealthPanel';
import StatsCard from '../components/StatsCard';
import RecentOperationsList from '../components/RecentOperationsList';
import { createApi } from '../api';

const Dashboard = ( { api = createApi() } ) => (
	<div>
		<h1>{ __( 'Dashboard', 'content-ops' ) }</h1>
		<StatsCard api={ api } />
		<HealthPanel api={ api } />
		<RecentOperationsList api={ api } />
	</div>
);

export default Dashboard;
```

- [ ] **Step 4: Run tests**

Run: `npm run test:js -- --testPathPattern="StatsCard|RecentOperationsList"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/StatsCard.jsx assets/src/admin/components/RecentOperationsList.jsx assets/src/admin/__tests__/StatsCard.test.js assets/src/admin/__tests__/RecentOperationsList.test.js assets/src/admin/pages/Dashboard.jsx
git commit -m "feat: dashboard stats card + recent operations list with undo"
```

---

### Task 9: Dashboard — Common Cleanups preset cards

**Files:**
- Create: `assets/src/admin/components/PresetCards.jsx`
- Create: `assets/src/admin/__tests__/PresetCards.test.js`
- Modify: `assets/src/admin/pages/Dashboard.jsx`

- [ ] **Step 1: Write the failing test**

```js
// assets/src/admin/__tests__/PresetCards.test.js
import { render, screen } from '@testing-library/react';
import PresetCards from '../components/PresetCards';

describe( 'PresetCards', () => {
	it( 'renders a card per preset and links to Operations Builder with prefill', () => {
		const presets = [
			{ slug: 'trash-old-drafts', label: 'Trash old drafts', description: 'Modified > 90d ago',
			  target: 'post', operation: 'delete', filters: { status: 'draft' }, params: {} },
		];
		const baseUrl = 'http://example.test/wp-admin/admin.php?page=content-ops-operations';
		render( <PresetCards presets={ presets } operationsUrl={ baseUrl } /> );
		expect( screen.getByText( 'Trash old drafts' ) ).toBeInTheDocument();
		const link = screen.getByRole( 'link', { name: /trash old drafts/i } );
		expect( link.getAttribute( 'href' ) ).toContain( 'preset=trash-old-drafts' );
	} );
} );
```

- [ ] **Step 2: Run test — verify failure**

Run: `npm run test:js -- --testPathPattern=PresetCards`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```jsx
// assets/src/admin/components/PresetCards.jsx
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PresetCards = ( { presets, operationsUrl } ) => {
	if ( ! presets || presets.length === 0 ) {
		return <p>{ __( 'No presets available.', 'content-ops' ) }</p>;
	}
	return (
		<div className="content-ops-preset-grid">
			{ presets.map( ( p ) => {
				const href = `${ operationsUrl }&preset=${ encodeURIComponent( p.slug ) }`;
				return (
					<Card key={ p.slug }>
						<CardHeader><a href={ href }>{ p.label }</a></CardHeader>
						<CardBody>
							<p>{ p.description }</p>
							<p><code>{ p.target }</code> · <code>{ p.operation }</code></p>
						</CardBody>
					</Card>
				);
			} ) }
		</div>
	);
};

export default PresetCards;
```

Update `Dashboard.jsx`:

```jsx
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import HealthPanel from '../components/HealthPanel';
import StatsCard from '../components/StatsCard';
import RecentOperationsList from '../components/RecentOperationsList';
import PresetCards from '../components/PresetCards';
import { createApi } from '../api';
import { getBootstrap } from '../bootstrap';

const Dashboard = ( { api = createApi(), bootstrap = getBootstrap() } ) => {
	const [ presets, setPresets ] = useState( [] );
	useEffect( () => {
		api.fetchCatalog().then( ( c ) => setPresets( c.presets || [] ) ).catch( () => setPresets( [] ) );
	}, [ api ] );

	return (
		<div>
			<h1>{ __( 'Dashboard', 'content-ops' ) }</h1>
			<StatsCard api={ api } />
			<HealthPanel api={ api } />
			<h2>{ __( 'Common cleanups', 'content-ops' ) }</h2>
			<PresetCards presets={ presets } operationsUrl={ bootstrap.pages.operations } />
			<RecentOperationsList api={ api } />
		</div>
	);
};

export default Dashboard;
```

- [ ] **Step 4: Run test — verify pass**

Run: `npm run test:js -- --testPathPattern=PresetCards`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/PresetCards.jsx assets/src/admin/__tests__/PresetCards.test.js assets/src/admin/pages/Dashboard.jsx
git commit -m "feat: dashboard common cleanups preset cards"
```

---

### Task 10: Operations Builder shell + state + TargetPicker

**Files:**
- Create: `assets/src/admin/state/builderReducer.js`
- Create: `assets/src/admin/state/builderContext.js`
- Create: `assets/src/admin/components/TargetPicker.jsx`
- Create: `assets/src/admin/hooks/useCatalog.js`
- Create: `assets/src/admin/__tests__/builderReducer.test.js`
- Create: `assets/src/admin/__tests__/TargetPicker.test.js`
- Modify: `assets/src/admin/pages/OperationsBuilder.jsx`

- [ ] **Step 1: Write failing tests**

```js
// assets/src/admin/__tests__/builderReducer.test.js
import { initialState, reducer } from '../state/builderReducer';

describe( 'builderReducer', () => {
	it( 'sets the target and clears filters + operation + preview', () => {
		const prev = { ...initialState, target: 'post', operation: 'delete', filters: [ { id: 'a', key: 'status', value: 'draft' } ], preview: { count: 5 } };
		const next = reducer( prev, { type: 'SET_TARGET', target: 'page' } );
		expect( next.target ).toBe( 'page' );
		expect( next.filters ).toEqual( [] );
		expect( next.operation ).toBeNull();
		expect( next.preview ).toBeNull();
	} );

	it( 'adds a filter row with a generated id', () => {
		const state = reducer( initialState, { type: 'ADD_FILTER' } );
		expect( state.filters.length ).toBe( 1 );
		expect( state.filters[0].id ).toBeTruthy();
	} );

	it( 'updates a filter row and invalidates preview', () => {
		const state = {
			...initialState,
			filters: [ { id: 'x', key: null, value: null } ],
			preview: { count: 10, preview_token: 't' },
		};
		const next = reducer( state, { type: 'UPDATE_FILTER', id: 'x', patch: { key: 'status', value: 'draft' } } );
		expect( next.filters[0] ).toEqual( { id: 'x', key: 'status', value: 'draft' } );
		expect( next.preview ).toBeNull();
	} );

	it( 'sets preview result', () => {
		const next = reducer( initialState, { type: 'SET_PREVIEW', preview: { count: 3, preview_token: 'tok', sample_ids: [], display_rows: [], warnings: [] } } );
		expect( next.preview.count ).toBe( 3 );
	} );

	it( 'removes a filter', () => {
		const state = { ...initialState, filters: [ { id: 'a' }, { id: 'b' } ] };
		expect( reducer( state, { type: 'REMOVE_FILTER', id: 'a' } ).filters ).toEqual( [ { id: 'b' } ] );
	} );

	it( 'SET_FILTERS replaces the whole filter array', () => {
		const next = reducer( initialState, { type: 'SET_FILTERS', filters: [ { id: 'z', key: 'status', value: 'draft' } ] } );
		expect( next.filters ).toHaveLength( 1 );
		expect( next.preview ).toBeNull();
	} );
} );
```

```js
// assets/src/admin/__tests__/TargetPicker.test.js
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TargetPicker from '../components/TargetPicker';

describe( 'TargetPicker', () => {
	it( 'renders a pill per target and selects on click', async () => {
		const onSelect = jest.fn();
		render( <TargetPicker targets={ [
			{ slug: 'post', label: 'Posts' },
			{ slug: 'page', label: 'Pages' },
		] } selected={ null } onSelect={ onSelect } /> );

		expect( screen.getByRole( 'button', { name: 'Posts' } ) ).toBeInTheDocument();
		await userEvent.click( screen.getByRole( 'button', { name: 'Pages' } ) );
		expect( onSelect ).toHaveBeenCalledWith( 'page' );
	} );

	it( 'marks selected target aria-pressed=true', () => {
		render( <TargetPicker targets={ [ { slug: 'post', label: 'Posts' } ] } selected="post" onSelect={ () => {} } /> );
		expect( screen.getByRole( 'button', { name: 'Posts' } ) ).toHaveAttribute( 'aria-pressed', 'true' );
	} );
} );
```

- [ ] **Step 2: Run tests — verify failure**

Run: `npm run test:js -- --testPathPattern="builderReducer|TargetPicker"`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```js
// assets/src/admin/state/builderReducer.js
export const initialState = {
	target:       null,
	operation:    null,
	filters:      [],
	params:       {},
	preview:      null,
	previewing:   false,
	previewError: null,
	execution:    null,
	executing:    false,
};

const newId = () => Math.random().toString( 36 ).slice( 2, 10 );

export const reducer = ( state, action ) => {
	switch ( action.type ) {
		case 'SET_TARGET':
			return { ...initialState, target: action.target };
		case 'SET_OPERATION':
			return { ...state, operation: action.operation, params: {}, preview: null };
		case 'ADD_FILTER':
			return { ...state, filters: [ ...state.filters, { id: newId(), key: null, value: null } ], preview: null };
		case 'UPDATE_FILTER':
			return {
				...state,
				filters: state.filters.map( ( f ) => ( f.id === action.id ? { ...f, ...action.patch } : f ) ),
				preview: null,
			};
		case 'REMOVE_FILTER':
			return { ...state, filters: state.filters.filter( ( f ) => f.id !== action.id ), preview: null };
		case 'SET_FILTERS':
			return { ...state, filters: action.filters, preview: null };
		case 'SET_PARAMS':
			return { ...state, params: action.params, preview: null };
		case 'SET_PREVIEWING':
			return { ...state, previewing: action.value, previewError: action.value ? null : state.previewError };
		case 'SET_PREVIEW':
			return { ...state, preview: action.preview, previewing: false, previewError: null };
		case 'SET_PREVIEW_ERROR':
			return { ...state, previewError: action.error, previewing: false };
		case 'SET_EXECUTING':
			return { ...state, executing: action.value };
		case 'SET_EXECUTION':
			return { ...state, execution: action.execution, executing: false };
		case 'RESET':
			return { ...initialState };
		default:
			return state;
	}
};
```

```js
// assets/src/admin/state/builderContext.js
import { createContext } from '@wordpress/element';
export const BuilderContext = createContext( null );
```

```jsx
// assets/src/admin/components/TargetPicker.jsx
import { Button } from '@wordpress/components';

const TargetPicker = ( { targets, selected, onSelect } ) => (
	<div className="content-ops-target-picker" role="group" aria-label="Target">
		{ targets.map( ( t ) => (
			<Button
				key={ t.slug }
				variant={ selected === t.slug ? 'primary' : 'secondary' }
				aria-pressed={ selected === t.slug }
				onClick={ () => onSelect( t.slug ) }
			>
				{ t.label }
			</Button>
		) ) }
	</div>
);

export default TargetPicker;
```

```js
// assets/src/admin/hooks/useCatalog.js
import { useEffect, useState } from '@wordpress/element';
import { normalizeError } from '../api';

export const useCatalog = ( api ) => {
	const [ catalog, setCatalog ] = useState( null );
	const [ error, setError ]     = useState( null );
	useEffect( () => {
		let cancelled = false;
		api.fetchCatalog()
			.then( ( c ) => { if ( ! cancelled ) setCatalog( c ); } )
			.catch( ( e ) => { if ( ! cancelled ) setError( normalizeError( e ) ); } );
		return () => { cancelled = true; };
	}, [ api ] );
	return { catalog, error };
};
```

Update `OperationsBuilder.jsx`:

```jsx
import { useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Notice } from '@wordpress/components';
import { createApi } from '../api';
import { useCatalog } from '../hooks/useCatalog';
import { BuilderContext } from '../state/builderContext';
import { reducer, initialState } from '../state/builderReducer';
import TargetPicker from '../components/TargetPicker';

const OperationsBuilder = ( { api = createApi() } ) => {
	const { catalog, error }  = useCatalog( api );
	const [ state, dispatch ] = useReducer( reducer, initialState );

	if ( error ) return <Notice status="error" role="alert">{ error.message }</Notice>;
	if ( ! catalog ) return <Spinner />;

	return (
		<BuilderContext.Provider value={ { state, dispatch, catalog, api } }>
			<div>
				<h1>{ __( 'Operations Builder', 'content-ops' ) }</h1>
				<section>
					<h2>{ __( 'Target', 'content-ops' ) }</h2>
					<TargetPicker
						targets={ catalog.targets }
						selected={ state.target }
						onSelect={ ( slug ) => dispatch( { type: 'SET_TARGET', target: slug } ) }
					/>
				</section>
			</div>
		</BuilderContext.Provider>
	);
};

export default OperationsBuilder;
```

- [ ] **Step 4: Run tests to verify pass**

Run: `npm run test:js -- --testPathPattern="builderReducer|TargetPicker"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/state/ assets/src/admin/components/TargetPicker.jsx assets/src/admin/hooks/useCatalog.js assets/src/admin/__tests__/builderReducer.test.js assets/src/admin/__tests__/TargetPicker.test.js assets/src/admin/pages/OperationsBuilder.jsx
git commit -m "feat: operations builder shell with target picker + reducer"
```

---

### Task 11: FilterRow — enum + bool inputs

**Files:**
- Create: `assets/src/admin/components/FilterRow.jsx`
- Create: `assets/src/admin/__tests__/FilterRow.test.js`

- [ ] **Step 1: Write the failing test**

```js
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FilterRow from '../components/FilterRow';

const defs = [
	{ key: 'status',       label: 'Status',       type: 'enum', schema: { multiple: true } },
	{ key: 'has_comments', label: 'Has comments', type: 'bool', schema: {} },
];

describe( 'FilterRow', () => {
	it( 'renders a key picker populated from filter defs', () => {
		render( <FilterRow row={ { id: '1', key: null, value: null } } defs={ defs } onChange={ () => {} } onRemove={ () => {} } /> );
		expect( screen.getByRole( 'combobox', { name: /filter/i } ) ).toBeInTheDocument();
	} );

	it( 'calls onChange when key is selected', async () => {
		const onChange = jest.fn();
		render( <FilterRow row={ { id: '1', key: null, value: null } } defs={ defs } onChange={ onChange } onRemove={ () => {} } /> );
		await userEvent.selectOptions( screen.getByRole( 'combobox', { name: /filter/i } ), 'status' );
		expect( onChange ).toHaveBeenCalledWith( { key: 'status', value: null } );
	} );

	it( 'renders bool toggle for bool types', async () => {
		const onChange = jest.fn();
		render( <FilterRow row={ { id: '1', key: 'has_comments', value: null } } defs={ defs } onChange={ onChange } onRemove={ () => {} } /> );
		const toggle = screen.getByRole( 'checkbox' );
		await userEvent.click( toggle );
		expect( onChange ).toHaveBeenCalledWith( { key: 'has_comments', value: true } );
	} );

	it( 'triggers onRemove', async () => {
		const onRemove = jest.fn();
		render( <FilterRow row={ { id: '1', key: 'status', value: null } } defs={ defs } onChange={ () => {} } onRemove={ onRemove } /> );
		await userEvent.click( screen.getByRole( 'button', { name: /remove/i } ) );
		expect( onRemove ).toHaveBeenCalled();
	} );
} );
```

- [ ] **Step 2: Run test — verify failure**

Run: `npm run test:js -- --testPathPattern=FilterRow`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```jsx
// assets/src/admin/components/FilterRow.jsx
import { Button, ToggleControl, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const EnumInput = ( { def, value, onChange } ) => {
	const options = def.schema && Array.isArray( def.schema.options ) ? def.schema.options : null;
	const multiple = Boolean( def.schema && def.schema.multiple );
	if ( ! options ) {
		return (
			<TextControl
				label={ def.label }
				help={ multiple ? __( 'Comma-separated values.', 'content-ops' ) : '' }
				value={ Array.isArray( value ) ? value.join( ',' ) : ( value || '' ) }
				onChange={ ( v ) => onChange( multiple ? v.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean ) : v ) }
			/>
		);
	}
	if ( multiple ) {
		return (
			<fieldset>
				<legend>{ def.label }</legend>
				{ options.map( ( o ) => {
					const checked = Array.isArray( value ) && value.includes( o.value );
					return (
						<label key={ o.value }>
							<input
								type="checkbox"
								checked={ checked }
								onChange={ () => {
									const arr = Array.isArray( value ) ? [ ...value ] : [];
									onChange( checked ? arr.filter( ( v ) => v !== o.value ) : [ ...arr, o.value ] );
								} }
							/>
							{ o.label }
						</label>
					);
				} ) }
			</fieldset>
		);
	}
	return (
		<SelectControl
			label={ def.label }
			value={ value || '' }
			options={ [ { label: __( 'Choose…', 'content-ops' ), value: '' }, ...options ] }
			onChange={ onChange }
		/>
	);
};

const BoolInput = ( { def, value, onChange } ) => (
	<ToggleControl label={ def.label } checked={ !! value } onChange={ onChange } />
);

const FilterRow = ( { row, defs, onChange, onRemove } ) => {
	const def = defs.find( ( d ) => d.key === row.key ) || null;
	const keyOptions = [ { label: __( 'Choose filter…', 'content-ops' ), value: '' }, ...defs.map( ( d ) => ( { label: d.label, value: d.key } ) ) ];

	return (
		<div className="content-ops-filter-row" role="group">
			<SelectControl
				label={ __( 'Filter', 'content-ops' ) }
				value={ row.key || '' }
				options={ keyOptions }
				onChange={ ( key ) => onChange( { key: key || null, value: null } ) }
			/>
			{ def && def.type === 'enum' && (
				<EnumInput def={ def } value={ row.value } onChange={ ( value ) => onChange( { key: def.key, value } ) } />
			) }
			{ def && def.type === 'bool' && (
				<BoolInput def={ def } value={ row.value } onChange={ ( value ) => onChange( { key: def.key, value } ) } />
			) }
			<Button isDestructive onClick={ onRemove }>{ __( 'Remove', 'content-ops' ) }</Button>
		</div>
	);
};

export default FilterRow;
```

- [ ] **Step 4: Run test — verify pass**

Run: `npm run test:js -- --testPathPattern=FilterRow`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/FilterRow.jsx assets/src/admin/__tests__/FilterRow.test.js
git commit -m "feat: filter row with enum + bool + key picker"
```

---

### Task 12: FilterRow — date / user / post / taxonomy inputs + FilterList

**Files:**
- Modify: `assets/src/admin/components/FilterRow.jsx`
- Create: `assets/src/admin/components/FilterList.jsx`
- Create: `assets/src/admin/__tests__/FilterList.test.js`
- Modify: `assets/src/admin/__tests__/FilterRow.test.js`
- Modify: `assets/src/admin/pages/OperationsBuilder.jsx`

- [ ] **Step 1: Extend test files**

Append to `assets/src/admin/__tests__/FilterRow.test.js`:

```js
describe( 'FilterRow extra types', () => {
	it( 'renders a date input for date type', async () => {
		const onChange = jest.fn();
		render( <FilterRow row={ { id: '1', key: 'modified_before', value: null } }
			defs={ [ { key: 'modified_before', label: 'Modified before', type: 'date', schema: {} } ] }
			onChange={ onChange } onRemove={ () => {} } /> );
		const input = screen.getByLabelText( 'Modified before' );
		expect( input ).toHaveAttribute( 'type', 'date' );
		await userEvent.type( input, '2026-04-01' );
		expect( onChange ).toHaveBeenLastCalledWith( { key: 'modified_before', value: '2026-04-01' } );
	} );

	it( 'renders numeric input for user/post id types', async () => {
		const onChange = jest.fn();
		render( <FilterRow row={ { id: '1', key: 'author', value: null } }
			defs={ [ { key: 'author', label: 'Author ID', type: 'user', schema: {} } ] }
			onChange={ onChange } onRemove={ () => {} } /> );
		const input = screen.getByLabelText( /author id/i );
		await userEvent.type( input, '7' );
		expect( onChange ).toHaveBeenLastCalledWith( { key: 'author', value: 7 } );
	} );

	it( 'renders taxonomy input with slug and term IDs', async () => {
		const onChange = jest.fn();
		render( <FilterRow row={ { id: '1', key: 'taxonomy', value: null } }
			defs={ [ { key: 'taxonomy', label: 'Taxonomy', type: 'taxonomy', schema: {} } ] }
			onChange={ onChange } onRemove={ () => {} } /> );
		await userEvent.type( screen.getByLabelText( /taxonomy slug/i ), 'category' );
		await userEvent.type( screen.getByLabelText( /term ids/i ), '3, 5' );
		expect( onChange ).toHaveBeenLastCalledWith( { key: 'taxonomy', value: { taxonomy: 'category', term_ids: [ 3, 5 ] } } );
	} );
} );
```

Create `assets/src/admin/__tests__/FilterList.test.js`:

```js
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FilterList from '../components/FilterList';

describe( 'FilterList', () => {
	it( 'renders rows and dispatches ADD_FILTER on add', async () => {
		const dispatch = jest.fn();
		render( <FilterList
			filters={ [ { id: 'a', key: null, value: null } ] }
			defs={ [ { key: 'status', label: 'Status', type: 'enum', schema: {} } ] }
			dispatch={ dispatch } /> );

		await userEvent.click( screen.getByRole( 'button', { name: /add filter/i } ) );
		expect( dispatch ).toHaveBeenCalledWith( { type: 'ADD_FILTER' } );
	} );
} );
```

- [ ] **Step 2: Run tests — verify failure**

Run: `npm run test:js -- --testPathPattern="FilterRow|FilterList"`
Expected: FAIL.

- [ ] **Step 3: Extend `FilterRow.jsx`**

Add inside `FilterRow.jsx` before the `const FilterRow = ...` definition:

```jsx
const DateInput = ( { def, value, onChange } ) => (
	<div>
		<label htmlFor={ `co-date-${ def.key }` }>{ def.label }</label>
		<input id={ `co-date-${ def.key }` } type="date" value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) } />
	</div>
);

const IdInput = ( { def, value, onChange } ) => (
	<TextControl
		label={ def.label }
		type="number"
		value={ value === null || value === undefined ? '' : String( value ) }
		onChange={ ( v ) => onChange( v === '' ? null : parseInt( v, 10 ) ) }
	/>
);

const TaxonomyInput = ( { def, value, onChange } ) => {
	const tax = ( value && value.taxonomy ) || '';
	const ids = ( value && Array.isArray( value.term_ids ) ) ? value.term_ids.join( ', ' ) : '';
	const update = ( patch ) => {
		const next = { taxonomy: tax, term_ids: value && value.term_ids ? value.term_ids : [], ...patch };
		onChange( next );
	};
	return (
		<div>
			<TextControl label={ __( 'Taxonomy slug', 'content-ops' ) } value={ tax } onChange={ ( v ) => update( { taxonomy: v } ) } />
			<TextControl
				label={ __( 'Term IDs', 'content-ops' ) }
				value={ ids }
				help={ __( 'Comma-separated.', 'content-ops' ) }
				onChange={ ( v ) => update( { term_ids: v.split( ',' ).map( ( s ) => parseInt( s.trim(), 10 ) ).filter( ( n ) => ! Number.isNaN( n ) ) } ) }
			/>
		</div>
	);
};
```

In `FilterRow`, after the enum/bool branches, add:

```jsx
{ def && def.type === 'date' && (
	<DateInput def={ def } value={ row.value } onChange={ ( value ) => onChange( { key: def.key, value } ) } />
) }
{ def && ( def.type === 'user' || def.type === 'post' ) && (
	<IdInput def={ def } value={ row.value } onChange={ ( value ) => onChange( { key: def.key, value } ) } />
) }
{ def && def.type === 'taxonomy' && (
	<TaxonomyInput def={ def } value={ row.value } onChange={ ( value ) => onChange( { key: def.key, value } ) } />
) }
```

Create `FilterList.jsx`:

```jsx
// assets/src/admin/components/FilterList.jsx
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FilterRow from './FilterRow';

const FilterList = ( { filters, defs, dispatch } ) => (
	<div className="content-ops-filter-list">
		{ filters.map( ( row ) => (
			<FilterRow
				key={ row.id }
				row={ row }
				defs={ defs }
				onChange={ ( patch ) => dispatch( { type: 'UPDATE_FILTER', id: row.id, patch } ) }
				onRemove={ () => dispatch( { type: 'REMOVE_FILTER', id: row.id } ) }
			/>
		) ) }
		<Button variant="secondary" onClick={ () => dispatch( { type: 'ADD_FILTER' } ) }>{ __( 'Add filter', 'content-ops' ) }</Button>
		<p className="description">{ __( 'Match mode: all filters must match.', 'content-ops' ) }</p>
	</div>
);

export default FilterList;
```

Wire `FilterList` into `OperationsBuilder.jsx` after the target section:

```jsx
import FilterList from '../components/FilterList';
// ...
{ state.target && (
	<section>
		<h2>{ __( 'Filters', 'content-ops' ) }</h2>
		<FilterList
			filters={ state.filters }
			defs={ ( catalog.targets.find( ( t ) => t.slug === state.target ) || { filters: [] } ).filters }
			dispatch={ dispatch }
		/>
	</section>
) }
```

- [ ] **Step 4: Run tests — verify pass**

Run: `npm run test:js -- --testPathPattern="FilterRow|FilterList"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/FilterRow.jsx assets/src/admin/components/FilterList.jsx assets/src/admin/__tests__/FilterRow.test.js assets/src/admin/__tests__/FilterList.test.js assets/src/admin/pages/OperationsBuilder.jsx
git commit -m "feat: filter list with date, id, taxonomy inputs + add/remove"
```

---

### Task 13: OperationPicker + OperationParamsForm

**Files:**
- Create: `assets/src/admin/components/OperationPicker.jsx`
- Create: `assets/src/admin/components/OperationParamsForm.jsx`
- Create: `assets/src/admin/__tests__/OperationPicker.test.js`
- Create: `assets/src/admin/__tests__/OperationParamsForm.test.js`
- Modify: `assets/src/admin/pages/OperationsBuilder.jsx`

- [ ] **Step 1: Write failing tests**

```js
// assets/src/admin/__tests__/OperationPicker.test.js
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import OperationPicker from '../components/OperationPicker';

describe( 'OperationPicker', () => {
	it( 'renders only operations supported by the target', async () => {
		const onSelect = jest.fn();
		render( <OperationPicker
			operations={ [
				{ slug: 'delete', label: 'Delete' },
				{ slug: 'duplicate', label: 'Duplicate' },
				{ slug: 'edit', label: 'Bulk edit' },
			] }
			supported={ [ 'delete', 'edit' ] }
			selected={ null }
			onSelect={ onSelect }
		/> );
		expect( screen.getByRole( 'button', { name: 'Delete' } ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'button', { name: 'Duplicate' } ) ).not.toBeInTheDocument();
		await userEvent.click( screen.getByRole( 'button', { name: 'Bulk edit' } ) );
		expect( onSelect ).toHaveBeenCalledWith( 'edit' );
	} );
} );
```

```js
// assets/src/admin/__tests__/OperationParamsForm.test.js
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import OperationParamsForm from '../components/OperationParamsForm';

describe( 'OperationParamsForm', () => {
	it( 'renders a boolean schema field as a ToggleControl', async () => {
		const onChange = jest.fn();
		render( <OperationParamsForm
			schema={ { type: 'object', properties: { permanent: { type: 'boolean', default: false } } } }
			value={ {} }
			onChange={ onChange }
		/> );
		await userEvent.click( screen.getByRole( 'checkbox', { name: /permanent/i } ) );
		expect( onChange ).toHaveBeenCalledWith( { permanent: true } );
	} );

	it( 'renders a string schema field as a TextControl', async () => {
		const onChange = jest.fn();
		render( <OperationParamsForm
			schema={ { type: 'object', properties: { target_status: { type: 'string', default: 'draft' } } } }
			value={ {} }
			onChange={ onChange }
		/> );
		await userEvent.type( screen.getByLabelText( /target_status/i ), 'x' );
		expect( onChange ).toHaveBeenLastCalledWith( { target_status: 'x' } );
	} );
} );
```

- [ ] **Step 2: Run tests — verify failure**

Run: `npm run test:js -- --testPathPattern="OperationPicker|OperationParamsForm"`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```jsx
// assets/src/admin/components/OperationPicker.jsx
import { Button } from '@wordpress/components';

const OperationPicker = ( { operations, supported, selected, onSelect } ) => {
	const visible = operations.filter( ( op ) => supported.includes( op.slug ) );
	return (
		<div className="content-ops-operation-picker">
			{ visible.map( ( op ) => (
				<Button
					key={ op.slug }
					variant={ selected === op.slug ? 'primary' : 'secondary' }
					aria-pressed={ selected === op.slug }
					onClick={ () => onSelect( op.slug ) }
				>
					{ op.label }
				</Button>
			) ) }
		</div>
	);
};

export default OperationPicker;
```

```jsx
// assets/src/admin/components/OperationParamsForm.jsx
import { TextControl, ToggleControl } from '@wordpress/components';

const OperationParamsForm = ( { schema, value, onChange } ) => {
	if ( ! schema || schema.type !== 'object' || ! schema.properties ) return null;
	const update = ( key, v ) => onChange( { ...value, [ key ]: v } );

	return (
		<div className="content-ops-op-params">
			{ Object.entries( schema.properties ).map( ( [ key, prop ] ) => {
				const current = value[ key ] !== undefined ? value[ key ] : prop.default;
				if ( prop.type === 'boolean' ) {
					return (
						<ToggleControl
							key={ key }
							label={ key }
							checked={ !! current }
							onChange={ ( v ) => update( key, v ) }
						/>
					);
				}
				if ( prop.type === 'integer' ) {
					return (
						<TextControl
							key={ key }
							label={ key }
							type="number"
							value={ current === undefined || current === null ? '' : String( current ) }
							onChange={ ( v ) => update( key, v === '' ? undefined : parseInt( v, 10 ) ) }
						/>
					);
				}
				return (
					<TextControl
						key={ key }
						label={ key }
						value={ current || '' }
						onChange={ ( v ) => update( key, v ) }
					/>
				);
			} ) }
		</div>
	);
};

export default OperationParamsForm;
```

Wire in `OperationsBuilder.jsx` after filters section:

```jsx
import OperationPicker from '../components/OperationPicker';
import OperationParamsForm from '../components/OperationParamsForm';

// ...
{ state.target && (
	<section>
		<h2>{ __( 'Operation', 'content-ops' ) }</h2>
		<OperationPicker
			operations={ catalog.operations }
			supported={ [ 'delete', 'duplicate', 'edit' ] }
			selected={ state.operation }
			onSelect={ ( slug ) => dispatch( { type: 'SET_OPERATION', operation: slug } ) }
		/>
		{ state.operation && (
			<OperationParamsForm
				schema={ ( catalog.operations.find( ( o ) => o.slug === state.operation ) || {} ).params_schema }
				value={ state.params }
				onChange={ ( params ) => dispatch( { type: 'SET_PARAMS', params } ) }
			/>
		) }
	</section>
) }
```

- [ ] **Step 4: Run tests — verify pass**

Run: `npm run test:js -- --testPathPattern="OperationPicker|OperationParamsForm"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/OperationPicker.jsx assets/src/admin/components/OperationParamsForm.jsx assets/src/admin/__tests__/OperationPicker.test.js assets/src/admin/__tests__/OperationParamsForm.test.js assets/src/admin/pages/OperationsBuilder.jsx
git commit -m "feat: operation picker + params form derived from schema"
```

---

### Task 14: Debounced live preview hook

**Files:**
- Create: `assets/src/admin/hooks/useDebouncedPreview.js`
- Create: `assets/src/admin/__tests__/useDebouncedPreview.test.js`

- [ ] **Step 1: Write the failing test**

```js
// assets/src/admin/__tests__/useDebouncedPreview.test.js
import { renderHook, act, waitFor } from '@testing-library/react';
import { useDebouncedPreview } from '../hooks/useDebouncedPreview';

jest.useFakeTimers();

const filtersToArgs = ( filters ) => Object.fromEntries(
	filters.filter( ( f ) => f.key ).map( ( f ) => [ f.key, f.value ] )
);

describe( 'useDebouncedPreview', () => {
	afterEach( () => jest.clearAllTimers() );

	it( 'debounces 300ms and calls api.preview with filters object', async () => {
		const api = { preview: jest.fn().mockResolvedValue( { count: 2, sample_ids: [ 1, 2 ], preview_token: 't', warnings: [], display_rows: [] } ) };
		const { result, rerender } = renderHook(
			( { state } ) => useDebouncedPreview( api, state, filtersToArgs ),
			{ initialProps: { state: { target: 'post', operation: 'delete', filters: [], params: {} } } }
		);

		rerender( { state: { target: 'post', operation: 'delete', filters: [ { id: 'a', key: 'status', value: 'draft' } ], params: {} } } );
		expect( api.preview ).not.toHaveBeenCalled();

		act( () => jest.advanceTimersByTime( 299 ) );
		expect( api.preview ).not.toHaveBeenCalled();

		act( () => jest.advanceTimersByTime( 10 ) );
		await waitFor( () => expect( api.preview ).toHaveBeenCalledTimes( 1 ) );
		expect( api.preview ).toHaveBeenCalledWith(
			{ target: 'post', operation: 'delete', filters: { status: 'draft' }, params: {} },
			expect.any( AbortSignal )
		);
		expect( result.current.preview.count ).toBe( 2 );
	} );

	it( 'does nothing until target + operation are both set', () => {
		const api = { preview: jest.fn() };
		renderHook( () => useDebouncedPreview( api, { target: null, operation: null, filters: [], params: {} }, filtersToArgs ) );
		act( () => jest.advanceTimersByTime( 500 ) );
		expect( api.preview ).not.toHaveBeenCalled();
	} );
} );
```

- [ ] **Step 2: Run test — verify failure**

Run: `npm run test:js -- --testPathPattern=useDebouncedPreview`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```js
// assets/src/admin/hooks/useDebouncedPreview.js
import { useEffect, useRef, useState } from '@wordpress/element';
import { normalizeError } from '../api';

export const useDebouncedPreview = ( api, state, filtersToArgs, delayMs = 300 ) => {
	const [ preview, setPreview ]           = useState( null );
	const [ previewing, setPreviewing ]     = useState( false );
	const [ previewError, setPreviewError ] = useState( null );
	const abortRef = useRef( null );
	const timerRef = useRef( null );

	useEffect( () => {
		if ( ! state.target || ! state.operation ) {
			return undefined;
		}
		if ( timerRef.current ) clearTimeout( timerRef.current );
		timerRef.current = setTimeout( async () => {
			if ( abortRef.current ) abortRef.current.abort();
			abortRef.current = new AbortController();
			setPreviewing( true );
			setPreviewError( null );
			try {
				const res = await api.preview( {
					target:    state.target,
					operation: state.operation,
					filters:   filtersToArgs( state.filters ),
					params:    state.params,
				}, abortRef.current.signal );
				setPreview( res );
			} catch ( err ) {
				const n = normalizeError( err );
				if ( ! n.aborted ) setPreviewError( n );
			} finally {
				setPreviewing( false );
			}
		}, delayMs );

		return () => {
			if ( timerRef.current ) clearTimeout( timerRef.current );
		};
	}, [ api, state, filtersToArgs, delayMs ] );

	return { preview, previewing, previewError };
};
```

- [ ] **Step 4: Run test — verify pass**

Run: `npm run test:js -- --testPathPattern=useDebouncedPreview`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/hooks/useDebouncedPreview.js assets/src/admin/__tests__/useDebouncedPreview.test.js
git commit -m "feat: debounced live preview hook with AbortController"
```

---

### Task 15: PreviewPanel + ExecuteButton + ExecutionResult, wired into builder

**Files:**
- Create: `assets/src/admin/components/PreviewPanel.jsx`
- Create: `assets/src/admin/components/ExecuteButton.jsx`
- Create: `assets/src/admin/components/ExecutionResult.jsx`
- Create: `assets/src/admin/__tests__/PreviewPanel.test.js`
- Create: `assets/src/admin/__tests__/ExecuteButton.test.js`
- Create: `assets/src/admin/__tests__/ExecutionResult.test.js`
- Modify: `assets/src/admin/pages/OperationsBuilder.jsx`

- [ ] **Step 1: Write failing tests**

```js
// assets/src/admin/__tests__/PreviewPanel.test.js
import { render, screen } from '@testing-library/react';
import PreviewPanel from '../components/PreviewPanel';

describe( 'PreviewPanel', () => {
	it( 'shows count and sample rows', () => {
		render( <PreviewPanel preview={ {
			count: 42,
			sample_ids: [ 1, 2 ],
			display_rows: [
				{ id: 1, title: 'First',  status: 'draft', date: '2026-04-01 10:00:00', edit_url: 'http://x/edit/1', thumbnail_url: null },
				{ id: 2, title: 'Second', status: 'draft', date: '2026-04-02 11:00:00', edit_url: 'http://x/edit/2', thumbnail_url: null },
			],
			warnings: [],
		} } previewing={ false } previewError={ null } /> );
		expect( screen.getByText( /42/ ) ).toBeInTheDocument();
		expect( screen.getByText( 'First' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Second' ) ).toBeInTheDocument();
		expect( screen.getByRole( 'link', { name: /first/i } ) ).toHaveAttribute( 'href', 'http://x/edit/1' );
	} );

	it( 'shows error message when previewError is set', () => {
		render( <PreviewPanel preview={ null } previewing={ false } previewError={ { code: 'co.x', message: 'bad filter', context: {} } } /> );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent( /bad filter/ );
	} );
} );
```

```js
// assets/src/admin/__tests__/ExecuteButton.test.js
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ExecuteButton from '../components/ExecuteButton';

describe( 'ExecuteButton', () => {
	it( 'is disabled when there is no preview', () => {
		render( <ExecuteButton preview={ null } onExecute={ () => {} } executing={ false } /> );
		expect( screen.getByRole( 'button' ) ).toBeDisabled();
	} );

	it( 'shows "Will delete N items" when a fresh preview is present', () => {
		render( <ExecuteButton preview={ { count: 7, preview_token: 't' } } operation="delete" onExecute={ () => {} } executing={ false } /> );
		expect( screen.getByRole( 'button' ) ).toHaveTextContent( /will delete 7/i );
	} );

	it( 'calls onExecute on click', async () => {
		const onExecute = jest.fn();
		render( <ExecuteButton preview={ { count: 1, preview_token: 't' } } operation="duplicate" onExecute={ onExecute } executing={ false } /> );
		await userEvent.click( screen.getByRole( 'button' ) );
		expect( onExecute ).toHaveBeenCalled();
	} );
} );
```

```js
// assets/src/admin/__tests__/ExecutionResult.test.js
import { render, screen } from '@testing-library/react';
import ExecutionResult from '../components/ExecutionResult';

describe( 'ExecutionResult', () => {
	it( 'shows completed batch stats', () => {
		render( <ExecutionResult execution={ { status: 'completed', operation_id: 12, batch: { processed: 10, succeeded: 10, failed: 0 } } } historyUrl="http://x?page=content-ops-history" /> );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( /10 succeeded/i );
	} );
	it( 'shows queued state with history link', () => {
		render( <ExecutionResult execution={ { status: 'queued', operation_id: 5 } } historyUrl="http://x?page=content-ops-history" /> );
		expect( screen.getByRole( 'link', { name: /view in history/i } ) ).toHaveAttribute( 'href', expect.stringContaining( 'content-ops-history' ) );
	} );
} );
```

- [ ] **Step 2: Run tests — verify failure**

Run: `npm run test:js -- --testPathPattern="PreviewPanel|ExecuteButton|ExecutionResult"`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```jsx
// assets/src/admin/components/PreviewPanel.jsx
import { Card, CardBody, CardHeader, Spinner, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const PreviewPanel = ( { preview, previewing, previewError } ) => {
	if ( previewError ) {
		return <Notice status="error" isDismissible={ false } role="alert">{ previewError.message }</Notice>;
	}
	if ( previewing && ! preview ) return <Spinner />;
	if ( ! preview ) return <p>{ __( 'Add filters to see a live count.', 'content-ops' ) }</p>;

	return (
		<Card>
			<CardHeader>
				{ sprintf( __( 'Matched: %d items', 'content-ops' ), preview.count ) }
				{ previewing && <Spinner /> }
			</CardHeader>
			<CardBody>
				{ preview.warnings && preview.warnings.length > 0 && (
					<Notice status="warning" isDismissible={ false }>
						<ul>{ preview.warnings.map( ( w, i ) => <li key={ i }>{ w }</li> ) }</ul>
					</Notice>
				) }
				<table className="widefat">
					<thead>
						<tr>
							<th>ID</th>
							<th>{ __( 'Title', 'content-ops' ) }</th>
							<th>{ __( 'Status', 'content-ops' ) }</th>
							<th>{ __( 'Date', 'content-ops' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ ( preview.display_rows || [] ).map( ( r ) => (
							<tr key={ r.id }>
								<td>{ r.id }</td>
								<td><a href={ r.edit_url || '#' }>{ r.title || __( '(no title)', 'content-ops' ) }</a></td>
								<td>{ r.status }</td>
								<td>{ r.date }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</CardBody>
		</Card>
	);
};

export default PreviewPanel;
```

```jsx
// assets/src/admin/components/ExecuteButton.jsx
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const VERB = {
	delete:    'Will delete %d items',
	duplicate: 'Will duplicate %d items',
	edit:      'Will edit %d items',
};

const ExecuteButton = ( { preview, operation, onExecute, executing } ) => {
	const disabled = ! preview || ! preview.preview_token || executing;
	const tmpl     = VERB[ operation ] || 'Will process %d items';
	const label    = preview ? sprintf( __( tmpl, 'content-ops' ), preview.count ) : __( 'Preview first', 'content-ops' ); // phpcs:ignore
	return (
		<Button variant="primary" isBusy={ executing } disabled={ disabled } onClick={ onExecute }>
			{ label }
		</Button>
	);
};

export default ExecuteButton;
```

```jsx
// assets/src/admin/components/ExecutionResult.jsx
import { Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const ExecutionResult = ( { execution, historyUrl } ) => {
	if ( ! execution ) return null;
	if ( execution.status === 'queued' ) {
		return (
			<Notice status="info" isDismissible={ false } role="status">
				{ __( 'Operation queued. It will run in the background.', 'content-ops' ) }{ ' ' }
				<a href={ `${ historyUrl }&operation=${ execution.operation_id }` }>{ __( 'View in history', 'content-ops' ) }</a>
			</Notice>
		);
	}
	const { processed = 0, succeeded = 0, failed = 0 } = execution.batch || {};
	return (
		<Notice status={ failed > 0 ? 'warning' : 'success' } isDismissible={ false } role="status">
			{ sprintf( __( '%1$d processed · %2$d succeeded · %3$d failed', 'content-ops' ), processed, succeeded, failed ) }
			{ ' ' }
			<a href={ `${ historyUrl }&operation=${ execution.operation_id }` }>{ __( 'View in history', 'content-ops' ) }</a>
		</Notice>
	);
};

export default ExecutionResult;
```

Integrate into `OperationsBuilder.jsx`. At the top of the file add:

```jsx
import PreviewPanel from '../components/PreviewPanel';
import ExecuteButton from '../components/ExecuteButton';
import ExecutionResult from '../components/ExecutionResult';
import { useDebouncedPreview } from '../hooks/useDebouncedPreview';
import { getBootstrap } from '../bootstrap';
import { normalizeError } from '../api';

const filtersToArgs = ( rows ) => {
	const out = {};
	for ( const row of rows ) {
		if ( row.key && row.value !== null && row.value !== undefined && row.value !== '' ) {
			out[ row.key ] = row.value;
		}
	}
	return out;
};
```

Inside the component body, after `[ state, dispatch ] = useReducer( ... )`:

```jsx
const bootstrap = getBootstrap();
const { preview, previewing, previewError } = useDebouncedPreview( api, state, filtersToArgs );

const execute = async () => {
	if ( ! preview || ! preview.preview_token ) return;
	dispatch( { type: 'SET_EXECUTING', value: true } );
	try {
		const res = await api.execute( {
			preview_token: preview.preview_token,
			target:        state.target,
			operation:     state.operation,
			filters:       filtersToArgs( state.filters ),
			params:        state.params,
		} );
		dispatch( { type: 'SET_EXECUTION', execution: res } );
	} catch ( err ) {
		dispatch( { type: 'SET_PREVIEW_ERROR', error: normalizeError( err ) } );
		dispatch( { type: 'SET_EXECUTING', value: false } );
	}
};
```

Render after the operation section:

```jsx
{ state.operation && (
	<section>
		<h2>{ __( 'Preview & Execute', 'content-ops' ) }</h2>
		<PreviewPanel preview={ preview } previewing={ previewing } previewError={ previewError } />
		<ExecuteButton preview={ preview } operation={ state.operation } onExecute={ execute } executing={ state.executing } />
		{ state.execution && <ExecutionResult execution={ state.execution } historyUrl={ bootstrap.pages.history } /> }
	</section>
) }
```

- [ ] **Step 4: Run tests — verify pass**

Run: `npm run test:js -- --testPathPattern="PreviewPanel|ExecuteButton|ExecutionResult"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/PreviewPanel.jsx assets/src/admin/components/ExecuteButton.jsx assets/src/admin/components/ExecutionResult.jsx assets/src/admin/__tests__/PreviewPanel.test.js assets/src/admin/__tests__/ExecuteButton.test.js assets/src/admin/__tests__/ExecutionResult.test.js assets/src/admin/pages/OperationsBuilder.jsx
git commit -m "feat: preview panel + execute button + execution result"
```

---

### Task 16: Operations Builder deep-link prefill

**Files:**
- Modify: `assets/src/admin/pages/OperationsBuilder.jsx`
- Create: `assets/src/admin/__tests__/OperationsBuilder.prefill.test.js`

- [ ] **Step 1: Write the failing test**

```js
// assets/src/admin/__tests__/OperationsBuilder.prefill.test.js
import { render, screen, waitFor } from '@testing-library/react';
import OperationsBuilder from '../pages/OperationsBuilder';

beforeAll( () => {
	global.window.contentOpsAdmin = {
		namespace: 'content-ops/v1', restUrl: 'x', nonce: 'n', capabilities: {},
		pages: { history: 'h', operations: 'o', dashboard: 'd', settings: 's' },
		adminUrl: 'a/', pluginUrl: 'p/', version: 'v',
	};
} );

describe( 'OperationsBuilder prefill', () => {
	it( 'prefills target/operation/filters from a matched preset', async () => {
		const api = {
			fetchCatalog: jest.fn().mockResolvedValue( {
				targets:    [ { slug: 'post', label: 'Posts', filters: [ { key: 'status', label: 'Status', type: 'enum', schema: {} } ] } ],
				operations: [ { slug: 'delete', label: 'Delete', params_schema: { type: 'object', properties: { permanent: { type: 'boolean' } } }, supports_undo: true } ],
				presets:    [ { slug: 'trash-old-drafts', label: 'Trash', description: '', target: 'post', operation: 'delete', filters: { status: 'draft' }, params: { permanent: false } } ],
			} ),
			preview: jest.fn().mockResolvedValue( { count: 0, sample_ids: [], preview_token: '', warnings: [], display_rows: [] } ),
		};

		window.history.replaceState( {}, '', '/?preset=trash-old-drafts' );
		render( <OperationsBuilder api={ api } /> );

		await waitFor( () => expect( screen.getByRole( 'button', { name: 'Posts' } ) ).toHaveAttribute( 'aria-pressed', 'true' ) );
		expect( screen.getByRole( 'button', { name: 'Delete' } ) ).toHaveAttribute( 'aria-pressed', 'true' );
	} );
} );
```

- [ ] **Step 2: Run test — verify failure**

Run: `npm run test:js -- --testPathPattern=OperationsBuilder.prefill`
Expected: FAIL.

- [ ] **Step 3: Add the prefill `useEffect`**

Near the top of `OperationsBuilder.jsx`:

```jsx
import { useEffect } from '@wordpress/element';

const readQuery = () => ( typeof window === 'undefined' ) ? new URLSearchParams() : new URLSearchParams( window.location.search );
const newRowId  = () => Math.random().toString( 36 ).slice( 2, 10 );
```

Inside the component body, after `dispatch` is declared:

```jsx
useEffect( () => {
	if ( ! catalog ) return;
	const params = readQuery();
	const presetSlug = params.get( 'preset' );
	const rerunId    = params.get( 'rerun' );
	const urlTarget    = params.get( 'target' );
	const urlOperation = params.get( 'operation' );
	const urlIds       = params.getAll( 'filters[ids][]' ).map( ( v ) => parseInt( v, 10 ) ).filter( ( n ) => ! Number.isNaN( n ) );

	if ( presetSlug ) {
		const preset = ( catalog.presets || [] ).find( ( p ) => p.slug === presetSlug );
		if ( preset ) {
			dispatch( { type: 'SET_TARGET', target: preset.target } );
			dispatch( { type: 'SET_OPERATION', operation: preset.operation } );
			const filterRows = Object.entries( preset.filters || {} )
				.map( ( [ key, value ] ) => ( { id: newRowId(), key, value } ) );
			dispatch( { type: 'SET_FILTERS', filters: filterRows } );
			dispatch( { type: 'SET_PARAMS', params: preset.params || {} } );
			return;
		}
	}

	if ( rerunId ) {
		api.getOperation( parseInt( rerunId, 10 ) )
			.then( ( op ) => {
				dispatch( { type: 'SET_TARGET', target: op.target } );
				dispatch( { type: 'SET_OPERATION', operation: op.type } );
				const rows = Object.entries( op.filters || {} )
					.map( ( [ key, value ] ) => ( { id: newRowId(), key, value } ) );
				dispatch( { type: 'SET_FILTERS', filters: rows } );
				dispatch( { type: 'SET_PARAMS', params: op.params || {} } );
			} )
			.catch( () => {} );
		return;
	}

	if ( urlTarget && urlOperation ) {
		dispatch( { type: 'SET_TARGET', target: urlTarget } );
		dispatch( { type: 'SET_OPERATION', operation: urlOperation } );
		if ( urlIds.length > 0 ) {
			dispatch( { type: 'SET_FILTERS', filters: [ { id: newRowId(), key: 'ids', value: urlIds } ] } );
		}
	}
}, [ catalog ] ); // eslint-disable-line react-hooks/exhaustive-deps
```

- [ ] **Step 4: Run test — verify pass**

Run: `npm run test:js -- --testPathPattern=OperationsBuilder.prefill`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/pages/OperationsBuilder.jsx assets/src/admin/__tests__/OperationsBuilder.prefill.test.js
git commit -m "feat: operations builder prefill from preset, rerun, and deep-link query strings"
```

---

### Task 17: History page — table + pagination

**Files:**
- Create: `assets/src/admin/components/HistoryTable.jsx`
- Create: `assets/src/admin/__tests__/HistoryTable.test.js`
- Modify: `assets/src/admin/pages/History.jsx`

- [ ] **Step 1: Write the failing test**

```js
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import HistoryTable from '../components/HistoryTable';

const rows = ( n ) => Array.from( { length: n }, ( _, i ) => ( {
	id: i + 1, type: 'delete', target: 'post', affected_count: 5, status: 'completed',
	user_id: 1, created_at: '2026-04-01 10:00:00', completed_at: '2026-04-01 10:00:01',
	filters: {}, params: {}, affected_ids: [ 10, 11 ],
} ) );

describe( 'HistoryTable', () => {
	it( 'loads first page and paginates', async () => {
		const api = { listOperations: jest.fn().mockResolvedValueOnce( rows( 20 ) ).mockResolvedValueOnce( rows( 3 ) ) };
		render( <HistoryTable api={ api } pageSize={ 20 } /> );
		await waitFor( () => expect( screen.getAllByRole( 'row' ) ).toHaveLength( 21 ) );
		await userEvent.click( screen.getByRole( 'button', { name: /next/i } ) );
		await waitFor( () => expect( api.listOperations ).toHaveBeenCalledWith( { limit: 20, offset: 20 } ) );
	} );
} );
```

- [ ] **Step 2: Run test — verify failure**

Run: `npm run test:js -- --testPathPattern=HistoryTable`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```jsx
// assets/src/admin/components/HistoryTable.jsx
import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const HistoryTable = ( { api, pageSize = 20, onRowAction } ) => {
	const [ page, setPage ] = useState( 0 );
	const [ rows, setRows ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		api.listOperations( { limit: pageSize, offset: page * pageSize } )
			.then( ( r ) => { if ( ! cancelled ) setRows( r ); } )
			.catch( () => { if ( ! cancelled ) setRows( [] ); } );
		return () => { cancelled = true; };
	}, [ api, page, pageSize ] );

	if ( rows === null ) return <Spinner />;

	return (
		<div>
			<table className="widefat striped">
				<thead>
					<tr>
						<th>{ __( 'Date', 'content-ops' ) }</th>
						<th>{ __( 'Type', 'content-ops' ) }</th>
						<th>{ __( 'Target', 'content-ops' ) }</th>
						<th>{ __( 'Items', 'content-ops' ) }</th>
						<th>{ __( 'Status', 'content-ops' ) }</th>
						<th>{ __( 'User', 'content-ops' ) }</th>
						<th>{ __( 'Actions', 'content-ops' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( r ) => (
						<tr key={ r.id }>
							<td>{ r.created_at }</td>
							<td>{ r.type }</td>
							<td>{ r.target }</td>
							<td>{ r.affected_count }</td>
							<td>{ r.status }</td>
							<td>{ r.user_id }</td>
							<td>
								<Button variant="link" onClick={ () => onRowAction && onRowAction( 'view', r ) }>{ __( 'Details', 'content-ops' ) }</Button>
								{ r.status === 'completed' && (
									<Button variant="link" onClick={ () => onRowAction && onRowAction( 'undo', r ) }>{ __( 'Undo', 'content-ops' ) }</Button>
								) }
								<Button variant="link" onClick={ () => onRowAction && onRowAction( 'rerun', r ) }>{ __( 'Re-run', 'content-ops' ) }</Button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<div className="content-ops-pagination">
				<Button variant="secondary" disabled={ page === 0 } onClick={ () => setPage( ( p ) => Math.max( 0, p - 1 ) ) }>{ __( 'Previous', 'content-ops' ) }</Button>
				<span>{ __( 'Page', 'content-ops' ) } { page + 1 }</span>
				<Button variant="secondary" disabled={ rows.length < pageSize } onClick={ () => setPage( ( p ) => p + 1 ) }>{ __( 'Next', 'content-ops' ) }</Button>
			</div>
		</div>
	);
};

export default HistoryTable;
```

```jsx
// assets/src/admin/pages/History.jsx
import { __ } from '@wordpress/i18n';
import HistoryTable from '../components/HistoryTable';
import { createApi } from '../api';

const History = ( { api = createApi() } ) => (
	<div>
		<h1>{ __( 'Operations history', 'content-ops' ) }</h1>
		<HistoryTable api={ api } />
	</div>
);

export default History;
```

- [ ] **Step 4: Run test — verify pass**

Run: `npm run test:js -- --testPathPattern=HistoryTable`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/HistoryTable.jsx assets/src/admin/__tests__/HistoryTable.test.js assets/src/admin/pages/History.jsx
git commit -m "feat: history page with paginated table"
```

---

### Task 18: History row actions — details modal, undo, re-run deep link

**Files:**
- Create: `assets/src/admin/components/OperationDetailsModal.jsx`
- Create: `assets/src/admin/__tests__/OperationDetailsModal.test.js`
- Modify: `assets/src/admin/pages/History.jsx`
- Modify: `assets/src/admin/__tests__/HistoryTable.test.js`

- [ ] **Step 1: Write failing tests**

```js
// assets/src/admin/__tests__/OperationDetailsModal.test.js
import { render, screen } from '@testing-library/react';
import OperationDetailsModal from '../components/OperationDetailsModal';

describe( 'OperationDetailsModal', () => {
	it( 'renders JSON of filters and params and the list of affected IDs', () => {
		render( <OperationDetailsModal
			operation={ { id: 1, filters: { status: 'draft' }, params: { permanent: false }, affected_ids: [ 10, 11, 12 ] } }
			onClose={ () => {} }
		/> );
		expect( screen.getByText( /"status": "draft"/ ) ).toBeInTheDocument();
		expect( screen.getByText( /10, 11, 12/ ) ).toBeInTheDocument();
	} );
} );
```

Append to `HistoryTable.test.js`:

```js
it( 'invokes onRowAction with view / undo / rerun', async () => {
	const onRowAction = jest.fn();
	const api = { listOperations: jest.fn().mockResolvedValue( rows( 1 ) ) };
	render( <HistoryTable api={ api } pageSize={ 20 } onRowAction={ onRowAction } /> );
	await waitFor( () => screen.getAllByRole( 'row' ) );
	await userEvent.click( screen.getByRole( 'button', { name: /details/i } ) );
	expect( onRowAction ).toHaveBeenCalledWith( 'view', expect.any( Object ) );
	await userEvent.click( screen.getByRole( 'button', { name: /undo/i } ) );
	expect( onRowAction ).toHaveBeenCalledWith( 'undo', expect.any( Object ) );
} );
```

- [ ] **Step 2: Run tests — verify failure**

Run: `npm run test:js -- --testPathPattern="OperationDetailsModal|HistoryTable"`
Expected: FAIL for new cases.

- [ ] **Step 3: Write implementation**

```jsx
// assets/src/admin/components/OperationDetailsModal.jsx
import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const OperationDetailsModal = ( { operation, onClose } ) => (
	<Modal title={ __( 'Operation details', 'content-ops' ) } onRequestClose={ onClose } shouldCloseOnClickOutside={ true }>
		<h3>{ __( 'Filters', 'content-ops' ) }</h3>
		<pre>{ JSON.stringify( operation.filters || {}, null, 2 ) }</pre>
		<h3>{ __( 'Parameters', 'content-ops' ) }</h3>
		<pre>{ JSON.stringify( operation.params || {}, null, 2 ) }</pre>
		<h3>{ __( 'Affected IDs', 'content-ops' ) }</h3>
		<p>{ ( operation.affected_ids || [] ).join( ', ' ) }</p>
		<Button variant="primary" onClick={ onClose }>{ __( 'Close', 'content-ops' ) }</Button>
	</Modal>
);

export default OperationDetailsModal;
```

Rewrite `pages/History.jsx`:

```jsx
import { useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import HistoryTable from '../components/HistoryTable';
import OperationDetailsModal from '../components/OperationDetailsModal';
import { createApi, normalizeError } from '../api';
import { getBootstrap } from '../bootstrap';

const History = ( { api = createApi(), bootstrap = getBootstrap() } ) => {
	const [ viewing, setViewing ]   = useState( null );
	const [ notice, setNotice ]     = useState( null );
	const [ reloadKey, setReloadKey ] = useState( 0 );

	const onRowAction = async ( action, row ) => {
		if ( action === 'view' ) setViewing( row );
		if ( action === 'undo' ) {
			try {
				const r = await api.undoOperation( row.id );
				setNotice( { status: 'success', text: __( 'Restored: ', 'content-ops' ) + r.restored } );
				setReloadKey( ( k ) => k + 1 );
			} catch ( err ) {
				setNotice( { status: 'error', text: normalizeError( err ).message } );
			}
		}
		if ( action === 'rerun' ) {
			const params = new URLSearchParams();
			params.set( 'rerun', String( row.id ) );
			window.open( `${ bootstrap.pages.operations }&${ params.toString() }`, '_blank', 'noopener' );
		}
	};

	return (
		<div>
			<h1>{ __( 'Operations history', 'content-ops' ) }</h1>
			{ notice && <Notice status={ notice.status } onRemove={ () => setNotice( null ) }>{ notice.text }</Notice> }
			<HistoryTable key={ reloadKey } api={ api } onRowAction={ onRowAction } />
			{ viewing && <OperationDetailsModal operation={ viewing } onClose={ () => setViewing( null ) } /> }
		</div>
	);
};

export default History;
```

- [ ] **Step 4: Run tests — verify pass**

Run: `npm run test:js -- --testPathPattern="OperationDetailsModal|HistoryTable"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/OperationDetailsModal.jsx assets/src/admin/__tests__/OperationDetailsModal.test.js assets/src/admin/__tests__/HistoryTable.test.js assets/src/admin/pages/History.jsx
git commit -m "feat: history row actions with details modal, undo, and re-run deep link"
```

---

### Task 19: Settings page — SettingsForm

**Files:**
- Create: `assets/src/admin/components/SettingsForm.jsx`
- Create: `assets/src/admin/__tests__/SettingsForm.test.js`
- Modify: `assets/src/admin/pages/Settings.jsx`

- [ ] **Step 1: Write the failing test**

```js
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SettingsForm from '../components/SettingsForm';

describe( 'SettingsForm', () => {
	it( 'loads settings, edits them, and saves', async () => {
		const api = {
			getSettings:  jest.fn().mockResolvedValue( {
				async_threshold: 100, batch_size: 50, delete_permanent_default: false,
				history_retention_days: 30, role_caps: {},
			} ),
			saveSettings: jest.fn().mockResolvedValue( {
				async_threshold: 250, batch_size: 50, delete_permanent_default: false,
				history_retention_days: 30, role_caps: {},
			} ),
		};
		render( <SettingsForm api={ api } /> );
		const threshold = await screen.findByLabelText( /async threshold/i );
		await userEvent.clear( threshold );
		await userEvent.type( threshold, '250' );
		await userEvent.click( screen.getByRole( 'button', { name: /save/i } ) );
		await waitFor( () => expect( api.saveSettings ).toHaveBeenCalledWith( expect.objectContaining( { async_threshold: 250 } ) ) );
		expect( await screen.findByText( /saved/i ) ).toBeInTheDocument();
	} );
} );
```

- [ ] **Step 2: Run test — verify failure**

Run: `npm run test:js -- --testPathPattern=SettingsForm`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```jsx
// assets/src/admin/components/SettingsForm.jsx
import { useEffect, useState } from '@wordpress/element';
import { TextControl, ToggleControl, Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { normalizeError } from '../api';

const SettingsForm = ( { api } ) => {
	const [ values, setValues ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		api.getSettings().then( setValues ).catch( ( e ) => setNotice( { status: 'error', text: normalizeError( e ).message } ) );
	}, [ api ] );

	if ( ! values ) return <Spinner />;

	const update = ( patch ) => setValues( ( prev ) => ( { ...prev, ...patch } ) );

	const save = async () => {
		setSaving( true );
		try {
			const next = await api.saveSettings( values );
			setValues( next );
			setNotice( { status: 'success', text: __( 'Settings saved.', 'content-ops' ) } );
		} catch ( err ) {
			setNotice( { status: 'error', text: normalizeError( err ).message } );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="content-ops-settings-form">
			{ notice && <Notice status={ notice.status } onRemove={ () => setNotice( null ) }>{ notice.text }</Notice> }

			<h2>{ __( 'General', 'content-ops' ) }</h2>
			<TextControl label={ __( 'Async threshold', 'content-ops' ) } type="number"
				value={ String( values.async_threshold ) } onChange={ ( v ) => update( { async_threshold: parseInt( v, 10 ) || 0 } ) } />
			<TextControl label={ __( 'Batch size', 'content-ops' ) } type="number"
				value={ String( values.batch_size ) } onChange={ ( v ) => update( { batch_size: parseInt( v, 10 ) || 0 } ) } />
			<ToggleControl label={ __( 'Default to permanent delete (skip trash)', 'content-ops' ) }
				checked={ !! values.delete_permanent_default } onChange={ ( v ) => update( { delete_permanent_default: v } ) } />

			<h2>{ __( 'History', 'content-ops' ) }</h2>
			<TextControl label={ __( 'Retention (days)', 'content-ops' ) } type="number"
				value={ String( values.history_retention_days ) } onChange={ ( v ) => update( { history_retention_days: parseInt( v, 10 ) || 0 } ) } />

			<Button variant="primary" isBusy={ saving } onClick={ save }>{ __( 'Save settings', 'content-ops' ) }</Button>
		</div>
	);
};

export default SettingsForm;
```

Replace `pages/Settings.jsx`:

```jsx
import { __ } from '@wordpress/i18n';
import SettingsForm from '../components/SettingsForm';
import { createApi } from '../api';
import { getBootstrap } from '../bootstrap';

const Settings = ( { api = createApi(), bootstrap = getBootstrap() } ) => (
	<div>
		<h1>{ __( 'Settings', 'content-ops' ) }</h1>
		<SettingsForm api={ api } />
		<h2>{ __( 'AI agent access', 'content-ops' ) }</h2>
		<p>
			{ __( 'Generate a scoped Application Password in the WordPress users admin:', 'content-ops' ) }{ ' ' }
			<a href={ bootstrap.adminUrl + 'users.php' }>{ __( 'Users admin', 'content-ops' ) }</a>
		</p>
	</div>
);

export default Settings;
```

- [ ] **Step 4: Run test — verify pass**

Run: `npm run test:js -- --testPathPattern=SettingsForm`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/src/admin/components/SettingsForm.jsx assets/src/admin/__tests__/SettingsForm.test.js assets/src/admin/pages/Settings.jsx
git commit -m "feat: settings form + agent access panel"
```

---

### Task 20: Post list row actions + bulk actions (PHP)

**Files:**
- Create: `src/Admin/PostListIntegration.php`
- Modify: `src/Plugin.php`
- Test: `tests/integration/Admin/PostListIntegrationTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
namespace ContentOps\Tests\Integration\Admin;

use ContentOps\Admin\PostListIntegration;
use ContentOps\Tests\Integration\TestCase;

final class PostListIntegrationTest extends TestCase {

	public function test_injects_duplicate_row_action_for_administrator(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create();
		$integ   = new PostListIntegration( 'http://example.test/wp-admin/admin.php?page=content-ops-operations' );
		$actions = $integ->filter_row_actions( [ 'edit' => '<a>Edit</a>' ], get_post( $post_id ) );

		$this->assertArrayHasKey( 'content_ops_duplicate', $actions );
		$this->assertStringContainsString( 'page=content-ops-operations', $actions['content_ops_duplicate'] );
		$this->assertStringContainsString( 'operation=duplicate', $actions['content_ops_duplicate'] );
		$this->assertStringContainsString( 'target=post', $actions['content_ops_duplicate'] );
		$this->assertStringContainsString( 'filters%5Bids%5D%5B%5D=' . $post_id, $actions['content_ops_duplicate'] );
	}

	public function test_injects_bulk_actions(): void {
		$integ = new PostListIntegration( 'http://example.test/' );
		$bulk  = $integ->filter_bulk_actions( [ 'trash' => 'Trash' ] );
		$this->assertArrayHasKey( 'content_ops_delete',    $bulk );
		$this->assertArrayHasKey( 'content_ops_duplicate', $bulk );
		$this->assertArrayHasKey( 'content_ops_edit',      $bulk );
	}

	public function test_handle_bulk_action_returns_operations_builder_url_with_ids(): void {
		$url   = 'http://example.test/edit.php';
		$integ = new PostListIntegration( 'http://example.test/wp-admin/admin.php?page=content-ops-operations' );
		$_REQUEST['post_type'] = 'post';
		$out   = $integ->handle_bulk_action( $url, 'content_ops_delete', [ 1, 2, 3 ] );
		$this->assertStringContainsString( 'operation=delete', $out );
		$this->assertStringContainsString( 'filters%5Bids%5D%5B%5D=1', $out );
		$this->assertStringContainsString( 'filters%5Bids%5D%5B%5D=3', $out );
		unset( $_REQUEST['post_type'] );
	}
}
```

- [ ] **Step 2: Run tests — verify failure**

Run: `composer test:integration -- --filter=PostListIntegrationTest`
Expected: FAIL.

- [ ] **Step 3: Write implementation**

```php
<?php
namespace ContentOps\Admin;

use WP_Post;

final class PostListIntegration {

	private string $operations_url;

	public function __construct( string $operations_url ) {
		$this->operations_url = $operations_url;
	}

	public function register(): void {
		foreach ( get_post_types( [ 'public' => true ] ) as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", [ $this, 'filter_bulk_actions' ] );
			add_filter( "handle_bulk_actions-edit-{$post_type}", [ $this, 'handle_bulk_action' ], 10, 3 );
		}
		add_filter( 'post_row_actions', [ $this, 'filter_row_actions' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'filter_row_actions' ], 10, 2 );
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function filter_row_actions( array $actions, WP_Post $post ): array {
		if ( ! current_user_can( 'content_ops_duplicate' ) ) {
			return $actions;
		}
		$url = $this->build_url( 'duplicate', $post->post_type, [ 'ids' => [ (int) $post->ID ] ] );
		$actions['content_ops_duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Duplicate with Content Ops', 'content-ops' )
		);
		return $actions;
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function filter_bulk_actions( array $actions ): array {
		return array_merge(
			$actions,
			[
				'content_ops_delete'    => __( 'Content Ops: Delete', 'content-ops' ),
				'content_ops_duplicate' => __( 'Content Ops: Duplicate', 'content-ops' ),
				'content_ops_edit'      => __( 'Content Ops: Bulk edit', 'content-ops' ),
			]
		);
	}

	/**
	 * @param int[] $post_ids
	 */
	public function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( 0 !== strpos( $action, 'content_ops_' ) ) {
			return $redirect_to;
		}
		$op        = substr( $action, strlen( 'content_ops_' ) );
		$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $this->build_url( $op, $post_type, [ 'ids' => array_map( 'intval', $post_ids ) ] );
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	private function build_url( string $operation, string $target, array $filters ): string {
		return add_query_arg(
			[
				'target'    => $target,
				'operation' => $operation,
				'filters'   => $filters,
			],
			$this->operations_url
		);
	}
}
```

Wire into `Plugin.php` after the asset loader:

```php
$post_list_integration = new \ContentOps\Admin\PostListIntegration(
	admin_url( 'admin.php?page=content-ops-operations' )
);
$post_list_integration->register();
$this->set( 'admin.post_list', $post_list_integration );
```

Known limitation: `PostTarget` does not yet register an `ids` filter, so the deep-link preview from this flow will return count = 0 until a Phase 1b.1 follow-up adds it. Document this in the commit message.

- [ ] **Step 4: Run tests — verify pass**

Run: `composer test:integration -- --filter=PostListIntegrationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Admin/PostListIntegration.php src/Plugin.php tests/integration/Admin/PostListIntegrationTest.php
git commit -m "feat: post list row and bulk actions deep-link into operations builder

Known limitation: the 'ids' filter emitted by this deep link is not yet recognised
by PostTarget — follow-up in Phase 1b.1 will add an ids FilterDefinition."
```

---

### Task 21: Playwright E2E harness + Operations Builder happy path

**Files:**
- Modify: `package.json`
- Create: `tests/e2e/playwright.config.ts`
- Create: `tests/e2e/fixtures.ts`
- Create: `tests/e2e/operations-builder.spec.ts`

- [ ] **Step 1: Install Playwright**

Run:

```bash
npm install --save-dev @playwright/test @wordpress/e2e-test-utils-playwright
npx playwright install --with-deps chromium
```

Add to `package.json` scripts:

```json
"test:e2e": "playwright test -c tests/e2e/playwright.config.ts",
"test:e2e:install": "playwright install --with-deps chromium"
```

- [ ] **Step 2: Write config and fixtures**

```ts
// tests/e2e/playwright.config.ts
import { defineConfig } from '@playwright/test';

export default defineConfig( {
	testDir: __dirname,
	timeout: 60_000,
	retries: 0,
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		storageState: undefined,
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	reporter: 'list',
} );
```

```ts
// tests/e2e/fixtures.ts
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';
export const test = base;
export { expect };
```

- [ ] **Step 3: Write the failing test**

```ts
// tests/e2e/operations-builder.spec.ts
import { test, expect } from './fixtures';

test.describe( 'Operations Builder — happy path', () => {

	test.beforeEach( async ( { requestUtils, admin } ) => {
		await requestUtils.activatePlugin( 'content-ops' );
		for ( let i = 0; i < 3; i++ ) {
			await requestUtils.rest( {
				method: 'POST',
				path:   '/wp/v2/posts',
				data:   { title: `E2E draft ${ i + 1 }`, status: 'draft', content: 'seeded' },
			} );
		}
		await admin.visitAdminPage( 'admin.php', 'page=content-ops-operations' );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'admin can preview and execute a delete on drafts', async ( { page } ) => {
		await expect( page.getByRole( 'heading', { name: 'Operations Builder' } ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Posts' } ).click();

		await page.getByRole( 'button', { name: 'Add filter' } ).click();
		await page.getByRole( 'combobox', { name: 'Filter' } ).selectOption( 'status' );
		await page.getByLabel( 'Status' ).fill( 'draft' );

		await page.getByRole( 'button', { name: 'Delete', exact: true } ).click();

		await expect( page.getByText( /Matched: 3 items/ ) ).toBeVisible( { timeout: 5000 } );
		await expect( page.getByText( 'E2E draft 1' ) ).toBeVisible();

		const executeBtn = page.getByRole( 'button', { name: /Will delete 3 items/i } );
		await expect( executeBtn ).toBeEnabled();
		await executeBtn.click();

		await expect( page.getByRole( 'status' ) ).toContainText( /3 succeeded/ );
	} );
} );
```

- [ ] **Step 4: Run the test**

Prerequisites: `npm run env:start` and `npm run build`. Then:

```bash
npm run test:e2e -- operations-builder.spec.ts
```

Expected: PASS (once all prior tasks are in).

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json tests/e2e/playwright.config.ts tests/e2e/fixtures.ts tests/e2e/operations-builder.spec.ts
git commit -m "test: playwright e2e for operations builder happy path"
```

---

### Task 22: Playwright E2E — History page undo flow

**Files:**
- Create: `tests/e2e/history-undo.spec.ts`

- [ ] **Step 1: Write the failing test**

```ts
// tests/e2e/history-undo.spec.ts
import { test, expect } from './fixtures';

test.describe( 'History — undo', () => {

	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'content-ops' );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'admin can undo a delete from history', async ( { page, admin, requestUtils } ) => {
		const seeded = await requestUtils.rest( {
			method: 'POST',
			path:   '/wp/v2/posts',
			data:   { title: 'Undo target', status: 'draft' },
		} );
		const postId = ( seeded as any ).id as number;

		const preview = await requestUtils.rest( {
			method: 'POST',
			path:   '/content-ops/v1/preview',
			data:   { target: 'post', operation: 'delete', filters: { status: 'draft' }, params: {} },
		} );
		const exec = await requestUtils.rest( {
			method: 'POST',
			path:   '/content-ops/v1/execute',
			data:   {
				preview_token: ( preview as any ).preview_token,
				target:        'post',
				operation:     'delete',
				filters:       { status: 'draft' },
				params:        {},
			},
		} );
		const opId = ( exec as any ).operation_id as number;

		await admin.visitAdminPage( 'admin.php', 'page=content-ops-history' );
		await expect( page.getByText( String( opId ), { exact: false } ) ).toBeVisible();

		const row = page.getByRole( 'row' ).filter( { hasText: String( opId ) } );
		await row.getByRole( 'button', { name: /undo/i } ).click();

		await expect( page.getByText( /Restored:/ ) ).toBeVisible();

		const checked = await requestUtils.rest( { method: 'GET', path: `/wp/v2/posts/${ postId }` } );
		expect( ( checked as any ).status ).toBe( 'draft' );
	} );
} );
```

- [ ] **Step 2: Run the test**

```bash
npm run test:e2e -- history-undo.spec.ts
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/history-undo.spec.ts
git commit -m "test: playwright e2e for history undo flow"
```

---

### Task 23: Verification + CHANGELOG + tag `v0.3.0-alpha`

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `content-ops.php` (version header + `CONTENT_OPS_VERSION`)

- [ ] **Step 1: Run the full verification matrix**

```bash
composer test:unit
composer test:integration
composer lint
composer stan
npm run lint:js
npm run test:js
npm run build
npm run env:start
npm run test:e2e
```

Each must exit 0. Fix any red before proceeding.

- [ ] **Step 2: Manual smoke test**

Visit `http://localhost:8888/wp-admin/admin.php?page=content-ops` and confirm:
- Dashboard renders StatsCard + HealthPanel + PresetCards + RecentOperationsList.
- Operations Builder: select Posts → add status=draft filter → click Delete → see "Matched: N items" with sample rows → click "Will delete N items" → see success notice.
- History: table loads, Details opens modal with JSON, Undo restores from trash, Re-run opens new tab with prefilled builder.
- Settings: form loads defaults, edit async threshold + save, reload, value persists.
- `/wp-admin/edit.php`: "Duplicate with Content Ops" appears under each row; "Content Ops: Delete / Duplicate / Bulk edit" appears in Bulk Actions dropdown; submitting a bulk action redirects to Operations Builder with prefilled target/operation/ids query string.

- [ ] **Step 3: Update version + changelog**

In `content-ops.php`, change `Version:` header and `CONTENT_OPS_VERSION` constant to `0.3.0-alpha`.

Prepend this entry to `CHANGELOG.md` above the `[0.2.0-alpha]` section:

```markdown
## [0.3.0-alpha] - 2026-04-23

### Added

Phase 1b admin UI — the plugin now has a polished React admin with no WP-CLI or raw REST required.

#### Admin menu + assets
- Top-level Content Ops menu (dashicon `list-view`) with Dashboard, Operations, History, Settings submenus.
- Single webpack bundle (`assets/build/admin.js`) auto-detecting the mounted page and rendering the matching component.
- Bootstrap payload (`window.contentOpsAdmin`) localizes REST URL, nonce, capabilities, and admin page URLs.

#### REST additions
- `GET /content-ops/v1/settings` and `POST /content-ops/v1/settings` backed by a single `content_ops_settings` option.
- `POST /content-ops/v1/preview` response gains `display_rows` so the UI can render sample rows with titles, statuses, dates, and edit links without a second round-trip.

#### Dashboard
- StatsCard (ops this week + items affected), HealthPanel (live `/doctor`), PresetCards (Common Cleanups from `/catalog.presets`), RecentOperationsList (last 5 with inline Undo).

#### Operations Builder
- Target pill selector, filter-row builder (enum / bool / date / user / post / taxonomy inputs), operation selector, params form derived from JSON schema.
- Debounced live preview (300 ms, AbortController) with sample table (20 rows) + warnings.
- Execute button armed with a fresh preview token; sync completion + async queued states handled.
- Deep-link prefill via `?preset=`, `?rerun=`, and `?target=...&operation=...&filters[ids][]=...` query strings.

#### History
- Paginated operations table with row actions: View details (JSON modal), Undo (inline), Re-run (new-tab deep link into Operations Builder).

#### Settings
- General (async threshold, batch size, delete-permanent default), History (retention days), AI agent access panel linking to WordPress users admin for Application Password generation.

#### Post list integrations
- "Duplicate with Content Ops" row action across public post types.
- "Content Ops: Delete / Duplicate / Bulk edit" entries in the native Bulk Actions dropdown. Both deep-link (query-string prefilled) to Operations Builder.

### Known limitations
- The `filters[ids][]=…` deep link emitted by post-list row + bulk actions is accepted by the Operations Builder but PostTarget does not yet register an `ids` filter — previews from that flow return count=0 until a Phase 1b.1 follow-up adds it.

### Test coverage
- Integration: AdminMenu, AssetLoader, Settings, SettingsRoute, PreviewRoute display_rows, PostListIntegration.
- Jest: api, router, bootstrap, builderReducer, TargetPicker, FilterRow, FilterList, OperationPicker, OperationParamsForm, useDebouncedPreview, PreviewPanel, ExecuteButton, ExecutionResult, HistoryTable, OperationDetailsModal, StatsCard, RecentOperationsList, PresetCards, SettingsForm, OperationsBuilder.prefill.
- E2E: operations-builder happy path, history undo.
```

- [ ] **Step 4: Commit + tag**

```bash
git add content-ops.php CHANGELOG.md
git commit -m "chore: release v0.3.0-alpha (Phase 1b admin UI)"
git tag -a v0.3.0-alpha -m "Phase 1b — admin UI"
```

- [ ] **Step 5: Final sanity check**

```bash
git log --oneline -5
git tag --list 'v0.3.0-alpha'
```

Expected: release commit on top, tag present.

---

## Self-review results

**Spec §8 coverage:**
- §8.1 Menu — Task 1 (top-level + submenus), Task 20 (post-list row + bulk actions).
- §8.2 Operations Builder — Tasks 10 (shell + target), 11–12 (filters), 13 (operation + params), 14 (debounced live preview), 15 (preview panel + execute + result), 16 (preset/rerun/deep-link prefill).
- §8.3 Dashboard — Tasks 7 (HealthPanel), 8 (StatsCard + RecentOperationsList), 9 (PresetCards).
- §8.4 History — Tasks 17 (table + pagination), 18 (details / undo / re-run).
- §8.5 Schedules (Pro) — explicitly deferred.
- §8.6 Settings — Tasks 5 (storage + REST) and 19 (form + agent panel).
- §8.7 Confirmation philosophy — enforced by ExecuteButton's "preview token required to enable" behavior in Task 15.

**Placeholder scan:** Every step has concrete code or a concrete command. No TBD/TODO/"add appropriate error handling" language. The one caveat ("ids filter on PostTarget needs a Phase 1b.1 follow-up") is called out explicitly in Task 20 and the CHANGELOG entry, not swept under the rug.

**Type / name consistency:**
- Reducer actions: `SET_TARGET`, `SET_OPERATION`, `SET_PARAMS`, `ADD_FILTER`, `UPDATE_FILTER`, `REMOVE_FILTER`, `SET_FILTERS`, `SET_PREVIEW`, `SET_PREVIEWING`, `SET_PREVIEW_ERROR`, `SET_EXECUTING`, `SET_EXECUTION`, `RESET` — all defined in Task 10, consistent thereafter.
- `createApi` methods `fetchCatalog`, `preview`, `execute`, `listOperations`, `getOperation`, `undoOperation`, `fetchDoctor`, `getSettings`, `saveSettings` — defined in Task 4, referenced consistently.
- PHP classes `AdminMenu`, `AssetLoader`, `Settings`, `SettingsController`, `PostListIntegration` — created and wired in the Plugin constructor in the same tasks that introduce them.
- Option key `content_ops_settings` (Task 5) matches `Settings::OPTION` referenced in tests.
- REST namespace `content-ops/v1` consistent with Phase 1a routes.
