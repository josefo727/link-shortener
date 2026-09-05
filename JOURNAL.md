# Journal

## 2026-09-05 09:00 — session 1 (brownfield onboarding)

### Context

Onboarding `link-shortener` onto `spec-tdd-kit` (quick-path, brownfield — repo was previously
unmanaged by the kit). Driven by the pending MySQL→PostgreSQL migration described in
`prompt-db-migration.md`.

### Done this session

- Ran discovery (`Explore` subagent): confirmed no hardcoded DB connection names anywhere in
  code, mapped boundaries, entry points, test suite shape.
- Wrote `.specs/onboarding.md` (intake + discovery), `.specs/constitution.md` (12 unified
  articles), `.specs/test-inventory.md` (220 tests / 462 assertions / 5.15s, no coverage driver
  installed, no slow or flaky tests), `.specs/index.md` (boundaries + the two planned features).
- User approved the constitution as drafted.
- Added `CONTRIBUTING.md` pointing new feature work at
  `@~/Projects/spec-tdd-kit/workflows/feature-flow.md`.

### Open

- Run `/sdd-specify` for **Feature 001 — PostgreSQL compatibility** (the four blockers in
  `prompt-db-migration.md` §6: driver disabled in the Dockerfile, hardcoded `sslmode`,
  MySQL-only `allowDisabledPk()`, tests on SQLite instead of the production engine).
- Feature 002 (data migration) stays parked until Feature 001 is closed and deployed — production
  must stay on MySQL until then.

### Blockers / open questions

- None yet — Feature 001's spec hasn't surfaced any `[NEEDS CLARIFICATION]` markers because it
  hasn't been drafted yet.

### Decisions recorded elsewhere

- None (no ADRs yet; the constitution is v1, no amendments).

### Dead ends / discarded

- None this session.

### Resume from

Run `/sdd-specify` for Feature 001 — PostgreSQL compatibility, branch `feat/001-postgresql-compatibility`,
folder `.specs/001-postgresql-compatibility/`. Source material already gathered:
`prompt-db-migration.md` §6 (blockers 1, 2, 3, 5) and §10 (deployment facts).
