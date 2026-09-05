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

## 2026-09-05 16:30 — session 2 (Feature 001 + Feature 002 complete)

### Context

Continued straight from session 1's resume point, in the same conversation. Ran both remaining
features from `prompt-db-migration.md` §8 fully through the kit's flow, each closed and merged.

### Done this session

- **Feature 001 — PostgreSQL compatibility**: spec (8 criteria) → clarify (4 resolved) →
  plan/research/contracts/2 ADRs (0001 driver guard, 0002 local pgsql test tooling) → tasks (7) →
  implement (T001–T007, each its own R-G-F) → verify (R1–R10; two real gaps caught *during*
  verify rather than assumed — the suite had never actually run against real MySQL, and pcov had
  only been checked via `php -m`, not a real coverage run — both closed on the spot, 94.3%
  coverage measured) → closed → merged to `master` (fast-forward) → pushed (`7c86282`).
- **Feature 002 — data migration tooling**: spec (8 criteria) → clarify (4 resolved; the key one:
  the real-database rehearsal is deferred to a separate, later, VPS-only SSH step — never this
  session) → plan/research/contracts/2 ADRs (0003 application-level copy strategy, 0004 legacy
  connection is a local SQLite double under test) → tasks (9) → implement (T001–T009; T005's
  sequence-reset test was made genuinely red against Feature 001's real local PostgreSQL before
  implementing the fix, so its later pass is real evidence, not assumed) → verify (R1–R10, incl.
  a dedicated security-review pass — table-name interpolation in the sequence-reset SQL traced to
  a fixed literal array, no findings) → closed → merged to `master` (fast-forward) → pushed
  (`d73a6c6`).
- `runbook.md` (`.specs/002-data-migration/runbook.md`) is written and adapted to this project's
  real deployment facts (VPS, container, Redis ACL, supervisor group name — each verified against
  this repo's actual config, not assumed from the source doc alone). Its rehearsal and
  "what actually happened" sections are explicitly marked **PENDING** — never fabricated ahead of
  the real event.
- Saved a global memory: the user wants Venezuelan-register Spanish, never Argentine.

### Open

- The real rehearsal (VPS-only, via SSH on `vps_josefo_01`, throwaway container, against the real
  MySQL source and real PostgreSQL target) — a separate, explicitly-triggered step. Not started.
- The actual production cutover — same; waits for the owner's go-ahead at that moment, with real
  credentials in hand.
- Day-30 decommission of the legacy MySQL database — only after the cutover actually happens.

### Blockers / open questions

- None from the kit's side. The real rehearsal/cutover need the owner physically present (or
  explicitly delegating) with real production credentials — not something to start unprompted,
  and not something this local session has touched at any point (ADR 0004).

### Decisions recorded elsewhere

- ADR 0001, 0002 (Feature 001) · ADR 0003, 0004 (Feature 002).
- `clarify.md` in each feature folder — all 8 clarification Q&As.

### Dead ends / discarded

- None discarded — two real defects were caught and fixed inline during implementation rather
  than designed around: `php artisan test --configuration=` duplicates the flag internally
  (Feature 001 T005, worked around with `vendor/bin/pest` directly), and PostgreSQL 18+ changed
  its Docker volume-mount convention (`/var/lib/postgresql`, not `.../data`).

### Resume from

The next real step is `.specs/002-data-migration/runbook.md` §Rehearsal — run it only when the
owner is ready to proceed with real credentials, via SSH on `vps_josefo_01`, never from a local
development session. Once that rehearsal is clean, the same runbook's §Cutover is the actual
production switch.
