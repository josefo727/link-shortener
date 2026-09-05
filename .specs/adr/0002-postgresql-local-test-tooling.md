# ADR 0002 — Run the full suite against PostgreSQL via a dedicated compose service and test config

- **Status:** proposed
- **Date:** 2026-09-05
- **Deciders:** José R. Gutierrez (project owner), drafted by Claude Code during `/sdd-plan`
- **Context links:** `.specs/001-postgresql-compatibility/spec.md` (criteria 4, 5),
  `prompt-db-migration.md` §6 blocker 5, `.specs/index.md` (ranked test-debt)

## Context

The suite currently only runs against real in-memory SQLite (`phpunit.xml`), and `compose.yaml`
has no `pgsql` service. Criterion 4 requires the full suite to pass against PostgreSQL; criterion
5 requires this to need no manual setup beyond the project's existing documented test command.
Clarify Q2 already ruled CI out of scope — this only needs to work locally, on demand.

## Options considered

### Option A — Add a `pgsql` service to `compose.yaml`, a second PHPUnit config
(`phpunit.pgsql.xml`) pointing at it, and a `composer test:pgsql` script

- Pros: One documented command (`composer test:pgsql`) runs the whole suite against PostgreSQL
  with the service up; SQLite stays the fast default for `composer test`, so day-to-day iteration
  speed is unaffected. Mirrors the existing `phpunit.xml` structure closely — low novelty.
- Cons: Two PHPUnit config files to keep in sync if global suite settings change.
- Effort / risk: low.

### Option B — Replace SQLite with PostgreSQL as the suite's only configured database

- Pros: Zero drift between "the tests" and "the tests against PostgreSQL" — there's only one
  suite.
- Cons: Makes every local test run depend on the `pgsql` service being up, slows down the
  fast local loop TDD depends on constantly, and is a bigger, riskier change than this feature's
  scope (criteria only ask for PostgreSQL compatibility to be provable, not for SQLite to be
  removed). Also a bigger blast radius change to revert if something's wrong.
- Effort / risk: medium; unnecessarily couples an unrelated concern (test speed) to this feature.

### Option C — No compose service; developers export `DB_CONNECTION=pgsql` env vars by hand
against an already-running PostgreSQL instance

- Pros: No new service to maintain.
- Cons: Fails criterion 5 directly — "no manual setup beyond the existing documented test
  command." Every run requires remembering/typing several env vars correctly.
- Effort / risk: low effort, but does not satisfy the spec.

## Decision

Option A. `postgres:18-alpine` (pinned to major version `18`, per clarify Q3 — not `18.6`
exactly) is added to `compose.yaml` as a new service; `phpunit.pgsql.xml` mirrors `phpunit.xml`
with `DB_CONNECTION=pgsql` and matching host/port/credentials; `composer.json` gets a
`test:pgsql` script analogous to the existing `test` script. `CLAUDE.md` gets a one-line addition
documenting the new command, consistent with how `composer test`/`--filter` are already
documented there.

## Consequences

- **Positive:** Criterion 4 and 5 both satisfied without touching the fast SQLite-based default
  loop. The new service is reusable by whichever CI setup gets built later (explicitly a
  follow-up, not this feature).
- **Negative:** A second phpunit config file exists; must be kept in sync manually if e.g. a new
  global env var is added to `phpunit.xml` (a review-gate concern, not automated).
- **Follow-ups:** A future CI feature wires `composer test:pgsql` into a pipeline (tracked in
  `.specs/index.md` and `test-inventory.md`, explicitly out of scope here).

## Constitution impact

None.

## References

- Research entries: n/a (no external library involved — internal tooling decision)
- External sources: none
