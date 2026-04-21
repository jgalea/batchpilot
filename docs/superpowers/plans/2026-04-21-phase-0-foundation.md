# Phase 0 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Content Ops plugin foundation — a tested platform that Phase 1 features drop into. No user-facing operations yet; this plan produces plumbing plus one end-to-end verification (the doctor command).

**Architecture:** WordPress plugin with PSR-4 Composer autoload under namespace `ContentOps\`. Core abstractions are `TargetInterface` (kind of thing) and `OperationInterface` (what to do); phase 1+ plugs concrete targets and operations into registries. Three custom tables store operation history, undo snapshots, and schedule definitions. Preview tokens, Action Scheduler async execution, structured errors, capability gating, REST + WP-CLI + Abilities API are wired in as cross-cutting mechanisms. Admin UI scaffold uses `@wordpress/scripts` + React but renders nothing yet. All behavior is covered by unit tests (brain-monkey) and integration tests (wp-phpunit against real WordPress via wp-env).

**Tech Stack:**
- PHP 7.4+, WordPress 6.3+, Composer PSR-4
- Action Scheduler 3.x (bundled via Composer, with environment detection to prefer an already-loaded copy in WooCommerce installs)
- PHPUnit 9.x, brain-monkey, wp-phpunit, yoast/phpunit-polyfills
- `@wordpress/scripts`, React, Jest (via wp-scripts)
- `wp-env` for local dev
- PHPCS + WordPress-Coding-Standards, PHPStan + szepeviktor/phpstan-wordpress
- GitHub Actions CI

---

## File structure at end of Phase 0

```
content-ops/
├── content-ops.php              # plugin bootstrap (header + require autoload + Plugin::boot)
├── uninstall.php                # drops tables if admin opted in
├── composer.json / composer.lock
├── package.json / package-lock.json
├── phpunit.xml.dist             # unit tests (brain-monkey, no WP)
├── phpunit-integration.xml.dist # integration tests (wp-phpunit)
├── phpcs.xml.dist
├── phpstan.neon.dist
├── .wp-env.json
├── .gitignore
├── .editorconfig
├── LICENSE                      # GPL-2.0-or-later
├── README.md
├── CHANGELOG.md
├── .github/workflows/ci.yml
├── assets/src/admin/index.js    # React entry (empty scaffold)
├── assets/build/                # wp-scripts output, gitignored
├── src/
│   ├── Plugin.php
│   ├── Activator.php
│   ├── Deactivator.php
│   ├── Abilities/AbilitiesBridge.php
│   ├── Async/ActionSchedulerBridge.php
│   ├── Capabilities/Capabilities.php
│   ├── CLI/CommandRegistrar.php
│   ├── CLI/DoctorCommand.php
│   ├── Contracts/
│   │   ├── TargetInterface.php
│   │   ├── OperationInterface.php
│   │   ├── QueryArgs.php
│   │   ├── FilterDefinition.php
│   │   ├── ValidationResult.php
│   │   ├── PreviewResult.php
│   │   ├── BatchResult.php
│   │   └── UndoResult.php
│   ├── Database/Schema.php
│   ├── Database/Migrations.php
│   ├── Errors/ContentOpsError.php
│   ├── History/Operation.php
│   ├── History/OperationRepository.php
│   ├── History/Snapshot.php
│   ├── History/SnapshotRepository.php
│   ├── PreviewToken/TokenGenerator.php
│   ├── PreviewToken/TokenStore.php
│   ├── PreviewToken/TokenVerifier.php
│   ├── Registry/TargetRegistry.php
│   ├── Registry/OperationRegistry.php
│   └── REST/
│       ├── RestController.php
│       ├── DoctorController.php
│       └── RouteRegistrar.php
├── tests/unit/...
├── tests/integration/...
└── docs/
    ├── development.md
    ├── superpowers/specs/2026-04-21-content-ops-plugin-design.md
    └── superpowers/plans/2026-04-21-phase-0-foundation.md
```

Responsibilities:
- `Plugin.php` — service locator and boot orchestrator. Instantiates components, wires hooks, exposes them via getters for tests.
- `Activator`/`Deactivator` — fire on activation/deactivation, delegate to `Schema`, `Migrations`, `Capabilities`.
- `Contracts/` — pure data/interface types, no WP coupling, fully unit-testable.
- `Database/Schema` — DDL via `dbDelta`; `Migrations` — version-bumped migrations keyed on `content_ops_schema_version` option.
- `History/*Repository` — CRUD for custom tables using `$wpdb`.
- `PreviewToken/*` — hash-based preview-to-execute handoff; store is transient-backed.
- `Async/ActionSchedulerBridge` — detects or loads Action Scheduler, exposes scheduling helpers.
- `Registry/*` — in-memory holders keyed by slug.
- `Errors/ContentOpsError` — structured error object convertible to `WP_Error` and REST responses.
- `CLI/*` — WP-CLI registrar + the `wp content-ops doctor` end-to-end smoke command.
- `Abilities/AbilitiesBridge` — detects Abilities API, registers the `content-ops/doctor` ability as Phase 0 proof-of-integration.
- `REST/*` — base controller + route registrar; only wires the `/doctor` endpoint in Phase 0.

---

## Task 1: Initialize plugin skeleton and git repository

**Files:**
- Create: `content-ops.php`
- Create: `.gitignore`
- Create: `.editorconfig`
- Create: `LICENSE`
- Create: `README.md`
- Create: `CHANGELOG.md`

Plan specs already live at `docs/superpowers/specs/2026-04-21-content-ops-plugin-design.md` and `docs/superpowers/plans/2026-04-21-phase-0-foundation.md`. This task initializes the git repo so everything can be committed.

- [ ] **Step 1: Initialize git repo in plugin root**

Run from `content-ops/` directory:
```bash
git init -b main
```

Expected: `Initialized empty Git repository in .../content-ops/.git/`

- [ ] **Step 2: Create `.gitignore`**

```gitignore
/vendor/
/node_modules/
/assets/build/
/assets/build-types/
composer.lock.bak
.DS_Store
*.log
/.phpunit.result.cache
/.phpcs.cache
/.phpstan-cache/
.idea/
.vscode/
/coverage/
```

- [ ] **Step 3: Create `.editorconfig`**

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = tab

[*.{yml,yaml,json,md}]
indent_style = space
indent_size = 2

[*.php]
indent_style = tab
```

- [ ] **Step 4: Create `LICENSE` (GPL-2.0-or-later)**

Paste the full text of the GNU General Public License, version 2, from https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt.

- [ ] **Step 5: Create `content-ops.php`**

```php
<?php
/**
 * Plugin Name:       Content Ops
 * Plugin URI:        https://contentops.example
 * Description:       Bulk operations for WordPress and WooCommerce, designed for humans and AI agents.
 * Version:           0.1.0-alpha
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Jean Galea
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       content-ops
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONTENT_OPS_VERSION', '0.1.0-alpha' );
define( 'CONTENT_OPS_PLUGIN_FILE', __FILE__ );
define( 'CONTENT_OPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

$content_ops_autoload = CONTENT_OPS_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $content_ops_autoload ) ) {
	add_action( 'admin_notices', static function () {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'Content Ops: run `composer install` in the plugin directory before activating.', 'content-ops' );
		echo '</p></div>';
	} );
	return;
}
require $content_ops_autoload;

\ContentOps\Plugin::boot( __FILE__ );
```

- [ ] **Step 6: Create `README.md`**

```markdown
# Content Ops

Bulk operations for WordPress and WooCommerce — designed for humans and AI agents.

> Pre-release. Phase 0 (foundation) in progress. See `docs/superpowers/specs/` for design, `docs/superpowers/plans/` for implementation plans.

## Development

See `docs/development.md`.

## License

GPL-2.0-or-later.
```

- [ ] **Step 7: Create `CHANGELOG.md`**

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [Unreleased]
```

- [ ] **Step 8: Commit**

```bash
git add .gitignore .editorconfig LICENSE content-ops.php README.md CHANGELOG.md docs/
git commit -m "chore: initialize plugin skeleton with bootstrap, license, and docs"
```

---

## Task 2: Composer setup and Plugin boot class

**Files:**
- Create: `composer.json`
- Create: `src/Plugin.php`
- Create: `src/Activator.php`
- Create: `src/Deactivator.php`

- [ ] **Step 1: Create `composer.json`**

```json
{
  "name": "jeangalea/content-ops",
  "description": "Bulk operations for WordPress and WooCommerce, designed for humans and AI agents.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=7.4",
    "woocommerce/action-scheduler": "^3.7"
  },
  "require-dev": {
    "brain/monkey": "^2.6",
    "phpunit/phpunit": "^9.6",
    "yoast/phpunit-polyfills": "^2.0",
    "wp-phpunit/wp-phpunit": "^6.5",
    "wp-cli/wp-cli": "^2.10",
    "wp-cli/wp-cli-tests": "^4.3",
    "squizlabs/php_codesniffer": "^3.10",
    "wp-coding-standards/wpcs": "^3.1",
    "phpstan/phpstan": "^1.11",
    "szepeviktor/phpstan-wordpress": "^1.3"
  },
  "autoload": {
    "psr-4": {
      "ContentOps\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "ContentOps\\Tests\\Unit\\": "tests/unit/",
      "ContentOps\\Tests\\Integration\\": "tests/integration/"
    }
  },
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    },
    "platform": {
      "php": "7.4"
    }
  },
  "scripts": {
    "test:unit": "phpunit -c phpunit.xml.dist",
    "test:integration": "phpunit -c phpunit-integration.xml.dist",
    "lint": "phpcs",
    "lint:fix": "phpcbf",
    "stan": "phpstan analyze"
  }
}
```

- [ ] **Step 2: Run composer install**

```bash
composer install
```

Expected: `vendor/` directory created, `composer.lock` generated, no errors.

- [ ] **Step 3: Create `src/Plugin.php`**

```php
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
		\add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
	}

	public function on_plugins_loaded(): void {
		\load_plugin_textdomain( 'content-ops', false, dirname( \plugin_basename( $this->plugin_file ) ) . '/languages' );
		\do_action( 'content_ops_booted', $this );
	}

	public static function reset_for_tests(): void {
		self::$instance = null;
	}
}
```

- [ ] **Step 4: Create stub `src/Activator.php`**

```php
<?php
namespace ContentOps;

final class Activator {
	public static function activate(): void {
		// Task 12 wires schema install and capabilities here.
	}
}
```

- [ ] **Step 5: Create stub `src/Deactivator.php`**

```php
<?php
namespace ContentOps;

final class Deactivator {
	public static function deactivate(): void {
		// Later phases unschedule recurring Action Scheduler actions here.
	}
}
```

- [ ] **Step 6: Verify autoload**

```bash
php -r 'require "vendor/autoload.php"; echo class_exists("ContentOps\\Plugin") ? "ok" : "fail";'
```

Expected: `ok`

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock src/
git commit -m "feat: add composer autoload and Plugin service-locator bootstrap"
```

---

## Task 3: PHPUnit unit test setup with brain-monkey

**Files:**
- Create: `phpunit.xml.dist`
- Create: `tests/unit/bootstrap.php`
- Create: `tests/unit/TestCase.php`
- Create: `tests/unit/PluginTest.php`

- [ ] **Step 1: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd"
	bootstrap="tests/unit/bootstrap.php"
	colors="true"
	convertErrorsToExceptions="true"
	convertNoticesToExceptions="true"
	convertWarningsToExceptions="true"
	beStrictAboutOutputDuringTests="true"
	beStrictAboutTestsThatDoNotTestAnything="true"
	failOnRisky="true"
	failOnWarning="true"
	cacheResult="false"
>
	<testsuites>
		<testsuite name="unit">
			<directory>tests/unit</directory>
			<exclude>tests/unit/bootstrap.php</exclude>
			<exclude>tests/unit/TestCase.php</exclude>
		</testsuite>
	</testsuites>
	<coverage>
		<include>
			<directory suffix=".php">src</directory>
		</include>
	</coverage>
</phpunit>
```

- [ ] **Step 2: Create `tests/unit/bootstrap.php`**

```php
<?php
require __DIR__ . '/../../vendor/autoload.php';
```

- [ ] **Step 3: Create `tests/unit/TestCase.php`**

```php
<?php
namespace ContentOps\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'__',
			'_e',
			'esc_html__',
			'esc_html_e',
			'esc_attr__',
			'wp_parse_args',
		] );

		Monkey\Functions\when( 'wp_json_encode' )->alias(
			static fn ( $value, $flags = 0 ) => json_encode( $value, $flags | JSON_UNESCAPED_SLASHES )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
```

- [ ] **Step 4: Write test for Plugin boot**

Create `tests/unit/PluginTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit;

use Brain\Monkey\Functions;
use ContentOps\Plugin;

final class PluginTest extends TestCase {

	protected function tearDown(): void {
		Plugin::reset_for_tests();
		parent::tearDown();
	}

	public function test_boot_returns_same_instance_on_second_call(): void {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		$first  = Plugin::boot( __FILE__ );
		$second = Plugin::boot( '/some/other/file.php' );

		$this->assertSame( $first, $second );
		$this->assertSame( __FILE__, $first->plugin_file() );
	}

	public function test_services_can_be_registered_and_retrieved(): void {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );

		$plugin  = Plugin::boot( __FILE__ );
		$service = new \stdClass();

		$plugin->set( 'test.service', $service );

		$this->assertSame( $service, $plugin->get( 'test.service' ) );
		$this->assertNull( $plugin->get( 'missing' ) );
	}
}
```

- [ ] **Step 5: Run — confirm pass**

```bash
composer test:unit
```

Expected: `OK (2 tests, 4 assertions)`

- [ ] **Step 6: Commit**

```bash
git add phpunit.xml.dist tests/unit/
git commit -m "test: add PHPUnit unit test harness with brain-monkey and Plugin coverage"
```

---

## Task 4: PHPCS with WordPress Coding Standards

**Files:**
- Create: `phpcs.xml.dist`

- [ ] **Step 1: Create `phpcs.xml.dist`**

```xml
<?xml version="1.0"?>
<ruleset name="Content Ops">
	<description>Content Ops coding standards — WordPress-Extra minus the bits that fight modern PHP.</description>

	<file>src</file>
	<file>tests</file>
	<file>content-ops.php</file>
	<file>uninstall.php</file>

	<exclude-pattern>*/vendor/*</exclude-pattern>
	<exclude-pattern>*/node_modules/*</exclude-pattern>
	<exclude-pattern>*/assets/build/*</exclude-pattern>

	<arg name="basepath" value="."/>
	<arg name="colors"/>
	<arg name="parallel" value="8"/>
	<arg value="ps"/>

	<rule ref="WordPress-Extra">
		<exclude name="WordPress.Files.FileName"/>
		<exclude name="Universal.Files.SeparateFunctionsFromOO"/>
	</rule>

	<config name="minimum_supported_wp_version" value="6.3"/>
	<config name="testVersion" value="7.4-"/>

	<rule ref="WordPress.WP.I18n">
		<properties>
			<property name="text_domain" type="array" value="content-ops"/>
		</properties>
	</rule>
</ruleset>
```

- [ ] **Step 2: Run PHPCS**

```bash
composer lint
```

Expected: clean. If errors, run `composer lint:fix` then fix the rest manually before committing.

- [ ] **Step 3: Commit**

```bash
git add phpcs.xml.dist
git commit -m "chore: add PHPCS config using WordPress-Extra ruleset"
```

---

## Task 5: wp-env and integration test setup

**Files:**
- Create: `.wp-env.json`
- Create: `package.json`
- Create: `phpunit-integration.xml.dist`
- Create: `tests/integration/bootstrap.php`
- Create: `tests/integration/TestCase.php`
- Create: `tests/integration/PluginBootsTest.php`

- [ ] **Step 1: Create minimal `package.json`**

```json
{
  "name": "content-ops",
  "version": "0.1.0-alpha",
  "private": true,
  "scripts": {
    "env:start": "wp-env start",
    "env:stop": "wp-env stop",
    "env:destroy": "wp-env destroy",
    "env:test": "wp-env run tests-cli --env-cwd=wp-content/plugins/content-ops composer test:integration"
  },
  "devDependencies": {
    "@wordpress/env": "^10.0.0"
  }
}
```

- [ ] **Step 2: Install node deps**

```bash
npm install
```

- [ ] **Step 3: Create `.wp-env.json`**

```json
{
  "core": "WordPress/WordPress#6.5",
  "phpVersion": "7.4",
  "plugins": [ "." ],
  "env": {
    "tests": {
      "mappings": {
        "wp-content/plugins/content-ops": "."
      }
    }
  }
}
```

- [ ] **Step 4: Create `phpunit-integration.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd"
	bootstrap="tests/integration/bootstrap.php"
	colors="true"
	convertErrorsToExceptions="true"
	convertNoticesToExceptions="true"
	convertWarningsToExceptions="true"
	cacheResult="false"
>
	<testsuites>
		<testsuite name="integration">
			<directory>tests/integration</directory>
			<exclude>tests/integration/bootstrap.php</exclude>
			<exclude>tests/integration/TestCase.php</exclude>
		</testsuite>
	</testsuites>
</phpunit>
```

- [ ] **Step 5: Create `tests/integration/bootstrap.php`**

```php
<?php
require __DIR__ . '/../../vendor/autoload.php';

$wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( false === $wp_phpunit_dir ) {
	$wp_phpunit_dir = __DIR__ . '/../../vendor/wp-phpunit/wp-phpunit';
}

$_tests_dir = rtrim( $wp_phpunit_dir, '/\\' );

$GLOBALS['wp_tests_options'] = [
	'active_plugins' => [ 'content-ops/content-ops.php' ],
];

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', static function (): void {
	require dirname( __DIR__, 2 ) . '/content-ops.php';
} );

require $_tests_dir . '/includes/bootstrap.php';
```

- [ ] **Step 6: Create `tests/integration/TestCase.php`**

```php
<?php
namespace ContentOps\Tests\Integration;

use ContentOps\Plugin;
use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		if ( null === Plugin::instance() ) {
			Plugin::boot( dirname( __DIR__, 2 ) . '/content-ops.php' );
		}
	}
}
```

- [ ] **Step 7: Write integration test — plugin boots**

Create `tests/integration/PluginBootsTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration;

use ContentOps\Plugin;

final class PluginBootsTest extends TestCase {

	public function test_plugin_instance_is_available(): void {
		$this->assertInstanceOf( Plugin::class, Plugin::instance() );
	}

	public function test_content_ops_booted_action_fires(): void {
		$fired    = 0;
		$callback = static function () use ( &$fired ): void { ++$fired; };

		add_action( 'content_ops_booted', $callback );
		Plugin::instance()->on_plugins_loaded();
		remove_action( 'content_ops_booted', $callback );

		$this->assertSame( 1, $fired );
	}
}
```

- [ ] **Step 8: Start wp-env and run integration tests**

```bash
npm run env:start
npm run env:test
```

Expected: `OK (2 tests, 2 assertions)`.

- [ ] **Step 9: Commit**

```bash
git add .wp-env.json package.json package-lock.json phpunit-integration.xml.dist tests/integration/
git commit -m "test: add wp-env + wp-phpunit integration test harness with plugin boot coverage"
```

---

## Task 6: ContentOpsError structured error class

**Files:**
- Create: `src/Errors/ContentOpsError.php`
- Create: `tests/unit/Errors/ContentOpsErrorTest.php`

Spec §7.5.4 requires every error to return `{ code, message, context }`. `ContentOpsError` is convertible to `WP_Error` for CLI/REST responses.

- [ ] **Step 1: Write failing test**

Create `tests/unit/Errors/ContentOpsErrorTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit\Errors;

use ContentOps\Errors\ContentOpsError;
use ContentOps\Tests\Unit\TestCase;

final class ContentOpsErrorTest extends TestCase {

	public function test_error_exposes_code_message_and_context(): void {
		$error = new ContentOpsError( 'co.filter.invalid_post_type', 'Unknown post type.', [ 'post_type' => 'widget' ] );

		$this->assertSame( 'co.filter.invalid_post_type', $error->code() );
		$this->assertSame( 'Unknown post type.', $error->message() );
		$this->assertSame( [ 'post_type' => 'widget' ], $error->context() );
	}

	public function test_context_defaults_to_empty_array(): void {
		$error = new ContentOpsError( 'co.generic', 'Something failed.' );

		$this->assertSame( [], $error->context() );
	}

	public function test_to_array_returns_canonical_shape(): void {
		$error = new ContentOpsError( 'co.preview.stale_token', 'Preview token has expired.', [ 'ttl' => 300 ] );

		$this->assertSame(
			[
				'code'    => 'co.preview.stale_token',
				'message' => 'Preview token has expired.',
				'context' => [ 'ttl' => 300 ],
			],
			$error->to_array()
		);
	}

	public function test_code_must_be_non_empty(): void {
		$this->expectException( \InvalidArgumentException::class );
		new ContentOpsError( '', 'Boom.' );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
composer test:unit -- --filter ContentOpsErrorTest
```

- [ ] **Step 3: Implement `src/Errors/ContentOpsError.php`**

```php
<?php
namespace ContentOps\Errors;

use InvalidArgumentException;

final class ContentOpsError {

	private string $code;
	private string $message;
	private array $context;

	public function __construct( string $code, string $message, array $context = [] ) {
		if ( '' === $code ) {
			throw new InvalidArgumentException( 'ContentOpsError code must be a non-empty string.' );
		}

		$this->code    = $code;
		$this->message = $message;
		$this->context = $context;
	}

	public function code(): string {
		return $this->code;
	}

	public function message(): string {
		return $this->message;
	}

	public function context(): array {
		return $this->context;
	}

	public function to_array(): array {
		return [
			'code'    => $this->code,
			'message' => $this->message,
			'context' => $this->context,
		];
	}

	public function to_wp_error(): \WP_Error {
		return new \WP_Error( $this->code, $this->message, $this->context );
	}
}
```

- [ ] **Step 4: Run — confirm pass**

```bash
composer test:unit -- --filter ContentOpsErrorTest
```

Expected: `OK (4 tests, 5 assertions)`

- [ ] **Step 5: Commit**

```bash
git add src/Errors/ContentOpsError.php tests/unit/Errors/
git commit -m "feat: add ContentOpsError structured error type"
```

---

## Task 7: QueryArgs and FilterDefinition value objects

**Files:**
- Create: `src/Contracts/QueryArgs.php`
- Create: `src/Contracts/FilterDefinition.php`
- Create: `tests/unit/Contracts/QueryArgsTest.php`
- Create: `tests/unit/Contracts/FilterDefinitionTest.php`

`QueryArgs` is an immutable filter-arg bag. `FilterDefinition` describes what filters a Target exposes.

- [ ] **Step 1: Write failing QueryArgs test**

Create `tests/unit/Contracts/QueryArgsTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit\Contracts;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Tests\Unit\TestCase;

final class QueryArgsTest extends TestCase {

	public function test_empty_args_return_empty_array(): void {
		$this->assertSame( [], ( new QueryArgs() )->to_array() );
	}

	public function test_with_returns_new_instance(): void {
		$args    = new QueryArgs();
		$updated = $args->with( 'post_type', 'post' );

		$this->assertNotSame( $args, $updated );
		$this->assertSame( [], $args->to_array() );
		$this->assertSame( [ 'post_type' => 'post' ], $updated->to_array() );
	}

	public function test_get_returns_default_when_missing(): void {
		$args = ( new QueryArgs() )->with( 'status', 'draft' );

		$this->assertSame( 'draft', $args->get( 'status' ) );
		$this->assertNull( $args->get( 'post_type' ) );
		$this->assertSame( 'fallback', $args->get( 'post_type', 'fallback' ) );
	}

	public function test_from_array_creates_instance(): void {
		$args = QueryArgs::from_array( [ 'a' => 1, 'b' => 2 ] );
		$this->assertSame( [ 'a' => 1, 'b' => 2 ], $args->to_array() );
	}

	public function test_has_reports_presence_even_for_null(): void {
		$args = ( new QueryArgs() )->with( 'key', null );

		$this->assertTrue( $args->has( 'key' ) );
		$this->assertFalse( $args->has( 'other' ) );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
composer test:unit -- --filter QueryArgsTest
```

- [ ] **Step 3: Implement `src/Contracts/QueryArgs.php`**

```php
<?php
namespace ContentOps\Contracts;

final class QueryArgs {

	private array $args;

	public function __construct( array $args = [] ) {
		$this->args = $args;
	}

	public static function from_array( array $args ): self {
		return new self( $args );
	}

	public function with( string $key, $value ): self {
		$next         = $this->args;
		$next[ $key ] = $value;
		return new self( $next );
	}

	public function get( string $key, $default = null ) {
		return \array_key_exists( $key, $this->args ) ? $this->args[ $key ] : $default;
	}

	public function has( string $key ): bool {
		return \array_key_exists( $key, $this->args );
	}

	public function to_array(): array {
		return $this->args;
	}
}
```

- [ ] **Step 4: Run — confirm pass**

```bash
composer test:unit -- --filter QueryArgsTest
```

- [ ] **Step 5: Write FilterDefinition test**

Create `tests/unit/Contracts/FilterDefinitionTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit\Contracts;

use ContentOps\Contracts\FilterDefinition;
use ContentOps\Tests\Unit\TestCase;

final class FilterDefinitionTest extends TestCase {

	public function test_exposes_schema_fields(): void {
		$def = new FilterDefinition( 'status', 'Status', 'enum', [ 'options' => [ 'draft', 'publish' ] ] );

		$this->assertSame( 'status', $def->key() );
		$this->assertSame( 'Status', $def->label() );
		$this->assertSame( 'enum', $def->type() );
		$this->assertSame( [ 'draft', 'publish' ], $def->schema()['options'] );
	}

	public function test_to_array_is_serializable(): void {
		$def = new FilterDefinition( 'author', 'Author', 'user_id' );

		$this->assertSame(
			[ 'key' => 'author', 'label' => 'Author', 'type' => 'user_id', 'schema' => [] ],
			$def->to_array()
		);
	}

	public function test_empty_key_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new FilterDefinition( '', 'Empty', 'string' );
	}
}
```

- [ ] **Step 6: Implement `src/Contracts/FilterDefinition.php`**

```php
<?php
namespace ContentOps\Contracts;

use InvalidArgumentException;

final class FilterDefinition {

	private string $key;
	private string $label;
	private string $type;
	private array $schema;

	public function __construct( string $key, string $label, string $type, array $schema = [] ) {
		if ( '' === $key ) {
			throw new InvalidArgumentException( 'FilterDefinition key must be non-empty.' );
		}

		$this->key    = $key;
		$this->label  = $label;
		$this->type   = $type;
		$this->schema = $schema;
	}

	public function key(): string { return $this->key; }
	public function label(): string { return $this->label; }
	public function type(): string { return $this->type; }
	public function schema(): array { return $this->schema; }

	public function to_array(): array {
		return [
			'key'    => $this->key,
			'label'  => $this->label,
			'type'   => $this->type,
			'schema' => $this->schema,
		];
	}
}
```

- [ ] **Step 7: Run — confirm pass**

```bash
composer test:unit -- --filter FilterDefinitionTest
```

- [ ] **Step 8: Commit**

```bash
git add src/Contracts/QueryArgs.php src/Contracts/FilterDefinition.php tests/unit/Contracts/
git commit -m "feat: add QueryArgs and FilterDefinition value objects"
```

---

## Task 8: Result value objects

**Files:**
- Create: `src/Contracts/ValidationResult.php`
- Create: `src/Contracts/PreviewResult.php`
- Create: `src/Contracts/BatchResult.php`
- Create: `src/Contracts/UndoResult.php`
- Create: `tests/unit/Contracts/ResultsTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/unit/Contracts/ResultsTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit\Contracts;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\UndoResult;
use ContentOps\Contracts\ValidationResult;
use ContentOps\Errors\ContentOpsError;
use ContentOps\Tests\Unit\TestCase;

final class ResultsTest extends TestCase {

	public function test_validation_ok(): void {
		$result = ValidationResult::ok();
		$this->assertTrue( $result->is_ok() );
		$this->assertNull( $result->error() );
	}

	public function test_validation_error(): void {
		$error  = new ContentOpsError( 'co.filter.invalid', 'Invalid filter.' );
		$result = ValidationResult::error( $error );

		$this->assertFalse( $result->is_ok() );
		$this->assertSame( $error, $result->error() );
	}

	public function test_preview_carries_count_and_sample(): void {
		$preview = PreviewResult::of( 1247, [ 1, 2, 3 ], 'token-abc', [ 'warning' ] );

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 1247, $preview->count() );
		$this->assertSame( [ 1, 2, 3 ], $preview->sample_ids() );
		$this->assertSame( 'token-abc', $preview->preview_token() );
		$this->assertSame( [ 'warning' ], $preview->warnings() );
	}

	public function test_preview_error(): void {
		$preview = PreviewResult::error( new ContentOpsError( 'co.query.too_many', 'Too many matches.' ) );
		$this->assertFalse( $preview->is_ok() );
	}

	public function test_batch_aggregates_counts(): void {
		$batch = BatchResult::of( 50, 48, 2, [ 17 => 'missing', 22 => 'permission' ] );

		$this->assertTrue( $batch->is_ok() );
		$this->assertSame( 50, $batch->processed() );
		$this->assertSame( 48, $batch->succeeded() );
		$this->assertSame( 2, $batch->failed() );
		$this->assertSame( [ 17 => 'missing', 22 => 'permission' ], $batch->item_errors() );
	}

	public function test_undo_reports_restored_count(): void {
		$undo = UndoResult::of( 10 );
		$this->assertTrue( $undo->is_ok() );
		$this->assertSame( 10, $undo->restored() );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
composer test:unit -- --filter ResultsTest
```

- [ ] **Step 3: Implement `src/Contracts/ValidationResult.php`**

```php
<?php
namespace ContentOps\Contracts;

use ContentOps\Errors\ContentOpsError;

final class ValidationResult {

	private bool $ok;
	private ?ContentOpsError $error;

	private function __construct( bool $ok, ?ContentOpsError $error ) {
		$this->ok    = $ok;
		$this->error = $error;
	}

	public static function ok(): self {
		return new self( true, null );
	}

	public static function error( ContentOpsError $error ): self {
		return new self( false, $error );
	}

	public function is_ok(): bool {
		return $this->ok;
	}

	public function error(): ?ContentOpsError {
		return $this->error;
	}
}
```

- [ ] **Step 4: Implement `src/Contracts/PreviewResult.php`**

```php
<?php
namespace ContentOps\Contracts;

use ContentOps\Errors\ContentOpsError;

final class PreviewResult {

	private bool $ok;
	private int $count;
	private array $sample_ids;
	private string $preview_token;
	private array $warnings;
	private ?ContentOpsError $error;

	private function __construct(
		bool $ok,
		int $count,
		array $sample_ids,
		string $preview_token,
		array $warnings,
		?ContentOpsError $error
	) {
		$this->ok            = $ok;
		$this->count         = $count;
		$this->sample_ids    = $sample_ids;
		$this->preview_token = $preview_token;
		$this->warnings      = $warnings;
		$this->error         = $error;
	}

	public static function of( int $count, array $sample_ids, string $preview_token, array $warnings = [] ): self {
		return new self( true, $count, $sample_ids, $preview_token, $warnings, null );
	}

	public static function error( ContentOpsError $error ): self {
		return new self( false, 0, [], '', [], $error );
	}

	public function is_ok(): bool { return $this->ok; }
	public function count(): int { return $this->count; }
	public function sample_ids(): array { return $this->sample_ids; }
	public function preview_token(): string { return $this->preview_token; }
	public function warnings(): array { return $this->warnings; }
	public function error(): ?ContentOpsError { return $this->error; }
}
```

- [ ] **Step 5: Implement `src/Contracts/BatchResult.php`**

```php
<?php
namespace ContentOps\Contracts;

use ContentOps\Errors\ContentOpsError;

final class BatchResult {

	private bool $ok;
	private int $processed;
	private int $succeeded;
	private int $failed;
	private array $item_errors;
	private ?ContentOpsError $error;

	private function __construct(
		bool $ok,
		int $processed,
		int $succeeded,
		int $failed,
		array $item_errors,
		?ContentOpsError $error
	) {
		$this->ok          = $ok;
		$this->processed   = $processed;
		$this->succeeded   = $succeeded;
		$this->failed      = $failed;
		$this->item_errors = $item_errors;
		$this->error       = $error;
	}

	public static function of( int $processed, int $succeeded, int $failed, array $item_errors = [] ): self {
		return new self( true, $processed, $succeeded, $failed, $item_errors, null );
	}

	public static function error( ContentOpsError $error ): self {
		return new self( false, 0, 0, 0, [], $error );
	}

	public function is_ok(): bool { return $this->ok; }
	public function processed(): int { return $this->processed; }
	public function succeeded(): int { return $this->succeeded; }
	public function failed(): int { return $this->failed; }
	public function item_errors(): array { return $this->item_errors; }
	public function error(): ?ContentOpsError { return $this->error; }
}
```

- [ ] **Step 6: Implement `src/Contracts/UndoResult.php`**

```php
<?php
namespace ContentOps\Contracts;

use ContentOps\Errors\ContentOpsError;

final class UndoResult {

	private bool $ok;
	private int $restored;
	private ?ContentOpsError $error;

	private function __construct( bool $ok, int $restored, ?ContentOpsError $error ) {
		$this->ok       = $ok;
		$this->restored = $restored;
		$this->error    = $error;
	}

	public static function of( int $restored ): self {
		return new self( true, $restored, null );
	}

	public static function error( ContentOpsError $error ): self {
		return new self( false, 0, $error );
	}

	public function is_ok(): bool { return $this->ok; }
	public function restored(): int { return $this->restored; }
	public function error(): ?ContentOpsError { return $this->error; }
}
```

- [ ] **Step 7: Run — confirm pass**

```bash
composer test:unit -- --filter ResultsTest
```

Expected: `OK (6 tests, 18 assertions)`

- [ ] **Step 8: Commit**

```bash
git add src/Contracts/ValidationResult.php src/Contracts/PreviewResult.php src/Contracts/BatchResult.php src/Contracts/UndoResult.php tests/unit/Contracts/ResultsTest.php
git commit -m "feat: add Validation, Preview, Batch, and Undo result value objects"
```

---

## Task 9: TargetInterface and OperationInterface

**Files:**
- Create: `src/Contracts/TargetInterface.php`
- Create: `src/Contracts/OperationInterface.php`
- Create: `tests/unit/Contracts/ContractsShapeTest.php`

- [ ] **Step 1: Write test asserting interface shape**

Create `tests/unit/Contracts/ContractsShapeTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit\Contracts;

use ContentOps\Contracts\OperationInterface;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Tests\Unit\TestCase;
use ReflectionClass;

final class ContractsShapeTest extends TestCase {

	public function test_target_interface_exposes_expected_methods(): void {
		$methods = array_map(
			static fn ( \ReflectionMethod $m ) => $m->getName(),
			( new ReflectionClass( TargetInterface::class ) )->getMethods()
		);

		foreach ( [ 'slug', 'label', 'get_filters', 'query', 'count', 'get_display', 'supports_operation' ] as $expected ) {
			$this->assertContains( $expected, $methods );
		}
	}

	public function test_operation_interface_exposes_expected_methods(): void {
		$methods = array_map(
			static fn ( \ReflectionMethod $m ) => $m->getName(),
			( new ReflectionClass( OperationInterface::class ) )->getMethods()
		);

		foreach ( [ 'slug', 'label', 'get_params_schema', 'validate', 'preview', 'execute_batch', 'supports_undo', 'undo' ] as $expected ) {
			$this->assertContains( $expected, $methods );
		}
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
composer test:unit -- --filter ContractsShapeTest
```

- [ ] **Step 3: Implement `src/Contracts/TargetInterface.php`**

```php
<?php
namespace ContentOps\Contracts;

interface TargetInterface {

	public function slug(): string;

	public function label(): string;

	/** @return FilterDefinition[] */
	public function get_filters(): array;

	/** @return int[] */
	public function query( QueryArgs $args, int $limit = 0, int $offset = 0 ): array;

	public function count( QueryArgs $args ): int;

	public function get_display( int $id ): array;

	public function supports_operation( string $operation_slug ): bool;
}
```

- [ ] **Step 4: Implement `src/Contracts/OperationInterface.php`**

```php
<?php
namespace ContentOps\Contracts;

interface OperationInterface {

	public function slug(): string;

	public function label(): string;

	public function get_params_schema(): array;

	public function validate( QueryArgs $args, array $params ): ValidationResult;

	public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult;

	/** @param int[] $ids */
	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult;

	public function supports_undo(): bool;

	public function undo( int $operation_id ): UndoResult;
}
```

- [ ] **Step 5: Run — confirm pass**

```bash
composer test:unit -- --filter ContractsShapeTest
```

- [ ] **Step 6: Commit**

```bash
git add src/Contracts/TargetInterface.php src/Contracts/OperationInterface.php tests/unit/Contracts/ContractsShapeTest.php
git commit -m "feat: add TargetInterface and OperationInterface contracts"
```

---

## Task 10: TargetRegistry and OperationRegistry

**Files:**
- Create: `src/Registry/TargetRegistry.php`
- Create: `src/Registry/OperationRegistry.php`
- Create: `tests/unit/Registry/TargetRegistryTest.php`
- Create: `tests/unit/Registry/OperationRegistryTest.php`

- [ ] **Step 1: Write failing TargetRegistry test**

Create `tests/unit/Registry/TargetRegistryTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit\Registry;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Tests\Unit\TestCase;

final class TargetRegistryTest extends TestCase {

	public function test_register_and_retrieve(): void {
		$registry = new TargetRegistry();
		$target   = $this->fake_target( 'post' );

		$registry->register( $target );

		$this->assertSame( $target, $registry->get( 'post' ) );
		$this->assertTrue( $registry->has( 'post' ) );
	}

	public function test_duplicate_throws(): void {
		$registry = new TargetRegistry();
		$registry->register( $this->fake_target( 'post' ) );

		$this->expectException( \RuntimeException::class );
		$registry->register( $this->fake_target( 'post' ) );
	}

	public function test_missing_returns_null(): void {
		$registry = new TargetRegistry();
		$this->assertNull( $registry->get( 'missing' ) );
		$this->assertFalse( $registry->has( 'missing' ) );
	}

	public function test_all_preserves_insertion_order(): void {
		$registry = new TargetRegistry();
		$registry->register( $this->fake_target( 'post' ) );
		$registry->register( $this->fake_target( 'page' ) );

		$this->assertSame( [ 'post', 'page' ], array_keys( $registry->all() ) );
	}

	private function fake_target( string $slug ): TargetInterface {
		return new class( $slug ) implements TargetInterface {
			private string $slug;
			public function __construct( string $slug ) { $this->slug = $slug; }
			public function slug(): string { return $this->slug; }
			public function label(): string { return ucfirst( $this->slug ); }
			public function get_filters(): array { return []; }
			public function query( QueryArgs $args, int $limit = 0, int $offset = 0 ): array { return []; }
			public function count( QueryArgs $args ): int { return 0; }
			public function get_display( int $id ): array { return []; }
			public function supports_operation( string $operation_slug ): bool { return true; }
		};
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
composer test:unit -- --filter TargetRegistryTest
```

- [ ] **Step 3: Implement `src/Registry/TargetRegistry.php`**

```php
<?php
namespace ContentOps\Registry;

use ContentOps\Contracts\TargetInterface;
use RuntimeException;

final class TargetRegistry {

	/** @var array<string, TargetInterface> */
	private array $targets = [];

	public function register( TargetInterface $target ): void {
		$slug = $target->slug();
		if ( isset( $this->targets[ $slug ] ) ) {
			throw new RuntimeException( sprintf( 'Target "%s" already registered.', $slug ) );
		}

		$this->targets[ $slug ] = $target;
	}

	public function has( string $slug ): bool {
		return isset( $this->targets[ $slug ] );
	}

	public function get( string $slug ): ?TargetInterface {
		return $this->targets[ $slug ] ?? null;
	}

	/** @return array<string, TargetInterface> */
	public function all(): array {
		return $this->targets;
	}
}
```

- [ ] **Step 4: Run — confirm pass**

```bash
composer test:unit -- --filter TargetRegistryTest
```

- [ ] **Step 5: Write failing OperationRegistry test**

Create `tests/unit/Registry/OperationRegistryTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit\Registry;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\OperationInterface;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Contracts\UndoResult;
use ContentOps\Contracts\ValidationResult;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Tests\Unit\TestCase;

final class OperationRegistryTest extends TestCase {

	public function test_register_and_retrieve(): void {
		$registry  = new OperationRegistry();
		$operation = $this->fake_operation( 'delete' );

		$registry->register( $operation );

		$this->assertSame( $operation, $registry->get( 'delete' ) );
	}

	public function test_duplicate_throws(): void {
		$registry = new OperationRegistry();
		$registry->register( $this->fake_operation( 'delete' ) );

		$this->expectException( \RuntimeException::class );
		$registry->register( $this->fake_operation( 'delete' ) );
	}

	public function test_all_returns_registered(): void {
		$registry = new OperationRegistry();
		$registry->register( $this->fake_operation( 'delete' ) );
		$registry->register( $this->fake_operation( 'duplicate' ) );

		$this->assertCount( 2, $registry->all() );
	}

	private function fake_operation( string $slug ): OperationInterface {
		return new class( $slug ) implements OperationInterface {
			private string $slug;
			public function __construct( string $slug ) { $this->slug = $slug; }
			public function slug(): string { return $this->slug; }
			public function label(): string { return ucfirst( $this->slug ); }
			public function get_params_schema(): array { return []; }
			public function validate( QueryArgs $args, array $params ): ValidationResult { return ValidationResult::ok(); }
			public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult {
				return PreviewResult::of( 0, [], '' );
			}
			public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
				return BatchResult::of( 0, 0, 0 );
			}
			public function supports_undo(): bool { return false; }
			public function undo( int $operation_id ): UndoResult { return UndoResult::of( 0 ); }
		};
	}
}
```

- [ ] **Step 6: Implement `src/Registry/OperationRegistry.php`**

```php
<?php
namespace ContentOps\Registry;

use ContentOps\Contracts\OperationInterface;
use RuntimeException;

final class OperationRegistry {

	/** @var array<string, OperationInterface> */
	private array $operations = [];

	public function register( OperationInterface $operation ): void {
		$slug = $operation->slug();
		if ( isset( $this->operations[ $slug ] ) ) {
			throw new RuntimeException( sprintf( 'Operation "%s" already registered.', $slug ) );
		}

		$this->operations[ $slug ] = $operation;
	}

	public function has( string $slug ): bool {
		return isset( $this->operations[ $slug ] );
	}

	public function get( string $slug ): ?OperationInterface {
		return $this->operations[ $slug ] ?? null;
	}

	/** @return array<string, OperationInterface> */
	public function all(): array {
		return $this->operations;
	}
}
```

- [ ] **Step 7: Run — confirm pass**

```bash
composer test:unit -- --filter OperationRegistryTest
```

- [ ] **Step 8: Commit**

```bash
git add src/Registry/ tests/unit/Registry/
git commit -m "feat: add TargetRegistry and OperationRegistry with duplicate-slug protection"
```

---

## Task 11: Database schema and migrations

**Files:**
- Create: `src/Database/Schema.php`
- Create: `src/Database/Migrations.php`
- Create: `tests/integration/Database/SchemaTest.php`

Three tables: `co_operations`, `co_snapshots`, `co_schedules`. Version tracked in `content_ops_schema_version` option.

- [ ] **Step 1: Write failing integration test**

Create `tests/integration/Database/SchemaTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\Database;

use ContentOps\Database\Schema;
use ContentOps\Tests\Integration\TestCase;

final class SchemaTest extends TestCase {

	public function test_install_creates_all_tables(): void {
		global $wpdb;
		Schema::drop_all();

		Schema::install();

		$this->assertTableExists( $wpdb->prefix . 'co_operations' );
		$this->assertTableExists( $wpdb->prefix . 'co_snapshots' );
		$this->assertTableExists( $wpdb->prefix . 'co_schedules' );
		$this->assertSame( Schema::VERSION, get_option( Schema::VERSION_OPTION ) );
	}

	public function test_install_is_idempotent(): void {
		Schema::install();
		Schema::install();

		$this->assertTrue( true );
	}

	public function test_co_operations_has_expected_columns(): void {
		global $wpdb;
		Schema::install();
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}co_operations" );

		foreach ( [ 'id', 'type', 'target', 'user_id', 'filters_json', 'params_json', 'affected_count', 'affected_ids_json', 'status', 'error_message', 'created_at', 'completed_at' ] as $column ) {
			$this->assertContains( $column, $columns, "Missing column: {$column}" );
		}
	}

	private function assertTableExists( string $table ): void {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertSame( $table, $result );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter SchemaTest
```

- [ ] **Step 3: Implement `src/Database/Schema.php`**

```php
<?php
namespace ContentOps\Database;

final class Schema {

	public const VERSION        = '1.0.0';
	public const VERSION_OPTION = 'content_ops_schema_version';

	public static function install(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$operations = "CREATE TABLE {$wpdb->prefix}co_operations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(32) NOT NULL,
			target VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			filters_json LONGTEXT NULL,
			params_json LONGTEXT NULL,
			affected_count INT UNSIGNED NOT NULL DEFAULT 0,
			affected_ids_json LONGTEXT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'pending',
			error_message TEXT NULL,
			created_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY user_created (user_id, created_at),
			KEY status (status)
		) {$charset_collate};";

		$snapshots = "CREATE TABLE {$wpdb->prefix}co_snapshots (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			operation_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(64) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			field VARCHAR(64) NOT NULL,
			old_value LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY operation_id (operation_id),
			KEY object (object_type, object_id)
		) {$charset_collate};";

		$schedules = "CREATE TABLE {$wpdb->prefix}co_schedules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			operation_type VARCHAR(32) NOT NULL,
			target_type VARCHAR(64) NOT NULL,
			filters_json LONGTEXT NULL,
			params_json LONGTEXT NULL,
			recurrence_json LONGTEXT NULL,
			action_scheduler_id BIGINT UNSIGNED NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			last_run_at DATETIME NULL,
			next_run_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY enabled_next_run (enabled, next_run_at)
		) {$charset_collate};";

		dbDelta( $operations );
		dbDelta( $snapshots );
		dbDelta( $schedules );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	public static function drop_all(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}co_snapshots" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}co_operations" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}co_schedules" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_option( self::VERSION_OPTION );
	}
}
```

- [ ] **Step 4: Implement `src/Database/Migrations.php`**

```php
<?php
namespace ContentOps\Database;

final class Migrations {

	public static function maybe_migrate(): void {
		$current = (string) get_option( Schema::VERSION_OPTION, '' );

		if ( $current === Schema::VERSION ) {
			return;
		}

		Schema::install();
	}
}
```

- [ ] **Step 5: Run — confirm pass**

```bash
npm run env:test -- --filter SchemaTest
```

- [ ] **Step 6: Commit**

```bash
git add src/Database/ tests/integration/Database/
git commit -m "feat: add Schema DDL and Migrations runner for three custom tables"
```

---

## Task 12: Activator wires install

**Files:**
- Modify: `src/Activator.php`
- Create: `tests/integration/ActivationTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/integration/ActivationTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration;

use ContentOps\Activator;
use ContentOps\Database\Schema;

final class ActivationTest extends TestCase {

	public function test_activate_runs_migrations(): void {
		Schema::drop_all();

		Activator::activate();

		$this->assertSame( Schema::VERSION, get_option( Schema::VERSION_OPTION ) );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter ActivationTest
```

- [ ] **Step 3: Update `src/Activator.php`**

```php
<?php
namespace ContentOps;

use ContentOps\Database\Migrations;

final class Activator {

	public static function activate(): void {
		Migrations::maybe_migrate();
	}
}
```

- [ ] **Step 4: Run — confirm pass**

```bash
npm run env:test -- --filter ActivationTest
```

- [ ] **Step 5: Commit**

```bash
git add src/Activator.php tests/integration/ActivationTest.php
git commit -m "feat: wire Activator to run schema migrations on activation"
```

---

## Task 13: Operation value object + OperationRepository

**Files:**
- Create: `src/History/Operation.php`
- Create: `src/History/OperationRepository.php`
- Create: `tests/integration/History/OperationRepositoryTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/integration/History/OperationRepositoryTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\History;

use ContentOps\Database\Schema;
use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;
use ContentOps\Tests\Integration\TestCase;

final class OperationRepositoryTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		Schema::install();
	}

	public function test_create_persists_and_assigns_id(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$op   = Operation::newly_created( 'delete', 'post', 1, [ 'status' => 'draft' ], [ 'permanent' => false ] );

		$saved = $repo->create( $op );

		$this->assertGreaterThan( 0, $saved->id() );
		$this->assertSame( 'delete', $saved->type() );
		$this->assertSame( 'post', $saved->target() );
		$this->assertSame( 'pending', $saved->status() );
	}

	public function test_mark_completed_records_affected(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$op   = $repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );

		$repo->mark_completed( $op->id(), [ 10, 11, 12 ] );

		$reloaded = $repo->find( $op->id() );
		$this->assertSame( 'completed', $reloaded->status() );
		$this->assertSame( 3, $reloaded->affected_count() );
		$this->assertSame( [ 10, 11, 12 ], $reloaded->affected_ids() );
	}

	public function test_list_returns_most_recent_first(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$a    = $repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );
		$b    = $repo->create( Operation::newly_created( 'duplicate', 'post', 1, [], [] ) );

		$list = $repo->list( 10, 0 );

		$this->assertSame( $b->id(), $list[0]->id() );
		$this->assertSame( $a->id(), $list[1]->id() );
	}

	public function test_find_missing_returns_null(): void {
		$repo = new OperationRepository( $GLOBALS['wpdb'] );
		$this->assertNull( $repo->find( 999999 ) );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter OperationRepositoryTest
```

- [ ] **Step 3: Implement `src/History/Operation.php`**

```php
<?php
namespace ContentOps\History;

final class Operation {

	private int $id;
	private string $type;
	private string $target;
	private int $user_id;
	private array $filters;
	private array $params;
	private int $affected_count;
	private array $affected_ids;
	private string $status;
	private ?string $error_message;
	private string $created_at;
	private ?string $completed_at;

	private function __construct(
		int $id,
		string $type,
		string $target,
		int $user_id,
		array $filters,
		array $params,
		int $affected_count,
		array $affected_ids,
		string $status,
		?string $error_message,
		string $created_at,
		?string $completed_at
	) {
		$this->id             = $id;
		$this->type           = $type;
		$this->target         = $target;
		$this->user_id        = $user_id;
		$this->filters        = $filters;
		$this->params         = $params;
		$this->affected_count = $affected_count;
		$this->affected_ids   = $affected_ids;
		$this->status         = $status;
		$this->error_message  = $error_message;
		$this->created_at     = $created_at;
		$this->completed_at   = $completed_at;
	}

	public static function newly_created( string $type, string $target, int $user_id, array $filters, array $params ): self {
		return new self( 0, $type, $target, $user_id, $filters, $params, 0, [], 'pending', null, gmdate( 'Y-m-d H:i:s' ), null );
	}

	public static function from_row( array $row ): self {
		return new self(
			(int) $row['id'],
			(string) $row['type'],
			(string) $row['target'],
			(int) $row['user_id'],
			self::decode_json( $row['filters_json'] ?? null ),
			self::decode_json( $row['params_json'] ?? null ),
			(int) $row['affected_count'],
			self::decode_json( $row['affected_ids_json'] ?? null ),
			(string) $row['status'],
			isset( $row['error_message'] ) ? (string) $row['error_message'] : null,
			(string) $row['created_at'],
			isset( $row['completed_at'] ) ? (string) $row['completed_at'] : null
		);
	}

	public function with_id( int $id ): self {
		$clone     = clone $this;
		$clone->id = $id;
		return $clone;
	}

	public function id(): int { return $this->id; }
	public function type(): string { return $this->type; }
	public function target(): string { return $this->target; }
	public function user_id(): int { return $this->user_id; }
	public function filters(): array { return $this->filters; }
	public function params(): array { return $this->params; }
	public function affected_count(): int { return $this->affected_count; }
	public function affected_ids(): array { return $this->affected_ids; }
	public function status(): string { return $this->status; }
	public function error_message(): ?string { return $this->error_message; }
	public function created_at(): string { return $this->created_at; }
	public function completed_at(): ?string { return $this->completed_at; }

	private static function decode_json( $value ): array {
		if ( null === $value || '' === $value ) {
			return [];
		}
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}
}
```

- [ ] **Step 4: Implement `src/History/OperationRepository.php`**

```php
<?php
namespace ContentOps\History;

use wpdb;

final class OperationRepository {

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	private function table(): string {
		return $this->db->prefix . 'co_operations';
	}

	public function create( Operation $op ): Operation {
		$this->db->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[
				'type'              => $op->type(),
				'target'            => $op->target(),
				'user_id'           => $op->user_id(),
				'filters_json'      => wp_json_encode( $op->filters() ),
				'params_json'       => wp_json_encode( $op->params() ),
				'affected_count'    => $op->affected_count(),
				'affected_ids_json' => wp_json_encode( $op->affected_ids() ),
				'status'            => $op->status(),
				'error_message'     => $op->error_message(),
				'created_at'        => $op->created_at(),
				'completed_at'      => $op->completed_at(),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $op->with_id( (int) $this->db->insert_id );
	}

	public function mark_running( int $id ): void {
		$this->db->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[ 'status' => 'running' ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public function mark_completed( int $id, array $affected_ids ): void {
		$this->db->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[
				'status'            => 'completed',
				'affected_count'    => count( $affected_ids ),
				'affected_ids_json' => wp_json_encode( array_values( $affected_ids ) ),
				'completed_at'      => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function mark_failed( int $id, string $error_message ): void {
		$this->db->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[
				'status'        => 'failed',
				'error_message' => $error_message,
				'completed_at'  => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function find( int $id ): ?Operation {
		$row = $this->db->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? Operation::from_row( $row ) : null;
	}

	/** @return Operation[] */
	public function list( int $limit, int $offset ): array {
		$rows = $this->db->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->prepare( "SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ),
			ARRAY_A
		);

		return array_map( [ Operation::class, 'from_row' ], $rows ?: [] );
	}
}
```

- [ ] **Step 5: Run — confirm pass**

```bash
npm run env:test -- --filter OperationRepositoryTest
```

- [ ] **Step 6: Commit**

```bash
git add src/History/Operation.php src/History/OperationRepository.php tests/integration/History/
git commit -m "feat: add Operation value object and OperationRepository CRUD"
```

---

## Task 14: Snapshot value object + SnapshotRepository

**Files:**
- Create: `src/History/Snapshot.php`
- Create: `src/History/SnapshotRepository.php`
- Create: `tests/integration/History/SnapshotRepositoryTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/integration/History/SnapshotRepositoryTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\History;

use ContentOps\Database\Schema;
use ContentOps\History\Snapshot;
use ContentOps\History\SnapshotRepository;
use ContentOps\Tests\Integration\TestCase;

final class SnapshotRepositoryTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		Schema::install();
	}

	public function test_bulk_insert_and_retrieve_by_operation(): void {
		$repo      = new SnapshotRepository( $GLOBALS['wpdb'] );
		$snapshots = [
			new Snapshot( 42, 'post', 1, 'post_status', 'draft' ),
			new Snapshot( 42, 'post', 2, 'post_status', 'draft' ),
			new Snapshot( 42, 'post', 1, 'post_title', 'Old title' ),
		];

		$repo->bulk_insert( $snapshots );

		$this->assertCount( 3, $repo->for_operation( 42 ) );
	}

	public function test_delete_by_operation(): void {
		$repo = new SnapshotRepository( $GLOBALS['wpdb'] );
		$repo->bulk_insert( [ new Snapshot( 50, 'post', 1, 'post_status', 'publish' ) ] );
		$repo->bulk_insert( [ new Snapshot( 51, 'post', 1, 'post_status', 'publish' ) ] );

		$repo->delete_for_operation( 50 );

		$this->assertCount( 0, $repo->for_operation( 50 ) );
		$this->assertCount( 1, $repo->for_operation( 51 ) );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter SnapshotRepositoryTest
```

- [ ] **Step 3: Implement `src/History/Snapshot.php`**

```php
<?php
namespace ContentOps\History;

final class Snapshot {

	private int $operation_id;
	private string $object_type;
	private int $object_id;
	private string $field;
	private ?string $old_value;

	public function __construct( int $operation_id, string $object_type, int $object_id, string $field, ?string $old_value ) {
		$this->operation_id = $operation_id;
		$this->object_type  = $object_type;
		$this->object_id    = $object_id;
		$this->field        = $field;
		$this->old_value    = $old_value;
	}

	public function operation_id(): int { return $this->operation_id; }
	public function object_type(): string { return $this->object_type; }
	public function object_id(): int { return $this->object_id; }
	public function field(): string { return $this->field; }
	public function old_value(): ?string { return $this->old_value; }
}
```

- [ ] **Step 4: Implement `src/History/SnapshotRepository.php`**

```php
<?php
namespace ContentOps\History;

use wpdb;

final class SnapshotRepository {

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	private function table(): string {
		return $this->db->prefix . 'co_snapshots';
	}

	/** @param Snapshot[] $snapshots */
	public function bulk_insert( array $snapshots ): void {
		foreach ( $snapshots as $snapshot ) {
			$this->db->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table(),
				[
					'operation_id' => $snapshot->operation_id(),
					'object_type'  => $snapshot->object_type(),
					'object_id'    => $snapshot->object_id(),
					'field'        => $snapshot->field(),
					'old_value'    => $snapshot->old_value(),
				],
				[ '%d', '%s', '%d', '%s', '%s' ]
			);
		}
	}

	/** @return Snapshot[] */
	public function for_operation( int $operation_id ): array {
		$rows = $this->db->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->prepare( "SELECT * FROM {$this->table()} WHERE operation_id = %d", $operation_id ),
			ARRAY_A
		);

		return array_map(
			static fn ( array $row ) => new Snapshot(
				(int) $row['operation_id'],
				(string) $row['object_type'],
				(int) $row['object_id'],
				(string) $row['field'],
				isset( $row['old_value'] ) ? (string) $row['old_value'] : null
			),
			$rows ?: []
		);
	}

	public function delete_for_operation( int $operation_id ): void {
		$this->db->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			[ 'operation_id' => $operation_id ],
			[ '%d' ]
		);
	}
}
```

- [ ] **Step 5: Run — confirm pass**

```bash
npm run env:test -- --filter SnapshotRepositoryTest
```

- [ ] **Step 6: Commit**

```bash
git add src/History/Snapshot.php src/History/SnapshotRepository.php tests/integration/History/SnapshotRepositoryTest.php
git commit -m "feat: add Snapshot value object and SnapshotRepository for undo storage"
```

---

## Task 15: Preview token generator (unit)

**Files:**
- Create: `src/PreviewToken/TokenGenerator.php`
- Create: `tests/unit/PreviewToken/TokenGeneratorTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/unit/PreviewToken/TokenGeneratorTest.php`:

```php
<?php
namespace ContentOps\Tests\Unit\PreviewToken;

use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\Tests\Unit\TestCase;

final class TokenGeneratorTest extends TestCase {

	public function test_same_payload_produces_deterministic_token(): void {
		$generator = new TokenGenerator( 'site-salt' );

		$a = $generator->generate( [ 'op' => 'delete', 'ids' => [ 1, 2, 3 ] ] );
		$b = $generator->generate( [ 'op' => 'delete', 'ids' => [ 1, 2, 3 ] ] );

		$this->assertSame( $a, $b );
		$this->assertNotEmpty( $a );
	}

	public function test_different_payload_produces_different_token(): void {
		$generator = new TokenGenerator( 'site-salt' );

		$this->assertNotSame(
			$generator->generate( [ 'op' => 'delete', 'ids' => [ 1 ] ] ),
			$generator->generate( [ 'op' => 'delete', 'ids' => [ 2 ] ] )
		);
	}

	public function test_payload_key_order_does_not_affect_token(): void {
		$generator = new TokenGenerator( 'site-salt' );

		$this->assertSame(
			$generator->generate( [ 'op' => 'delete', 'ids' => [ 1, 2 ] ] ),
			$generator->generate( [ 'ids' => [ 1, 2 ], 'op' => 'delete' ] )
		);
	}

	public function test_different_salt_produces_different_token(): void {
		$this->assertNotSame(
			( new TokenGenerator( 'salt-a' ) )->generate( [ 'op' => 'delete' ] ),
			( new TokenGenerator( 'salt-b' ) )->generate( [ 'op' => 'delete' ] )
		);
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
composer test:unit -- --filter TokenGeneratorTest
```

- [ ] **Step 3: Implement `src/PreviewToken/TokenGenerator.php`**

```php
<?php
namespace ContentOps\PreviewToken;

final class TokenGenerator {

	private string $salt;

	public function __construct( string $salt ) {
		$this->salt = $salt;
	}

	public function generate( array $payload ): string {
		return hash_hmac( 'sha256', self::canonicalize( $payload ), $this->salt );
	}

	public static function canonicalize( array $payload ): string {
		$sort = static function ( &$value ) use ( &$sort ): void {
			if ( is_array( $value ) ) {
				ksort( $value );
				foreach ( $value as &$item ) {
					$sort( $item );
				}
			}
		};
		$sort( $payload );

		return (string) wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
	}
}
```

- [ ] **Step 4: Run — confirm pass**

```bash
composer test:unit -- --filter TokenGeneratorTest
```

- [ ] **Step 5: Commit**

```bash
git add src/PreviewToken/TokenGenerator.php tests/unit/PreviewToken/
git commit -m "feat: add deterministic HMAC-based preview token generator"
```

---

## Task 16: Preview token store and verifier (integration)

**Files:**
- Create: `src/PreviewToken/TokenStore.php`
- Create: `src/PreviewToken/TokenVerifier.php`
- Create: `tests/integration/PreviewToken/TokenFlowTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/integration/PreviewToken/TokenFlowTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\PreviewToken;

use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\PreviewToken\TokenVerifier;
use ContentOps\Tests\Integration\TestCase;

final class TokenFlowTest extends TestCase {

	public function test_valid_token_verifies(): void {
		$generator = new TokenGenerator( 'salt' );
		$store     = new TokenStore( 60 );
		$verifier  = new TokenVerifier( $generator, $store );

		$payload = [ 'op' => 'delete', 'ids' => [ 1, 2, 3 ] ];
		$token   = $generator->generate( $payload );
		$store->store( $token, $payload );

		$this->assertTrue( $verifier->verify( $token, $payload ) );
	}

	public function test_token_invalidates_when_payload_changes(): void {
		$generator = new TokenGenerator( 'salt' );
		$store     = new TokenStore( 60 );
		$verifier  = new TokenVerifier( $generator, $store );

		$original = [ 'op' => 'delete', 'ids' => [ 1, 2 ] ];
		$token    = $generator->generate( $original );
		$store->store( $token, $original );

		$this->assertFalse( $verifier->verify( $token, [ 'op' => 'delete', 'ids' => [ 1, 2, 3 ] ] ) );
	}

	public function test_consume_invalidates(): void {
		$generator = new TokenGenerator( 'salt' );
		$store     = new TokenStore( 60 );
		$verifier  = new TokenVerifier( $generator, $store );

		$payload = [ 'op' => 'delete', 'ids' => [ 1 ] ];
		$token   = $generator->generate( $payload );
		$store->store( $token, $payload );

		$verifier->consume( $token );

		$this->assertFalse( $verifier->verify( $token, $payload ) );
	}

	public function test_unknown_token_fails(): void {
		$generator = new TokenGenerator( 'salt' );
		$store     = new TokenStore( 60 );
		$verifier  = new TokenVerifier( $generator, $store );

		$this->assertFalse( $verifier->verify( 'bogus', [ 'op' => 'delete' ] ) );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter TokenFlowTest
```

- [ ] **Step 3: Implement `src/PreviewToken/TokenStore.php`**

```php
<?php
namespace ContentOps\PreviewToken;

final class TokenStore {

	private const TRANSIENT_PREFIX = 'co_preview_token_';

	private int $ttl_seconds;

	public function __construct( int $ttl_seconds = 300 ) {
		$this->ttl_seconds = $ttl_seconds;
	}

	public function store( string $token, array $payload ): void {
		set_transient( self::TRANSIENT_PREFIX . $token, $payload, $this->ttl_seconds );
	}

	public function retrieve( string $token ): ?array {
		$payload = get_transient( self::TRANSIENT_PREFIX . $token );
		return is_array( $payload ) ? $payload : null;
	}

	public function invalidate( string $token ): void {
		delete_transient( self::TRANSIENT_PREFIX . $token );
	}
}
```

- [ ] **Step 4: Implement `src/PreviewToken/TokenVerifier.php`**

```php
<?php
namespace ContentOps\PreviewToken;

final class TokenVerifier {

	private TokenGenerator $generator;
	private TokenStore $store;

	public function __construct( TokenGenerator $generator, TokenStore $store ) {
		$this->generator = $generator;
		$this->store     = $store;
	}

	public function verify( string $token, array $current_payload ): bool {
		$stored = $this->store->retrieve( $token );
		if ( null === $stored ) {
			return false;
		}

		$expected = $this->generator->generate( $current_payload );

		return hash_equals( $expected, $token ) && $this->payloads_match( $stored, $current_payload );
	}

	public function consume( string $token ): void {
		$this->store->invalidate( $token );
	}

	private function payloads_match( array $a, array $b ): bool {
		return TokenGenerator::canonicalize( $a ) === TokenGenerator::canonicalize( $b );
	}
}
```

- [ ] **Step 5: Run — confirm pass**

```bash
npm run env:test -- --filter TokenFlowTest
```

- [ ] **Step 6: Commit**

```bash
git add src/PreviewToken/TokenStore.php src/PreviewToken/TokenVerifier.php tests/integration/PreviewToken/
git commit -m "feat: add transient-backed preview token store and verifier"
```

---

## Task 17: Action Scheduler bridge

**Files:**
- Create: `src/Async/ActionSchedulerBridge.php`
- Modify: `src/Plugin.php`
- Create: `tests/integration/Async/ActionSchedulerBridgeTest.php`

Action Scheduler is pulled via Composer. Plugin boot detects whether AS is already loaded (WooCommerce scenario) and loads our bundled copy only if not.

- [ ] **Step 1: Update `src/Plugin.php` — add AS loader**

Replace `on_plugins_loaded` in `src/Plugin.php`:

```php
public function on_plugins_loaded(): void {
	\load_plugin_textdomain( 'content-ops', false, dirname( \plugin_basename( $this->plugin_file ) ) . '/languages' );
	$this->load_action_scheduler();
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
```

- [ ] **Step 2: Write failing test**

Create `tests/integration/Async/ActionSchedulerBridgeTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\Async;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Tests\Integration\TestCase;

final class ActionSchedulerBridgeTest extends TestCase {

	public function test_is_available(): void {
		$this->assertTrue( ( new ActionSchedulerBridge() )->is_available() );
	}

	public function test_schedule_single_action_returns_id(): void {
		$bridge = new ActionSchedulerBridge();

		$id = $bridge->schedule_single_action( time() + 60, 'content_ops_test_hook', [ 'arg' => 'value' ], 'content-ops' );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	public function test_cancel_action(): void {
		$bridge = new ActionSchedulerBridge();
		$id     = $bridge->schedule_single_action( time() + 60, 'content_ops_test_hook', [], 'content-ops' );

		$bridge->cancel_action( $id );

		$this->assertFalse( $bridge->action_exists( $id ) );
	}
}
```

- [ ] **Step 3: Run — confirm failure**

```bash
npm run env:test -- --filter ActionSchedulerBridgeTest
```

- [ ] **Step 4: Implement `src/Async/ActionSchedulerBridge.php`**

```php
<?php
namespace ContentOps\Async;

final class ActionSchedulerBridge {

	public function is_available(): bool {
		return function_exists( 'as_schedule_single_action' );
	}

	public function schedule_single_action( int $timestamp, string $hook, array $args, string $group ): int {
		if ( ! $this->is_available() ) {
			return 0;
		}
		return (int) as_schedule_single_action( $timestamp, $hook, $args, $group );
	}

	public function schedule_recurring_action( int $timestamp, int $interval_in_seconds, string $hook, array $args, string $group ): int {
		if ( ! $this->is_available() ) {
			return 0;
		}
		return (int) as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args, $group );
	}

	public function cancel_action( int $action_id ): void {
		if ( ! class_exists( \ActionScheduler::class ) ) {
			return;
		}
		$action = \ActionScheduler::store()->fetch_action( $action_id );
		if ( null === $action || $action instanceof \ActionScheduler_NullAction ) {
			return;
		}
		\ActionScheduler::store()->delete_action( $action_id );
	}

	public function action_exists( int $action_id ): bool {
		if ( ! class_exists( \ActionScheduler::class ) ) {
			return false;
		}
		$action = \ActionScheduler::store()->fetch_action( $action_id );
		return null !== $action && ! $action instanceof \ActionScheduler_NullAction;
	}
}
```

- [ ] **Step 5: Run — confirm pass**

```bash
npm run env:test -- --filter ActionSchedulerBridgeTest
```

- [ ] **Step 6: Commit**

```bash
git add src/Plugin.php src/Async/ tests/integration/Async/
git commit -m "feat: load Action Scheduler on boot and expose ActionSchedulerBridge"
```

---

## Task 18: Capability registration

**Files:**
- Create: `src/Capabilities/Capabilities.php`
- Modify: `src/Activator.php`
- Create: `tests/integration/Capabilities/CapabilitiesTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/integration/Capabilities/CapabilitiesTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\Capabilities;

use ContentOps\Capabilities\Capabilities;
use ContentOps\Tests\Integration\TestCase;

final class CapabilitiesTest extends TestCase {

	public function test_admin_gets_all_caps(): void {
		Capabilities::grant_to_admins();

		$admin = get_role( 'administrator' );
		foreach ( Capabilities::ALL as $cap ) {
			$this->assertTrue( $admin->has_cap( $cap ), "Missing cap: {$cap}" );
		}
	}

	public function test_all_constant_is_stable(): void {
		$this->assertSame(
			[
				'content_ops_delete',
				'content_ops_edit',
				'content_ops_duplicate',
				'content_ops_move',
				'content_ops_schedule',
			],
			Capabilities::ALL
		);
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter CapabilitiesTest
```

- [ ] **Step 3: Implement `src/Capabilities/Capabilities.php`**

```php
<?php
namespace ContentOps\Capabilities;

final class Capabilities {

	public const ALL = [
		'content_ops_delete',
		'content_ops_edit',
		'content_ops_duplicate',
		'content_ops_move',
		'content_ops_schedule',
	];

	public static function grant_to_admins(): void {
		$role = get_role( 'administrator' );
		if ( null === $role ) {
			return;
		}

		foreach ( self::ALL as $cap ) {
			$role->add_cap( $cap );
		}
	}
}
```

- [ ] **Step 4: Update `src/Activator.php`**

```php
<?php
namespace ContentOps;

use ContentOps\Capabilities\Capabilities;
use ContentOps\Database\Migrations;

final class Activator {

	public static function activate(): void {
		Migrations::maybe_migrate();
		Capabilities::grant_to_admins();
	}
}
```

- [ ] **Step 5: Run — confirm pass**

```bash
npm run env:test -- --filter CapabilitiesTest
```

- [ ] **Step 6: Commit**

```bash
git add src/Capabilities/ src/Activator.php tests/integration/Capabilities/
git commit -m "feat: register content_ops_* capabilities and grant to admin on activation"
```

---

## Task 19: Base REST controller (integration test)

**Files:**
- Create: `src/REST/RestController.php`
- Create: `tests/integration/REST/RestControllerTest.php`

Base controller is tested via integration tests because it returns real `WP_REST_Response` / `WP_Error` objects. Unit-testing it would require stubbing those classes — best avoided.

- [ ] **Step 1: Write failing integration test**

Create `tests/integration/REST/RestControllerTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Errors\ContentOpsError;
use ContentOps\REST\RestController;
use ContentOps\Tests\Integration\TestCase;
use WP_Error;
use WP_REST_Response;

final class RestControllerTest extends TestCase {

	public function test_error_response_has_canonical_shape(): void {
		$controller = new class extends RestController {};
		$error      = new ContentOpsError( 'co.filter.invalid', 'Bad filter.', [ 'key' => 'status' ] );

		$response = $controller->error_response( $error, 422 );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 422, $response->get_status() );
		$this->assertSame(
			[
				'code'    => 'co.filter.invalid',
				'message' => 'Bad filter.',
				'context' => [ 'key' => 'status' ],
			],
			$response->get_data()
		);
	}

	public function test_capability_denies_subscriber(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$controller = new class extends RestController {};
		$result     = $controller->require_capability( 'manage_options' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'co.auth.forbidden', $result->get_error_code() );
	}

	public function test_capability_passes_for_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$controller = new class extends RestController {};
		$this->assertTrue( $controller->require_capability( 'manage_options' ) );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter RestControllerTest
```

- [ ] **Step 3: Implement `src/REST/RestController.php`**

```php
<?php
namespace ContentOps\REST;

use ContentOps\Errors\ContentOpsError;
use WP_Error;
use WP_REST_Response;

abstract class RestController {

	public function error_response( ContentOpsError $error, int $status ): WP_REST_Response {
		return new WP_REST_Response( $error->to_array(), $status );
	}

	public function require_capability( string $capability ) {
		if ( current_user_can( $capability ) ) {
			return true;
		}

		return new WP_Error(
			'co.auth.forbidden',
			__( 'You are not allowed to perform this action.', 'content-ops' ),
			[ 'status' => 403, 'required_capability' => $capability ]
		);
	}
}
```

- [ ] **Step 4: Run — confirm pass**

```bash
npm run env:test -- --filter RestControllerTest
```

- [ ] **Step 5: Commit**

```bash
git add src/REST/RestController.php tests/integration/REST/
git commit -m "feat: add base REST controller with structured error helpers"
```

---

## Task 20: Doctor controller and REST route

**Files:**
- Create: `src/REST/DoctorController.php`
- Create: `src/REST/RouteRegistrar.php`
- Modify: `src/Plugin.php`
- Create: `tests/integration/REST/DoctorRouteTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/integration/REST/DoctorRouteTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class DoctorRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_doctor_returns_expected_shape(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/doctor' ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		foreach ( [ 'schema_version', 'action_scheduler', 'abilities_api', 'hpos', 'tables', 'cron' ] as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		$this->assertIsBool( $data['action_scheduler']['available'] );
	}

	public function test_doctor_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/doctor' ) );
		$this->assertSame( 403, $response->get_status() );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter DoctorRouteTest
```

- [ ] **Step 3: Implement `src/REST/DoctorController.php`**

```php
<?php
namespace ContentOps\REST;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Database\Schema;
use WP_REST_Request;
use WP_REST_Response;

final class DoctorController extends RestController {

	private ActionSchedulerBridge $action_scheduler;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->action_scheduler = $action_scheduler;
	}

	public function check_permission() {
		return $this->require_capability( 'manage_options' );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->collect_report() );
	}

	public function collect_report(): array {
		global $wpdb;

		$tables  = [
			$wpdb->prefix . 'co_operations',
			$wpdb->prefix . 'co_snapshots',
			$wpdb->prefix . 'co_schedules',
		];
		$missing = [];
		foreach ( $tables as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $found !== $table ) {
				$missing[] = $table;
			}
		}

		$hpos_class   = '\\Automattic\\WooCommerce\\Utilities\\OrderUtil';
		$hpos_enabled = class_exists( $hpos_class )
			? call_user_func( [ $hpos_class, 'custom_orders_table_usage_is_enabled' ] )
			: false;

		return [
			'schema_version'   => (string) get_option( Schema::VERSION_OPTION, '' ),
			'action_scheduler' => [
				'available' => $this->action_scheduler->is_available(),
			],
			'abilities_api'    => [
				'available' => function_exists( 'wp_register_ability' ),
			],
			'hpos'             => [
				'available' => class_exists( $hpos_class ),
				'enabled'   => (bool) $hpos_enabled,
			],
			'tables'           => [
				'expected' => $tables,
				'missing'  => $missing,
			],
			'cron'             => [
				'disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			],
		];
	}
}
```

- [ ] **Step 4: Implement `src/REST/RouteRegistrar.php`**

```php
<?php
namespace ContentOps\REST;

use ContentOps\Async\ActionSchedulerBridge;

final class RouteRegistrar {

	public const REST_NAMESPACE = 'content-ops/v1';

	private ActionSchedulerBridge $action_scheduler;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->action_scheduler = $action_scheduler;
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$doctor = new DoctorController( $this->action_scheduler );

		register_rest_route(
			self::REST_NAMESPACE,
			'/doctor',
			[
				'methods'             => 'GET',
				'callback'            => [ $doctor, 'handle' ],
				'permission_callback' => [ $doctor, 'check_permission' ],
			]
		);
	}
}
```

- [ ] **Step 5: Wire into `src/Plugin.php`**

Replace `on_plugins_loaded` in `src/Plugin.php`:

```php
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
```

- [ ] **Step 6: Run — confirm pass**

```bash
npm run env:test -- --filter DoctorRouteTest
```

- [ ] **Step 7: Commit**

```bash
git add src/REST/DoctorController.php src/REST/RouteRegistrar.php src/Plugin.php tests/integration/REST/DoctorRouteTest.php
git commit -m "feat: register /content-ops/v1/doctor REST route"
```

---

## Task 21: WP-CLI command registrar + doctor command

**Files:**
- Create: `src/CLI/CommandRegistrar.php`
- Create: `src/CLI/DoctorCommand.php`
- Modify: `src/Plugin.php`
- Create: `tests/integration/CLI/DoctorCommandTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/integration/CLI/DoctorCommandTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\CLI\DoctorCommand;
use ContentOps\Tests\Integration\TestCase;

final class DoctorCommandTest extends TestCase {

	public function test_collect_report_returns_expected_keys(): void {
		$command = new DoctorCommand( new ActionSchedulerBridge() );

		$result = $command->collect_report();

		foreach ( [ 'schema_version', 'action_scheduler', 'abilities_api', 'hpos', 'tables', 'cron' ] as $key ) {
			$this->assertArrayHasKey( $key, $result );
		}
		$this->assertIsBool( $result['action_scheduler']['available'] );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter DoctorCommandTest
```

- [ ] **Step 3: Implement `src/CLI/DoctorCommand.php`**

```php
<?php
namespace ContentOps\CLI;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\REST\DoctorController;

final class DoctorCommand {

	private DoctorController $controller;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->controller = new DoctorController( $action_scheduler );
	}

	/**
	 * Report Content Ops environment health.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$format = $assoc_args['format'] ?? 'table';
		$report = $this->collect_report();

		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $report, JSON_PRETTY_PRINT ) );
			return;
		}

		$rows    = [];
		$flatten = static function ( string $prefix, array $value ) use ( &$flatten, &$rows ): void {
			foreach ( $value as $k => $v ) {
				$key = '' === $prefix ? (string) $k : $prefix . '.' . $k;
				if ( is_array( $v ) ) {
					$flatten( $key, $v );
				} else {
					$rows[] = [ 'check' => $key, 'value' => var_export( $v, true ) ];
				}
			}
		};
		$flatten( '', $report );

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'check', 'value' ] );
	}

	public function collect_report(): array {
		return $this->controller->collect_report();
	}
}
```

- [ ] **Step 4: Implement `src/CLI/CommandRegistrar.php`**

```php
<?php
namespace ContentOps\CLI;

use ContentOps\Async\ActionSchedulerBridge;

final class CommandRegistrar {

	private ActionSchedulerBridge $action_scheduler;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->action_scheduler = $action_scheduler;
	}

	public function register(): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		\WP_CLI::add_command(
			'content-ops doctor',
			new DoctorCommand( $this->action_scheduler ),
			[ 'shortdesc' => __( 'Check Content Ops environment health.', 'content-ops' ) ]
		);
	}
}
```

- [ ] **Step 5: Wire into `src/Plugin.php`**

Extend `on_plugins_loaded` in `src/Plugin.php` — add after REST registration:

```php
$cli_registrar = new \ContentOps\CLI\CommandRegistrar( $action_scheduler_bridge );
$cli_registrar->register();
$this->set( 'cli.registrar', $cli_registrar );
```

- [ ] **Step 6: Run — confirm pass**

```bash
npm run env:test -- --filter DoctorCommandTest
```

- [ ] **Step 7: Manual CLI smoke test**

```bash
npm run env:start
wp-env run cli --env-cwd=wp-content/plugins/content-ops wp content-ops doctor --format=json
```

Expected: pretty-printed JSON report.

- [ ] **Step 8: Commit**

```bash
git add src/CLI/ src/Plugin.php tests/integration/CLI/
git commit -m "feat: add WP-CLI registrar and wp content-ops doctor command"
```

---

## Task 22: Abilities API bridge

**Files:**
- Create: `src/Abilities/AbilitiesBridge.php`
- Modify: `src/Plugin.php`
- Create: `tests/integration/Abilities/AbilitiesBridgeTest.php`

Soft dependency. Registers `content-ops/doctor` ability when Abilities API is loaded; no-op otherwise.

- [ ] **Step 1: Write failing test**

Create `tests/integration/Abilities/AbilitiesBridgeTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\Abilities;

use ContentOps\Abilities\AbilitiesBridge;
use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Tests\Integration\TestCase;

final class AbilitiesBridgeTest extends TestCase {

	public function test_is_available_reflects_function_presence(): void {
		$bridge = new AbilitiesBridge( new ActionSchedulerBridge() );
		$this->assertSame( function_exists( 'wp_register_ability' ), $bridge->is_available() );
	}

	public function test_register_is_safe_when_abilities_missing(): void {
		$bridge = new AbilitiesBridge( new ActionSchedulerBridge() );
		$bridge->register();

		$this->assertTrue( true );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter AbilitiesBridgeTest
```

- [ ] **Step 3: Implement `src/Abilities/AbilitiesBridge.php`**

```php
<?php
namespace ContentOps\Abilities;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\CLI\DoctorCommand;

final class AbilitiesBridge {

	private ActionSchedulerBridge $action_scheduler;

	public function __construct( ActionSchedulerBridge $action_scheduler ) {
		$this->action_scheduler = $action_scheduler;
	}

	public function is_available(): bool {
		return function_exists( 'wp_register_ability' );
	}

	public function register(): void {
		if ( ! $this->is_available() ) {
			return;
		}

		add_action( 'abilities_api_init', [ $this, 'register_abilities' ] );
	}

	public function register_abilities(): void {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category( 'content-ops', [
				'label'       => __( 'Content Ops', 'content-ops' ),
				'description' => __( 'Bulk operations for WordPress and WooCommerce content.', 'content-ops' ),
			] );
		}

		$doctor = new DoctorCommand( $this->action_scheduler );

		wp_register_ability( 'content-ops/doctor', [
			'label'               => __( 'Content Ops: doctor', 'content-ops' ),
			'description'         => __( 'Report Content Ops environment health.', 'content-ops' ),
			'category'            => 'content-ops',
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'schema_version'   => [ 'type' => 'string' ],
					'action_scheduler' => [ 'type' => 'object' ],
					'abilities_api'    => [ 'type' => 'object' ],
					'hpos'             => [ 'type' => 'object' ],
					'tables'           => [ 'type' => 'object' ],
					'cron'             => [ 'type' => 'object' ],
				],
			],
			'permission_callback' => static fn () => current_user_can( 'manage_options' ),
			'execute_callback'    => static fn () => $doctor->collect_report(),
		] );
	}
}
```

- [ ] **Step 4: Wire into `src/Plugin.php`**

Extend `on_plugins_loaded` — add after CLI registration:

```php
$abilities_bridge = new \ContentOps\Abilities\AbilitiesBridge( $action_scheduler_bridge );
$abilities_bridge->register();
$this->set( 'abilities.bridge', $abilities_bridge );
```

- [ ] **Step 5: Run — confirm pass**

```bash
npm run env:test -- --filter AbilitiesBridgeTest
```

- [ ] **Step 6: Commit**

```bash
git add src/Abilities/ src/Plugin.php tests/integration/Abilities/
git commit -m "feat: add AbilitiesBridge with soft dependency and content-ops/doctor ability"
```

---

## Task 23: Uninstall handler

**Files:**
- Create: `uninstall.php`
- Create: `tests/integration/UninstallTest.php`

Default: keep data on uninstall. User opts in by setting `content_ops_delete_data_on_uninstall = true`.

- [ ] **Step 1: Write failing test**

Create `tests/integration/UninstallTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration;

use ContentOps\Database\Schema;

final class UninstallTest extends TestCase {

	public function test_uninstall_with_opt_out_keeps_tables(): void {
		Schema::install();
		delete_option( 'content_ops_delete_data_on_uninstall' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'content-ops/content-ops.php' );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'co_operations' ) );
		$this->assertSame( $wpdb->prefix . 'co_operations', $found );
	}
}
```

- [ ] **Step 2: Run — confirm failure**

```bash
npm run env:test -- --filter UninstallTest
```

- [ ] **Step 3: Create `uninstall.php`**

```php
<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require __DIR__ . '/vendor/autoload.php';

if ( ! get_option( 'content_ops_delete_data_on_uninstall', false ) ) {
	return;
}

\ContentOps\Database\Schema::drop_all();

delete_option( 'content_ops_delete_data_on_uninstall' );
```

- [ ] **Step 4: Run — confirm pass**

```bash
npm run env:test -- --filter UninstallTest
```

- [ ] **Step 5: Commit**

```bash
git add uninstall.php tests/integration/UninstallTest.php
git commit -m "feat: add uninstall handler that retains data unless user opted in"
```

---

## Task 24: wp-scripts and React admin scaffold

**Files:**
- Modify: `package.json`
- Create: `assets/src/admin/index.js`

No admin page yet. Phase 1 adds the menu and mounts this bundle.

- [ ] **Step 1: Update `package.json`**

```json
{
  "name": "content-ops",
  "version": "0.1.0-alpha",
  "private": true,
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start",
    "lint:js": "wp-scripts lint-js assets/src",
    "lint:js:fix": "wp-scripts lint-js --fix assets/src",
    "test:js": "wp-scripts test-unit-js",
    "env:start": "wp-env start",
    "env:stop": "wp-env stop",
    "env:destroy": "wp-env destroy",
    "env:test": "wp-env run tests-cli --env-cwd=wp-content/plugins/content-ops composer test:integration"
  },
  "devDependencies": {
    "@wordpress/env": "^10.0.0",
    "@wordpress/scripts": "^30.0.0"
  }
}
```

- [ ] **Step 2: Install**

```bash
npm install
```

- [ ] **Step 3: Create `assets/src/admin/index.js`**

```js
import { createElement, render } from '@wordpress/element';

const mount = () => {
	const root = document.getElementById( 'content-ops-admin-root' );
	if ( ! root ) {
		return;
	}
	render( createElement( 'div', null, 'Content Ops admin scaffold loaded.' ), root );
};

if ( document.readyState !== 'loading' ) {
	mount();
} else {
	document.addEventListener( 'DOMContentLoaded', mount );
}
```

- [ ] **Step 4: Build**

```bash
npm run build
```

Expected: `assets/build/admin.js` and `assets/build/admin.asset.php` are produced.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json assets/src/
git commit -m "feat: add @wordpress/scripts build for React admin scaffold"
```

---

## Task 25: PHPStan configuration

**Files:**
- Create: `phpstan.neon.dist`

- [ ] **Step 1: Create `phpstan.neon.dist`**

```yaml
includes:
  - vendor/szepeviktor/phpstan-wordpress/extension.neon

parameters:
  level: 6
  paths:
    - src
    - content-ops.php
    - uninstall.php
  excludePaths:
    - vendor
    - node_modules
    - assets/build
    - tests
  bootstrapFiles:
    - vendor/autoload.php
  treatPhpDocTypesAsCertain: false
```

- [ ] **Step 2: Run PHPStan**

```bash
composer stan
```

Expected: no errors. Fix any surfaced issues inline or add narrowly-scoped `ignoreErrors` with a reason comment.

- [ ] **Step 3: Commit**

```bash
git add phpstan.neon.dist
git commit -m "chore: add PHPStan level 6 config with WordPress stubs"
```

---

## Task 26: GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Create `.github/workflows/ci.yml`**

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  unit:
    name: PHPUnit (unit) — PHP ${{ matrix.php }}
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['7.4', '8.1', '8.3']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none
          tools: composer:v2
      - run: composer install --prefer-dist --no-progress
      - run: composer test:unit

  lint:
    name: PHPCS + PHPStan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: none
          tools: composer:v2
      - run: composer install --prefer-dist --no-progress
      - run: composer lint
      - run: composer stan

  integration:
    name: PHPUnit (integration) via wp-env
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: none
          tools: composer:v2
      - run: composer install --prefer-dist --no-progress
      - run: npm ci
      - run: npm run env:start
      - run: npm run env:test
      - if: always()
        run: npm run env:destroy

  js:
    name: JS build
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      - run: npm ci
      - run: npm run build
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add GitHub Actions workflows for unit, lint, integration, and js build"
```

If a remote is configured, push and verify all four jobs go green. Otherwise, defer the remote push to when the repo is hosted.

---

## Task 27: Development documentation

**Files:**
- Create: `docs/development.md`
- Create: `docs/architecture.md`

- [ ] **Step 1: Create `docs/development.md`**

```markdown
# Development

## Prerequisites

- PHP 7.4+
- Composer 2.x
- Node 18+
- Docker (for wp-env)

## Setup

```
composer install
npm install
```

## Running tests

```
composer test:unit           # fast unit tests (brain-monkey, no WP)
npm run env:start            # boot wp-env
npm run env:test             # run integration tests inside wp-env
composer lint                # PHPCS
composer stan                # PHPStan
npm run build                # build admin JS
```

## Manual smoke test

```
npm run env:start
wp-env run cli --env-cwd=wp-content/plugins/content-ops wp content-ops doctor --format=json
```

Should return a JSON report with environment health.

## Layout

See `docs/architecture.md` for the architectural overview. Specs and plans live under `docs/superpowers/`.
```

- [ ] **Step 2: Create `docs/architecture.md`**

```markdown
# Architecture

Content Ops unifies bulk content operations — delete, duplicate, bulk edit, move, find/replace, CSV round-trip — across any post type, with WooCommerce support in later phases.

## Core abstractions

Every feature is the intersection of a **Target** (kind of thing) and an **Operation** (what to do). Each side plugs in independently.

- `ContentOps\Contracts\TargetInterface`
- `ContentOps\Contracts\OperationInterface`

Targets and operations self-register into `TargetRegistry` and `OperationRegistry`.

## Persistence

Three custom tables:

- `{prefix}co_operations` — one row per operation performed or scheduled.
- `{prefix}co_snapshots` — before-state for undo.
- `{prefix}co_schedules` — recurring-rule definitions (Pro).

Deletes rely on WordPress trash instead of snapshots.

## Safety

- Dry-run returns count, sample, and preview token.
- Preview token is HMAC(filters + params + matched IDs + salt). Execute requires a fresh token.
- Structured errors: `{ code, message, context }`.
- Undo from stored snapshots.

## Agent integration

- Abilities API: every Target × Operation registers when the Abilities API plugin is present.
- WP-CLI: peer command for every operation, with `--dry-run`, `--format=json|table`, stable exit codes.
- REST: same endpoints back UI and external agent callers.

## Async execution

Operations above threshold (default 100) queue to Action Scheduler in chunks (default 50). UI polls REST for progress.

## Phases

See `docs/superpowers/plans/` for detailed per-phase implementation plans.
```

- [ ] **Step 3: Commit**

```bash
git add docs/development.md docs/architecture.md
git commit -m "docs: add development setup and architecture overview"
```

---

## Task 28: End-to-end verification

- [ ] **Step 1: Unit tests green**

```bash
composer test:unit
```

Expected: all tests pass.

- [ ] **Step 2: Integration tests green**

```bash
npm run env:start
npm run env:test
```

Expected: all tests pass.

- [ ] **Step 3: PHPCS clean**

```bash
composer lint
```

Expected: no errors.

- [ ] **Step 4: PHPStan clean**

```bash
composer stan
```

Expected: `[OK] No errors`.

- [ ] **Step 5: JS builds**

```bash
npm run build
```

Expected: `assets/build/admin.js` produced.

- [ ] **Step 6: Doctor via CLI smoke test**

```bash
wp-env run cli --env-cwd=wp-content/plugins/content-ops wp content-ops doctor --format=json
```

Expected: JSON with `schema_version: "1.0.0"`, `action_scheduler.available: true`, `tables.missing: []`.

- [ ] **Step 7: Doctor via REST smoke test**

```bash
wp-env run cli wp eval 'wp_set_current_user(1); $r = rest_do_request( new WP_REST_Request( "GET", "/content-ops/v1/doctor" ) ); echo wp_json_encode( $r->get_data() );'
```

Expected: JSON with the same shape as the CLI.

- [ ] **Step 8: Update CHANGELOG**

Replace the `[Unreleased]` section of `CHANGELOG.md`:

```markdown
## [Unreleased]

### Added
- Phase 0 foundation complete:
  - Plugin bootstrap, Composer PSR-4 autoload, Plugin service locator.
  - `TargetInterface`, `OperationInterface`, `QueryArgs`, `FilterDefinition`, result value objects.
  - `TargetRegistry`, `OperationRegistry`.
  - Three custom tables: `co_operations`, `co_snapshots`, `co_schedules`.
  - `OperationRepository`, `SnapshotRepository`.
  - Preview token system (generator, transient-backed store, verifier).
  - Action Scheduler bridge (defers to WooCommerce's copy when present).
  - Capabilities: `content_ops_delete`, `content_ops_edit`, `content_ops_duplicate`, `content_ops_move`, `content_ops_schedule`.
  - REST controller base with structured error responses.
  - `GET /wp-json/content-ops/v1/doctor` endpoint.
  - `wp content-ops doctor` CLI command.
  - Abilities API bridge (soft dependency) registering `content-ops/doctor`.
  - Uninstall handler that retains data unless user opted in.
  - React admin scaffold via `@wordpress/scripts`.
  - PHPCS, PHPStan, PHPUnit (unit + integration), GitHub Actions CI.
```

- [ ] **Step 9: Tag and commit**

```bash
git add CHANGELOG.md
git commit -m "docs: mark Phase 0 foundation complete in changelog"
git tag v0.1.0-alpha
```

---

## What Phase 0 does NOT include (intentional)

- **No admin menu page.** Phase 1 adds the `Content Ops` menu and renders the React scaffold.
- **No concrete Targets or Operations.** Phase 1 ships `PostTarget`, `DeleteOperation`, `DuplicateOperation`, and bulk-edit operations.
- **No scheduling runtime.** The `co_schedules` table exists so later migrations aren't needed. Phase 3 reads/writes it.
- **No find/replace, CSV, or move operations.** Phase 3.
- **No WooCommerce integration.** Phase 4.
- **No `llms.txt`, no WP.org listing.** Phase 2.

After this plan completes, the next plan is `docs/superpowers/plans/<date>-phase-1-mvp.md`.

---

## Self-review (completed during plan authoring)

- **Spec coverage** — every Phase 0 bullet from §10 of the spec (plugin skeleton, Composer + wp-scripts, Target/Operation interfaces, operations history + undo plumbing, preview-token mechanism, CLI command registrar, Abilities API integration scaffold) has a task. The doctor command gives us one end-to-end thread from CLI → Controller → Abilities to prove the integration works.
- **Placeholder scan** — no TBDs, no "implement later", no "add error handling" vagueness. Every step has the actual code or the exact command.
- **Type consistency** — `Operation`, `Snapshot`, `OperationRepository`, `SnapshotRepository`, `Schema`, `Migrations`, `ActionSchedulerBridge`, `ContentOpsError`, `QueryArgs`, `FilterDefinition`, `ValidationResult`, `PreviewResult`, `BatchResult`, `UndoResult`, `TargetInterface`, `OperationInterface`, `TargetRegistry`, `OperationRegistry`, `TokenGenerator`, `TokenStore`, `TokenVerifier`, `Capabilities`, `RestController`, `DoctorController`, `RouteRegistrar`, `CommandRegistrar`, `DoctorCommand`, `AbilitiesBridge` — all referenced names match across tasks.
- **Scope check** — 28 tasks, each producing a single focused, testable change. Suitable for subagent-driven execution with per-task review.
