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

## 2026-09-06 00:50 — session 3 (real rehearsal, cutover, and a production incident)

### Context

Continued straight from session 2. Ran the real rehearsal, the actual data cutover to
PostgreSQL, and along the way hit and resolved a real production data-loss incident and a real
nginx routing regression — neither one caused by Feature 002's own code.

### Done this session

- Real rehearsal via SSH on `vps_josefo_01`, from a fully isolated setup (fresh GitHub clone,
  distinct image tag `link-shortener-rehearsal:latest`) — never the live container. 37 rows
  copied and verified against the real MySQL source and real PostgreSQL target; sequence reset
  and byte-for-byte timestamp preservation confirmed for real.
- Deployed Feature 001's rebuilt image to the live container, while still on MySQL — verified
  `pdo_pgsql`/`pgsql`/`pcov` present, app healthy, still on MySQL at that point.
- **Incident 1 — data loss on the legacy source.** Attempting the cutover's final copy, found the
  real MySQL source (`link_shortener_jrg`) had 0 rows in `users`/`short_urls`/
  `personal_access_tokens` — confirmed 3 independent ways (the live container's own connection,
  the migration container, raw PDO bypassing Laravel). Reviewed every real-credential command run
  this session; none explain it via a `migrate`/`:fresh`/`:wipe` mechanism (the `migrations`
  table's batch-1/batch-2 history was intact, arguing against a fresh/refresh having run
  anywhere). **Root cause not established.** My own `--truncate` step then emptied the
  already-good 37-row PostgreSQL target from the rehearsal, since it ran before the empty source
  was discovered.
- Recovery: the owner's own call to use DigitalOcean's point-in-time restore-to-**new**-cluster
  (deliberately not an in-place restore, since the shared cluster hosts other real production
  databases). Verified the 37 rows intact on the restored backup cluster; took a `mysqldump` of
  the 4 relevant tables (owner's explicit instruction: back up before touching anything else) —
  stored on the VPS (mode 600) and copied to this repo's local, gitignored `.local-backups/`.
  Re-pointed the migration's `LEGACY_DB_*` at the backup cluster (never restored into the
  original shared cluster) and re-ran copy + verify for real.
- Ran the actual cutover: stopped `queue-worker:*`/scheduler, `artisan down`, final copy, verify
  gate (exit 0), switched `~/www/josefo-link/.env` to PostgreSQL (kept old values as
  `LEGACY_DB_*`), reloaded config, deleted this app's own Redis keys (scoped SCAN+DEL, 66 keys,
  never `cache:clear`), restarted workers, `artisan up`.
- **Incident 2 — nginx routing regression, found during the cutover's own smoke test.** A real
  redirect returned 200 instead of 301, serving `public/index.php`'s raw source instead of
  executing it — every route except the bare `/` was broken (redirects, `/admin`, the API).
  Root cause: `WEB_DOCUMENT_INDEX="index.php index.html"` (set earlier for `/sismos-vzla/`'s
  static page, unrelated to this migration) makes the webdevops base image's entrypoint
  regenerate `10-location-root.conf`'s `try_files` with `/index.php` as a non-final parameter —
  nginx treats every non-final `try_files` parameter as a file-existence check, and
  `public/index.php` always exists, so it "finds" and serves it statically, never reaching
  PHP-FPM. Fixed live first (`docker cp` + `nginx -s reload`), then made durable:
  `docker/nginx-location-root.conf` added to the repo, bind-mounted in
  `~/www/nginx-proxy/docker-compose.yml` (same pattern as `supervisord.conf`), container
  recreated, fix confirmed to survive the recreate. Confirmed via every other webdevops-based
  project's Dockerfile on the VPS that `josefo-link` is the only one setting
  `WEB_DOCUMENT_INDEX` to more than one value — this exact bug can't recur elsewhere there.
- Full smoke test passed after the durable fix: real redirect 301 + click increment in Postgres
  (31→32), `/admin` → `/admin/login` (302→200, dynamic), `/sismos-vzla/` still static, real 404s,
  workers/scheduler running, no new log errors. (The owner's report of a download on
  `/admin/login` traced to browser cache from the broken window — resolved in another browser.)
- Cleanup: deleted `/root/link-shortener-migration/` (checkout, migration `.env`, rescue dump)
  and the throwaway `link-shortener-rehearsal:latest` image from the VPS, once the local dump
  copy was confirmed intact. Left `~/www/josefo-link/.env.pre-cutover-backup` in place (redundant
  with `LEGACY_DB_*`, harmless).

### Open

- **The root cause of the MySQL data loss is still not established.** Not urgent operationally
  (the app runs on PostgreSQL now, with verified-correct data), but worth understanding given
  other real, higher-stakes production databases share that same cluster. Owner's call whether to
  pursue it (DigitalOcean support, cluster-wide audit).
- Day-30 decommission of the legacy MySQL database and removal of `LEGACY_DB_*` from
  `~/www/josefo-link/.env` — needs a dated reminder, counting from 2026-09-06.
- The original shared cluster's `link_shortener_jrg` database is now empty; decide later whether
  to delete it or leave it.
- The DigitalOcean backup cluster created during recovery (`db-mysql-payments-ya-01-sep-5-backup-…`)
  still exists as a real DO resource — owner's call whether to keep or delete it.

### Blockers / open questions

- Whether and how to investigate the data-loss root cause further — the owner's call, not
  something to pursue unprompted given the shared cluster hosts other real production data.

### Decisions recorded elsewhere

- No new ADRs today — everything was operational (rehearsal, cutover, incident response), not new
  feature design.
- `docker/nginx-location-root.conf`'s own header comment documents the nginx bug's full mechanism
  and fix.
- `.specs/002-data-migration/runbook.md`'s Rehearsal and Cutover sections were updated in place
  with real evidence as each step happened, not written after the fact.

### Dead ends / discarded

- First guess at the nginx bug's cause (`fastcgi_param SCRIPT_FILENAME $request_filename` vs.
  `$document_root$fastcgi_script_name`) was wrong — applied, tested, didn't fix it. Moved straight
  to the real cause (`try_files`'s parameter-count semantics) rather than stacking more
  speculative fixes.

### Resume from

Feature 002 is fully executed: production runs on PostgreSQL, verified end to end. Nothing is
scheduled next — the open items above (decommission date, backup-cluster disposal, root-cause
investigation) are the owner's to prioritize whenever, none of them blocking.
