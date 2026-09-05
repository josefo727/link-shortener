# Onboarding — link-shortener (brownfield, quick-path)

Repo is small (~2000 LOC in `app/`, 36 PHP files under `app/`), so this onboarding follows the
quick-path variant of `workflows/brownfield.md`: unified constitution (no Current/Aspirational
split), lightweight test inventory, no separate discovery write-up beyond what feeds the
constitution directly.

## Intake

- **Language/framework:** PHP (Laravel 12), admin panel on Filament 4.
- **Package manager:** Composer (PHP), npm (frontend assets via Vite).
- **Test runner/command:** Pest, via `composer test` (`php artisan config:clear && php artisan test`).
  Also `php artisan test tests/Unit/Services/CodeGeneratorServiceTest.php` for a single file,
  `php artisan test --filter=ShortUrl` for a filtered run.
- **Where tests live:** `tests/Unit/` (Services, DTOs, Enums, isolated logic) and `tests/Feature/`
  (Actions, Filament, Http, Models, Observers, Services — HTTP endpoints and DB-touching code).
- **CI provider:** none configured (no `.github/workflows/`). Tests, lint (`pint`), and static
  analysis (`phpstan` level 9, `phpcs`) run locally / on demand only.
- **Deployment target:** VPS (`ssh vps_josefo_01`), Docker container `josefo-link-container`
  (image `josefo727/josefo-link`), orchestrated via `~/www/nginx-proxy/docker-compose.yml`.
  Supervisor runs queue workers and the scheduler. Production DB today is a shared MySQL cluster
  on DigitalOcean; PostgreSQL is the migration target (see below).
- **Size:** ~2000 LOC / 36 files under `app/`; qualifies for brownfield quick-path (<2kLOC).
- **Current pain point / reason for adopting SDD+TDD now:** the pending MySQL→PostgreSQL
  migration (`prompt-db-migration.md`). The app currently connects with credentials (`doadmin`)
  that also reach unrelated production schemas on a cluster the owner doesn't own; that container
  was compromised in September 2026. This is not a tidiness migration — it removes a live
  credential-exposure surface, and the cutover has real blast radius (redirect hot path, cached
  Eloquent models with a one-week TTL). The kit is being adopted specifically so this migration
  (and the PostgreSQL-compatibility work ahead of it) is spec'd, planned, and test-driven rather
  than done ad hoc.

## Discovery

Full report from the `Explore` subagent, condensed:

- **Stack confirmed:** PHP ^8.2 (running 8.4.1), Laravel 12.44.0, Filament 4, Sanctum 4.2,
  Pest 4 + PHPUnit 12.
- **Entry points:** `routes/web.php` (`GET /` landing, `GET /{code}` → `RedirectController`),
  `routes/api.php` (all routes under `auth:sanctum` → `Api\ShortUrlController`), Filament panel at
  `/admin` (`app/Providers/Filament/AdminPanelProvider.php`), app-specific bindings in
  `app/Providers/UrlShortenerServiceProvider.php`.
- **External services actually configured:** mail via Mailpit (dev-only, does not exist on the
  VPS — pre-existing gap, unrelated to this migration), queue driver `redis` but **no Job classes
  exist anywhere** — everything (code generation, caching, observer side effects) runs
  synchronously inline in Actions/Observers. Cache and session also on `redis`. Meilisearch is
  configured but no Scout-searchable models exist. No outbound HTTP to third parties (QR codes are
  generated locally via `chillerlan/php-qrcode`, no network calls).
- **Database:** `config/database.php` defines `sqlite`/`mysql`/`mariadb`/`pgsql`/`sqlsrv`; `.env`
  currently pins `mysql`. `phpunit.xml` forces the whole test suite onto **real** in-memory SQLite
  (`DB_DATABASE=:memory:`), not mocks.
- **No hardcoded connection name anywhere in code** (`DB::connection('mysql')`,
  `->on('mysql')`, etc. — zero matches across `app/`, `database/`, `tests/`, `routes/`, `config/`).
  All Eloquent/query usage goes through the default connection. This narrows (but does not
  eliminate) the migration risk `prompt-db-migration.md` §7 flags: the risk is specifically that
  **serialized Eloquent models cached in Redis** (`CacheService::put()`) carry casts (the
  `UrlStatus` enum, `Carbon` datetimes) and get deserialized and written back to
  (`incrementClicks()`) after the connection's underlying engine changes — a config-level problem,
  not a hardcoded-connection-name problem, but real all the same.
- **Auth:** web/Filament panel is session-based (`guard: web`); the REST API uses Sanctum personal
  access tokens (`config/sanctum.php` guard `['web']`), issued via a custom Artisan command
  (`api:token:create {email}` in `app/Console/Commands/CreateApiTokenCommand.php`).
- **Architectural patterns, with evidence:**
  - Actions: `app/Actions/Url/CreateShortUrlAction.php`, `ResolveShortUrlAction.php`,
    `UpdateShortUrlAction.php`.
  - DTOs: `app/DataTransferObjects/CreateUrlData.php` (readonly, `fromArray()` factory).
  - Services: `app/Services/CacheService.php` (bound to `CacheServiceInterface` in
    `UrlShortenerServiceProvider`).
  - Observer: `app/Observers/ShortUrlObserver.php`, wired via `#[ObservedBy(...)]` attribute on
    `app/Models/ShortUrl.php`.
  - Clock: **no injected clock** — one direct `now()` call, `app/Models/ShortUrl.php:148`
    (`scopeAccessible()`).
  - Randomness: `app/Services/CodeGeneratorService.php` — `random_bytes()` primary, `random_int()`
    fallback if it throws. No `uniqid()`/`mt_rand()` anywhere.
- **Tests:** `tests/Pest.php` applies `RefreshDatabase` globally to everything under `Feature/`
  only (not `Unit/`). Pest functional style (`it('...', function (): void { ... })` +
  `expect()->toBe()/...`). Example Feature test: `tests/Feature/Actions/CreateShortUrlActionTest.php`.
  Example Unit test: `tests/Unit/Services/CodeGeneratorServiceTest.php`.
- **`config/shortener.php`:** cache (`enabled`, `prefix: shorturl`, `ttl: 604800` = 1 week),
  code (`length: 6`, `alphabet` excludes ambiguous chars `0/O,1/l/I,2/Z,5/S`, `max_attempts: 10`).
