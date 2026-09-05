# Brownfield Index — link-shortener

## Modules under SDD+TDD

- **PostgreSQL compatibility** (Feature 001, closed 2026-09-05): `Dockerfile` (PHP driver/pcov),
  `config/database.php` (pgsql connection), `AppServiceProvider::allowDisabledPk()` (migration
  driver guard), local test tooling (`compose.yaml`, `phpunit.pgsql.xml`,
  `composer test:pgsql`). See `.specs/001-postgresql-compatibility/`.
- **Data migration tooling** (Feature 002, closed 2026-09-05): `legacy` connection
  (`config/database.php`), `TableManifest`, `db:copy-from-legacy`, `db:verify-copy`. Built and
  verified entirely against local fixtures (ADR 0004) — **the real MySQL source and real
  PostgreSQL target have not been touched**; the rehearsal and the actual cutover remain
  separate, explicitly-triggered steps (`runbook.md`). See `.specs/002-data-migration/`.

## Modules under legacy rules

- Everything else in `app/` (the whole codebase predates this onboarding). No rewrite planned;
  touched only opportunistically or when a feature under SDD+TDD needs to change it.

## Boundaries

See `constitution.md` Article VII for the authoritative, evidenced list. Summary:

- HTTP out: none.
- Database: Eloquent via the default connection (`config/database.php`). Feature 002 added a
  named `legacy` connection (read-only, used only by the one-time migration commands).
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
2. ~~**Feature 002 — Data migration.**~~ **Closed 2026-09-05** — see
   `.specs/002-data-migration/`. Built and verified against local fixtures only; the real
   rehearsal and the actual cutover against production (`prompt-db-migration.md` §9) are
   separate, explicitly-triggered steps that have not run yet — tracked in `runbook.md`, not
   this index.

Update this file's "Modules under SDD+TDD" section as each feature closes, per
`workflows/feature-flow.md`'s close step.
