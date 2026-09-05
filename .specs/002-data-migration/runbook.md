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

## Rehearsal — PENDING, not yet run (per clarify Q1/Q2)

This feature (`002-data-migration`) was built and verified entirely against local fixtures — a
second SQLite connection standing in for `legacy`, and Feature 001's local `pgsql` compose
service for the PostgreSQL-specific checks (ADR 0004). **Nothing in this repository's automated
suite has ever connected to the real MySQL source or the real PostgreSQL target.**

The rehearsal below is the first time that happens, and it is a separate, later,
explicitly-triggered step — not something this feature's `implement`/`verify` phases did, and not
something to run without the owner's go-ahead at that moment.

Planned rehearsal steps, to run via SSH on `vps_josefo_01` in a throwaway container (never from a
local development session):

```bash
RUN="docker run --rm --network nginx-proxy_proxy-network -v /root/link-shortener-migration/app:/app -w /app josefo727/josefo-link"

# Migration environment lives at /root/link-shortener-migration/.env (mode 600, outside the
# container bind mount), holding both DB_* (target) and LEGACY_DB_* (source) — mirroring the
# blog's equivalent migration environment.

$RUN php artisan migrate --force
$RUN php artisan db:copy-from-legacy
$RUN php artisan db:verify-copy ; echo "exit=$?"

# Guard check (repeatability):
$RUN php artisan db:copy-from-legacy   # should refuse -- target already holds rows
$RUN php artisan db:copy-from-legacy --truncate
$RUN php artisan db:verify-copy ; echo "exit=$?"
```

**Evidence to fill in here once the rehearsal actually runs** (do not fabricate ahead of time):
- Row counts copied (expected: 1 user, 35 short_urls, 1 personal_access_tokens — 37 rows total,
  per `prompt-db-migration.md` §5, measured at write-time — may drift before the real cutover).
- `db:verify-copy` exit code and per-table report.
- Measured wall-clock time for the copy and the verification (37 rows — expected to be well
  under a second, unlike the blog's ~2s/1.86s at ~2,000 rows).
- Spot checks: a known `short_urls.created_at` value on both sides (same wall-clock string, no
  timezone shift — both source and target run `UTC`, per research.md); the single
  `personal_access_tokens` row's `token` value unchanged.

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
