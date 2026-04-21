# Content Ops — Plugin Design Spec

**Date:** 2026-04-21
**Status:** Approved (brainstorming phase complete)
**Author:** Jean Galea (with Claude)

## 1. Overview

Content Ops is a freemium WordPress plugin that unifies bulk content operations — duplicate, delete, bulk edit, move, find/replace, CSV round-trip — across any post type, with first-class support for WooCommerce products and orders. It is distributed on WordPress.org (free tier) with an in-plugin upgrade to a single Pro SKU.

The distinctive positioning is **AI-agent-first**: every operation is registered as a WordPress Ability and exposed via MCP when an adapter is present, with preview-token safety patterns, structured errors, and granular capabilities. Site owners running AI agents on their WordPress site get a tool their agent can safely drive, with undo/restore as a safety net.

## 2. Goals

- Beat incumbents (Copy & Delete Posts, WP Bulk Delete) on usability, safety, and scope in the free tier.
- Capture the WooCommerce bulk-ops niche, where the native WC duplicate and bulk-edit flows are weak (especially for variable products and order cleanup).
- Be the first widely-distributed WP plugin built from the ground up for safe AI-agent invocation via the Abilities API.
- Sustain a profitable Pro SKU via automation/scheduling features, advanced filters, and WooCommerce depth.

## 3. Non-goals

- Full audit/activity logging (WP Activity Log handles this; we fire hooks so it picks up our ops).
- Database janitorial cleanup — transients, orphan options, revisions (WP-Sweep handles this).
- Backup/restore (per-operation undo is not a backup product; UpdraftPlus etc. handle full backups).
- Cross-site migration or WXR-style export (WP All Import/Export covers this).
- Subscriptions, Memberships, Bookings in Phase 1 — deliberately deferred to Phase 5.
- Page-builder-specific ops (Elementor, Divi templates).

## 4. Audience & positioning

Primary audience: **everybody running WordPress or WooCommerce who does bulk content operations.** The free tier is the acquisition engine; Pro targets:

- Content publishers cleaning up long-running sites
- WooCommerce store owners managing products and orders
- Agencies and site operators who inherit messy sites
- Site owners running AI agents (emerging but growing)

Positioning tagline: **"Bulk operations for WordPress & WooCommerce, designed for humans and AI agents."**

## 5. Business model

**Freemium monolith.** One codebase, free on WordPress.org, Pro unlocked via license activation in-plugin. No core+add-ons fragmentation.

EDD Software Licensing for the Pro licensing layer (implemented later, in Phase 3).

Pricing, domain, branding, and telemetry strategy are deferred — none are blockers for Phases 0–2.

## 6. Feature surface

### 6.1 Free (WordPress.org)

**Operations**
- **Duplicate** — any post / page / CPT. Preserves post meta, taxonomies, featured image, children. Options: target status, author reassignment, date behavior, optional title suffix.
- **Delete** — with filters. Trash by default; hard-delete is an explicit opt-in.
- **Bulk edit essentials** — status, author reassignment, date shift (±N days), taxonomy add/remove/replace, password, comment status, `menu_order`.

**Filters**
Post type, status, author, date range (published/modified), taxonomy, has/doesn't-have comments, has/doesn't-have featured image, post parent, has children.

**Safety & agent layer**
- Dry-run preview (count + 20-item sample) on every destructive op.
- Operations History (30 days) with Undo and Re-run.
- Preview-token pattern and structured errors on all endpoints.
- Idempotency keys supported on destructive operations.
- Granular capabilities (`content_ops_delete`, `content_ops_edit`, `content_ops_duplicate`).
- WP-CLI parity for every operation (`--dry-run`, `--format=json|table|csv|count|ids`, stable exit codes).
- Abilities API registration (soft dependency — lights up when the Abilities API plugin is present).
- `wp content-ops doctor` environment check command.

### 6.2 Pro

**Scheduling & automation** (flagship Pro value)
- Named recurring rules (cron expression or friendly picker).
- Per-rule enable/disable, last-run/next-run, run history.
- Failure notifications via email or admin notice.
- Rules engine for conditional chains ("if matches > 0, run X, then email summary").

**Advanced filters**
- Custom field / meta queries (`key`, `value`, `compare`).
- ACF-aware filter builder when ACF is installed.
- Regex on title, content, excerpt, slug.
- Word-count bounds.
- Orphaned attachments (no post parent).
- No inbound internal links.
- Expression builder for power users.

**Move operations**
- Change post type in bulk. Handles taxonomy-compatibility (strip, keep as orphaned, or warn and skip) and optional children-follow behavior (child posts/attachments inherit the new post type or stay with the old one).
- Move comments between posts (single or bulk merge).
- Reassign attachment parents.
- Merge/rename taxonomy terms.

**Content transforms**
- Bulk find & replace on titles/content/meta, regex supported, per-field scoping, preview.
- CSV export of any selection.
- CSV import to update matched items (edit externally, push back).

**Operations infrastructure**
- Extended History retention (configurable up to indefinite).
- Saved Operation Templates (named filter+op presets, one-click re-run or scheduling). These are user-created; distinct from the curated "Common Cleanups" that ship with the free plugin.
- Commercial-scale REST/Abilities access. Free exposes the full REST/Abilities surface for agent use but enforces a conservative per-user rate limit (default: 20 requests/min, configurable by site admin). Pro raises the default cap substantially (600 req/min) and unlocks multi-user application passwords without shared quota — needed for agencies and CI pipelines. The REST surface itself is not gated; only the throughput.
- Multisite network operations.
- White-label branding and team access controls (per-capability role assignment).

**WooCommerce Pack (Phase 4, part of Pro)**

Products:
- Correct duplication of variable products — all variations, attributes, prices, stock, images, shipping/tax classes, ACF. Fixes WC core's weak variable-product duplicate.
- Bulk delete by stock status, zero-sales, category/tag, visibility, price range.
- Bulk edit price (percentage or flat, with "round to .99"), stock, visibility, tax class, shipping class, categories, tags, on-sale toggle.
- HPOS-safe throughout.

Orders:
- Bulk delete by status, age, email pattern (regex for test/staging emails), zero-value, no-customer.
- Bulk edit order status (with optional order note).
- Abandoned cart cleanup (WC sessions older than X).
- Refund bulk operations (stretch).

WC schedules: nightly test-order cleanup, weekly out-of-stock sweep, etc.

### 6.3 Out of scope (explicit non-goals)

See Section 3.

## 7. Architecture

### 7.1 Tech stack

- PHP 7.4+ minimum, WordPress 6.3+, WooCommerce 8.0+ (when used).
- Action Scheduler for async batching and scheduled ops (bundled, works with or without WC).
- React + `@wordpress/components` + `@wordpress/data`, built with `@wordpress/scripts`.
- PHP namespace `ContentOps\`, PSR-4 autoload via Composer, plugin slug `content-ops`.
- REST API for UI ↔ backend and external access.
- WP-CLI commands as peers to the UI.
- HPOS-declared compatible from day one — no direct `wp_posts` access for WooCommerce orders; always CRUD through WC data stores.

### 7.2 Core abstractions — Target × Operation matrix

Every feature is the intersection of a **Target** (kind of thing) and an **Operation** (what to do). New targets or operations plug in independently without cross-cutting edits.

Targets:
- Post, Page, any CPT (Phase 1)
- Comment, User, Term, Attachment (Phase 3 — alongside Move operations)
- WC Product, WC Order (Phase 4)

Operations:
- Delete, Duplicate, Edit, Move, Find-Replace, Export, Import

`TargetInterface`:
- `get_filters(): FilterDefinition[]`
- `query(QueryArgs $args): int[]`
- `count(QueryArgs $args): int`
- `get_display($id): array` (for preview samples)
- `supports_operation(string $op): bool`

`OperationInterface`:
- `get_params_schema(): array`
- `validate(QueryArgs $args, array $params): ValidationResult`
- `preview(QueryArgs $args, array $params): PreviewResult`
- `execute_batch(int[] $ids, array $params): BatchResult`
- `supports_undo(): bool`
- `undo(Operation $op): UndoResult`

### 7.3 Data model (three custom tables)

**`co_operations`** — one row per operation performed or scheduled.
Columns: `id`, `type`, `target`, `user_id`, `filters_json`, `params_json`, `affected_count`, `affected_ids_json`, `status` (pending/running/completed/failed/undone), `error_message`, `created_at`, `completed_at`. Indexed by `(user_id, created_at)` and `status`.

**`co_snapshots`** — before-state for undo on edits/moves. Row per `(operation_id, object_type, object_id, field, old_value)`. Deletes rely on WP trash instead of snapshots.

**`co_schedules`** — Pro-only in runtime, but the table ships in free (empty). Columns: `id`, `name`, `operation_type`, `target_type`, `filters_json`, `params_json`, `recurrence_json`, `action_scheduler_id`, `enabled`, `last_run_at`, `next_run_at`, timestamps.

No full-post serialization — we store only the minimum needed for undo. Bulk edits on 10k items produce thousands of small snapshot rows, not thousands of post copies.

### 7.4 Async execution

Operations above a configurable threshold (default: 100 items) are queued to Action Scheduler in chunks (default: 50 items). The UI polls REST for progress. This handles PHP timeouts, allows cancellation, and survives page reloads. Scheduled ops use the same pipeline.

### 7.5 AI agent integration

This is a first-class concern, not an afterthought.

1. **Abilities API registration.** Every Target × Operation is registered via `wp_register_ability()` under the `content-ops/*` category. Discoverable through `/wp-json/wp-abilities/v1/*`. When an MCP adapter is installed, every operation becomes an MCP tool automatically.

2. **Preview-token pattern replaces "Are you sure?" dialogs.** `preview` returns `preview_token = hash(filters, params, matched_ids, timestamp)`. `execute` requires the token. If the underlying data changes after preview, token invalidates → caller must re-preview. Same pattern for UI and agents — no divergent flows. Default TTL: 5 minutes, configurable.

3. **WP-CLI parity.** Every operation has a CLI command with `--dry-run`, `--format=json|table|csv|count|ids`, stable exit codes, `--quiet`, and `--log-to=<path>` for structured logs.

4. **Structured errors.** Every error returns `{ code, message, context }` — e.g., `co.filter.invalid_post_type`, `co.preview.stale_token`, `co.hpos.order_not_found`. No free-form strings to regex.

5. **Idempotency keys.** Optional `X-Content-Ops-Idempotency-Key` header or `--idempotency-key` flag. Same key within TTL returns cached result; retries don't double-execute.

6. **Operations History as agent memory.** Agents list, inspect, and undo operations through the same REST endpoints the UI uses — no agent-specific forks.

7. **Capabilities for agents.** `content_ops_delete`, `content_ops_edit`, `content_ops_duplicate`, `content_ops_move`, `content_ops_schedule`. Application Passwords can be scoped via caps, letting the site owner limit an agent's privileges.

8. **`llms.txt`** published at `/wp-content/plugins/content-ops/llms.txt` and on the docs site — plain markdown catalog of operations with examples.

9. **`wp content-ops doctor`** verifies environment (Abilities present? Action Scheduler healthy? HPOS active? cron working?) and outputs JSON. Agents run this before attempting other ops.

10. **Abilities API is a soft dependency.** Content Ops works standalone; agent features light up when Abilities is installed. This is intentional — 99% of installs won't yet run agents, and we must not force an install friction on them.

### 7.6 Extensibility

- `content_ops_register_target` filter for plugins to add new targets.
- `content_ops_register_operation` filter for new operations.
- `content_ops_filter_definitions` per target to modify available filters.
- Standard action hooks fired before/after each operation so WP Activity Log and similar tools can observe.

## 8. UX

### 8.1 Menu

Top-level admin menu: **Content Ops** (custom icon). Submenu: Dashboard, Operations, History, Schedules (Pro), Settings.

Contextual injections:
- Row action on any post/product list: "Duplicate" via Content Ops (stronger than core).
- Native Bulk Actions dropdown entries: Content Ops: Delete / Duplicate / Edit.

### 8.2 Operations Builder (core screen)

Single-page interface (not a wizard). Sections unlock as the user fills them:

1. **Target** — pill selector across all registered targets. Pro targets carry a Pro badge.
2. **Filters** — add-filter-row builder. AND/OR grouping. Live matched-count updates as filters change.
3. **Operation** — buttons for operations compatible with the chosen target. Pro ops are badged.
4. **Preview & Execute** — Dry-run preview button. After preview, shows count, 20-item sample, warnings, and the Execute button (armed with a fresh preview token). Schedule mode replaces Execute with a recurrence picker.

### 8.3 Dashboard

Quick stats (ops this week, items affected, active schedules, next scheduled run); Health panel (live doctor output); Recent Operations (last 5, one-click Undo); Common Cleanups — a curated set of pre-built templates we ship with the plugin (trash old drafts, clean failed WC orders, remove empty posts, etc.) that open the Operations Builder pre-filled. Distinct from user-saved Operation Templates (Pro).

### 8.4 History

Table: Date · Type · Target · Items · Status · User · Actions. Row actions: View details (filters/params JSON + affected IDs), Undo, Re-run. Filters for date, operation type, target, user.

### 8.5 Schedules (Pro)

List of rules with enable/disable toggles, last-run result, next-run time. "+ New rule" opens the Operations Builder in schedule mode. Per-rule run history.

### 8.6 Settings

- **General** — async threshold (default 100), batch size (50), trash vs hard-delete default.
- **History** — retention (30 days free / configurable Pro), auto-cleanup.
- **Capabilities** — role assignment for each `content_ops_*` cap.
- **AI agent access** — Abilities status, Application Password generator, list of connected agents.
- **Notifications (Pro)** — destination for failure alerts.
- **License (Pro)** — activate/deactivate/view entitlement.
- **Danger zone** — reset history, disable all schedules.

### 8.7 Confirmation philosophy

No "Are you sure?" modal. The **dry-run preview is the confirmation.** Execute buttons require a fresh preview token. Humans and agents go through the same gate.

## 9. Testing

- **PHPUnit + wp-phpunit** — Target classes, Operation classes, filter builders, REST controllers. Fixture-based integration tests spinning up real posts, comments, users, terms.
- **WooCommerce integration suite** — runs with HPOS on and off, covers variable-product duplication across several variation configurations.
- **Action Scheduler tests** — verify batching, resumption, cancellation.
- **Undo correctness tests** — every operation claiming `supports_undo()` gets a round-trip test (snapshot → execute → undo → assert original state).
- **Agent surface tests** — Abilities appear in `/wp-json/wp-abilities/v1/`, preview tokens invalidate correctly, idempotency keys deduplicate, structured errors have stable shapes.
- **WP-CLI tests** — one per command (using `wp-cli/wp-cli-tests`), asserting JSON output shape and exit codes.
- **Jest + React Testing Library** — Operations Builder interaction flow, preview rendering, undo actions.
- **Release QA matrix** — WP 6.3 / 6.4 / latest, PHP 7.4 / 8.1 / 8.3, MySQL + MariaDB, HPOS on/off, Abilities API on/off.

## 10. Release phasing

**Phase 0 — Foundation (1–2 sprints)**
Plugin skeleton; Composer + wp-scripts setup; namespace structure; Target/Operation interfaces; Operations History table + undo plumbing; preview-token mechanism; CLI command registrar; Abilities API integration scaffold. No user-facing features; this is the platform.

**Phase 1 — Free MVP (2–3 sprints)**
Targets: Post, Page, any CPT. Operations: Delete, Duplicate, Bulk Edit essentials. Operations Builder UI. Dashboard + History + Settings. CLI parity. Doctor command. Abilities registered for every shipped Target × Operation (others register as the phases below ship). Internal dogfooding on jeangalea.com and theglc.xyz.

**Phase 2 — WP.org launch (1 sprint)**
Polish, accessibility pass, docs site (with `llms.txt`), WP.org listing tuned for SEO keywords, support infrastructure. Soft launch to the WPRA audience for feedback. Public launch with a positioning post ("Content Ops for WordPress in the age of AI agents").

**Phase 3 — Pro v1 (3–4 sprints)**
Scheduling + rules engine + advanced filters + find/replace + CSV + Move operations + extended history retention + team access. EDD Software Licensing integration. Pricing page.

**Phase 4 — WooCommerce Pack (2–3 sprints)**
Product + Order targets with all filters, edits, and ops. HPOS audit. Ships as part of Pro. WC-focused marketing pass.

**Phase 5 — Expansion (demand-driven)**
Customers, Reviews, Coupons; then Subscriptions/Memberships/Bookings; multisite polish; MCP-adapter compatibility testing across clients.

## 11. Risks & mitigations

- **Incumbents have scale (Copy & Delete Posts ~300k+ active installs).** Lead on AI-agent positioning, safety model (undo/preview), and WooCommerce — dimensions they don't compete on. Avoid head-to-head on generic "bulk delete" SEO.
- **HPOS + variable products is hard.** Lean on WC CRUD exclusively, comprehensive fixture test coverage, dogfood on real WooCommerce store data early.
- **Abilities API is young.** Soft dependency; fall back to plain REST for agents that don't consume abilities; track Abilities API evolution.
- **Undo for complex Move ops is tricky.** Aggressive snapshotting for those ops; UI clearly marks them as "Undo may be imperfect — preview carefully."

## 12. Open questions (deferred, not blockers)

1. **Pricing tiers and SKU structure** — defer until Phase 3 is closer.
2. **Brand identity** — logo, palette, landing page. Defer.
3. **Domain** — contentops.wp, contentops.com, getcontentops.com. Defer.
4. **Telemetry** — opt-in (probably via EDD or DIY). Defer.
5. **Rollout channels** — WPRA site banner, email list, blog post. Plan during Phase 2.
