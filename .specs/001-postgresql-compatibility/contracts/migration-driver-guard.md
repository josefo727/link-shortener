# contract: migration-driver-guard
# version: 1.0.0
# captured: 2026-09-05
# source: ADR 0001, app/Providers/AppServiceProvider.php (existing allowDisabledPk())

## Boundary

The `MigrationsStarted`/`MigrationsEnded` event listeners registered by
`AppServiceProvider::allowDisabledPk()`.

## Contract

| Given | When | Then |
|---|---|---|
| `ALLOW_DISABLED_PK=true`, active connection driver is `mysql` | `php artisan migrate` runs | `SET SESSION sql_require_primary_key=0` runs before migrations, `=1` runs after — unchanged from today |
| `ALLOW_DISABLED_PK=true`, active connection driver is `pgsql` | `php artisan migrate` runs | neither `SET SESSION` statement is issued; no error is raised |
| `ALLOW_DISABLED_PK=true`, active connection driver is `sqlite` (the test suite's connection) | `php artisan migrate` runs (as `RefreshDatabase` does per test) | neither `SET SESSION` statement is issued; no error is raised |
| `ALLOW_DISABLED_PK=false`, any driver | `php artisan migrate` runs | neither listener is registered at all — unchanged from today |

The guard is evaluated per-event, against the actual connection the migration run is targeting —
not cached from the app's boot-time default connection — so it stays correct even if a migration
run ever targets a non-default connection explicitly.

## Out of scope for this contract

- Whether `sql_require_primary_key` enforcement itself is a good idea (pre-existing, unrelated to
  this feature).
