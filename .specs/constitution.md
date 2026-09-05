# Project Constitution — link-shortener

_Brownfield onboarding, quick-path (repo <2kLOC). Unified articles: each states the rule as it
applies from here forward; where the codebase doesn't yet fully satisfy an article, the
Enforcement/migration note says so explicitly rather than pretending otherwise. No separate
Current/Aspirational split — see `workflows/brownfield.md`, quick-path variant._

## Preamble

`link-shortener` is a personal URL shortener (Laravel 12 + Filament 4) serving redirects and a
small admin panel for its owner. The bar: correctness on the redirect hot path (it's the only
thing end users touch), least-privilege credentials, and changes — especially to the data layer —
that are spec'd, tested, and reversible before they touch production.

## Articles

### Article I — Spec-anchored development

**Statement.** Every production change traces to a spec section under `.specs/`. Spec and code
are committed together when behavior changes.

**Rationale.** Prevents documented drift between what the app does and what anyone can find out
about why.

**Enforcement.** `sdd-verify` R1; review gate. Applies from this onboarding commit forward —
pre-existing code has no spec and none will be retrofitted (see `anti-patterns.md`: retrofitting
specs to old code).

---

### Article II — Test-first

**Statement.** No production code is merged without a prior failing test. Git history shows a
`test:` (red) commit before the corresponding `feat:` (green) commit for the same task.

**Rationale.** Design feedback and regression safety; the existing suite (220 tests, 462
assertions, all green — see `test-inventory.md`) is real evidence the project already values this,
even without a documented history of how each test was born.

**Enforcement.** Commit history audit during `sdd-verify`. Applies from this onboarding commit
forward.

---

### Article III — Boundary-only mocks

**Statement.** Doubles are permitted only at the boundaries declared in Article VII. Domain code
(Actions, DTOs, Services, Observers) is tested against real collaborators elsewhere.

**Rationale.** Avoids tautology tests; matches what the repo already does — the suite runs
against real in-memory SQLite (`phpunit.xml`), not a mocked database.

**Enforcement.** Review gate. Already the de facto practice — see `tests/Feature/**Test.php`,
none of which mock the database.

---

### Article IV — Contract-first integration

**Statement.** A new integration with an external system requires a contract file in
`contracts/` before consumer code is written.

**Rationale.** Stable boundaries; contract tests surface drift early — directly relevant to
Feature 002, where the `legacy` MySQL connection is itself a boundary being integrated against.

**Enforcement.** `tasks.md` scheduling rule; `sdd-verify` R4. Nothing in the current codebase
integrates with an external system over the network (discovery confirmed no outbound HTTP), so
this article has no pre-existing violations to migrate.

---

### Article V — No silent clarification

**Statement.** `[NEEDS CLARIFICATION: …]` markers are resolved only through explicit user dialog
captured in `clarify.md` (or an inlined spec update citing it).

**Rationale.** Preserves the owner's actual intent on a project with real production credentials
at stake; avoids confabulating scope for the migration work ahead.

**Enforcement.** `clarify` phase tooling (prefer `AskUserQuestion`); `sdd-verify` orphan-marker
check.

---

### Article VI — ADR for non-local decisions

**Statement.** A decision that affects more than one task, introduces a new dependency/runtime/
service, or amends an article requires an ADR in `.specs/adr/`.

**Rationale.** Auditability — especially for Feature 001/002, where decisions like "add a
`pgsql` service to `compose.yaml`" or "guard `allowDisabledPk()` by driver" have multi-task
impact.

**Enforcement.** Review gate; ADR template linkage.

---

### Article VII — Boundaries

**Statement.** The following are this project's declared boundaries. Mocks are permitted here and
only here:

- **HTTP out:** none today (no outbound HTTP calls exist in the codebase).
- **Database:** Eloquent, via the default connection resolved from `config/database.php`. No code
  hardcodes a connection name (`DB::connection('mysql')` etc. — confirmed absent by discovery
  grep). Feature 002 will add a named `legacy` connection as an explicit second boundary.
- **Queue:** configured (`redis`) but unused — no Job classes exist; everything runs synchronously
  inline in Actions/Observers.
- **Cache:** `App\Contracts\CacheServiceInterface` / `App\Services\CacheService`, backed by Redis,
  1-week TTL, storing serialized `ShortUrl` Eloquent models. This is the project's most delicate
  boundary — see the migration cutover risk in `prompt-db-migration.md` §7.
- **Clock:** **gap.** One direct `now()` call exists (`app/Models/ShortUrl.php:148`,
  `scopeAccessible()`); no injected `Clock` interface exists anywhere. Not worth a dedicated
  migration project at this size — touch-fix if that file is next edited for an unrelated reason,
  otherwise leave as is.
- **Randomness:** `App\Services\CodeGeneratorService` — `random_bytes()` primary,
  `random_int()` fallback. Already isolated in one service; nothing to migrate.
- **Filesystem:** local disk (`FILESYSTEM_DISK=local`); S3 disk defined in config but unused.
- **Third parties:** none integrated over the network. QR generation
  (`App\Services\QrCodeGeneratorService`) runs locally via `chillerlan/php-qrcode`.

**Rationale.** Uniform isolation policy; enables classicist testing of domain code, matching what
the suite already does.

**Enforcement.** Review gate; `plan.md` for any feature must map every external interaction to a
boundary here or add one via ADR (Article VI).

---

### Article VIII — Actions are the business-logic entry point

**Statement.** Business logic — create, update, delete, resolve a short URL — is implemented in
`app/Actions/`, never inline in a controller or a Filament resource. Controllers, CLI commands,
and Filament resources call an Action; they do not reimplement its logic.

**Rationale.** One tested path per operation, reusable across HTTP, CLI, and the admin panel.

**Enforcement.** Review gate. De facto rule — evidence: `app/Actions/Url/CreateShortUrlAction.php`,
`ResolveShortUrlAction.php`, `UpdateShortUrlAction.php`, all consumed from
`app/Http/Controllers/Api/ShortUrlController.php` and Filament resource pages rather than
reimplemented there.

---

### Article IX — Strict typing and a static analysis floor

**Statement.** Every file declares `strict_types=1`. Classes are `final` and properties `readonly`
unless there's a documented reason not to. PHPStan runs at level 9. PSR-12 via Pint/PHPCS.
Minimum test coverage 80% (currently **unmeasured** — no coverage driver installed; see
`test-inventory.md` — this is a known gap, not a passing floor today).

**Rationale.** Type safety and a consistent style floor catch a whole class of bugs before they
reach the redirect hot path.

**Enforcement.** `./vendor/bin/phpstan analyse` (level 9), `./vendor/bin/pint --test`,
`./vendor/bin/phpcs`. Coverage enforcement is currently **aspirational-in-practice**: the number
exists as policy (project's `CLAUDE.md`) but nothing measures it yet. Installing a coverage driver
is tracked in `test-inventory.md`'s ranked test-debt list.

---

### Article X — Reversible data migrations

**Statement.** A schema or data migration against a real database must have a documented,
tested rollback path before it runs against production. "Reversible" means: the old data source is
left untouched and can be switched back to, not merely that a script exists in theory.

**Rationale.** Written directly for the work this onboarding exists to support: the
MySQL→PostgreSQL migration keeps the MySQL source read-only for 30 days specifically so
`LEGACY_DB_*` values are a real rollback, not a fire drill (`prompt-db-migration.md` §9).

**Enforcement.** Review gate + ADR (Article VI) for any data migration; `sdd-verify` for Feature
002 must confirm a rollback path is documented in that feature's `runbook.md` before the cutover
step is allowed to run.

---

### Article XI — Least-privilege, non-shared credentials

**Statement.** A credential this application uses reaches only what this application owns. No
production credential is shared across unrelated schemas, databases, or applications.

**Rationale.** This is not a hypothetical: the current `doadmin` MySQL credential reaches 334
tables across unrelated production schemas on a cluster that isn't this project's, and that
container was compromised in September 2026. This article exists because of that incident, not in
spite of it.

**Enforcement.** Review gate on any new credential or connection string; the target PostgreSQL
grants already verified in `prompt-db-migration.md` §4 (`link_shortener_user` owns exactly
`link_shortener`, confirmed denied on `josefo_site`) are the model to match going forward.

---

### Article XII — Language convention

**Statement.** Code, comments, commit messages, and documentation are written in English.
User-facing interface text and messages are in Spanish (with i18n support for English).

**Rationale.** Matches the project's existing, explicit convention (`CLAUDE.md`).

**Enforcement.** Review gate.

## Amendments

| Date | Article | Change | ADR |
|------|---------|--------|-----|
|      |         |        |     |
