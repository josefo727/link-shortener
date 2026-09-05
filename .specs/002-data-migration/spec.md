# Spec — 002-data-migration

## Summary

Move `link-shortener`'s production data from the shared, unrelated-schema-reaching MySQL cluster
to the owner's own PostgreSQL cluster: a repeatable copy command, a verification that must pass
before any cutover, and a cutover runbook with a bounded outage and a rollback that never touches
the legacy data. Second slice, after Feature 001 (PostgreSQL compatibility).

## User story

As the project's owner, I want production running on my own PostgreSQL database with every user,
short URL, and API token intact, so that the application stops depending on a shared credential
that also reaches unrelated production schemas on a cluster that was compromised in September
2026.

## Acceptance criteria

1. A single command copies all three business tables (`users`, `short_urls`,
   `personal_access_tokens`) from the legacy MySQL source into the target PostgreSQL database,
   preserving primary keys and every column value exactly, including timestamps at the same
   wall-clock value as the source (not shifted by a timezone conversion).
2. After a copy, creating a new record in any copied table on the target gets an identifier
   greater than every copied identifier — no sequence collision.
3. A verification command compares every row of every copied table field by field between source
   and target, and exits non-zero if any table or any row differs.
4. Running the copy command again against a target that already holds the previously-copied data
   refuses by default (non-zero exit, no rows written) rather than silently duplicating anything;
   an explicit flag makes it replace the target's data cleanly instead, so the same command can
   be rehearsed more than once before the real cutover.
5. The `migrations` table and every transient table (`cache`, `cache_locks`, `sessions`, `jobs`,
   `job_batches`, `failed_jobs`, `password_reset_tokens`) are excluded from the copy.
6. The legacy MySQL source receives no writes at any point before, during, or after the copy —
   the copy command only ever reads from it.
7. A documented cutover runbook exists, covering at minimum: stopping the queue workers, taking
   the application down, running the final copy, running verification as a hard gate (a
   non-zero exit aborts the cutover), switching configuration while keeping the previous values
   as a named rollback, deleting only this application's own cached keys (never a blanket
   cache-clearing command), bringing the application back up, and a defined smoke-test list
   (the landing page, a real short-code redirect with its click count confirmed incremented on
   the target database, the admin panel, and one authenticated API call).
8. A documented rollback exists: restoring the previous configuration values is sufficient to
   return production to the legacy database, which was never written to and remains available
   for a defined retention window.

## Non-goals

- Executing the actual production cutover. This feature produces and rehearses the tooling and
  the runbook; pulling the trigger on live production is a separate, explicitly-confirmed action
  that happens after this feature closes — not automatically as part of `implement` or `verify`.
- Rehearsing against the *real* MySQL source and *real* PostgreSQL target. This feature's
  `implement`/`verify` phases prove the copy/verify commands against realistic local fixtures
  only. A rehearsal against the real databases runs later, exclusively via SSH on the VPS in a
  throwaway container (never from this local session), as its own separate, explicitly-triggered
  step immediately before the real cutover (clarify Q1, Q2).
- Any schema change (columns, types, indexes) — this is a data copy, not a migration of shape.
- Decommissioning the legacy MySQL database, or rotating/revoking its credentials — tracked as a
  dated follow-up after the retention window, not part of this feature.
- Changing `CacheService`'s runtime behavior. The cached-Eloquent-model risk
  (`prompt-db-migration.md` §7) is mitigated by a runbook step (deleting this application's Redis
  keys at cutover time), not by a code change to the caching layer.
- The unrelated loose ends already on the VPS (`prompt-db-migration.md` §11: a stray `.env.bak`
  with plaintext secrets, uncommitted `supervisord.conf` drift, untracked static HTML files,
  `migrate-module.md`, mail configuration naming a nonexistent host) — explicitly flagged there as
  not this migration's decision to make.

## Applicable constitution articles

- Article I — Spec-anchored development: the copy/verify commands trace to this spec.
- Article II — Test-first: both commands are built against a failing test before implementation.
- Article IV — Contract-first integration: the legacy MySQL connection is a new external boundary
  and gets a contract in `contracts/` before consumer code.
- Article VI — ADR for non-local decisions: the copy strategy (a small dedicated command over a
  3-table manifest, not a generic migration tool) and the guard-vs-replace behavior (criterion 4)
  are both ADR-worthy.
- Article VII — Boundaries: this feature adds a named `legacy` database connection as a new
  boundary.
- Article X — Reversible data migrations: this feature's entire purpose. The rollback (criterion
  8) must be real, not theoretical.
- Article XI — Least-privilege, non-shared credentials: the reason this migration exists at all.

## Open questions

None remaining — all four resolved during clarify (see `clarify.md`).

## Glossary additions

- **Legacy database** — the shared managed MySQL database production uses today (`doadmin` on
  `db-mysql-payments-ya-01-…`).
- **Target database** — the owner's own managed PostgreSQL database (`link_shortener`, already
  provisioned and granted per `prompt-db-migration.md` §4).
- **Business tables** — `users`, `short_urls`, `personal_access_tokens` — the only tables with
  data worth copying (37 rows total, per `prompt-db-migration.md` §5).

---

## Closed (filled during verify)

- Date: `<pending>`
- Date: 2026-09-05
- Commit: a3f6b4a
- Notes: All 8 acceptance criteria verified against local fixtures (a second SQLite connection
  standing in for `legacy`, Feature 001's real local PostgreSQL 18.4 for the pgsql-specific
  checks — ADR 0004): 243 tests passing against the real local pgsql target, 94.4% coverage. A
  dedicated security-review pass (this feature moves PII and auth tokens between databases)
  found no issues. **The real MySQL source and real PostgreSQL target have not been touched at
  all** — the rehearsal against them, and the cutover itself, remain separate, explicitly-
  triggered steps documented in `runbook.md` but deliberately not executed here (clarify Q1, Q2).
