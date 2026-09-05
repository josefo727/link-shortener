# Runbook — 002 cutover and rollback

Target: managed PostgreSQL 18.6 `link_shortener` as `link_shortener_user`, TLS required
(`DB_SSLMODE=require`).
Source: managed MySQL `link_shortener_jrg` as `doadmin` on the shared
`db-mysql-payments-ya-01-…` cluster (retained 30 days as rollback — see §After the cutover).

Everything below runs on `vps_josefo_01`, directory `~/www/josefo-link`, container
`josefo-link-container`, image `josefo727/josefo-link`, orchestrated by
`~/www/nginx-proxy/docker-compose.yml` (service `josefo-link`, bind mount
`./../josefo-link:/app`). Production Redis is shared across cache, sessions, and queue.

**Precondition, not yet satisfied at the time this runbook was written**: the Docker image must
already be rebuilt and deployed with `pdo_pgsql` enabled (Feature 001), and this feature's commands
merged to `master` and deployed, **while still on MySQL** — this runbook does not rebuild the
image, it assumes that already happened.

---

## Rehearsal evidence (2026-09-05)

Run against the real source and target, via SSH on `vps_josefo_01`, from a throwaway container —
never from a local development session (clarify Q1). The live `josefo-link-container` and
`~/www/josefo-link` were never touched.

**Deviation from the original plan, found at rehearsal time, not assumed away**: the plan above
called for running `docker run ... josefo727/josefo-link` — the production image tag. That image
had **not actually been rebuilt with Feature 001's changes** on the VPS (its container was still
running the pre-`pdo_pgsql` image, and `~/www/josefo-link`'s checkout was still at `5f58995`,
before either feature merged). Rather than rebuild the production tag in place (a separate,
bigger decision than "run the rehearsal"), the rehearsal used a fully isolated setup instead:

```bash
# Fresh clone from GitHub (not the live, locally-modified ~/www/josefo-link checkout):
git clone git@github.com:josefo727/link-shortener.git /root/link-shortener-migration/app
cd /root/link-shortener-migration/app && git log --oneline -1   # a2725cf

# Built with a distinct tag -- never collides with the production tag josefo727/josefo-link:
docker build -t link-shortener-rehearsal:latest .

RUN="docker run --rm --env-file /root/link-shortener-migration/.env -v /root/link-shortener-migration/app:/app -w /app link-shortener-rehearsal:latest"

# Migration environment lives at /root/link-shortener-migration/.env (mode 600, outside any
# container bind mount used by the live application), holding both DB_* (target) and
# LEGACY_DB_* (source). CACHE_STORE/SESSION_DRIVER/QUEUE_CONNECTION deliberately set to
# array/array/sync in that env file -- this rehearsal never touches production Redis.

$RUN php artisan migrate --force
$RUN php artisan db:copy-from-legacy
$RUN php artisan db:verify-copy ; echo "exit=$?"

# Guard check (repeatability):
$RUN php artisan db:copy-from-legacy          # refuses -- target already holds rows
$RUN php artisan db:copy-from-legacy --truncate
$RUN php artisan db:verify-copy ; echo "exit=$?"
```

Output, verbatim:

```
$ php artisan migrate --force
  Creating migration table ...................................... 39.32ms DONE
  Running migrations.
  0001_01_01_000000_create_users_table .......................... 36.02ms DONE
  0001_01_01_000001_create_cache_table .......................... 10.86ms DONE
  0001_01_01_000002_create_jobs_table ........................... 21.61ms DONE
  2025_12_24_230813_create_short_urls_table ..................... 10.27ms DONE
  2025_12_31_021245_create_personal_access_tokens_table ......... 11.83ms DONE

$ php artisan db:show   (confirms target identity before writing anything)
  PostgreSQL 18.6, database link_shortener, host db-postgresql-nyc3-01-…,
  username link_shortener_user, 11 tables, 264.00 KB

$ php artisan db:copy-from-legacy
  users: 1 rows copied
  short_urls: 35 rows copied
  personal_access_tokens: 1 rows copied
  3 tables copied (37 rows)                        -- exactly prompt-db-migration.md §5's count

$ php artisan db:verify-copy ; echo "exit=$?"
  users: match
  short_urls: match
  personal_access_tokens: match
  All tables match.
  exit=0

$ php artisan db:copy-from-legacy          # guard: target not empty
  The target already holds rows in: users, short_urls, personal_access_tokens.
  Nothing was written. Re-run with --truncate to replace them.
  exit=1

$ php artisan db:copy-from-legacy --truncate && php artisan db:verify-copy ; echo "exit=$?"
  3 tables copied (37 rows)
  All tables match.
  exit=0
```

Measured wall clock against the real databases: copy `--truncate` **1.33s**, verification
**1.30s** (mostly the two remote managed-database round trips plus container startup — 37 rows
themselves are instant, as expected, unlike the blog's ~2s/1.86s at ~2,000 rows).

Spot checks on the target after the copy (a standalone script, deleted afterward — not part of
the application):

| Check | Result |
|---|---|
| Sequence reset | `short_urls` max copied id 35; a probe insert got id 36, then deleted — proves criterion 2 for real, not just in the local `pgsql` test |
| Timestamps | `short_urls` id 1 `created_at`: **`2025-12-25 03:49:26`** on both `legacy` and the target — byte-for-byte, no timezone shift (both run UTC, per research.md) |
| Auth token | `personal_access_tokens` row: `name` "bajo-la-lupa", `tokenable_id` 1 — unchanged, and this is the exact row that makes the API smoke check (step 9 below) possible |
| Final target state | 1 user, 35 short_urls, 1 personal_access_tokens — exactly matches the source |

No defect surfaced. Unlike the blog's rehearsal (which found a real bug in `db:verify-copy`),
this project's local-fixture-based `implement`/`verify` phases already caught everything —
consistent with research.md's schema-profile conclusion that these three tables carry none of
the portability traps the blog's 23-table schema had.

The migration environment (`/root/link-shortener-migration/`) is left in place for the actual
cutover, not deleted after this rehearsal — see §After the cutover for its eventual cleanup.

**Still outstanding before a real cutover can run**: the production image itself still needs
Feature 001's changes rebuilt and deployed to `josefo-link-container` (this rehearsal
deliberately did not do that — it's a separate decision, not a rehearsal step), and
`~/www/josefo-link` still needs to be brought up to `master`.

---

## Cutover

Downtime budget: kept small deliberately, since this is 37 rows — the copy and verification
should take a small fraction of a second once rehearsed. Steps 1–8 are the closed window.

0. **Stop the queue workers first.** Supervisor runs `queue-worker` as a **group** — stopping the
   bare name does nothing (confirmed the hard way in a past migration, per
   `prompt-db-migration.md` §9):
   ```bash
   docker exec josefo-link-container supervisorctl stop 'queue-worker:*' scheduler
   ```
1. **Announce and freeze.** No admin edits (Filament panel, API writes) from now until step 8.
2. **Refresh the code on the VPS.**
   ```bash
   cd ~/www/josefo-link && git fetch origin && git status --short
   ```
   The working tree must be clean, on `master`, with Feature 002 merged.
3. **Take the site down.**
   ```bash
   docker exec josefo-link-container php artisan down
   ```
4. **Final copy**, into the already-migrated PostgreSQL schema:
   ```bash
   docker exec josefo-link-container php artisan db:copy-from-legacy --truncate
   ```
   Expect `3 tables copied` and a row total at least as large as the rehearsal's 37.
5. **Verify. This is the gate.**
   ```bash
   docker exec josefo-link-container php artisan db:verify-copy ; echo "exit=$?"
   ```
   `exit=0` continues. **Anything else stops the cutover** — go to §Rollback and investigate.
   Never proceed on a non-zero exit.
6. **Switch the application to the target.** In `~/www/josefo-link/.env`, keep the current MySQL
   values commented in place as `LEGACY_DB_*` (so rollback and any future verification stay one
   edit away), then set:
   ```
   DB_CONNECTION=pgsql
   DB_HOST=db-postgresql-nyc3-01-do-user-4408101-0.g.db.ondigitalocean.com
   DB_PORT=25060
   DB_DATABASE=link_shortener
   DB_USERNAME=link_shortener_user
   DB_PASSWORD=<the provisioned password — never in this file, never in a commit>
   DB_SSLMODE=require
   ALLOW_DISABLED_PK=false
   ```
7. **Reload configuration.**
   ```bash
   docker exec josefo-link-container php artisan config:cache
   docker exec josefo-link-container php artisan db:show | head -5   # must say pgsql / link_shortener
   docker exec josefo-link-container supervisorctl start 'queue-worker:*' scheduler
   ```

7b. **Delete this application's own Redis keys. Do not skip this — this is the actual reason
    this feature's spec calls out the caching risk (`prompt-db-migration.md` §7).**

   `CacheService` caches the full `ShortUrl` Eloquent model in Redis with a **one-week TTL**, and
   `ResolveShortUrlAction::execute()` calls `$shortUrl->incrementClicks()` — a *write* — on
   whatever comes back from that cache. A cached model carries the name of the connection it was
   loaded from; after the switch, `mysql` still resolves as a connection name, but nothing in the
   app uses it anymore for new lookups, while any *already-cached* model instance still thinks it
   belongs to a connection whose underlying config no longer points anywhere sensible for it.
   Leaving stale entries risks exactly the class of outage the blog hit on its own equivalent
   cutover (~10 minutes of 500s/504s) — for a different reason there (stale connection resolution)
   but the same root cause (a cached Eloquent model surviving a database switch).

   **Never run `php artisan cache:clear` here.** Laravel's Redis cache store implements
   `flush()` as `FLUSHDB`, and this Redis instance is shared with five other applications' cache,
   sessions, and queues. Delete only this application's keys, scoped by its own ACL user:

   ```bash
   PW=$(grep -E '^REDIS_PASSWORD=' ~/www/josefo-link/.env | cut -d= -f2-)
   R="docker exec nginx-proxy-redis-1 redis-cli --no-auth-warning --user josefo-link -a $PW"
   for db in 0 1; do
     $R -n $db --scan --pattern 'link-shortener-database-*' | while read -r k; do
       [ -n "$k" ] && $R -n $db del "$k" > /dev/null
     done
   done
   ```
   The Redis ACL for `josefo-link` (`~/www/nginx-proxy/redis/users.acl`) is restricted to this
   app's own key pattern, so this command cannot reach another application's keys even by
   mistake. Sessions live under the same prefix and are intentionally cleared too (forces
   re-login to `/admin`, an acceptable one-time cost).

8. **Bring the site up.**
   ```bash
   docker exec josefo-link-container php artisan up
   ```
9. **Post-cutover smoke checks** (spec criterion 7):

   | Check | Expected |
   |---|---|
   | `curl -o /dev/null -w '%{http_code}' -L https://josefo.link/` | 200 |
   | A real short-code redirect | 301 to the target URL |
   | That code's `clicks` column on PostgreSQL, before and after the redirect | incremented by 1 — proves the redirect hot path is writing to the new database, not a stale cache |
   | Admin login at `/admin` | session works, short URL list renders |
   | One authenticated API call (`GET`/`POST` `/api/urls` with the migrated token) | succeeds — the single `personal_access_tokens` row is what makes this work, so it proves that table's copy specifically |
   | `docker exec josefo-link-container tail -20 storage/logs/laravel.log` | no database errors |

10. **Rollback** (any step above failing) — see below.

---

## Rollback

Restore the previous `DB_*` block in `~/www/josefo-link/.env` (the `LEGACY_DB_*` values kept in
step 6), then:
```bash
docker exec josefo-link-container php artisan config:cache
docker exec josefo-link-container supervisorctl start 'queue-worker:*' scheduler
docker exec josefo-link-container php artisan up
```
The legacy MySQL database was never written to (criterion 6, enforced by construction — the copy
command only ever reads from `legacy`), so this fully restores the pre-cutover state. Then run a
manual smoke check against MySQL: the landing page, a real redirect, admin login — there is no
automated MySQL test coverage to lean on once the cutover has begun.

---

## Do not (carried directly from `prompt-db-migration.md` §12)

- Do not run `php artisan cache:clear` (§7b above).
- Do not run `migrate:fresh` or `db:wipe` against anything.
- Do not write to the MySQL source at any point — it is the rollback for 30 days.
- Do not run anything against the other schemas on the shared payments cluster. The `doadmin`
  credential reaches them; this migration exists to end that exposure, not extend it.

---

## After the cutover

- Day 0: keep the legacy MySQL database and its credentials untouched.
- Day 30 from the actual cutover date (fill in once it happens): decommission the legacy MySQL
  database and remove `LEGACY_DB_*` from the production `.env`. Record the decommission in
  `JOURNAL.md`.
- Delete the VPS migration environment directory (env file with credentials, rehearsal checkout)
  once the retention window closes.

---

## What actually happened

**PENDING.** This section is filled in only after the real cutover runs — not before, and not
speculatively.
