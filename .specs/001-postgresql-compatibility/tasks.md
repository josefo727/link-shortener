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
status: open
commits:
  red:
  green:
  refactor:
notes: Ordered last so it documents the actual final command name/behavior from T005/T006, not a
  guess.
```

---

## Amendments

| Date | Change | Reason |
|------|--------|--------|
|      |        |        |
