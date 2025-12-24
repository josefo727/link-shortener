# 🔗 URL Shortener - Project Specification

## Overview

Personal URL shortener built with **Laravel 12** and **Filament 4**, designed with scalability, clean architecture, and best practices in mind.

**Language conventions:**
- Code, comments, commits, and documentation: **English**
- User interface and user-facing messages: **Spanish** (with i18n support for English)

---

## 🎯 Project Goals

### Phase 1 (Current Scope)
- Filament admin panel with CRUD for shortened URLs
- URL validation and unique code generation
- Redirect functionality via `{APP_URL}/{code}`
- Redis caching for visited links (1 week TTL)
- Full test coverage with TDD approach

### Phase 2 (Future)
- QR Code generation for each shortened URL
- Public API with authentication (Laravel Sanctum)
- Usage analytics and statistics

### Phase 3 (Future)
- Custom slugs/codes
- Link expiration dates
- Click tracking with geolocation
- Bulk URL shortening

---

## 🏗️ Architecture & Patterns

### Directory Structure

```
app/
├── Actions/                    # Single-purpose action classes
│   └── Url/
│       ├── CreateShortUrlAction.php
│       ├── UpdateShortUrlAction.php
│       ├── DeleteShortUrlAction.php
│       └── ResolveShortUrlAction.php
├── Contracts/                  # Interfaces
│   ├── UrlShortenerInterface.php
│   ├── CodeGeneratorInterface.php
│   └── CacheServiceInterface.php
├── DataTransferObjects/        # DTOs for data passing
│   └── Url/
│       ├── CreateUrlData.php
│       └── UpdateUrlData.php
├── Enums/                      # PHP 8.1+ Enums
│   └── UrlStatus.php
├── Events/                     # Domain events
│   └── Url/
│       ├── UrlCreated.php
│       ├── UrlAccessed.php
│       └── UrlUpdated.php
├── Exceptions/                 # Custom exceptions
│   └── Url/
│       ├── InvalidUrlException.php
│       ├── CodeAlreadyExistsException.php
│       └── UrlNotFoundException.php
├── Filament/
│   └── Resources/
│       └── ShortUrlResource/
├── Http/
│   └── Controllers/
│       └── RedirectController.php
├── Listeners/                  # Event listeners
│   └── Url/
│       └── InvalidateCacheOnUrlChange.php
├── Models/
│   └── ShortUrl.php
├── Observers/                  # Model observers
│   └── ShortUrlObserver.php
├── Policies/                   # Authorization
│   └── ShortUrlPolicy.php
├── Providers/
│   └── UrlShortenerServiceProvider.php
├── Repositories/               # Repository pattern (optional, for complex queries)
│   ├── Contracts/
│   │   └── ShortUrlRepositoryInterface.php
│   └── EloquentShortUrlRepository.php
└── Services/
    ├── CodeGeneratorService.php
    ├── UrlValidatorService.php
    └── CacheService.php

config/
└── shortener.php               # Custom configuration

database/
├── factories/
│   └── ShortUrlFactory.php
├── migrations/
│   └── xxxx_create_short_urls_table.php
└── seeders/
    └── ShortUrlSeeder.php

docs/                           # Project documentation
├── PROGRESS.md                 # Development progress tracking
├── NEXT_STEPS.md               # Upcoming tasks
├── ARCHITECTURE.md             # Architecture decisions
├── API.md                      # API documentation (Phase 2)
└── CHANGELOG.md                # Version changelog

lang/
├── en/
│   ├── shortener.php
│   ├── validation.php
│   └── filament/
│       └── short-url.php
└── es/
    ├── shortener.php
    ├── validation.php
    └── filament/
        └── short-url.php

tests/
├── Feature/
│   ├── Http/
│   │   └── RedirectControllerTest.php
│   ├── Filament/
│   │   └── ShortUrlResourceTest.php
│   └── Actions/
│       ├── CreateShortUrlActionTest.php
│       └── ResolveShortUrlActionTest.php
└── Unit/
    ├── Services/
    │   ├── CodeGeneratorServiceTest.php
    │   ├── UrlValidatorServiceTest.php
    │   └── CacheServiceTest.php
    ├── Models/
    │   └── ShortUrlTest.php
    └── DTOs/
        └── CreateUrlDataTest.php
```

---

## 💾 Database Design

### Table: `short_urls`

```php
Schema::create('short_urls', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();                    // Public identifier
    $table->string('code', 10)->unique();              // Short code (indexed)
    $table->text('original_url');                      // Destination URL
    $table->string('original_url_hash', 64);           // SHA-256 for fast lookups
    $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
    $table->unsignedBigInteger('clicks')->default(0);  // Click counter
    $table->timestamp('expires_at')->nullable();       // Future: expiration
    $table->timestamp('last_accessed_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    // Indexes for optimization
    $table->index('code');                             // Primary lookup
    $table->index('original_url_hash');                // Find by destination
    $table->index('status');                           // Filter by status
    $table->index(['status', 'expires_at']);           // Composite for active/expired
    $table->index('created_at');                       // Sorting/filtering
});
```

### Index Strategy
- `code`: B-tree unique index for O(log n) redirect lookups
- `original_url_hash`: SHA-256 hash for fast duplicate detection
- Composite indexes for common query patterns

---

## 🔴 Redis Caching Strategy

### Cache Configuration

```php
// config/shortener.php
return [
    'cache' => [
        'enabled' => env('SHORTENER_CACHE_ENABLED', true),
        'prefix' => 'shorturl',
        'ttl' => env('SHORTENER_CACHE_TTL', 604800), // 1 week in seconds
    ],
    'code' => [
        'length' => env('SHORTENER_CODE_LENGTH', 6),
        'alphabet' => env('SHORTENER_CODE_ALPHABET', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
        'max_attempts' => env('SHORTENER_CODE_MAX_ATTEMPTS', 10),
    ],
];
```

### Cache Keys Structure
```
shorturl:code:{code}          -> Full ShortUrl model (JSON)
shorturl:stats:{code}         -> Access statistics
shorturl:hash:{hash}          -> Code lookup by URL hash
```

### Cache Invalidation Rules (via Observer)
- **On Update**: Clear cache if `code` or `original_url` changed
- **On Delete**: Clear all related cache keys
- **On Access**: Update `last_accessed_at` (debounced)

---

## ✅ Quality Assurance

### Static Analysis Configuration

**PHPStan** (`phpstan.neon`):
```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - vendor/phpstan/phpstan-strict-rules/rules.neon

parameters:
    level: 9
    paths:
        - app
        - config
        - database
        - routes
        - tests
    excludePaths:
        - vendor
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    reportUnmatchedIgnoredErrors: true
```

**Laravel Pint** (`pint.json`):
```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "final_class": true,
        "global_namespace_import": {
            "import_classes": true,
            "import_constants": true,
            "import_functions": true
        },
        "ordered_class_elements": {
            "order": [
                "use_trait",
                "case",
                "constant_public",
                "constant_protected",
                "constant_private",
                "property_public",
                "property_protected",
                "property_private",
                "construct",
                "destruct",
                "magic",
                "phpunit",
                "method_public",
                "method_protected",
                "method_private"
            ]
        },
        "php_unit_method_casing": {
            "case": "snake_case"
        },
        "strict_comparison": true,
        "void_return": true
    }
}
```

**PHP_CodeSniffer** (`phpcs.xml`):
```xml
<?xml version="1.0"?>
<ruleset name="URL Shortener">
    <description>Coding standards for URL Shortener</description>
    
    <file>app</file>
    <file>config</file>
    <file>database</file>
    <file>routes</file>
    <file>tests</file>
    
    <exclude-pattern>vendor/*</exclude-pattern>
    <exclude-pattern>bootstrap/*</exclude-pattern>
    <exclude-pattern>storage/*</exclude-pattern>
    
    <rule ref="PSR12"/>
    <rule ref="Generic.Files.LineLength">
        <properties>
            <property name="lineLimit" value="120"/>
            <property name="absoluteLineLimit" value="150"/>
        </properties>
    </rule>
</ruleset>
```

### Git Hooks (via Husky/Captain Hook)

**Pre-commit**:
```bash
#!/bin/bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/phpcs
```

**Pre-push**:
```bash
#!/bin/bash
php artisan test --parallel
```

---

## 🧪 TDD Approach

### Test Categories

1. **Unit Tests** - Isolated, no database/external services
    - Services (CodeGenerator, UrlValidator, Cache)
    - DTOs
    - Value Objects
    - Helpers

2. **Feature Tests** - Integration with framework
    - HTTP endpoints (redirect)
    - Filament resources (CRUD operations)
    - Actions
    - Events/Listeners

3. **Architecture Tests** - Code structure validation
    - Strict types in all files
    - Final classes where appropriate
    - No debugging statements
    - Proper namespacing

### Test Examples

```php
// tests/Unit/Services/CodeGeneratorServiceTest.php
it('generates a code with correct length', function () {
    $generator = new CodeGeneratorService();
    
    $code = $generator->generate();
    
    expect($code)->toHaveLength(6);
});

it('generates unique codes', function () {
    $generator = new CodeGeneratorService();
    
    $codes = collect(range(1, 100))->map(fn () => $generator->generate());
    
    expect($codes->unique())->toHaveCount(100);
});

// tests/Feature/Http/RedirectControllerTest.php
it('redirects to original url when code exists', function () {
    $shortUrl = ShortUrl::factory()->create([
        'code' => 'abc123',
        'original_url' => 'https://example.com/long-url',
    ]);

    $this->get('/abc123')
        ->assertRedirect('https://example.com/long-url')
        ->assertStatus(301);
});

it('returns 404 for non-existent code', function () {
    $this->get('/nonexistent')
        ->assertNotFound();
});

it('caches the url on first access', function () {
    Cache::spy();
    
    $shortUrl = ShortUrl::factory()->create(['code' => 'abc123']);

    $this->get('/abc123');

    Cache::shouldHaveReceived('put')
        ->with("shorturl:code:abc123", Mockery::any(), Mockery::any());
});

// tests/Feature/Filament/ShortUrlResourceTest.php
it('can create a short url from filament panel', function () {
    $admin = User::factory()->create();
    
    $this->actingAs($admin);
    
    Livewire::test(CreateShortUrl::class)
        ->fillForm([
            'original_url' => 'https://example.com/very-long-url',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('short_urls', [
        'original_url' => 'https://example.com/very-long-url',
    ]);
});

it('validates url format', function () {
    $admin = User::factory()->create();
    
    $this->actingAs($admin);
    
    Livewire::test(CreateShortUrl::class)
        ->fillForm([
            'original_url' => 'not-a-valid-url',
        ])
        ->call('create')
        ->assertHasFormErrors(['original_url' => 'url']);
});
```

---

## 🌐 Internationalization

### Language Files Structure

```php
// lang/es/shortener.php
return [
    'resource' => [
        'label' => 'Enlace Corto',
        'plural_label' => 'Enlaces Cortos',
        'navigation_label' => 'Acortador de URLs',
        'navigation_group' => 'Gestión',
    ],
    'fields' => [
        'original_url' => 'URL Original',
        'original_url_helper' => 'Ingresa la URL que deseas acortar',
        'code' => 'Código',
        'short_url' => 'URL Corta',
        'status' => 'Estado',
        'clicks' => 'Clics',
        'created_at' => 'Creado',
        'last_accessed_at' => 'Último acceso',
    ],
    'status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'expired' => 'Expirado',
    ],
    'actions' => [
        'copy' => 'Copiar enlace',
        'copied' => 'Enlace copiado al portapapeles',
        'visit' => 'Visitar',
        'qr_code' => 'Generar QR',
    ],
    'messages' => [
        'created' => 'Enlace corto creado exitosamente',
        'updated' => 'Enlace corto actualizado',
        'deleted' => 'Enlace corto eliminado',
        'url_not_found' => 'El enlace solicitado no existe',
        'url_expired' => 'Este enlace ha expirado',
        'url_inactive' => 'Este enlace está inactivo',
    ],
    'notifications' => [
        'cache_cleared' => 'Caché del enlace limpiada',
    ],
];

// lang/en/shortener.php
return [
    'resource' => [
        'label' => 'Short URL',
        'plural_label' => 'Short URLs',
        'navigation_label' => 'URL Shortener',
        'navigation_group' => 'Management',
    ],
    'fields' => [
        'original_url' => 'Original URL',
        'original_url_helper' => 'Enter the URL you want to shorten',
        'code' => 'Code',
        'short_url' => 'Short URL',
        'status' => 'Status',
        'clicks' => 'Clicks',
        'created_at' => 'Created',
        'last_accessed_at' => 'Last accessed',
    ],
    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'expired' => 'Expired',
    ],
    'actions' => [
        'copy' => 'Copy link',
        'copied' => 'Link copied to clipboard',
        'visit' => 'Visit',
        'qr_code' => 'Generate QR',
    ],
    'messages' => [
        'created' => 'Short URL created successfully',
        'updated' => 'Short URL updated',
        'deleted' => 'Short URL deleted',
        'url_not_found' => 'The requested link does not exist',
        'url_expired' => 'This link has expired',
        'url_inactive' => 'This link is inactive',
    ],
    'notifications' => [
        'cache_cleared' => 'Link cache cleared',
    ],
];
```

---

## 🔧 Environment Configuration

### Required `.env` Variables

```env
# Application
APP_URL=https://short.example.com

# Database (PostgreSQL recommended for production)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=url_shortener
DB_USERNAME=shortener
DB_PASSWORD=secret

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# URL Shortener Config
SHORTENER_CACHE_ENABLED=true
SHORTENER_CACHE_TTL=604800
SHORTENER_CODE_LENGTH=6

# Filament
FILAMENT_FILESYSTEM_DISK=local
```

---

## 📋 Development Workflow

### Composer Scripts

```json
{
    "scripts": {
        "lint": "./vendor/bin/pint",
        "lint:test": "./vendor/bin/pint --test",
        "analyse": "./vendor/bin/phpstan analyse",
        "cs": "./vendor/bin/phpcs",
        "cs:fix": "./vendor/bin/phpcbf",
        "test": "php artisan test",
        "test:coverage": "php artisan test --coverage --min=80",
        "test:parallel": "php artisan test --parallel",
        "quality": [
            "@lint:test",
            "@analyse",
            "@cs"
        ],
        "ci": [
            "@quality",
            "@test:coverage"
        ]
    }
}
```

### Development Commands

```bash
# Fresh setup
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan filament:install

# Quality checks
composer quality      # Run all linters
composer test         # Run tests
composer ci           # Full CI pipeline

# Development
php artisan serve     # Start server
php artisan test --filter=ShortUrl  # Run specific tests
```

---

## 📝 Implementation Order

Follow this order for TDD development:

### Step 1: Project Foundation
1. [ ] Create Laravel 12 project
2. [ ] Configure database (SQLite for dev, PostgreSQL for prod)
3. [ ] Configure Redis
4. [ ] Install and configure Filament 4
5. [ ] Setup quality tools (Pint, PHPStan, PHPCS)
6. [ ] Create config file `config/shortener.php`
7. [ ] Create documentation structure in `docs/`

### Step 2: Core Domain (TDD)
1. [ ] Write tests for `CodeGeneratorService`
2. [ ] Implement `CodeGeneratorService`
3. [ ] Write tests for `UrlValidatorService`
4. [ ] Implement `UrlValidatorService`
5. [ ] Create DTOs (`CreateUrlData`, `UpdateUrlData`)
6. [ ] Create `UrlStatus` enum

### Step 3: Database Layer
1. [ ] Create migration with proper indexes
2. [ ] Create `ShortUrl` model with casts and relationships
3. [ ] Create `ShortUrlFactory`
4. [ ] Write model unit tests

### Step 4: Cache Layer (TDD)
1. [ ] Write tests for `CacheService`
2. [ ] Implement `CacheService`
3. [ ] Create `ShortUrlObserver` for cache invalidation
4. [ ] Write observer tests

### Step 5: Actions (TDD)
1. [ ] Write tests for `CreateShortUrlAction`
2. [ ] Implement `CreateShortUrlAction`
3. [ ] Write tests for `ResolveShortUrlAction`
4. [ ] Implement `ResolveShortUrlAction`
5. [ ] Write tests for `UpdateShortUrlAction`
6. [ ] Implement `UpdateShortUrlAction`

### Step 6: HTTP Layer (TDD)
1. [ ] Write tests for redirect endpoint
2. [ ] Create `RedirectController`
3. [ ] Configure routes
4. [ ] Write 404/expired/inactive handling tests

### Step 7: Filament Panel
1. [ ] Create `ShortUrlResource`
2. [ ] Configure form with validation
3. [ ] Configure table with filters
4. [ ] Add copy-to-clipboard action
5. [ ] Write Filament tests
6. [ ] Apply translations

### Step 8: Internationalization
1. [ ] Create language files (es, en)
2. [ ] Apply translations to Filament resource
3. [ ] Apply translations to error messages
4. [ ] Configure locale detection

### Step 9: Documentation
1. [ ] Update PROGRESS.md
2. [ ] Write ARCHITECTURE.md
3. [ ] Prepare CHANGELOG.md

---

## 🚨 Important Constraints

1. **Strict Types**: All PHP files must declare `strict_types=1`
2. **Final Classes**: Use `final` for classes not intended for inheritance
3. **Readonly Properties**: Use `readonly` for immutable properties
4. **Return Types**: All methods must have explicit return types
5. **PHPStan Level 9**: Code must pass highest static analysis level
6. **Test Coverage**: Minimum 80% code coverage
7. **No Magic Strings**: Use constants, enums, or config
8. **Fail Fast**: Validate early, throw exceptions for invalid states

---

## 🔐 Security Considerations

1. **URL Validation**: Sanitize and validate all URLs
2. **Rate Limiting**: Apply rate limits to redirect endpoint
3. **Code Generation**: Use cryptographically secure random generation
4. **SQL Injection**: Use parameterized queries (Eloquent handles this)
5. **XSS**: Escape all output (Blade handles this)
6. **HTTPS**: Enforce HTTPS in production

---

## 📊 Future Metrics (Phase 2+)

For analytics preparation, consider these future fields:
- User agent tracking
- Referrer tracking
- Geographic location (IP-based)
- Device type detection
- UTM parameters preservation

---

*Document version: 1.0.0*
*Last updated: Generated for Claude Code*
