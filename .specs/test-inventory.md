# Test Inventory — link-shortener (brownfield, quick-path)

Lightweight pass, per the quick-path variant (repo <2kLOC). Full run: `composer test`
(220 tests, 462 assertions, 5.15s, all green, run on 2026-09-05).

## Coverage

**Not measured.** No coverage driver installed (`php -m` shows neither `pcov` nor `xdebug`).
No fabricated number is recorded here — installing a coverage driver is a fair candidate for
Feature 001 (PostgreSQL compatibility) since that feature is already touching `compose.yaml` /
the Docker image, or a small standalone follow-up. The constitution's coverage-floor article
(Article IX) cannot be enforced by CI today because there is no CI; see `index.md`.

## Pyramid shape

| Layer | Files | What they cover |
|---|---|---|
| Unit (`tests/Unit/`) | 6 | DTOs (`CreateUrlData`, `UpdateUrlData`), `UrlStatus` enum, `CodeGeneratorService`, `QrCodeGeneratorService`, `UrlValidatorService` |
| Feature (`tests/Feature/`) | 11 | Actions (Create/Resolve/Update), Filament resource, API controller, landing page, redirect controller, `ShortUrl` model, observer, cache service, QR download endpoints |

11 Feature files vs. 6 Unit files — reasonably balanced for the domain size; Actions and the
cache/observer interplay (the exact area `prompt-db-migration.md` §7 flags as the cutover risk)
already have dedicated Feature coverage (`ResolveShortUrlActionTest.php`, `CacheServiceTest.php`,
`ShortUrlObserverTest.php`) to build on for Feature 002.

## Slow tests (>500ms)

None. Slowest observed test is 0.09s (`QrCodeDownloadTest`). The whole suite runs in ~5.15s.

## Flaky tests

No history available — no CI, so no retry/rerun data exists. Nothing flagged from local runs.

## Test smells

None significant found. Notes:

- `tests/Pest.php` applies `RefreshDatabase` globally to everything under `Feature/` (not
  `Unit/`), and the whole suite runs against real in-memory SQLite (`phpunit.xml`:
  `DB_DATABASE=:memory:`) rather than a mocked database — consistent with Article III
  (boundary-only mocks).
- No giant fixtures, no chained mock towers, no sleep-based synchronization observed.

## Ranked test-debt (address opportunistically, not as a backlog)

1. No coverage measurement — install `pcov` (lighter than `xdebug`) when next touching the
   Docker image or CI setup.
2. No CI — currently nothing runs the suite except local `composer test`. Worth a minimal
   GitHub Actions workflow once Feature 001 adds a `pgsql` test service, so the suite runs on
   the engine production uses (constitution Article IX / project's standing rule from ADR 0003
   on the sibling `my-blog` project).
