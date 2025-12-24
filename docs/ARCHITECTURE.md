# 🏗️ Architecture Decisions

## Overview

This document records the architectural decisions made for the URL Shortener project.

---

## ADR-001: Action Pattern for Business Logic

**Status:** Accepted

**Context:**
Need a clear separation between HTTP layer and business logic that's testable and reusable.

**Decision:**
Use single-purpose Action classes for all business operations.

**Consequences:**
- ✅ Easy to test in isolation
- ✅ Reusable across HTTP, CLI, and Filament
- ✅ Clear single responsibility
- ❌ More files to maintain
- ❌ Slight overhead for simple operations

**Example:**
```php
final readonly class CreateShortUrlAction
{
    public function __construct(
        private CodeGeneratorService $codeGenerator,
        private CacheService $cache,
    ) {}

    public function execute(CreateUrlData $data): ShortUrl
    {
        // Business logic here
    }
}
```

---

## ADR-002: Repository Pattern - Optional

**Status:** Deferred

**Context:**
Whether to use Repository pattern for database access.

**Decision:**
Start with Eloquent directly in Actions. Add Repository layer only if:
- Complex queries emerge
- Multiple data sources needed
- Caching at query level required

**Consequences:**
- ✅ Simpler initial codebase
- ✅ Faster development
- ❌ May need refactoring later

---

## ADR-003: URL Hash for Duplicate Detection

**Status:** Accepted

**Context:**
Need to efficiently check if a URL has already been shortened.

**Decision:**
Store SHA-256 hash of original URL in indexed column.

**Consequences:**
- ✅ Fast O(log n) lookups
- ✅ Fixed 64-character index size
- ✅ Handles URLs of any length
- ❌ Theoretical collision risk (negligible)

**Implementation:**
```php
$hash = hash('sha256', $originalUrl);
```

---

## ADR-004: Code Generation Strategy

**Status:** Accepted

**Context:**
Need unique, short codes that are URL-safe and human-readable.

**Decision:**
- 6 characters alphanumeric (62^6 = 56+ billion combinations)
- Use `random_bytes()` for cryptographic randomness
- Retry up to 10 times on collision
- Future: Allow custom codes with validation

**Alphabet:**
```
abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789
```

**Considerations:**
- Excluded ambiguous: `0O1lI` could be excluded in future for readability
- Case-sensitive: `abc` ≠ `ABC`

---

## ADR-005: Cache Strategy

**Status:** Accepted

**Context:**
Redirect lookups must be fast. Database queries for every redirect is inefficient.

**Decision:**
- Redis as primary cache
- TTL: 1 week (configurable)
- Cache full model data, not just URL
- Invalidate on update/delete via Observer

**Cache Keys:**
```
shorturl:code:{code} -> ShortUrl JSON
shorturl:hash:{hash} -> code (for duplicate check)
```

**Invalidation Triggers:**
- `original_url` changed
- `code` changed
- `status` changed
- Model deleted

---

## ADR-006: Redirect HTTP Status

**Status:** Accepted

**Context:**
Which HTTP status code for redirects: 301, 302, 307, 308?

**Decision:**
Use **301 Moved Permanently** for active URLs.

**Rationale:**
- Browser caches redirect (faster subsequent visits)
- SEO: Passes link juice to destination
- Standard for URL shorteners

**Special Cases:**
- 302 for temporary/expiring URLs (Phase 2)
- 404 for non-existent codes
- 410 Gone for deleted URLs (optional)

---

## ADR-007: Database Choice

**Status:** Accepted

**Context:**
Which database for development and production?

**Decision:**
- **Development:** SQLite (zero config, portable)
- **Testing:** SQLite in-memory
- **Production:** PostgreSQL (robust, scalable)

**Consequences:**
- ✅ Easy local development
- ✅ Fast test execution
- ✅ Production-ready database
- ❌ Must test PostgreSQL-specific features separately

---

## ADR-008: Internationalization Strategy

**Status:** Accepted

**Context:**
UI must support Spanish and English.

**Decision:**
- Use Laravel's built-in translation system
- Feature-specific language files
- Default locale: Spanish (`es`)
- Fallback locale: English (`en`)

**Structure:**
```
lang/
├── es/
│   ├── shortener.php      # Feature-specific
│   └── validation.php     # Override Laravel
└── en/
    ├── shortener.php
    └── validation.php
```

---

## ADR-009: DTOs for Data Transfer

**Status:** Accepted

**Context:**
Need type-safe way to pass data between layers.

**Decision:**
Use readonly DTOs with static constructors.

**Example:**
```php
final readonly class CreateUrlData
{
    public function __construct(
        public string $originalUrl,
        public ?string $customCode = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            originalUrl: $request->validated('original_url'),
            customCode: $request->validated('custom_code'),
        );
    }
}
```

---

## ADR-010: Error Handling Strategy

**Status:** Accepted

**Context:**
How to handle and communicate errors.

**Decision:**
- Custom exceptions for domain errors
- Exception handler for HTTP responses
- Translated error messages

**Exception Hierarchy:**
```
ShortenerException (abstract)
├── InvalidUrlException
├── CodeAlreadyExistsException
├── UrlNotFoundException
├── UrlExpiredException
└── UrlInactiveException
```

---

## Future Considerations

### For Phase 2 (API)
- ADR-011: API Versioning Strategy
- ADR-012: Rate Limiting Configuration
- ADR-013: Authentication Method (Sanctum)

### For Phase 3 (Analytics)
- ADR-014: Event Sourcing for Clicks
- ADR-015: Async Processing (Queues)
- ADR-016: Time-Series Data Storage

---

## Template for New ADRs

```markdown
## ADR-XXX: [Title]

**Status:** [Proposed | Accepted | Deprecated | Superseded]

**Context:**
[Why is this decision needed?]

**Decision:**
[What is the decision?]

**Consequences:**
- ✅ [Positive]
- ❌ [Negative]
```

---
