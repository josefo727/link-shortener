# Contracts — 001-postgresql-compatibility

Two internal boundaries are touched by this feature; neither is a new external system, so both
contracts are markdown behavior specs rather than a typed interface or OpenAPI file — there's no
new class being introduced, just new guaranteed behavior on existing config/event boundaries.

- `database-pgsql-connection.md` — the `pgsql` database connection's configuration contract
  (env vars, defaults, TLS behavior).
- `migration-driver-guard.md` — the behavior contract for `AppServiceProvider::allowDisabledPk()`'s
  driver guard (ADR 0001).
