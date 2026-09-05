# Contracts — 002-data-migration

Two boundaries this feature touches:

- `legacy-database.md` — the `legacy` MySQL connection's configuration contract and its
  read-only guarantee.
- `verification-report.md` — the `db:verify-copy` command's output shape and exit-code contract.
