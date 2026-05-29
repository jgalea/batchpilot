# Architecture

BatchPilot unifies bulk content operations — delete, duplicate, bulk edit, move, find/replace, CSV round-trip — across any post type, with WooCommerce support in later phases.

## Core abstractions

Every feature is the intersection of a **Target** (kind of thing) and an **Operation** (what to do). Each side plugs in independently.

- `BatchPilot\Contracts\TargetInterface`
- `BatchPilot\Contracts\OperationInterface`

Targets and operations self-register into `TargetRegistry` and `OperationRegistry`.

## Persistence

Three custom tables:

- `{prefix}batchpilot_operations` — one row per operation performed or scheduled.
- `{prefix}batchpilot_snapshots` — before-state for undo.
- `{prefix}batchpilot_schedules` — recurring-rule definitions (Pro).

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
