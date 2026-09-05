# Test Inventory — link-shortener (brownfield, quick-path)

Lightweight pass, per the quick-path variant (repo <2kLOC). Full run: `composer test`
(220 tests, 462 assertions, 5.15s, all green, run on 2026-09-05).

## Coverage

**Closed by Feature 001** (2026-09-05). `pcov` is now installed in the production Docker image
(T002). Verified end to end: running the suite with `--coverage` inside the built image reports
**94.3% total coverage** — well above the project's 80% floor (`CLAUDE.md`). This was not
measurable on this dev machine's host PHP (no coverage driver there) or in CI (none exists) —
only inside the container, which is where `composer test:coverage` is expected to run until CI
exists (still a separate follow-up, out of scope for Feature 001 per its clarify log).

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

1. ~~No coverage measurement~~ — closed by Feature 001 (T002 installs `pcov`; 94.3% confirmed).
2. No CI — currently nothing runs the suite except local `composer test`/`composer test:pgsql`.
   Worth a minimal GitHub Actions workflow now that Feature 001 added a `pgsql` test service, so
   the suite runs on the engine production uses (constitution Article IX / project's standing
   rule from ADR 0003 on the sibling `my-blog` project). Explicitly out of scope for Feature 001
   (clarify Q2).
