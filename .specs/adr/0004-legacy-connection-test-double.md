# ADR 0004 — Test the copy against local fixtures, never the real databases, in this feature's own suite

- **Status:** proposed
- **Date:** 2026-09-05
- **Deciders:** José R. Gutierrez (project owner), drafted by Claude Code during `/sdd-plan`
- **Context links:** `.specs/002-data-migration/spec.md` (non-goals),
  `.specs/002-data-migration/clarify.md` (Q1, Q2)

## Context

Clarify Q1/Q2 already decided: the real MySQL source and real PostgreSQL target are touched only
during a later, separate, explicitly-triggered rehearsal via SSH on the VPS — never from this
local session, and never as a precondition for closing this feature. That leaves the question
this ADR answers: what does the `legacy` connection point at *during* `implement`/`verify`, so
`db:copy-from-legacy` and `db:verify-copy` have something real to run against without touching
production-adjacent credentials?

## Options considered

### Option A — A second local SQLite connection as `legacy`, migrated with this app's own schema

- Pros: Works under both `composer test` (SQLite default) and `composer test:pgsql` (PostgreSQL
  target) without any new service. Proves the copy/verify *logic* — ordering, exclusion list,
  guard-vs-`--truncate`, field-by-field comparison, exit codes — completely generically. This
  project's own schema profile (research.md) has zero MySQL-specific coercion traps in these 3
  tables (no booleans, no JSON, no enums), so a same-shape SQLite fixture exercises the same code
  paths a real MySQL source would, for everything except literally MySQL's wire format.
- Cons: Does not exercise MySQL's actual driver behavior (even though research.md found nothing
  that behavior would need to override for these 3 tables specifically).
- Effort / risk: low.

### Option B — Bring up the project's existing local `mysql` and `pgsql` compose services (Feature
001) as `legacy` and target, both real engines, both local and non-production

- Pros: Closer to the real engines involved. Reuses infrastructure Feature 001 already built.
- Cons: Every run of this feature's tests would require two Docker services up simultaneously,
  breaking the "no manual setup beyond the documented command" pattern the default suite relies
  on — this project has no test grouping/tagging convention today, and introducing one for a
  single feature's tests is a bigger structural change than the 37-row problem justifies.
- Effort / risk: medium — mostly in the test-suite plumbing, not the migration logic itself.

### Option C — Skip these tests entirely; only prove the copy works during the VPS rehearsal

- Pros: Least local work.
- Cons: Directly violates Article II (test-first) and Article X (reversible migrations must be
  proven, not assumed) — the first time the copy command's logic would be exercised is against
  production-adjacent data, which is exactly the risk this constitution exists to avoid.
- Effort / risk: not acceptable regardless of effort.

## Decision

Option A for the copy/verify logic itself. The PostgreSQL-specific sequence-reset behavior
(criterion 2) is the one piece that is genuinely meaningless against SQLite — that test is
written to run only under `composer test:pgsql` (Pest's conditional `skip()` when the target
driver isn't `pgsql`), against Feature 001's real local `postgres:18-alpine` service. Nothing in
this feature's automated suite ever opens a connection to the real MySQL source or real
PostgreSQL target named in `prompt-db-migration.md`.

## Consequences

- **Positive:** This feature's `implement`/`verify` phases stay exactly as safe as Feature 001's —
  zero real-credential exposure, zero new required local services beyond what Feature 001 already
  added. Directly implements clarify Q1/Q2.
- **Negative:** MySQL's actual driver behavior for these 3 tables is proven only at the VPS
  rehearsal stage, not by this feature's own suite. Accepted explicitly (clarify Q2) — and
  research.md's schema profile is the evidence that this gap is small (no type-coercion traps
  exist for these specific tables), unlike the blog's case where more was riding on the
  real-database rehearsal.
- **Follow-ups:** The VPS rehearsal (separate, later, explicitly-triggered) is where MySQL's real
  behavior gets proven — tracked in `plan.md` §Rollout, not as a task in this feature's
  `tasks.md`.

## Constitution impact

None.

## References

- Research entries: `.specs/002-data-migration/research.md` §schema profile
- Clarify log: `.specs/002-data-migration/clarify.md` (Q1, Q2)
