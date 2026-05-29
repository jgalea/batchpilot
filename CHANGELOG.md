# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [Unreleased]

## [1.0.0] - 2026-05-29

### Changed

- Renamed plugin from "Content Ops" to "BatchPilot" across PHP namespace, slug, text domain, REST namespace, WP-CLI command, capabilities, Action Scheduler hook, DB table prefix, JS bootstrap global, CSS prefix, and error-code namespace. See the rename commit for the full identifier map.
- Bumped Plugin URI from placeholder to GitHub repo URL.
- Removed `load_plugin_textdomain()` call now that WordPress.org auto-loads translations under the plugin slug.
- Removed `Domain Path: /languages` header since the languages directory does not ship.

### Added

- `ids` filter on `PostTarget` so the post-list row and bulk-action deep links into the Operations Builder preview correctly (previously they opened the builder but showed 0 matches because the `filters[ids][]=N` query string had no matching `FilterDefinition`).
- `if ( ! defined( 'ABSPATH' ) ) { exit; }` direct-access guard on every PHP file under `src/`.
- WordPress.org-format `readme.txt` with description, FAQ, screenshots list, and changelog.

### Fixed

- `tests/unit/bootstrap.php` defines `ABSPATH` so the new direct-access guards do not short-circuit Brain Monkey unit runs outside WordPress.

## [0.3.0-alpha] - 2026-04-23

### Added

Phase 1b admin UI — plugin now usable entirely from WordPress admin, no CLI or REST required.

#### Admin shell (PHP)
- `AdminMenu` — top-level "BatchPilot" menu (`dashicons-list-view`) with four submenus: Dashboard, Operations, History, Settings. Each renders a page-slug-specific `<div id="batchpilot-{slug}-root">` into which React mounts.
- `AssetLoader` — enqueues `assets/build/admin.js` only on BatchPilot pages, with `window.batchPilotAdmin` bootstrap carrying REST URL, nonce, capability map, page URLs.
- `Settings` + `SettingsController` — single `batchpilot_settings` option, `GET`/`POST /batchpilot/v1/settings` (manage_options). Defaults merged server-side; unknown keys rejected.
- `PostListIntegration` — injects "Duplicate with BatchPilot" row action and "BatchPilot: Delete / Duplicate / Bulk edit" bulk-action entries on post/page list tables. All deep-link into the Operations Builder prefilled.

#### REST additions
- `POST /preview` response extended with `display_rows` array (same length as `sample_ids`, each entry from `target.get_display()`) so the preview panel renders titles/status/dates/edit links without a second round-trip.
- `GET`/`POST /batchpilot/v1/settings` — new endpoints.

#### React admin app (single bundle, `assets/src/admin/`)
- Entry + router: `index.js` + `router.js` detect which `#batchpilot-{slug}-root` is present and mount the matching page.
- `api.js` — `@wordpress/api-fetch` wrapper with typed helpers (`fetchCatalog`, `preview`, `execute`, `listOperations`, `getOperation`, `undoOperation`, `fetchDoctor`, `getSettings`, `saveSettings`), AbortController plumbing, normalized `{ code, message, context }` errors.
- **Dashboard** — HealthPanel (doctor checklist), StatsCard (ops this week, items affected), RecentOperationsList (last 5 with one-click undo), PresetCards (Common Cleanups deep-linking into Operations Builder).
- **Operations Builder** — TargetPicker, FilterList with FilterRow supporting 6 input types (enum/bool/date/user/post/taxonomy), OperationPicker (pills filtered by target support), OperationParamsForm (derived from `params_schema`), PreviewPanel (debounced live preview with `useDebouncedPreview`, 300ms + AbortController), ExecuteButton (armed after successful preview, disabled when no token), ExecutionResult (completed/queued display). Reducer-based state (`state/builderReducer.js` + `state/builderContext.js`).
- **Deep-link prefill** — `?preset=...`, `?rerun=...`, and raw `?target=...&operation=...&filters[ids][]=...` all dispatched on mount after catalog loads.
- **History** — HistoryTable (Date/Type/Target/Items/Status/User/Actions columns, prev/next pagination via `listOperations`). OperationDetailsModal (filters/params JSON + affected IDs). Row actions: view details, undo (with confirmation + refresh), re-run (deep link).
- **Settings** — SettingsForm with async_threshold, batch_size, delete_permanent_default, history_retention_days. AI agent access panel linking to WP users admin.

#### Tests
- **Jest + React Testing Library** suite: 51 tests / 21 suites covering every component, reducer, hook, and api helper. `tests/js/setup-jest-dom.js` + `jest-unit.config.js` added.
- PHP unit and integration suites extended: 43 unit / 134 integration (1 skipped — Abilities matrix), 367 assertions.

### Known limitations / deferred to Phase 1b.1
- `PostTarget` does not register an `ids` filter — bulk-action and row-action deep links open the Operations Builder correctly but the live preview shows 0 matches until Phase 1b.1 adds an `ids` `FilterDefinition`.
- Playwright E2E tests (plan Tasks 21–22) deferred to Phase 1b.1; Jest + PHP integration coverage is thorough, E2E is belt-and-suspenders.
- Async autocomplete for user/post filters deferred — Phase 1b ships simple numeric ID inputs.
- Match mode is "All filters" only; "Any" deferred to Phase 2.
- Row actions open Operations Builder via query-string deep link (new tab); in-place modals are Phase 2 polish.

### Test coverage at release
- **PHP Unit:** 43 tests / 96 assertions.
- **PHP Integration:** 134 tests / 367 assertions, 1 skipped (Abilities matrix).
- **JS (Jest):** 51 tests / 21 suites.
- **Static analysis:** PHPCS clean, PHPStan level 6 `[OK] No errors`, ESLint clean.
- **Bundle size:** `admin.js` ≈ 25 KB minified.

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
- `OperationRunner` — Action Scheduler hook handler (`batchpilot_run_operation`). Loads operation row, re-resolves Target/Operation, chunks IDs at `ExecutionService::BATCH_SIZE = 50`, calls `execute_batch()` per chunk, aggregates results.

#### REST surface (`batchpilot/v1`)
- `GET /catalog` — registered targets, operations (with param schemas), and curated presets.
- `POST /preview` — runs `preview()`, returns count + sample_ids + `preview_token` + warnings. Per-op capability check.
- `POST /execute` — verifies `preview_token`, records operation history row, either runs synchronously OR enqueues Action Scheduler action when matched count exceeds `apply_filters('batchpilot_async_threshold', 100)`. Returns `200` (completed) or `202` (queued).
- `GET /operations` — paginated history list (`?limit=20&offset=0`).
- `GET /operations/{id}` — history detail.
- `POST /operations/{id}/undo` — resolves Operation by stored type, calls `undo()`, returns `UndoResult` JSON.

#### WP-CLI commands
- `wp batchpilot delete [--post-type=X] [--status=Y] [--older-than=Nd] [--permanent] [--dry-run] [--yes] [--format=json|table|count|ids]` — builds QueryArgs from flags, previews, then executes synchronously.
- `wp batchpilot duplicate [--post-type=X] [--status=Y] [--target-status=draft] [--title-suffix=...] [--dry-run] [--yes] [--format=...]`.
- `wp batchpilot edit [--post-type=X] [--status=Y] [--set-status=...] [--reassign-author=N] [--shift-dates=N] [--comment-status=...] [--menu-order=N] [--dry-run] [--yes] [--format=...]`.
- `wp batchpilot history [--limit=N] [--format=table|json]` — lists recent operations newest-first.
- `wp batchpilot undo <operation_id> [--yes] [--format=...]` — invokes the operation's `undo()` handler.

#### Abilities API (soft dependency)
- `AbilitiesBridge` now iterates Target × Operation pairs and registers `batchpilot/{target_slug}_{op_slug}` abilities when Abilities API is installed. Each ability's input schema merges filters + operation params, output schema mirrors `PreviewResult`, permission callback maps to the per-op `batchpilot_*` capability, execute callback delegates to `ExecutionService::preview()`.

#### Plugin boot wiring
- `Plugin::on_plugins_loaded()` now instantiates registries, creates one `PostTarget` per public post type, registers the three operations, wires `OperationRunner` to the `batchpilot_run_operation` Action Scheduler hook, and passes registries into `RouteRegistrar`, `CommandRegistrar`, and `AbilitiesBridge`.
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
- **End-to-end smoke:** `wp batchpilot doctor --format=json`, `GET /wp-json/batchpilot/v1/catalog` (HTTP 200 with 2 targets / 3 operations / 2 presets) both verified green.

## [0.1.0-alpha] - 2026-04-22

### Added

Phase 0 foundation — a tested platform; no user-facing features yet.

#### Platform
- Plugin bootstrap (`batchpilot.php`) with header, autoload guard, and admin-notice fallback.
- Composer PSR-4 autoload under namespace `BatchPilot\`.
- `Plugin` service-locator with idempotent `boot()`, hook registration, and test-friendly reset.
- `Activator` wires database migrations + capability grants on activation.
- `Deactivator` stub (later phases unschedule recurring Action Scheduler actions here).
- `uninstall.php` that retains data by default; drops tables only when the user opts in via `batchpilot_delete_data_on_uninstall` option.

#### Core abstractions
- `TargetInterface`, `OperationInterface` — the Target × Operation matrix contracts.
- `QueryArgs` immutable filter bag, `FilterDefinition` filter descriptor.
- `ValidationResult`, `PreviewResult`, `BatchResult`, `UndoResult` — outcome types for operation methods.
- `BatchPilotError` structured error (`{ code, message, context }`), convertible to `WP_Error`.
- `TargetRegistry`, `OperationRegistry` — duplicate-slug protection via `LogicException`.

#### Persistence
- Three custom tables via `dbDelta`:
  - `{prefix}batchpilot_operations` — operation history.
  - `{prefix}batchpilot_snapshots` — undo before-state.
  - `{prefix}batchpilot_schedules` — ships empty; read/written starting Phase 3.
- `Schema::install()` / `drop_all()`, `Migrations::maybe_migrate()` keyed on `batchpilot_schema_version` option.
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
- Five granular caps: `batchpilot_delete`, `batchpilot_edit`, `batchpilot_duplicate`, `batchpilot_move`, `batchpilot_schedule`. Granted to administrator role on activation.

#### REST, CLI, Abilities
- Base `RestController` with structured error responses and capability permission helper (`co.auth.forbidden`).
- `DoctorController` + `RouteRegistrar` — `GET /wp-json/batchpilot/v1/doctor` reports schema version, Action Scheduler availability, Abilities API availability, HPOS status, table presence, cron status.
- `CommandRegistrar` + `DoctorCommand` — `wp batchpilot doctor [--format=json|table]`.
- `AbilitiesBridge` (soft dependency) — registers `batchpilot` category and `batchpilot/doctor` ability when the Abilities API plugin is present. No-ops gracefully otherwise.

#### Dev tooling
- PHPUnit unit test harness with Brain Monkey (`tests/unit`, `phpunit.xml.dist`).
- `wp-env` + `wp-phpunit` integration test harness (`tests/integration`, `phpunit-integration.xml.dist`, `.wp-env.json`).
- PHPCS config using WordPress-Extra ruleset with short-array syntax permitted.
- PHPStan level 6 with `phpstan-wordpress` stubs (`phpstan.neon.dist`).
- `@wordpress/scripts` build toolchain with React admin entry scaffold (renders to `#batchpilot-admin-root`).
- GitHub Actions CI matrix: PHPUnit (PHP 7.4, 8.1, 8.3), PHPCS + PHPStan, wp-env integration, JS build.

#### Docs
- `docs/development.md` — setup + command reference.
- `docs/architecture.md` — core abstractions, persistence, safety model, agent integration, async execution.
- `docs/superpowers/specs/2026-04-21-batchpilot-plugin-design.md` — product design spec.
- `docs/superpowers/plans/2026-04-21-phase-0-foundation.md` — this Phase 0 plan.

### Test coverage
- **Unit:** 33 tests / 72 assertions.
- **Integration:** 32 tests / 79 assertions.
- **Static analysis:** PHPCS clean on 8 files; PHPStan level 6 `[OK] No errors`.
- **End-to-end smoke:** `wp batchpilot doctor --format=json` and `GET /wp-json/batchpilot/v1/doctor` (HTTP 200) both return the expected shape.

### Deviations from plan (documented in individual commits)
- Task 4: `uninstall.php` was temporarily excluded from PHPCS file list (re-added in Task 23); short-array rule explicitly excluded.
- Task 5: integration test bootstrap prefers `WP_TESTS_DIR` over `WP_PHPUNIT__DIR` for wp-env compatibility.
- Task 8: PHP name collision between static factory `error()` and instance getter forced renaming the getter to `get_error()` on all four Result classes.
- Task 10: `RuntimeException` bumped to `LogicException` for duplicate-slug registration (programmer error, not runtime).
- Task 11 hygiene: widened `type` / `status` / `operation_type` column widths preemptively to avoid a first-migration ceremony for a VARCHAR bump.
- Task 17: `plugins_loaded` hook priority changed to `-1` so Action Scheduler's own priority-0/1 init callbacks can fire after our require.
- Task 24: wp-scripts build extended with `--webpack-src-dir` / `--output-path` flags to use `assets/src/admin/index.js` entry without introducing a custom `webpack.config.js`.
- Task 25: PHPStan phpdoc additions across `src/` shipped as a separate `chore:` commit before the config itself.
