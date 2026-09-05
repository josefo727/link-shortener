# contract: verification-report
# version: 1.0.0
# captured: 2026-09-05
# source: spec.md acceptance criterion 3

## Boundary

The `db:verify-copy` artisan command's console output and process exit status.

## Contract

- One line of output per table in the manifest (`users`, `short_urls`, `personal_access_tokens`),
  naming: the table, whether source and target row counts match, and whether every field of every
  row matches.
- A table is reported as **matching** only if: the row count is identical AND every column of
  every row compares equal between source and target (byte-for-byte for strings, exact for
  numbers, same wall-clock value for timestamps).
- Exit code is `0` if and only if every table in the manifest matches. Any mismatch — a row count
  difference, a single differing field, or an extra/missing row — makes the command exit non-zero
  (`1`) and names every offending table in its output (not just the first one found).
- The command never modifies either connection — it is read-only against both `legacy` and the
  target, matching Article III (boundary-only mocks; the boundary here is exercised for real, not
  doubled, in this command's own tests per ADR 0004).

## Out of scope for this contract

- Performance characteristics (this is 37 rows; no timing budget is meaningful here, unlike the
  blog's 10-minute cutover budget for ~1,800 rows).
