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
wp-env run cli --env-cwd=wp-content/plugins/batchpilot wp batchpilot doctor --format=json
```

Should return a JSON report with environment health.

## Layout

See `docs/architecture.md` for the architectural overview. Specs and plans live under `docs/superpowers/`.
