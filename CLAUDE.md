# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Personal URL shortener built with **Laravel 12** and **Filament 4**. Uses MySQL (via Sail) for development, PostgreSQL for production, and Redis for caching redirects.

**Language conventions:**
- Code, comments, commits, documentation: **English**
- User interface and user-facing messages: **Spanish** (with i18n support for English)

## Commands

```bash
# Development (runs server, queue, logs, and vite concurrently)
composer dev

# Run all tests
composer test

# Run a single test file
php artisan test tests/Unit/Services/CodeGeneratorServiceTest.php

# Run tests matching a filter
php artisan test --filter=ShortUrl

# Code formatting
./vendor/bin/pint           # Fix style issues
./vendor/bin/pint --test    # Check without fixing

# Static analysis (target: level 9)
./vendor/bin/phpstan analyse

# Code standards (PSR-12)
./vendor/bin/phpcs
./vendor/bin/phpcbf         # Auto-fix

# Full project setup
composer setup
```

## Architecture

### Design Patterns

- **Action Classes** (`app/Actions/`): Single-purpose classes for business logic. All operations (create, update, delete, resolve URLs) go through Actions, making them testable and reusable across HTTP, CLI, and Filament.

- **DTOs** (`app/DataTransferObjects/`): Readonly data transfer objects for passing data between layers with type safety.

- **Services** (`app/Services/`): Stateless services for specific concerns:
  - `CodeGeneratorService`: Generates unique 6-char alphanumeric codes
  - `UrlValidatorService`: Validates and sanitizes URLs
  - `CacheService`: Redis caching with 1-week TTL

- **Observer Pattern** (`app/Observers/`): `ShortUrlObserver` handles cache invalidation on model changes.

### Key Architectural Decisions

1. **Cache-first redirects**: Redis caches full ShortUrl model data, invalidated via Observer
2. **URL deduplication**: SHA-256 hash stored in `original_url_hash` for fast duplicate detection
3. **301 redirects**: Permanent redirects for SEO, browser caching
4. **Strict typing**: All files use `declare(strict_types=1)`, final classes, readonly properties

### Directory Structure (Domain-specific)

```
app/
├── Actions/Url/           # CreateShortUrlAction, ResolveShortUrlAction, etc.
├── Contracts/             # Interfaces (UrlShortenerInterface, etc.)
├── DataTransferObjects/   # CreateUrlData, UpdateUrlData
├── Enums/                 # UrlStatus (active, inactive, expired)
├── Events/Url/            # UrlCreated, UrlAccessed, UrlUpdated
├── Exceptions/Url/        # InvalidUrlException, UrlNotFoundException, etc.
├── Filament/Resources/    # ShortUrlResource with CRUD
├── Services/              # CodeGenerator, UrlValidator, Cache
config/shortener.php       # Cache TTL, code length, alphabet config
```

### Database

Table `short_urls`:
- `code`: Unique 6-char identifier (indexed)
- `original_url`: Destination URL
- `original_url_hash`: SHA-256 for duplicate detection (indexed)
- `status`: enum (active, inactive, expired)
- `clicks`: Counter
- Soft deletes enabled

### Cache Keys

```
shorturl:code:{code}   -> Full ShortUrl JSON
shorturl:hash:{hash}   -> Code lookup by URL hash
```

## REST API

Authentication via Laravel Sanctum Bearer tokens.

### Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `POST` | `/api/urls` | Create short URL (returns existing if URL exists) |
| `PUT` | `/api/urls/{code}` | Update URL and/or title |
| `DELETE` | `/api/urls/{code}` | Soft delete |

### Generate API Token

```bash
./vendor/bin/sail artisan api:token:create user@example.com --name="my-app"
```

### Example Request

```bash
curl -X POST https://domain.com/api/urls \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"original_url": "https://example.com", "title": "My link", "expires_at": "2025-12-31"}'
```

### Rate Limiting

60 requests/minute per authenticated user.

## Testing Approach (TDD)

- **Unit tests** (`tests/Unit/`): Services, DTOs, isolated logic
- **Feature tests** (`tests/Feature/`): HTTP endpoints, Filament resources, Actions with database

Test naming uses snake_case: `it_generates_a_code_with_correct_length`

## Quality Constraints

- PHPStan level 9
- Test coverage minimum 80%
- PSR-12 code style
- All methods must have explicit return types
- Use enums/constants instead of magic strings
