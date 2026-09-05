# Research — 001-postgresql-compatibility

## `laravel/framework@13.x docs (applies to 12.x — no breaking change on the areas touched)`

- **captured:** 2026-09-05
- **source:** context7, libraryId `/laravel/docs`
- **why consulted:** confirm `sslmode` is a real, recognized `pgsql` connection option and check
  the current shape of migration lifecycle events, before touching `config/database.php` and
  `AppServiceProvider::allowDisabledPk()`.

### Relevant API shape

```php
// config/database.php, pgsql connection (Laravel's own docs example uses sslmode
// as a first-class option, e.g. under `direct` for pooled setups):
'pgsql' => [
    'driver' => 'pgsql',
    // ...
    'sslmode' => env('DB_SSLMODE', 'prefer'),
],
```

```
Migrations > Events — Laravel dispatches MigrationsStarted / MigrationsEnded (and others),
all extending the base MigrationEvent class, as lifecycle hooks around migration runs.
```

### Gotchas / rate limits / versioning

- Context7's Laravel docs are indexed at 10.x / 13.x / 6.x branches — no 12.x branch exists in
  the index. The areas touched here (`sslmode` as a connection option, the existence and naming
  of `MigrationsStarted`/`MigrationsEnded`) are unchanged across 10.x–13.x, and the project's own
  code already uses these exact class names successfully today (`app/Providers/AppServiceProvider.php`),
  which is stronger evidence than the docs for this specific version.
- No evidence found (docs or otherwise) that `sslmode` behaves differently across driver
  versions relevant here — treated as a stable, first-class PDO pgsql connection parameter.

### Decision impact

- Ties to `plan.md` §Stack decision / Data model: confirms `'sslmode' => env('DB_SSLMODE', 'prefer')`
  is the correct, idiomatic fix for blocker 2 — no custom connector needed.
- Ties to `plan.md` §Error model: confirms `MigrationsStarted`/`MigrationsEnded` are the right
  events to guard in `allowDisabledPk()` — the fix is scoping the existing listeners by driver,
  not switching to a different event.

---

## `webdevops/php-nginx (Docker base image, 8.4-alpine tag)`

- **captured:** 2026-09-05
- **source:** WebFetch (`https://github.com/webdevops/Dockerfile`,
  `https://dockerfile.readthedocs.io/en/latest/content/DockerImages/dockerfiles/php-nginx.html`)
  + WebSearch (context7 does not index this image).
- **why consulted:** confirm the `PHP_DISMOD` mechanism (blocker 1) and check whether `pcov` is
  available in this base image or needs a separate install step.

### Relevant API shape

```
PHP_DISMOD=<comma-separated module list>   # disables listed modules; the project's own
                                            # Dockerfile already lists pdo_pgsql,pgsql here,
                                            # which confirms both are bundled in the image and
                                            # merely turned off — removing them from the list is
                                            # the fix for blocker 1.
```

### Gotchas / rate limits / versioning

- The image's documentation (GitHub README + readthedocs mirror) does not enumerate bundled
  extensions or confirm whether `pcov` ships pre-installed. **This is a genuine open technical
  question, not a business decision** — it does not need a `[NEEDS CLARIFICATION]` marker in the
  spec (already resolved: pcov is in scope, clarify Q1) but does need an empirical check during
  `implement`.
- Community sources (webdevops/Dockerfile issue #184, general PHP coverage articles) confirm the
  common pattern is "disable Xdebug, install PCOV" for speed, and that PCOV requires Xdebug to be
  fully disabled to take effect — relevant since this image's `-dev` variants ship Xdebug, but the
  project's `Dockerfile` uses the non-`-dev` `php-nginx` base, so this conflict is unlikely to
  apply, but worth confirming once the image is actually built.

### Decision impact

- Ties to `plan.md` §Test strategy: the first implementation task for the pcov install step is a
  **spike** (build the image, run `php -m`, confirm `pcov` is listed) rather than a task that
  assumes a specific install command up front. If the base image doesn't bundle it, the fallback
  is `apk add --no-cache $PHPIZE_DEPS && pecl install pcov && docker-php-ext-enable pcov` (standard
  PECL install pattern), verified the same way.
