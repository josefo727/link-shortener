# Tasks — 002-data-migration

## Legend

- `T{NNN}` — task id, unique within feature, zero-padded.
- `[P]` — safe to execute in parallel with other `[P]` tasks (disjoint files, no shared mutable state).
- `R` — Red beat description.
- `G` — Green beat description.
- `F` — Refactor beat description.
- `status` — `open | in_progress | closed | skipped`.

Reminder carried from ADR 0004: every task's tests run against local fixtures (a second SQLite
connection as `legacy`, Feature 001's real local `pgsql` service as the target for
PostgreSQL-specific checks). **Nothing in this feature's implement/verify phases opens a
connection to the real MySQL source or real PostgreSQL target named in `prompt-db-migration.md`.**
That only happens later, via SSH on the VPS, as a separate step this feature's tasks do not
include (see `plan.md` §Rollout).

---

## T001 [P] `Add the legacy database connection`

```
spec-ref:        Applicable constitution articles — Article VII (boundaries); prerequisite for
                  criteria 1, 6
contract-ref:    contracts/legacy-database.md
constitution-ref:Article VII
DoD:
  - config/database.php has a 'legacy' connection entry driven by LEGACY_DB_* env vars, mirroring
    the shape of the existing DB_* keys
  - .env.example documents the new LEGACY_DB_* keys (commented, matching how DB_* is documented)
  - 'legacy' is never referenced by DB_CONNECTION's own value — it only exists as a named
    secondary connection
R: n/a — scaffolding, no behavior to fail yet (mirrors how Feature 001's T001 handled a
   preparatory step). The DoD above is the check.
G: add the 'legacy' array to config/database.php and the commented keys to .env.example.
F: skipped — config-only task.
files:
  - config/database.php
  - .env.example
status: closed
commits:
  red: n/a — scaffolding
  green: 82a8f5f
  refactor: skipped — config-only task
notes:
```

---

## T002 [P] `TableManifest — ordered table list and exclusions`

```
spec-ref:        Acceptance criterion 5
contract-ref:    n/a
constitution-ref:Article I, Article II
DoD:
  - TableManifest::tables() returns ['users', 'short_urls', 'personal_access_tokens'], in that
    order
  - TableManifest::excluded() returns 'migrations' plus every transient table from criterion 5
    (cache, cache_locks, sessions, jobs, job_batches, failed_jobs, password_reset_tokens)
  - no table appears in both lists; every table this app's migrations create is accounted for in
    exactly one list
R: a new Unit test asserts both lists' exact contents — fails today (class doesn't exist).
G: implement TableManifest as a small final class with two static methods returning the two
   arrays above.
F: skipped — no smell detected, it's two constant arrays.
files:
  - app/Database/LegacyMigration/TableManifest.php
  - tests/Unit/Database/TableManifestTest.php
status: closed
commits:
  red: 0a9878a
  green: 3104f11
  refactor: skipped — no smell detected, Pint/PHPStan clean
notes:
```

---

## T003 `db:copy-from-legacy — happy path`

```
spec-ref:        Acceptance criterion 1
contract-ref:    contracts/legacy-database.md
constitution-ref:Article II, Article III (no Eloquent — the copy stays at the query-builder
                  boundary, per ADR 0003)
DoD:
  - seeding a few rows per manifest table on the `legacy` SQLite double and running
    `db:copy-from-legacy` against an empty target copies every row, every column value exactly,
    including timestamps at the same wall-clock value (no Carbon reparsing)
  - only TableManifest::tables() are touched on the target — nothing else gets written
  - the copy uses DB::table() only; no Eloquent model class is imported, and the target's cache
    (App\Services\CacheService) has no entries immediately after the copy (no observer fired)
R: a new Feature test seeds `legacy`, runs the (not-yet-existing) `db:copy-from-legacy` against
   an empty target, and asserts the above — fails today (command doesn't exist).
G: implement CopyFromLegacyCommand: for each TableManifest::tables() entry, read from
   DB::connection('legacy')->table($table)->orderBy('id')->chunkById(...), write via
   DB::table($table)->insert(...) on the target, inside a transaction per table. No sequence
   reset yet (T005) and no non-empty-target guard yet (T004) — this task is the copy path alone.
F: extract the per-table chunked-copy step into a small private method if the loop over
   TableManifest::tables() reads repetitively once green.
files:
  - app/Console/Commands/CopyFromLegacyCommand.php
  - tests/Feature/Database/CopyFromLegacyCommandTest.php
status: open
commits:
  red:
  green:
  refactor:
notes:
```

---

## T004 `db:copy-from-legacy — guard on a non-empty target, --truncate, repeat-run`

```
spec-ref:        Acceptance criterion 4
contract-ref:    n/a
constitution-ref:Article X (reversible migrations — a re-run must never silently corrupt state)
DoD:
  - running the copy against a target that already holds rows in any manifest table, without
    --truncate, exits non-zero, writes nothing, and names every populated table in its message
  - the same run with --truncate deletes the manifest tables' existing target rows first, then
    copies cleanly
  - running --truncate twice in a row produces the same clean result both times (rehearsal-safe)
R: a new test runs the copy against a target pre-seeded with a manifest table's rows, without
   --truncate — asserts non-zero exit and zero additional rows written; fails today (no guard
   exists, T003's implementation writes unconditionally).
G: add a pre-flight check (count each manifest table on the target) before any write; abort with
   a descriptive error naming populated tables unless --truncate is given; when given, delete
   existing manifest-table rows on the target first.
F: extract the pre-flight check into its own method if CopyFromLegacyCommand::handle() grows
   past a clear read.
files:
  - app/Console/Commands/CopyFromLegacyCommand.php
  - tests/Feature/Database/CopyFromLegacyCommandTest.php
status: open
commits:
  red:
  green:
  refactor:
notes:
```

---

## T005 `db:copy-from-legacy — reset PostgreSQL identity sequences`

```
spec-ref:        Acceptance criterion 2
contract-ref:    n/a
constitution-ref:Article II
DoD:
  - after a copy against a real PostgreSQL target (Feature 001's local `pgsql` service),
    inserting a new row in any manifest table gets an id greater than every id just copied
  - the reset is skipped (no-op, no error) for a table copied with zero rows, and skipped
    entirely when the target driver isn't pgsql (e.g. SQLite — nothing to reset)
  - this specific test is conditionally skipped unless the suite is running against pgsql
    (ADR 0004) — it does not run under the default `composer test`
R: a Postgres-only test (`composer test:pgsql`) copies fixture data, inserts a new row, and
   asserts its id exceeds every copied id — fails today (no sequence-reset step exists yet, so a
   fresh insert would collide with or trail behind the copied ids).
G: after each table's copy, when DB::connection()->getDriverName() === 'pgsql', run
   `SELECT setval(pg_get_serial_sequence('<table>', 'id'), (SELECT MAX(id) FROM <table>))`,
   skipped when that table's copied row count was zero.
F: skipped — no smell detected.
files:
  - app/Console/Commands/CopyFromLegacyCommand.php
  - tests/Feature/Database/CopyFromLegacyCommandTest.php
status: open
commits:
  red:
  green:
  refactor:
notes: This is the one task in this feature whose test opens a real (local, non-production)
  PostgreSQL connection — Feature 001's `pgsql` compose service. Still never the real production
  target (ADR 0004).
```

---

## T006 `db:verify-copy — matching case`

```
spec-ref:        Acceptance criterion 3
contract-ref:    contracts/verification-report.md
constitution-ref:Article II
DoD:
  - identical data on `legacy` and target: the command exits 0
  - output has one line per TableManifest::tables() entry, each showing a match
  - the command never writes to either connection (read-only both sides)
R: a new Feature test seeds identical data on both connections, runs the (not-yet-existing)
   `db:verify-copy`, and asserts exit 0 plus the expected per-table lines — fails today (command
   doesn't exist).
G: implement VerifyLegacyCopyCommand: for each manifest table, compare row counts and every
   row's every column between `legacy` and the target; print a line per table; exit 0 if all
   match.
F: skipped — no smell detected yet, revisit after T007.
files:
  - app/Console/Commands/VerifyLegacyCopyCommand.php
  - tests/Feature/Database/VerifyLegacyCopyCommandTest.php
status: open
commits:
  red:
  green:
  refactor:
notes:
```

---

## T007 `db:verify-copy — mismatch cases`

```
spec-ref:        Acceptance criterion 3
contract-ref:    contracts/verification-report.md
constitution-ref:Article II
DoD:
  - a row-count difference in one table: exit 1, that table named in the output
  - a single differing field value in an otherwise-matching table: exit 1, that table named
  - mismatches in two different tables at once: BOTH are named, not just the first found
    (collect-all, not short-circuit)
R: three new test cases (row-count diff, field diff, two-table diff) — the third fails today even
   once the first two pass, if the command short-circuits on the first mismatch found; written to
   fail for that specific reason.
G: adjust VerifyLegacyCopyCommand to check every manifest table before deciding the exit code,
   collecting every mismatching table's name rather than returning on the first one.
F: extract the per-table comparison into a small value object/array if the collect-all logic
   makes handle() harder to read.
files:
  - app/Console/Commands/VerifyLegacyCopyCommand.php
  - tests/Feature/Database/VerifyLegacyCopyCommandTest.php
status: open
commits:
  red:
  green:
  refactor:
notes:
```

---

## T008 `Legacy connection is read-only in practice`

```
spec-ref:        Acceptance criterion 6
contract-ref:    contracts/legacy-database.md
constitution-ref:Article VII
DoD:
  - running db:copy-from-legacy (all paths: fresh copy, guard rejection, --truncate) and
    db:verify-copy against a `legacy` connection opened in SQLite read-only mode
    (`?mode=ro` / `PRAGMA query_only`) completes without any "attempt to write a readonly
    database" error
  - this is a stronger, always-on regression guarantee for behavior T003-T007 already implement,
    not new behavior of its own
R: n/a — this task adds a regression guarantee for already-implemented behavior (T003-T007); if
   it ever fails, that failure IS the signal that a write was attempted against `legacy`.
G: add the read-only-connection test; no production code change expected. If it fails, that
   reveals a real defect to fix test-first (its own R-G-F, tracked as an amendment below rather
   than invented here).
F: skipped — test-only task.
files:
  - tests/Feature/Database/CopyFromLegacyCommandTest.php
  - tests/Feature/Database/VerifyLegacyCopyCommandTest.php
status: open
commits:
  red:
  green:
  refactor:
notes:
```

---

## T009 `Write the cutover and rollback runbook`

```
spec-ref:        Acceptance criteria 7, 8
contract-ref:    n/a
constitution-ref:Article X
DoD:
  - runbook.md documents, at minimum: stopping queue workers (the supervisor GROUP
    'queue-worker:*', not a single process — prompt-db-migration.md §9), artisan down, the final
    db:copy-from-legacy --truncate, db:verify-copy as a hard gate (non-zero exit stops the
    cutover), switching .env (keeping old values as LEGACY_DB_*, adding DB_SSLMODE=require and
    ALLOW_DISABLED_PK=false), deleting only this application's Redis keys
    (link-shortener-database-*, ACL user josefo-link, scoped SCAN+DEL, never cache:clear) per
    prompt-db-migration.md §7, restarting workers, artisan up, and the smoke-test list (landing
    page, a real redirect with its click count confirmed incremented on the target, the admin
    panel, one authenticated API call)
  - a rollback section: restoring the previous .env values is sufficient, the legacy database was
    never written to, retained per the dated reminder in prompt-db-migration.md §9
  - a rehearsal section left for the owner to fill in with real evidence once the VPS rehearsal
    (a separate, later, explicitly-triggered step) actually runs — not fabricated here
R: n/a — documentation task, no failing test; the DoD is the check.
G: write .specs/002-data-migration/runbook.md, adapting the shape of the blog's equivalent
   runbook (~/Projects/my-blog/jose-gutierrez/.specs/002-.../runbook.md) to this project's real
   facts (table manifest, Redis ACL, supervisor group name, VPS deployment details).
F: skipped — doc-only.
files:
  - .specs/002-data-migration/runbook.md
status: open
commits:
  red:
  green:
  refactor:
notes: The rehearsal-evidence and "what actually happened" sections stay explicitly blank/marked
  pending until the real, separate, VPS-only steps actually run — never fabricated.
```

---

## Amendments

| Date | Change | Reason |
|------|--------|--------|
|      |        |        |
