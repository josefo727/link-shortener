# ADR 0003 — Copy the legacy data with an application-level command, not Eloquent or a generic tool

- **Status:** proposed
- **Date:** 2026-09-05
- **Deciders:** José R. Gutierrez (project owner), drafted by Claude Code during `/sdd-plan`
- **Context links:** `.specs/002-data-migration/spec.md` (criteria 1–6),
  `prompt-db-migration.md` §8

## Context

37 rows across 3 tables need to move from MySQL to PostgreSQL, preserving values exactly and
resetting PostgreSQL's identity sequences afterward. The blog's equivalent feature (23 tables,
~1,800 rows, real portability traps) chose a hand-written artisan command over the query builder,
documented in its own ADR 0004. `prompt-db-migration.md` §8 explicitly says this project's scale
doesn't justify that project's eleven-task copy engine, but does justify "a tested, repeatable,
idempotent command."

## Options considered

### Option A — A pair of artisan commands (`db:copy-from-legacy`, `db:verify-copy`) over the query builder

- Pros: No new dependency. `DB::table()` bypasses Eloquent entirely, so no model event
  (`ShortUrlObserver`'s Redis writes) fires during a copy. Full control over per-table ordering,
  the guard-vs-`--truncate` behavior, and the exact verification semantics the spec requires
  (field-by-field, not just checksums). Matches the constitution's boundary-only-mocks stance —
  the Database boundary is exercised directly and honestly.
- Cons: Someone has to write and test it — at 3 tables/37 rows, this is small.
- Effort / risk: low.

### Option B — A generic migration tool (e.g., `pgloader`)

- Pros: Battle-tested for large migrations; not this project's code to maintain.
- Cons: A new external dependency and runtime (pgloader itself, plus wherever it needs to run)
  for 37 rows. Its own type-coercion rules are one more thing to verify rather than trust, and it
  doesn't naturally produce this spec's exact verification contract (field-by-field comparison
  with a defined exit code). Overkill relative to what `prompt-db-migration.md` §8 asks for.
- Effort / risk: low effort to run once, but adds a dependency and an opaque behavior surface for
  a problem this small.

### Option C — Eloquent models (`ShortUrl::create()`, `User::create()`, etc.)

- Pros: Reuses existing casts/validation.
- Cons: `ShortUrlObserver` writes to the Redis cache (1-week TTL) on every create — a rehearsal
  run would populate production-shaped cache entries as a side effect of a *data copy*, which is
  not what a copy should do, and makes the copy slower and its behavior harder to reason about
  under test. Violates the spirit of Article III (boundary-only mocks; Eloquent's side effects
  are not the boundary being exercised here).
- Effort / risk: low effort, but the side-effect risk is exactly the kind of thing this project's
  constitution exists to catch.

## Decision

Option A. Two artisan commands, `db:copy-from-legacy` and `db:verify-copy` (clarify Q4), built on
`DB::connection('legacy')->table(...)` reads and `DB::table(...)->insert(...)` writes, driven by a
small `TableManifest` class listing the 3 tables in dependency order and the excluded transient
tables. No Eloquent, no external migration tool.

## Consequences

- **Positive:** No observer side effects during a copy or rehearsal. The verification command's
  exact contract (field-by-field, non-zero exit on any difference) is fully under this project's
  control, matching spec criterion 3 precisely rather than approximating it with a tool's own
  reporting format.
- **Negative:** None significant at this scale.
- **Follow-ups:** None — this is the whole approach, not a stepping stone to something else.

## Constitution impact

None.

## References

- Research entries: `.specs/002-data-migration/research.md` §schema profile,
  §laravel/framework query builder
- External sources: the blog's own ADR 0004
  (`~/Projects/my-blog/jose-gutierrez/.specs/adr/0004-copy-strategy-application-level.md`), same
  reasoning at a different scale
