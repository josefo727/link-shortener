# Brownfield Index — link-shortener

## Modules under SDD+TDD

- **PostgreSQL compatibility** (Feature 001, closed 2026-09-05): `Dockerfile` (PHP driver/pcov),
  `config/database.php` (pgsql connection), `AppServiceProvider::allowDisabledPk()` (migration
  driver guard), local test tooling (`compose.yaml`, `phpunit.pgsql.xml`,
  `composer test:pgsql`). See `.specs/001-postgresql-compatibility/`.

## Modules under legacy rules

- Everything else in `app/` (the whole codebase predates this onboarding). No rewrite planned;
  touched only opportunistically or when a feature under SDD+TDD needs to change it.

## Boundaries

See `constitution.md` Article VII for the authoritative, evidenced list. Summary:

- HTTP out: none.
- Database: Eloquent via the default connection (`config/database.php`). Feature 002 adds a named
  `legacy` connection.
- Queue: `redis`, configured but unused (no Job classes).
- Cache: `App\Contracts\CacheServiceInterface` / `App\Services\CacheService` (Redis, 1-week TTL,
  serialized `ShortUrl` models) — the delicate one, see `prompt-db-migration.md` §7.
- Clock: no injected abstraction; one direct `now()` in `ShortUrl::scopeAccessible()`.
- Randomness: `App\Services\CodeGeneratorService`.
- Filesystem: local disk.
- Third parties: none.

## Planned features (this onboarding's reason for existing)

1. ~~**Feature 001 — PostgreSQL compatibility.**~~ **Closed 2026-09-05** — see
   `.specs/001-postgresql-compatibility/`. Production still runs on MySQL; this feature only
   proved compatibility (`prompt-db-migration.md` §6, blockers 1, 2, 3, 5).
2. **Feature 002 — Data migration.** `legacy` connection + `LEGACY_DB_*`, a
   `db:copy-from-legacy` command over the 3-table manifest (`users`, `short_urls`,
   `personal_access_tokens`), sequence reset, and a row-by-row verification gate. Cutover follows
   the order in `prompt-db-migration.md` §9. Not started until Feature 001 is closed and deployed.

Update this file's "Modules under SDD+TDD" section as each feature closes, per
`workflows/feature-flow.md`'s close step.
