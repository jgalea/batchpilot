=== BatchPilot ===
Contributors: jeangalea
Tags: bulk delete, bulk edit, duplicate posts, bulk operations, undo
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
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

== Development ==

Source code, including the un-minified JavaScript sources for the admin app, lives at https://github.com/jgalea/batchpilot

The compiled assets in `assets/build/` are generated with [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) from the sources in `assets/src/`. To rebuild:

`npm install && npm run build`

PHP dependencies are managed with Composer (`composer install`).

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
4. Dashboard with quick actions, weekly stats, and environment checks for Action Scheduler, Abilities API, and database tables.

== Changelog ==

= 1.0.0 =

Initial release.

* Three operations on posts, pages, and any registered public post type: Delete (trash or permanent), Duplicate (meta, taxonomies, featured image, optional child posts), Bulk Edit (status, author, dates, taxonomies, password, comment status, menu order).
* Thirteen filters: specific IDs, post type, status, author, modified before/after, published before/after, taxonomy term, has comments, has featured image, post parent, has children.
* Stepper-driven Operations Builder with live preview, smart widgets per param type, and a destructive-action confirmation guard for unfiltered or large deletes.
* Snapshot-based undo for every operation except permanent deletes. Full audit history with one-click re-run.
* Preview tokens (HMAC-signed, 5-minute TTL) prevent stale state from being executed.
* Async execution via Action Scheduler when matched count exceeds the configurable threshold.
* Surfaces: admin UI, WP-CLI (`wp batchpilot`), REST API (`/wp-json/batchpilot/v1/*`), WordPress Abilities API.
* Post-list integration: row action ("Duplicate with BatchPilot") and bulk actions deep-link into the Operations Builder pre-filled with the selected IDs.
* Per-operation capability gates: `batchpilot_delete`, `batchpilot_edit`, `batchpilot_duplicate`, `batchpilot_move`, `batchpilot_schedule`.
* Doctor screen and `wp batchpilot doctor` for environment checks.

== Upgrade Notice ==

= 1.0.0 =

Initial release.
