# Plan — 001-postgresql-compatibility

## Summary

Every acceptance criterion in `spec.md` maps to a small, independent, already-identified change:
enable the `pdo_pgsql`/`pgsql` PHP extensions in the Docker image (currently disabled), make the
hardcoded `sslmode` connection option env-driven, guard the MySQL-only primary-key-enforcement
migration hook by the active connection's driver (ADR 0001), and add local test tooling —
compose service, PHPUnit config, composer script — so the whole suite can run against PostgreSQL
on demand (ADR 0002). No new architecture, no new domain concepts; this is a brownfield
compatibility fix-up, done test-first per the constitution.

## Stack decision

| Item | Choice | Rationale |
|------|--------|-----------|
| Language | PHP 8.2+ (running 8.4.1) | unchanged — project's existing stack |
| Framework | Laravel 12 | unchanged |
| Test runner | Pest 4 / PHPUnit 12 | unchanged; a second config file (`phpunit.pgsql.xml`) added, not a new runner |
| Linter | Pint | unchanged |
| Static analysis | PHPStan level 9 | unchanged — constitution Article IX |
| Build tool | Docker (`webdevops/php-nginx:8.4-alpine` base) | unchanged base image; only `PHP_DISMOD` and a pcov install step change |
| Runtime target | Same VPS container (`josefo-link-container`) | unchanged; this feature stops at "image builds and tests green locally" per clarify Q4 — no redeploy here |

## Module layout

No new directories or namespaces. Files touched:

```
Dockerfile                                  # remove pdo_pgsql,pgsql from PHP_DISMOD; add pcov
config/database.php                        # pgsql.sslmode -> env('DB_SSLMODE', 'prefer')
app/Providers/AppServiceProvider.php        # allowDisabledPk(): guard listeners by driver (ADR 0001)
compose.yaml                                # add `pgsql` service (ADR 0002)
phpunit.pgsql.xml                           # new — mirrors phpunit.xml, DB_CONNECTION=pgsql
composer.json                               # new `test:pgsql` script
CLAUDE.md                                   # document the new command
```

Entry points: none new — same `php artisan migrate`, same `composer test`/`test:pgsql`.

Public interfaces: none new — `allowDisabledPk()` stays private; its observable contract is
described in `contracts/migration-driver-guard.md`.

## Data model

No entities, no schema change, no migrations authored by this feature. Existing migrations must
merely *run cleanly* against PostgreSQL (criterion 2) — this is validated by test, not by writing
new schema.

## Boundaries

| Boundary | Adapter | Contract |
|----------|---------|----------|
| Database (pgsql) | Laravel's built-in `PostgresConnector`, via `config/database.php` | `contracts/database-pgsql-connection.md` |
| Migration lifecycle | `AppServiceProvider::allowDisabledPk()` event listeners | `contracts/migration-driver-guard.md` |

No other boundary from `constitution.md` Article VII changes.

## Error model

- **`PDOException` on connect** (bad host/port/credentials/sslmode mismatch): surfaces exactly as
  Laravel already surfaces any DB connection failure — unchanged, no new error handling authored.
- **Migration failure due to the MySQL-only statement**: eliminated by ADR 0001's guard — this
  becomes a "does not happen" criterion (3), verified by test, not a new error type to catch.
- **`pcov` missing at test-run time**: `composer test:pgsql --coverage` fails loudly (PHPUnit's
  own "no code coverage driver" error) rather than silently reporting 0% — acceptable, this is a
  local dev-tooling failure mode, not user-facing.

## Observability

Not applicable — no new user-facing surface, no new logs/metrics/traces. The only new
"observability" is criterion 8's coverage percentage, which is a build-time/dev-time report, not
runtime telemetry.

## Security

- **Authn/Authz:** unchanged — this feature touches no auth code.
- **Input validation:** unchanged — no new user input.
- **Secrets handling:** the target PostgreSQL credentials already exist and were provisioned/
  verified in `prompt-db-migration.md` §4; this feature does not introduce, rotate, or touch them
  — it only makes the code capable of using them once Feature 002 runs. `DB_SSLMODE` is
  configuration, not a secret.
- **ADRs touched:** 0001, 0002 (see below).

## Test strategy

- **Unit:** none new — no new isolated domain logic is introduced by this feature.
- **Contract:** both contracts above get a Feature test each:
  - `database-pgsql-connection`: a test asserting `config('database.connections.pgsql.sslmode')`
    resolves from `DB_SSLMODE` (default `prefer`, overridden value when set) — a config-shape
    test, not a live-network test (no real PostgreSQL server needed for this one).
  - `migration-driver-guard`: a Feature test per row of the contract table — asserts
    `DB::statement` is/isn't called with the MySQL-only SQL, faked at the `DB` facade boundary
    (the only boundary-appropriate mock here, per constitution Article III/VII) for each of the
    driver/flag combinations.
- **Integration:** the full existing suite (220 tests today) run against a real PostgreSQL 18.x
  instance via `composer test:pgsql` (ADR 0002) — this is the suite's existing Feature-layer
  hermeticity (real database, no mocked DB), just pointed at a different engine. Hermetic
  environment: the `pgsql` compose service, matching how `mysql`/`redis`/etc. already work in
  `compose.yaml`.
- **E2E:** none — out of this feature's scope (no user-facing surface changes).
- **Spike (not a criterion, a task-0 activity):** confirm the exact mechanism to install `pcov`
  in the `webdevops/php-nginx:8.4-alpine` image (apk package vs. PECL) before writing the
  Dockerfile change as a normal R-G-F task — see `research.md`.

## Rollout

- **Feature flag:** none — this feature ships as a normal merge; production stays on MySQL
  because `.env` on the VPS is untouched (clarify Q4, criterion 7).
- **Migration order:** n/a — no data migration in this feature.
- **Compatibility windows:** the built image must keep working against MySQL exactly as before
  (criterion 7) — validated by running the *existing* full suite against the *existing* `mysql`
  compose service unchanged, as a regression check, not just the new `pgsql` run.
- **Rollback:** trivial — this feature's changes are additive/config-level (new compose service,
  new phpunit config, guarded — not removed — MySQL behavior); reverting the branch fully
  reverts to today's state with no data or deployed state to unwind, since nothing is deployed to
  the VPS as part of this feature (clarify Q4).

## References

- Spec: `./spec.md`
- Clarify log: `./clarify.md`
- Research: `./research.md`
- Contracts: `./contracts/`
- ADRs: `../adr/0001-guard-mysql-only-migration-behavior-by-driver.md`,
  `../adr/0002-postgresql-local-test-tooling.md`
