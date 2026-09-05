# contract: legacy-database
# version: 1.0.0
# captured: 2026-09-05
# source: prompt-db-migration.md §3, §12; ADR 0004

## Boundary

A new named database connection, `legacy`, added to `config/database.php`, driven by
`LEGACY_DB_*` environment variables (mirroring the existing `DB_*` keys' shape).

## Contract

| Guarantee | Detail |
|---|---|
| Read-only in practice | `db:copy-from-legacy` and `db:verify-copy` only ever call `->select()`/`->get()`/`chunkById()` reads against this connection. Neither command issues an `insert`, `update`, or `delete` against it. |
| Never the default connection | `legacy` is never set as `DB_CONNECTION`'s value; it exists solely as a named secondary connection, reached explicitly via `DB::connection('legacy')`. |
| Schema-compatible | The legacy source is assumed to already have this application's schema (it's the same app, currently running on MySQL) — no schema migration runs against `legacy`, ever. |
| Local test double | In this feature's own automated suite, `legacy` points at a second local SQLite connection (ADR 0004), never at a real MySQL server. In production and at the real rehearsal, `legacy` points at the real managed MySQL cluster named in `prompt-db-migration.md` §3 — that connection only ever happens via SSH on the VPS (clarify Q1), never from a local development session. |

## Out of scope for this contract

- The actual credentials (owned by deployment config / the VPS's migration environment file, not
  committed anywhere).
- Decommissioning this connection after the retention window (a follow-up, not this feature).
