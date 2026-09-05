# Research — 002-data-migration

## Schema profile of the three business tables (measured, not assumed)

- **captured:** 2026-09-05
- **source:** direct inspection of `database/migrations/0001_01_01_000000_create_users_table.php`,
  `2025_12_24_230813_create_short_urls_table.php`,
  `2025_12_31_021245_create_personal_access_tokens_table.php` in this repo.

### Findings

```
users:                   id, name, email, email_verified_at (nullable timestamp), password,
                         remember_token, timestamps. No FKs.
short_urls:              id, code, title, original_url (text), original_url_hash, status
                         (varchar, default 'active' — cast to UrlStatus enum in Eloquent, not a
                         native DB enum), clicks (unsigned bigint), expires_at (nullable
                         timestamp), timestamps, deleted_at (soft delete). No FKs.
personal_access_tokens:  id, tokenable_type + tokenable_id (via morphs(), bigint — no FK
                         constraint, just an index), name (text), token (unique), abilities
                         (nullable text), last_used_at, expires_at, timestamps. No FKs.
```

- Every primary key is a plain auto-increment `id()` (bigint unsigned). No keyless tables, no
  composite keys — unlike the blog's `post_tag`/`role_has_permissions`, ordering and chunking can
  always rely on `id`.
- No JSON columns, no boolean columns at all in these three tables (so no MySQL tinyint(1) →
  PostgreSQL boolean coercion question to resolve — confirmed by reading the migrations, not just
  asserted).
- No native DB enum type anywhere — `short_urls.status` is a plain varchar, portable as-is.
- `APP_TIMEZONE` is unset in `.env`; `config/app.php` defaults `'timezone' => 'UTC'`. Both the
  legacy MySQL source and the target PostgreSQL server therefore see UTC wall-clock values with
  no session-timezone conversion in play — simpler than the blog's case
  (`America/Bogota` in production), which needed to rule out a 5-hour shift.

### Decision impact

- Ties to `plan.md` §Data model: the manifest is `users, short_urls, personal_access_tokens` —
  `users` first (semantic parent of `personal_access_tokens`, though not FK-enforced),
  `short_urls` has no dependency either way.
- Ties to `plan.md` §Test strategy: no `RowNormalizer`-equivalent is needed (unlike the blog) —
  there is no boolean, JSON, or enum coercion to normalize between the two engines for these
  three tables specifically. Timestamps are copied as the driver returns them, no Carbon parsing,
  matching the blog's same conclusion for a different reason (there: same declared app timezone
  on both sides; here: UTC everywhere, nothing to convert).

---

## laravel/framework@13.x — query builder across two connections, no Eloquent

- **captured:** 2026-09-05
- **source:** context7, libraryId `/laravel/docs`, queries: "chunkById across two connections",
  "raw insert without Eloquent".

### Relevant API shape

```php
DB::connection('legacy')->table('short_urls')->orderBy('id')->chunk($size, function ($rows) {
    // ...
});
DB::connection('pgsql')->table('short_urls')->insert($rows->map(fn ($r) => (array) $r)->all());
```

`chunkById` requires a unique ordering column; since every business table here has a plain `id`
primary key (unlike the blog's keyless/composite-key tables), `chunkById(size, ..., column: 'id')`
applies uniformly to all three tables — no special-casing needed.

### Gotchas / rate limits / versioning

- Context7 indexes Laravel docs at 10.x/13.x/6.x — no 12.x branch, same gap noted in Feature
  001's research. The query builder API used here (`chunkById`, `DB::table()->insert()`) is
  unchanged across these versions.
- **Eloquent must not be used for the copy.** `ShortUrl` has an Observer
  (`ShortUrlObserver`, wired via `#[ObservedBy]`) that writes to the Redis cache on every
  create/update. A copy through the model would populate the cache with 35 rows during a
  rehearsal, which is himself harmless but not what "copy" should mean, and would make the copy
  slower and less deterministic to test. The copy command uses `DB::table()` only, never
  `ShortUrl::create()`/`User::create()`/etc.

### Decision impact

- Ties to `plan.md` §Module layout and §Error model: confirms the query-builder-only approach,
  consistent with the blog's ADR 0004 and with this project's own constitution Article III
  (boundary-only mocks — Database is the boundary; Eloquent's side effects are not a boundary to
  route a data copy through).

---

## PostgreSQL sequence reset after explicit-id inserts

- **captured:** 2026-09-05
- **source:** PostgreSQL's own documented system function (not context7-indexed; this is core
  PostgreSQL SQL, not a library API) — `pg_get_serial_sequence(table, column)` returns the
  sequence name backing a `serial`/`bigserial`/identity column, and `setval(seqname, value)` sets
  its next value.

### Relevant API shape

```sql
SELECT setval(pg_get_serial_sequence('short_urls', 'id'), (SELECT MAX(id) FROM short_urls));
```

### Gotchas

- If a copied table ends up empty (defensive case, not expected for these three tables today —
  all have at least one row per `prompt-db-migration.md` §5), `setval` with a `NULL` max would
  error; the command should skip the reset for a table it copied zero rows into rather than
  assume at least one row always exists.
- This is genuinely PostgreSQL-specific — meaningless against SQLite. The test covering it is
  written to only run under `composer test:pgsql` (Pest's conditional `skip()`), not the default
  suite. Empirical verification (does `setval` actually work as expected here) happens during
  `implement`, against the real local `pgsql` compose service from Feature 001 — not assumed from
  documentation alone.

### Decision impact

- Ties to `plan.md` §Error model and §Test strategy: the sequence-reset test is real (against a
  real local PostgreSQL, not mocked), but is not part of the default SQLite-based suite.
