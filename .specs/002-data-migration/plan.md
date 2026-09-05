# Plan — 002-data-migration

## Summary

The target PostgreSQL database is already provisioned and empty (`prompt-db-migration.md` §4), so
this feature only moves rows — no schema work. Two artisan commands do it:
`db:copy-from-legacy` reads the 3 business tables from a new `legacy` connection via the query
builder (never Eloquent) and writes them into the target with the same builder, then resets
PostgreSQL's identity sequences; `db:verify-copy` compares every row of every table field by
field and exits non-zero on any difference. Both are built and proven entirely against local
fixtures (ADR 0004) — this feature's own suite never touches the real MySQL source or real
PostgreSQL target. The real rehearsal, and the cutover itself, are separate, later,
explicitly-triggered steps via SSH on the VPS (clarify Q1, Q2), documented in a `runbook.md`
this feature produces but does not execute.

## Stack decision

| Item | Choice | Rationale |
|------|--------|-----------|
| Language / framework | PHP 8.2+, Laravel 12 | unchanged |
| Copy mechanism | Artisan commands over the query builder | ADR 0003 |
| Legacy access | New `legacy` connection, driven by `LEGACY_DB_*` env keys | mirrors the blog's equivalent connection; keeps `legacy` a named secondary, never the default |
| Test double for `legacy` | A second local SQLite connection | ADR 0004 |
| PostgreSQL-specific tests | Run only under `composer test:pgsql` (Feature 001's real local service), conditionally skipped otherwise | ADR 0004; `pg_get_serial_sequence` is meaningless against SQLite |
| Real-database rehearsal | Exclusively via SSH on the VPS, in a throwaway container, as a separate step after this feature closes | clarify Q1, Q2 |

## Module layout

```
app/Console/Commands/CopyFromLegacyCommand.php     # db:copy-from-legacy [--truncate]
app/Console/Commands/VerifyLegacyCopyCommand.php   # db:verify-copy
app/Database/LegacyMigration/TableManifest.php     # ordered table list + exclusions
config/database.php                                # + 'legacy' connection
tests/Feature/Database/CopyFromLegacyCommandTest.php
tests/Feature/Database/VerifyLegacyCopyCommandTest.php
tests/Unit/Database/TableManifestTest.php
.specs/002-data-migration/runbook.md                # cutover + rollback, written as a task below
```

Public interfaces:

- `db:copy-from-legacy [--truncate]` — copies the manifest's tables in order, then resets
  PostgreSQL's identity sequences for each. Refuses to run (non-zero exit, no rows written) when
  the target already holds rows in any manifest table, unless `--truncate` is given (clarify Q3).
- `db:verify-copy` — prints one line per table (row counts, field-by-field match) and returns
  exit code 1 if any table differs, per `contracts/verification-report.md`.
- `TableManifest::tables(): array` — `['users', 'short_urls', 'personal_access_tokens']`, in
  dependency order. `TableManifest::excluded(): array` — `migrations` plus every transient table
  from spec criterion 5.

## Data model

No schema change — the target already has this application's full schema via its own migrations
(Feature 001 proved `php artisan migrate` runs clean on PostgreSQL). The manifest fixes copy
order:

```
users, short_urls, personal_access_tokens
```

`users` first (semantic parent of `personal_access_tokens` via `tokenable_id`, though not an
FK-enforced relationship); `short_urls` has no dependency either direction. All three have a
plain auto-increment `id` primary key (research.md) — `chunkById` applies uniformly, no
keyless-table special-casing needed (unlike the blog's `post_tag`).

Excluded (criterion 5): `migrations` (written by `php artisan migrate` itself), `cache`,
`cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`.

Sequence reset: for each copied table, `SELECT setval(pg_get_serial_sequence(table, 'id'),
(SELECT MAX(id) FROM table))` — skipped for a table copied with zero rows (research.md gotcha),
though none of the three are expected to be empty in production.

## Boundaries

| Boundary | Adapter | Contract |
|----------|---------|----------|
| Legacy MySQL (read-only) | `legacy` connection, query builder | `contracts/legacy-database.md` |
| Target PostgreSQL | default connection, query builder | `contracts/legacy-database.md` |
| Verification output | `db:verify-copy` report format and exit status | `contracts/verification-report.md` |
| Cache | Redis, untouched by the copy itself; cleared as a runbook step at cutover (`prompt-db-migration.md` §7), not a code change | declared in `constitution.md` Article VII |

No other boundary from `constitution.md` Article VII changes.

## Error model

- **Eloquent is never used.** `ShortUrlObserver` writes to the Redis cache on every
  create/update; a copy through the model would populate cache entries as a side effect of a data
  copy. Enforced by the commands importing no model class, and by a test asserting the target's
  short-URL cache stays empty immediately after a copy (no cache writes happened).
- A copy attempt against a non-empty target without `--truncate` fails with a message naming the
  populated tables; nothing is written.
- The target-side writes for one table run inside a transaction on the target connection; a
  failure partway through a table's insert rolls that table back rather than leaving it
  half-populated. The legacy connection is only ever read, so there is nothing to roll back there.
- `db:verify-copy` exits 1 on any mismatch and names every offending table, per
  `contracts/verification-report.md`. The (future, separate) cutover runbook treats a non-zero
  exit as a hard stop.

## Observability

Both commands print a table-by-table progress line and a summary (rows copied/verified). No new
logging channels — failures surface as command output and a non-zero exit status, consistent
with Feature 001's approach.

## Security

- This feature's own `implement`/`verify` phases use no real credentials at all (ADR 0004) — the
  `legacy` connection under test points at a local SQLite double, never a network connection.
- The real legacy credentials (`prompt-db-migration.md` §3) are the shared cluster's already
  treated-as-compromised `doadmin` account; this feature's commands never write with them at any
  stage (copy is read-only against `legacy` by construction, not just by discipline).
- The real target credentials (§4) are already least-privilege, already verified. Nothing here
  introduces, rotates, or touches any credential — that only happens at the real rehearsal/cutover,
  outside this feature (clarify Q1).

## Test strategy

- **Unit** — `TableManifestTest`: the three tables are listed in the stated order; the exclusion
  list matches spec criterion 5 exactly (no table is both listed and excluded, none is silently
  missing from either list).
- **Feature, runs under both `composer test` and `composer test:pgsql`** —
  `CopyFromLegacyCommandTest` and `VerifyLegacyCopyCommandTest`, against a `legacy` SQLite
  double (migrated with this app's own schema, seeded with a handful of fixture rows) and
  whichever connection is the active suite's target: full copy of a seeded fixture, refusal on a
  non-empty target, `--truncate` replaces cleanly, repeat-run after `--truncate` stays clean, no
  Eloquent/observer side effects fire during a copy, verification passes on a clean copy and
  fails (naming the table) on a deliberately corrupted target.
- **Feature, PostgreSQL-only (skipped otherwise)** — sequence-reset assertion: after a copy
  against Feature 001's real local `pgsql` service, inserting a new row on the target gets an id
  greater than every copied id. Conditionally skipped when the target driver isn't `pgsql`
  (ADR 0004) — meaningless against SQLite, and this is the one place this feature's suite talks to
  a real (local, non-production) PostgreSQL server rather than a double.
- **Known gap, stated rather than hidden** (mirrors the blog's own §Test strategy note): this
  feature's suite proves the copy/verify *logic* against same-shape fixtures; it does not prove
  MySQL's actual driver behavior. Research.md's schema profile is why that gap is small here —
  no boolean/JSON/enum coercion risk exists in these three tables — but the real proof is the
  VPS rehearsal, tracked in §Rollout below, not a task in this feature.

## Rollout

1. Merge this feature; no production behavior changes — the commands are additive, and the
   `legacy` connection is inert until its env keys exist anywhere.
2. **Separate, later, explicitly-triggered step (not part of this feature):** rehearsal via SSH
   on the VPS, in a throwaway container, against the real MySQL source and real PostgreSQL
   target — `migrate`, `db:copy-from-legacy`, `db:verify-copy`, repeated until clean, following
   `runbook.md`'s rehearsal section (produced by this feature, run afterward).
3. **Separate, later, explicitly-triggered step:** the actual cutover, per `runbook.md` — stop
   queue workers, `artisan down`, final `db:copy-from-legacy --truncate`, `db:verify-copy` as a
   hard gate, switch `.env` (keeping the previous values as `LEGACY_DB_*`, adding
   `DB_SSLMODE=require` and `ALLOW_DISABLED_PK=false`), delete this application's own Redis keys
   (never a blanket cache-clear — `prompt-db-migration.md` §7), restart workers, `artisan up`,
   smoke-check.
4. **Rollback**, if any step above fails: restore the previous `.env` values, restart. The legacy
   database was never written to, so this is a configuration change only. Retained 30 days
   (`prompt-db-migration.md` §9).

## References

- Spec: `./spec.md` · Clarify: `./clarify.md` · Research: `./research.md` · Contracts: `./contracts/`
- ADRs: `../adr/0003-legacy-copy-strategy-application-level.md`,
  `../adr/0004-legacy-connection-test-double.md`
