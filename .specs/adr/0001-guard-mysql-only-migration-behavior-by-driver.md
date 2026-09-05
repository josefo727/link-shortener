# ADR 0001 — Guard MySQL-only migration behavior by checking the driver at event time

- **Status:** proposed
- **Date:** 2026-09-05
- **Deciders:** José R. Gutierrez (project owner), drafted by Claude Code during `/sdd-plan`
- **Context links:** `.specs/001-postgresql-compatibility/spec.md` (criterion 3),
  `prompt-db-migration.md` §6 blocker 3

## Context

`AppServiceProvider::allowDisabledPk()` unconditionally registers `MigrationsStarted`/
`MigrationsEnded` listeners that run `DB::statement('SET SESSION sql_require_primary_key=…')` —
valid MySQL syntax only. Today it's gated by `config('database.allow_disabled_pk')`
(`ALLOW_DISABLED_PK` env var), which the migration handoff plans to set to `false` on
PostgreSQL. That's an env-level safeguard, not a code-level one: if that env var were ever left
`true` against a PostgreSQL connection (misconfigured `.env`, a copy-paste mistake during the
cutover), `php artisan migrate` would abort with a PostgreSQL syntax error. The spec (criterion 3)
requires this to fail safe regardless of the env.

## Options considered

### Option A — Check the driver name inside each event listener closure

- Pros: Correct even if a migration ever targets a non-default connection
  (`php artisan migrate --database=some_other_connection`); the check reflects the actual
  connection being migrated at the moment the event fires, not just the app's default connection
  at boot time.
- Cons: A few extra lines per closure.
- Effort / risk: low.

### Option B — Check the driver once, at `register()`/`boot()` time, before deciding whether to call `allowDisabledPk()` at all

- Pros: Simpler, one check.
- Cons: Wrong if a migration run ever targets a different connection than the app's default one —
  the check happens too early to reflect that. Also still lets `allow_disabled_pk` from config
  through as the leading condition, that same class of accidental-env-mismatch bug.
- Effort / risk: low, but weaker correctness guarantee.

## Decision

Option A. Each listener closure checks `DB::connection()->getDriverName() === 'mysql'` (or the
migration's target connection, if a future migration ever specifies one explicitly) before
issuing the MySQL-only statement, and returns early otherwise. This makes the guard correct by
construction rather than by configuration discipline — the exact quality criterion 3 asks for.

## Consequences

- **Positive:** `php artisan migrate` against PostgreSQL never attempts the MySQL-only statement,
  with or without `ALLOW_DISABLED_PK` set correctly. The existing `ALLOW_DISABLED_PK` config gate
  stays as an outer on/off switch; the driver check is now a second, independent safety net.
- **Negative:** None significant — the added check is cheap and runs only during migrations.
- **Follow-ups:** Task-level: a failing test asserting the MySQL statement is NOT issued when the
  active connection's driver is `pgsql` (or `sqlite`, matching the test suite's own connection),
  before the guard is implemented (Red), then the minimal driver check (Green).

## Constitution impact

None — this operationalizes Article X (reversible data migrations must not abort on the wrong
engine) and Article VII (Database boundary), no article text changes.

## References

- Research entries: `.specs/001-postgresql-compatibility/research.md` §laravel/framework
- External sources: `app/Providers/AppServiceProvider.php` (current implementation)
