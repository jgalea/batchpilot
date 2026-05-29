=== BatchPilot ===
Contributors: jeangalea
Tags: bulk delete, bulk edit, duplicate posts, bulk operations, undo
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.0-alpha
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk delete, edit, and duplicate WordPress content with preview, undo, and full audit history.

== Description ==

BatchPilot is a single plugin that replaces the usual stack of bulk-delete, bulk-edit, and duplicate-post plugins. Every action is previewed before it runs, can be undone, and is recorded in a history log you can re-run.

Three operations on posts, pages, and any registered public post type:

* Delete. Trash by default, with a separate permanent option.
* Duplicate. Copies meta, taxonomies, featured image, and optionally child posts.
* Bulk edit. Change status, author, publish dates, taxonomies, password, comment status, or menu order.

Twelve filters narrow the matching set: post type, status, author, date ranges (modified, published), taxonomy terms, has comments, has featured image, parent, children.

Four ways to drive it:

* Admin UI. Stepper-driven Operations Builder with live preview.
* WP-CLI. `wp batchpilot delete`, `duplicate`, `edit`, `history`, `undo`, `doctor`.
* REST API. `/wp-json/batchpilot/v1/*` endpoints with capability-gated permissions.
* WordPress Abilities API. Each Target × Operation pair is exposed as a registered ability so AI agents and other clients can drive operations.

Safety features:

* Every operation is previewed (count plus sample rows) before it runs.
* Preview tokens (HMAC-signed, 5-minute TTL) prevent stale state from being executed.
* Snapshots are written before mutation so Undo restores the previous state.
* Operations over a configurable threshold (default 100 items) run in the background via Action Scheduler.
* Per-operation capabilities: `batchpilot_delete`, `batchpilot_edit`, `batchpilot_duplicate`, `batchpilot_move`, `batchpilot_schedule`. Grant per-role or per-user.

Use cases:

* Trash old drafts, auto-drafts, or revisions on a schedule.
* Re-attribute posts from a departing author.
* Shift publish dates on a backlog.
* Add or remove taxonomy terms across a content set.
* Duplicate templates or landing pages.

== Installation ==

1. Upload BatchPilot to `/wp-content/plugins/batchpilot/` or install via the Plugins screen.
2. Activate the plugin.
3. Open BatchPilot → Operations from the admin sidebar.

== Frequently Asked Questions ==

= Is everything undoable? =

Yes, except a permanent delete (which you have to enable explicitly). Trash, bulk edits, and duplicates all write before-state snapshots and can be reversed from the History screen.

= How long is the history kept? =

Configurable in Settings. Default is 90 days. Snapshots older than the retention window are pruned on a daily cron.

= Will it work on a large site? =

Operations exceeding the async threshold (default 100 items) run in the background via Action Scheduler, batched at 50 items per chunk. The plugin defers to WooCommerce's bundled Action Scheduler when present.

= Can AI agents use it? =

Yes. With the WordPress Abilities API installed, each Target × Operation pair is registered as an ability under the `batchpilot` category. Agents can query the catalog, preview, execute, and undo via the standard abilities surface.

= Does it work with WooCommerce? =

WooCommerce HPOS is detected (see the Doctor screen). Direct Woo product/order operations are on the roadmap; current release covers posts, pages, and any registered public post type.

= How do I uninstall cleanly? =

By default, uninstall leaves your operation history in place. To drop everything on uninstall, enable Settings → Delete data on uninstall before removing the plugin.

== Screenshots ==

1. Operations Builder. Pick a target, add filters, choose an operation, preview, execute.
2. Live preview panel showing matched count and sample rows.
3. History screen with one-click undo and re-run.
4. Doctor screen surfacing schema, Action Scheduler, and capability state.

== Changelog ==

= 0.3.0-alpha =

* Stepper-driven Operations Builder with live preview, smart filter widgets, and a destructive-action confirmation guard for unfiltered or large deletes.
* Catalog vocab block exposes statuses and taxonomies so the UI can render labelled selects and term pickers without round-tripping.
* Operation params schema annotated with widget, label, and description hints (post_status, user picker, taxonomy terms, password).
* Capability self-heal on admin_init re-grants missing capabilities after upgrades that bypass the activation hook.
* React 18 createRoot migration.

= 0.2.0-alpha =

* PostTarget with 12 filters and per-post-type registration.
* Delete, Duplicate, Bulk Edit operations with snapshot-based undo.
* REST surface: catalog, preview, execute, operations, undo.
* WP-CLI commands: delete, duplicate, edit, history, undo, doctor.
* Abilities API soft integration (one ability per Target × Operation).

= 0.1.0-alpha =

* Plugin foundation, custom tables, preview-token machinery, Action Scheduler bridge, capability model.

== Upgrade Notice ==

= 0.3.0-alpha =

Pre-release. UI is stepper-driven, capabilities self-heal on admin_init, REST catalog now includes a vocab block. No breaking changes for clients that used 0.2.0-alpha's REST or CLI surface.
