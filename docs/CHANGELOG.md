# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- REST API with Laravel Sanctum authentication
  - `POST /api/urls` - Create short URL (returns existing if URL exists)
  - `PUT /api/urls/{code}` - Update URL and/or title
  - `DELETE /api/urls/{code}` - Soft delete URL
- API token management command: `php artisan api:token:create`
- Rate limiting (60 requests/minute per user)
- Support for `expires_at` field in API creation

### Changed
- Updated User model with HasApiTokens trait for Sanctum

---

## [0.1.0] - 2025-12-31

### Added
- Initial project specification (prompt.md)
- Documentation structure
- Development workflow definition
- Core URL shortening functionality
- Filament admin panel with CRUD
- Redis caching layer
- Internationalization (ES/EN)

---

## Version History

_Versions will be added as development progresses_

### [0.1.0] - Pending
**Phase 1 MVP**
- Basic URL shortening
- Filament CRUD panel
- Redis caching
- Multi-language support

---

## Release Notes Template

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Added
- New features

### Changed
- Changes in existing functionality

### Deprecated
- Soon-to-be removed features

### Removed
- Removed features

### Fixed
- Bug fixes

### Security
- Vulnerability fixes
```

---
