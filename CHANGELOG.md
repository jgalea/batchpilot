# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [Unreleased]

## [0.2.0-alpha] - 2026-04-23

### Added

Phase 1a backend MVP — plugin is now usable via WP-CLI, REST, and the Abilities API.

#### Concrete Targets and Operations
- `PostTarget` — one instance per registered public post type (slug = post type slug). 12 filters: post_type, status, author, published_before/after, modified_before/after, taxonomy, has_comments, has_featured_image, post_parent, has_children. Query against real `WP_Query`; `get_display()` returns preview-row summary.
- `DeleteOperation` — trash by default, `permanent=true` to hard-delete. Undo untrashes (guards against permanent deletes).
- `DuplicateOperation` — copies post with meta (excluding `_edit_lock`), taxonomies, featured image, optional child-include. Target status defaults to `draft`. Undo hard-deletes the duplicates.
- `BulkEditOperation` — applies set_status, reassign_author, shift_dates (±N days), taxonomy add/remove, password, comment_status, menu_order. Snapshots every mutated field (including taxonomies as JSON-encoded term IDs) for undo. Undo restores from snapshots.

#### Execution pipeline
- `ExecutionService` — shared `preview()`, `record()`, `run_sync()` methods. Binds Target, Operation, query, preview token, and history row.
- `OperationRunner` — Action Scheduler hook handler (`content_ops_run_operation`). Loads operation row, re-resolves Target/Operation, chunks IDs at `ExecutionService::BATCH_SIZE = 50`, calls `execute_batch()` per chunk, aggregates results.

#### REST surface (`content-ops/v1`)
- `GET /catalog` — registered targets, operations (with param schemas), and curated presets.
- `POST /preview` — runs `preview()`, returns count + sample_ids + `preview_token` + warnings. Per-op capability check.
- `POST /execute` — verifies `preview_token`, records operation history row, either runs synchronously OR enqueues Action Scheduler action when matched count exceeds `apply_filters('content_ops_async_threshold', 100)`. Returns `200` (completed) or `202` (queued).
- `GET /operations` — paginated history list (`?limit=20&offset=0`).
- `GET /operations/{id}` — history detail.
- `POST /operations/{id}/undo` — resolves Operation by stored type, calls `undo()`, returns `UndoResult` JSON.

#### WP-CLI commands
- `wp content-ops delete [--post-type=X] [--status=Y] [--older-than=Nd] [--permanent] [--dry-run] [--yes] [--format=json|table|count|ids]` — builds QueryArgs from flags, previews, then executes synchronously.
- `wp content-ops duplicate [--post-type=X] [--status=Y] [--target-status=draft] [--title-suffix=...] [--dry-run] [--yes] [--format=...]`.
- `wp content-ops edit [--post-type=X] [--status=Y] [--set-status=...] [--reassign-author=N] [--shift-dates=N] [--comment-status=...] [--menu-order=N] [--dry-run] [--yes] [--format=...]`.
- `wp content-ops history [--limit=N] [--format=table|json]` — lists recent operations newest-first.
- `wp content-ops undo <operation_id> [--yes] [--format=...]` — invokes the operation's `undo()` handler.

#### Abilities API (soft dependency)
- `AbilitiesBridge` now iterates Target × Operation pairs and registers `content-ops/{target_slug}_{op_slug}` abilities when Abilities API is installed. Each ability's input schema merges filters + operation params, output schema mirrors `PreviewResult`, permission callback maps to the per-op `content_ops_*` capability, execute callback delegates to `ExecutionService::preview()`.

#### Plugin boot wiring
- `Plugin::on_plugins_loaded()` now instantiates registries, creates one `PostTarget` per public post type, registers the three operations, wires `OperationRunner` to the `content_ops_run_operation` Action Scheduler hook, and passes registries into `RouteRegistrar`, `CommandRegistrar`, and `AbilitiesBridge`.
- `RouteRegistrar`, `CommandRegistrar`, `AbilitiesBridge` constructors updated to accept registries + ExecutionService + repositories.

#### Curated presets
- `PresetCatalog` ships two starter presets accessible via `/catalog` → `presets`:
  - `trash-old-drafts` — trash post drafts modified over 90 days ago.
  - `trash-auto-drafts` — trash all auto-draft posts.

### Fixed / hardened
- Test isolation: operation test classes now use matching `wpSetUpBeforeClass` / `wpTearDownAfterClass` to install and drop schema, preventing cross-class DB leakage into `SchemaTest`.
- `tests/unit/bootstrap.php` polyfills `DAY_IN_SECONDS` for unit runs that load production code depending on the WP constant.

### Deviations from plan (documented in commits)
- Plan's `modified_before` / `post_modified` test pattern updated to use `$wpdb->update()` + `clean_post_cache()` — WordPress silently ignores `post_modified`/`post_modified_gmt` on `wp_insert_post`.
- `wp_untrash_post` restores to `draft` not previous status in WP 5.6+ by default; tests that require the "restore to previous status" contract register the `wp_untrash_post_set_previous_status` filter explicitly.
- `BatchResult::of` invariant (`processed === succeeded + failed`; `count(item_errors) <= failed`) enforced on every construction path.
- `composer stan` script: `--memory-limit=512M` baked in to avoid crashes on default PHP memory.

### Test coverage
- **Unit:** 43 tests / 96 assertions.
- **Integration:** 119 tests / 321 assertions (1 skipped: Abilities matrix — Abilities API not installed in wp-env).
- **Static analysis:** PHPCS clean, PHPStan level 6 `[OK] No errors`.
- **End-to-end smoke:** `wp content-ops doctor --format=json`, `GET /wp-json/content-ops/v1/catalog` (HTTP 200 with 2 targets / 3 operations / 2 presets) both verified green.

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
