# Tasks — 001-postgresql-compatibility

## Legend

- `T{NNN}` — task id, unique within feature, zero-padded.
- `[P]` — safe to execute in parallel with other `[P]` tasks (disjoint files, no shared mutable state).
- `R` — Red beat description.
- `G` — Green beat description.
- `F` — Refactor beat description.
- `status` — `open | in_progress | closed | skipped`.

Two independent tracks exist here, worth naming up front: the **production image** track (T001,
T002 — `Dockerfile`, the `webdevops/php-nginx` base used by the VPS deployment) and the **local
test tooling** track (T003, T004, T005 — `config/database.php`, `AppServiceProvider`,
`compose.yaml`/`phpunit.pgsql.xml`, none of which depend on the production Dockerfile, since local
dev runs PHP on the host, not inside that image — confirmed: this machine's host PHP already has
`pdo_pgsql` loaded). T006 only depends on the second track; T002 stays required for criterion 6
regardless.

---

## T001 `Spike: confirm the pcov install mechanism in webdevops/php-nginx:8.4-alpine`

```
spec-ref:        Acceptance criteria 6, 8
contract-ref:    n/a
constitution-ref:n/a (feeds ADR-adjacent research, not an article directly)
DoD:
  - a local Docker build is attempted with the candidate install step (apk package first, e.g.
    `php84-pcov`; PECL fallback — `pecl install pcov && docker-php-ext-enable pcov` — if the apk
    package doesn't exist)
  - `php -m` output from the built image is captured, confirming which mechanism actually works
  - research.md's open question is closed with the confirmed mechanism and date
R: no verified answer exists today for how to enable pcov in this base image (research.md marks
   it explicitly open).
G: build a throwaway image locally with the candidate install line(s); run `php -m`; record the
   working mechanism in research.md.
F: skipped — spike, nothing to refactor.
files:
  - .specs/001-postgresql-compatibility/research.md
status: closed
commits:
  red: n/a — spike, no automated test
  green: fcaddc5
  refactor: skipped — spike, nothing to refactor
notes: Confirmed via direct `docker run` experimentation (not just docs): pdo_pgsql/pgsql are
  already compiled in, just excluded by PHP_DISMOD (env-only fix, verified). pcov needs the PECL
  path — `apk add --virtual .build-deps autoconf gcc g++ make && pecl install pcov &&
  docker-php-ext-enable pcov && apk del .build-deps` — verified end-to-end, survives cleanup.
  Full findings in research.md.
```

---

## T002 `Enable pdo_pgsql, pgsql, and pcov in the production Docker image`

```
spec-ref:        Acceptance criteria 1, 6, 8
contract-ref:    n/a
constitution-ref:Article IX (static analysis / build floor), Article II (test-first, via the
                  smoke-check script)
DoD:
  - PHP_DISMOD in Dockerfile no longer lists pdo_pgsql or pgsql
  - Dockerfile includes the pcov install step confirmed by T001
  - a smoke-check script builds the image (or reuses an existing build) and asserts `php -m`
    lists pdo_pgsql, pgsql, and pcov
  - MySQL still connects afterward (nothing else in PHP_DISMOD changed) — regression check
R: the new smoke-check script (scripts/check-php-extensions.sh) fails today — pdo_pgsql, pgsql,
   and pcov are absent from the built image's `php -m`.
G: edit Dockerfile: remove `pdo_pgsql,pgsql` from PHP_DISMOD; add the pcov install step from T001.
F: skipped — no smell detected, single-purpose Dockerfile edit.
files:
  - Dockerfile
  - scripts/check-php-extensions.sh
status: closed
commits:
  red: 7ca1e86
  green: 916cdef
  refactor: skipped — no smell detected, single-purpose Dockerfile edit
notes: Depends on T001's confirmed mechanism. Does not touch VPS deployment (clarify Q4) — image
  is built and checked locally/in this repo only. MySQL regression bullet confirmed: pdo_mysql/
  mysqlnd were never in PHP_DISMOD, still present in the built image.
  Verify-time evidence (2026-09-05): ran the full suite with `--coverage` inside the built image
  (real pcov, not just `php -m`) — 94.3% total coverage, closing criterion 8 and the
  previously-unmeasured gap in test-inventory.md.
```

---

## T003 [P] `Make the pgsql connection's sslmode env-driven`

```
spec-ref:        Acceptance criterion 1
contract-ref:    contracts/database-pgsql-connection.md
constitution-ref:Article II, Article IX
DoD:
  - config('database.connections.pgsql.sslmode') resolves to DB_SSLMODE when the env var is set
  - defaults to 'prefer' when DB_SSLMODE is unset (unchanged default behavior)
  - no other pgsql connection key changes
R: a new Feature test sets DB_SSLMODE to a custom value and asserts
   config('database.connections.pgsql.sslmode') reflects it — fails today (hardcoded 'prefer').
G: change `'sslmode' => 'prefer'` to `'sslmode' => env('DB_SSLMODE', 'prefer')` in
   config/database.php.
F: skipped — one-line config change, nothing to extract.
files:
  - config/database.php
  - tests/Unit/Config/DatabasePgsqlConfigTest.php
status: closed
commits:
  red: 94d1203
  green: 50a7538
  refactor: skipped — one-line config change, nothing to extract
notes: Test moved to tests/Unit/Config/ (not Feature/) during implement — it re-requires
  config/database.php directly with a controlled env, no framework container/DB needed.
```

---

## T004 [P] `Guard allowDisabledPk()'s MySQL-only statement by connection driver`

```
spec-ref:        Acceptance criterion 3
contract-ref:    contracts/migration-driver-guard.md
constitution-ref:Article II; operationalizes ADR 0001
DoD:
  - ALLOW_DISABLED_PK=true + driver mysql: statement still issued before/after migrations
    (regression, existing behavior preserved)
  - ALLOW_DISABLED_PK=true + driver pgsql: statement NOT issued, no error
  - ALLOW_DISABLED_PK=true + driver sqlite (the test suite's own connection): statement NOT
    issued, no error
  - ALLOW_DISABLED_PK=false, any driver: neither listener registered (regression, unchanged)
R: a Feature test fakes the DB facade and asserts `DB::statement` is never called with the
   MySQL-only SQL when the active connection's driver is pgsql — fails today (listener fires
   unconditionally once ALLOW_DISABLED_PK is true).
G: add a driver check (`DB::connection()->getDriverName() === 'mysql'`) at the top of each
   listener closure in allowDisabledPk(), returning early otherwise.
F: extract the driver check into a small private guard method if the duplication between the two
   closures reads awkwardly once green.
files:
  - app/Providers/AppServiceProvider.php
  - tests/Feature/Providers/AllowDisabledPkGuardTest.php
status: closed
commits:
  red: df0c9d2
  green: 5529d60
  refactor: 21ba814 — concat_space style fix (Pint), no behavior change
notes: Driver check resolves DB::connection() once per event and reuses it for both the check
  and the statement, rather than two separate facade calls — makes the guard's boundary
  interaction explicit and the test's mock setup straightforward.
```

---

## T005 [P] `Add local PostgreSQL test tooling (compose service, phpunit config, composer script)`

```
spec-ref:        Acceptance criteria 4, 5
contract-ref:    n/a — governed by ADR 0002, not an app-facing contract
constitution-ref:Article VI (ADR 0002)
DoD:
  - compose.yaml has a pgsql service (postgres:18-alpine) on the sail network, with a healthcheck,
    analogous to the existing mysql service
  - phpunit.pgsql.xml exists, mirrors phpunit.xml, sets DB_CONNECTION=pgsql and matching
    host/port/credentials for that service
  - composer.json has a test:pgsql script analogous to the existing test script
  - running `composer test:pgsql` with the service up requires no other manual step
R: `composer test:pgsql` fails today — the script and phpunit.pgsql.xml don't exist.
G: add the compose service, the phpunit config, and the composer script.
F: skipped — config-only task, nothing to refactor.
files:
  - compose.yaml
  - phpunit.pgsql.xml
  - composer.json
status: closed
commits:
  red: n/a — verified (`composer test:pgsql` → "Command test:pgsql is not defined"), nothing to
    commit for an absent script
  green: f6a3bbd
  refactor: skipped — no smell detected
notes: compose.yaml here is Laravel Sail's local-dev compose file — a different Docker setup
  from the production Dockerfile touched in T002. Local PHP (this machine's Herd install)
  already has pdo_pgsql loaded, confirmed while planning — this task does not depend on T002.
  Two real problems fixed during implement (not anticipated in the plan): Postgres 18's changed
  volume-mount convention, and `php artisan test --configuration=` duplicating the flag
  internally (worked around by calling `./vendor/bin/pest --configuration=` directly). Verified
  end to end: `composer test:pgsql` — 226 tests, 470 assertions, exit 0 against real
  postgres:18-alpine.
```

---

## T006 `Full suite green against PostgreSQL; MySQL regression confirmed`

```
spec-ref:        Acceptance criteria 4, 7
contract-ref:    n/a
constitution-ref:Article II, Article VII
DoD:
  - `composer test:pgsql` (existing 220 tests + T003's + T004's new ones) passes against the
    pgsql service
  - `composer test` (SQLite, existing default) still passes unchanged — proves criterion 7
  - any PostgreSQL-specific failure that surfaces gets its own fix and its own test, not a skip
R: n/a — this task is the closing verification run, not a new unit of behavior; the "failing"
   state is simply "not yet proven green against pgsql".
G: run both suites; fix, with its own R-G-F, any PostgreSQL-specific failure that surfaces (none
   anticipated — discovery found no JSON columns, string-enums, or other MySQL/PostgreSQL
   portability traps in this schema, but this task exists to prove that rather than assume it).
F: n/a unless a fix from this task reveals duplicated logic worth extracting.
files: (open-ended — depends on whether a PostgreSQL-specific failure surfaces)
status: closed
commits:
  red: n/a — verification task, no failing state to commit
  green: n/a — no code change needed; both runs passed clean, evidence below
  refactor: n/a — nothing surfaced to refactor
notes: Depends on T003, T004, T005. Does not depend on T001/T002 (see track note above).
  Final verification, both clean: `composer test` (SQLite) — 226 passed, 470 assertions;
  `composer test:pgsql` (postgres:18-alpine) — 226 passed, 470 assertions. No PostgreSQL-specific
  failure surfaced, consistent with the discovery report finding no MySQL/PostgreSQL portability
  traps in this schema.
  Verify-time evidence (2026-09-05): criterion 7 ("MySQL remains fully functional") had not
  actually been run against a real MySQL server until `/sdd-verify` — closed the gap by running
  the suite against the project's real `mysql` compose service: 226 passed, 470 assertions, no
  regression.
```

---

## T007 `Document composer test:pgsql in CLAUDE.md`

```
spec-ref:        Acceptance criterion 5
contract-ref:    n/a
constitution-ref:Article XII (language convention — docs in English)
DoD:
  - CLAUDE.md's Commands section lists `composer test:pgsql` next to the existing `composer test`
  - one line explaining when to use it (proving PostgreSQL compatibility, not the everyday loop)
R: n/a — documentation task, no failing test; the DoD is the check.
G: add the line to CLAUDE.md.
F: skipped — doc-only.
files:
  - CLAUDE.md
status: closed
commits:
  red: n/a — documentation task
  green: b9d62e7
  refactor: skipped — doc-only
notes: Ordered last so it documents the actual final command name/behavior from T005/T006, not a
  guess.
```

---

## Verify

Run 2026-09-05. Full suite: 226 tests, 470 assertions — green against SQLite (`composer test`),
real PostgreSQL 18.4 (`composer test:pgsql`), and real MySQL 8.4 (ad hoc, see below). Pint clean
(83 files). PHPStan level 9 clean.

```
R1  Spec coverage:         PASS (8/8 criteria) — 2 notes, not gaps:
                             - criterion 1: sslmode-configurable is unit-tested (T003); actual
                               TLS negotiation isn't exercised locally (no SSL-enabled server in
                               dev tooling) — only provable at the real DigitalOcean cutover,
                               out of this feature's scope.
                             - criteria 2 and 7 are regression/integration criteria with no
                               dedicated spec-ref'd unit test; proven instead by the full suite
                               passing against real PostgreSQL (T005/T006) and, closed during
                               this verify pass, against real MySQL (see below).
R2  Task completeness:     PASS (7/7 closed; commit SHAs present, "n/a" beats have a reason)
R3  Orphan tests:          PASS (0 orphans — both new test files tie to a contract + criterion)
R4  Contracts match:       PASS (2/2 contracts, consumer tests green, versions/dates current)
R5  Constitution:          PASS (Articles I, II, III, IV, V, VI, VII, IX honored; X, XI, XII n/a
                             or unaffected — see notes below)
R6  Research freshness:    PASS (all entries dated 2026-09-05, <60 days)
R7  Observability:         N/A (no new logs/metrics/traces declared as needed in plan.md)
R8  Security review:       N/A (no auth/money/PII/external-write surface touched)
R9  Docs:                  PASS (CLAUDE.md, test-inventory.md updated)
R10 Changelog:             PASS (docs/CHANGELOG.md [Unreleased] entry added)
```

Gaps found and closed during this verify pass (not assumed, actually run):
- Criterion 7 had never been run against a real MySQL server — ran the suite against the
  project's `mysql` compose service: 226 passed, 470 assertions, no regression.
- Criterion 8's pcov install had only been checked via `php -m`, not an actual coverage run —
  ran the suite with `--coverage` inside the built production image: **94.3%** total, above the
  80% floor. `test-inventory.md`'s coverage gap is now closed.

R5 detail (constitution articles):
- I (spec-anchored): every task cites a `spec-ref`.
- II (test-first): Red-before-Green commit order confirmed in history for T002, T003, T004.
- III (boundary-only mocks): T004's mocks target the Database boundary only (declared in
  Article VII), consistent with `contracts/migration-driver-guard.md`.
- IV (contract-first): both contracts written during `plan` phase, before their consumer tests.
- V (no silent clarification): all 4 spec markers resolved via `AskUserQuestion`, logged in
  `clarify.md`.
- VI (ADR for non-local decisions): ADR 0001, ADR 0002 cover the two multi-task decisions.
- VII (boundaries): Database boundary extended to a second engine; documented in `plan.md`.
- IX (static analysis floor): Pint clean, PHPStan level 9 clean, coverage 94.3% ≥ 80% floor.
- X, XI, XII: not implicated by this feature (no data migration, no new credentials, docs/code
  stayed in English as required).

No FAIL. Feature closed below.

---

## Amendments

| Date | Change | Reason |
|------|--------|--------|
|      |        |        |
