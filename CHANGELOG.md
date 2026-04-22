# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [Unreleased]

## [0.1.0-alpha] - 2026-04-22

### Added

Phase 0 foundation — a tested platform; no user-facing features yet.

#### Platform
- Plugin bootstrap (`content-ops.php`) with header, autoload guard, and admin-notice fallback.
- Composer PSR-4 autoload under namespace `ContentOps\`.
- `Plugin` service-locator with idempotent `boot()`, hook registration, and test-friendly reset.
- `Activator` wires database migrations + capability grants on activation.
- `Deactivator` stub (later phases unschedule recurring Action Scheduler actions here).
- `uninstall.php` that retains data by default; drops tables only when the user opts in via `content_ops_delete_data_on_uninstall` option.

#### Core abstractions
- `TargetInterface`, `OperationInterface` — the Target × Operation matrix contracts.
- `QueryArgs` immutable filter bag, `FilterDefinition` filter descriptor.
- `ValidationResult`, `PreviewResult`, `BatchResult`, `UndoResult` — outcome types for operation methods.
- `ContentOpsError` structured error (`{ code, message, context }`), convertible to `WP_Error`.
- `TargetRegistry`, `OperationRegistry` — duplicate-slug protection via `LogicException`.

#### Persistence
- Three custom tables via `dbDelta`:
  - `{prefix}co_operations` — operation history.
  - `{prefix}co_snapshots` — undo before-state.
  - `{prefix}co_schedules` — ships empty; read/written starting Phase 3.
- `Schema::install()` / `drop_all()`, `Migrations::maybe_migrate()` keyed on `content_ops_schema_version` option.
- `Operation` + `OperationRepository` CRUD (create, mark_running, mark_completed, mark_failed, find, list).
- `Snapshot` + `SnapshotRepository` (bulk_insert, for_operation, delete_for_operation).

#### Safety mechanisms
- `TokenGenerator` — deterministic HMAC-SHA256 over canonicalized payload.
- `TokenStore` — transient-backed with configurable TTL (default 300 s).
- `TokenVerifier` — verifies token + re-canonicalizes current payload; invalidates on drift.

#### Async
- `ActionSchedulerBridge` — detects or requires bundled Action Scheduler; `schedule_single_action`, `schedule_recurring_action`, `cancel_action`, `action_exists`. Defers to WooCommerce's copy when present.
- `plugins_loaded` hook priority set to `-1` so Action Scheduler's own priority-0/1 init callbacks fire after our require.

#### Capabilities
- Five granular caps: `content_ops_delete`, `content_ops_edit`, `content_ops_duplicate`, `content_ops_move`, `content_ops_schedule`. Granted to administrator role on activation.

#### REST, CLI, Abilities
- Base `RestController` with structured error responses and capability permission helper (`co.auth.forbidden`).
- `DoctorController` + `RouteRegistrar` — `GET /wp-json/content-ops/v1/doctor` reports schema version, Action Scheduler availability, Abilities API availability, HPOS status, table presence, cron status.
- `CommandRegistrar` + `DoctorCommand` — `wp content-ops doctor [--format=json|table]`.
- `AbilitiesBridge` (soft dependency) — registers `content-ops` category and `content-ops/doctor` ability when the Abilities API plugin is present. No-ops gracefully otherwise.

#### Dev tooling
- PHPUnit unit test harness with Brain Monkey (`tests/unit`, `phpunit.xml.dist`).
- `wp-env` + `wp-phpunit` integration test harness (`tests/integration`, `phpunit-integration.xml.dist`, `.wp-env.json`).
- PHPCS config using WordPress-Extra ruleset with short-array syntax permitted.
- PHPStan level 6 with `phpstan-wordpress` stubs (`phpstan.neon.dist`).
- `@wordpress/scripts` build toolchain with React admin entry scaffold (renders to `#content-ops-admin-root`).
- GitHub Actions CI matrix: PHPUnit (PHP 7.4, 8.1, 8.3), PHPCS + PHPStan, wp-env integration, JS build.

#### Docs
- `docs/development.md` — setup + command reference.
- `docs/architecture.md` — core abstractions, persistence, safety model, agent integration, async execution.
- `docs/superpowers/specs/2026-04-21-content-ops-plugin-design.md` — product design spec.
- `docs/superpowers/plans/2026-04-21-phase-0-foundation.md` — this Phase 0 plan.

### Test coverage
- **Unit:** 33 tests / 72 assertions.
- **Integration:** 32 tests / 79 assertions.
- **Static analysis:** PHPCS clean on 8 files; PHPStan level 6 `[OK] No errors`.
- **End-to-end smoke:** `wp content-ops doctor --format=json` and `GET /wp-json/content-ops/v1/doctor` (HTTP 200) both return the expected shape.

### Deviations from plan (documented in individual commits)
- Task 4: `uninstall.php` was temporarily excluded from PHPCS file list (re-added in Task 23); short-array rule explicitly excluded.
- Task 5: integration test bootstrap prefers `WP_TESTS_DIR` over `WP_PHPUNIT__DIR` for wp-env compatibility.
- Task 8: PHP name collision between static factory `error()` and instance getter forced renaming the getter to `get_error()` on all four Result classes.
- Task 10: `RuntimeException` bumped to `LogicException` for duplicate-slug registration (programmer error, not runtime).
- Task 11 hygiene: widened `type` / `status` / `operation_type` column widths preemptively to avoid a first-migration ceremony for a VARCHAR bump.
- Task 17: `plugins_loaded` hook priority changed to `-1` so Action Scheduler's own priority-0/1 init callbacks can fire after our require.
- Task 24: wp-scripts build extended with `--webpack-src-dir` / `--output-path` flags to use `assets/src/admin/index.js` entry without introducing a custom `webpack.config.js`.
- Task 25: PHPStan phpdoc additions across `src/` shipped as a separate `chore:` commit before the config itself.
