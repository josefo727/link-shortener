# contract: database-pgsql-connection
# version: 1.0.0
# captured: 2026-09-05
# source: research.md §laravel/framework, config/database.php (existing pgsql block)

## Boundary

Laravel's `pgsql` database connection (`config/database.php`), consumed by every Eloquent model
and query builder call in the app through the default connection resolved from `DB_CONNECTION`.

## Contract

Given the following environment variables are set:

| Variable | Required | Default if unset | Effect |
|---|---|---|---|
| `DB_CONNECTION` | yes (to select this boundary) | `sqlite` | must be `pgsql` to activate this connection |
| `DB_HOST` | yes | `127.0.0.1` | PostgreSQL host |
| `DB_PORT` | yes | `5432` | PostgreSQL port |
| `DB_DATABASE` | yes | `laravel` | target database name |
| `DB_USERNAME` | yes | `root` | connecting role |
| `DB_PASSWORD` | yes | `''` | connecting role's password |
| `DB_SSLMODE` | no | `prefer` | **this feature's fix** — was hardcoded to `'prefer'`; now reads this env var, so `require` (what DigitalOcean's managed PostgreSQL needs) is expressible without a code change |

The application:

- Connects successfully when the above resolve to a reachable PostgreSQL 18.x server.
- Enforces TLS according to `DB_SSLMODE` — a value of `require` refuses a plaintext fallback.
- Does not require any code change to switch `DB_SSLMODE` between environments — only the env
  var.

## Out of scope for this contract

- Actual credentials (owned by deployment config, not this feature).
- Any data present in the target database (Feature 002).
