# Spec — 001-postgresql-compatibility

## Summary

Make `link-shortener` fully compatible with PostgreSQL — connecting, migrating, and passing its
whole automated test suite — while production keeps running on MySQL until this feature is
deployed and verified. This is the prerequisite for the data migration that follows it.

## User story

As the maintainer of `link-shortener`, I want the application (and its test suite) to run
correctly against PostgreSQL, so that I can later move production off a shared MySQL credential
that also reaches unrelated schemas, without any code getting in the way of that switch.

## Acceptance criteria

1. When configured with a PostgreSQL connection, the application connects successfully over TLS
   to a PostgreSQL 18.x database, using a configurable SSL mode rather than a fixed one.
2. Running the application's migration command against an empty PostgreSQL database completes
   without error and creates every table the application needs.
3. The application's primary-key-enforcement behavior, which today issues a MySQL-specific
   session command, does not run and does not raise an error when the configured database driver
   is not MySQL.
4. The full automated test suite passes when run against a PostgreSQL database matching the
   target version.
5. Running the test suite against PostgreSQL requires no manual setup beyond the project's
   existing documented test command — it is wired into the existing local dev tooling.
6. The application's deployed container image provides the PostgreSQL driver, verifiable at
   runtime, with no change to application code required to use it.
7. None of the above changes alter the application's behavior when configured against MySQL —
   MySQL remains fully functional throughout, since production stays on MySQL until this feature
   is deployed and Feature 002 begins.

## Non-goals

- Moving any production data from MySQL to PostgreSQL (Feature 002).
- Changing production's live configuration to point at PostgreSQL.
- Fixing the pre-existing, unrelated gaps noted in `prompt-db-migration.md` §11 (a stray
  `.env.bak` file with plaintext secrets, uncommitted `supervisord.conf` drift on the VPS, mail
  configuration naming a host that doesn't exist).
- Any change to `CacheService`/`ResolveShortUrlAction` runtime behavior — the Redis
  cached-model risk in `prompt-db-migration.md` §7 is a Feature 002 cutover concern, not a
  compatibility concern.

## Applicable constitution articles

- Article I — Spec-anchored development: this feature's changes trace to this spec.
- Article II — Test-first: driver/config/guard changes each get a failing test before the fix.
- Article VI — ADR for non-local decisions: guarding the primary-key-enforcement behavior by
  driver, and adding a database service to local dev tooling, affect more than one task.
- Article VII — Boundaries: this feature extends the Database boundary to a second engine;
  nothing else in the boundary list changes.
- Article IX — Strict typing and a static analysis floor: applies to any new or touched code
  (PHPStan level 9, PSR-12, `strict_types=1`).

## Open questions

- [NEEDS CLARIFICATION: should installing a code-coverage driver (`pcov`) ride along with this
  feature, since it already touches the container image/build, or stay a separate follow-up?]
- [NEEDS CLARIFICATION: does setting up a CI pipeline (none exists today) belong to this
  feature, or is it a separate follow-up? Criterion 5 only requires the suite to be runnable
  locally against PostgreSQL, not automated on every push.]
- [NEEDS CLARIFICATION: which exact PostgreSQL version/tag gets pinned for local test tooling —
  match production's `18.6` exactly, or accept any `18.x` image?]
- [NEEDS CLARIFICATION: does "deployed" in criterion 6 mean this feature's own closing step
  rebuilds and redeploys the container to the VPS (as `prompt-db-migration.md` describes for the
  blog's equivalent feature), or does deployment happen as a separate, explicitly-triggered step
  after the user reviews the change?]

## Glossary additions

- **Compatibility (this feature)** — the application functions correctly against PostgreSQL;
  it does not mean any data has moved, or that production is reconfigured.

---

## Closed (filled during verify)

- Date: `<pending>`
- Commit: `<pending>`
- Notes: `<pending>`
