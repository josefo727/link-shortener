# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- QR Code generation for shortened URLs (PNG and SVG formats)
  - `QrCodeGeneratorService` with `QrCodeGeneratorInterface` contract
  - Download buttons in Filament table (row actions) and edit page (header actions)
  - On-demand generation, no storage required
  - Uses `chillerlan/php-qrcode` v5 with ECC level H
- REST API with Laravel Sanctum authentication
  - `POST /api/urls` - Create short URL (returns existing if URL exists)
  - `PUT /api/urls/{code}` - Update URL and/or title
  - `DELETE /api/urls/{code}` - Soft delete URL
- API token management command: `php artisan api:token:create`
- Rate limiting (60 requests/minute per user)
- Support for `expires_at` field in API creation
- PostgreSQL 18 compatibility (prerequisite for migrating off the shared MySQL cluster)
  - Docker image now provides the `pdo_pgsql`, `pgsql`, and `pcov` (code coverage) PHP extensions
  - `DB_SSLMODE` is configurable via env (previously hardcoded to `prefer`)
  - The MySQL-only migration primary-key-enforcement statement is now driver-aware — it no
    longer fires against PostgreSQL or SQLite, regardless of the `ALLOW_DISABLED_PK` env value
  - `composer test:pgsql` runs the full test suite against a local PostgreSQL 18 service
    (`compose.yaml`), alongside the existing SQLite-based `composer test`
  - Production still runs on MySQL — this release only adds compatibility, it does not migrate
    any data (see `.specs/001-postgresql-compatibility/`)
- Data migration tooling for the move off the shared MySQL cluster (not yet executed against
  production)
  - `php artisan db:copy-from-legacy [--truncate]` copies `users`, `short_urls`, and
    `personal_access_tokens` from a new `legacy` connection into the target database, field for
    field, and resets PostgreSQL's identity sequences afterward
  - `php artisan db:verify-copy` compares every row of every copied table between the two
    databases and exits non-zero if any differ
  - Built and proven entirely against local fixtures — no real database credentials are used by
    this project's own test suite (see `.specs/002-data-migration/`)
  - Cutover and rollback runbook written (`.specs/002-data-migration/runbook.md`); the actual
    cutover against production is a separate, explicitly-triggered step, not part of this release

### Changed
- Updated User model with HasApiTokens trait for Sanctum

---

## [0.1.0] - 2025-12-31

### Added
- Core URL shortening functionality
  - `CodeGeneratorService` with cryptographic randomness
  - `UrlValidatorService` for URL sanitization
  - `CacheService` with Redis and 1-week TTL
  - `ShortUrlObserver` for cache invalidation
  - `CreateShortUrlAction`, `ResolveShortUrlAction`, `UpdateShortUrlAction`
- Database layer with optimized indexes (code, hash, status)
- Redirect endpoint with 301 permanent redirects
- Filament 4 admin panel with full CRUD
  - Table with search, filters (status, trashed), copy/visit actions
  - Form with URL validation and auto-title generation
- Internationalization (Spanish default, English fallback)
- Quality tooling: Pint, PHPStan level 9, PHPCS (PSR-12)
- Documentation structure (PROGRESS, ARCHITECTURE, CHANGELOG, NEXT_STEPS)
- Landing page and custom 404

---
