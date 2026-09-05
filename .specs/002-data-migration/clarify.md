# Clarify log — 002-data-migration

## Q1 — where does the rehearsal against the real databases run?
- Options: exclusively via SSH on the VPS (throwaway container, like the blog) / from this local
  session
- Decision: exclusively via SSH on the VPS.
- Reason: matches the project's own established practice (the blog did this for the same class
  of migration); keeps the plaintext credentials in `prompt-db-migration.md` from gaining any
  additional exposure surface beyond where they already sit. This local session's
  `implement`/`verify` phases never touch the real MySQL source or real PostgreSQL target.
- Date: 2026-09-05

## Q2 — does closing Feature 002 require a real-database rehearsal?
- Options: yes, required to close (like the blog) / no, fixtures suffice to close
- Decision: no — fixtures suffice to close Feature 002.
- Reason: keeps this feature's `implement`/`verify` entirely local and safe, consistent with Q1.
  The real-database rehearsal becomes its own separate, explicitly-triggered step, immediately
  before the real cutover — not bundled into this feature's definition of done.
- Date: 2026-09-05

## Q3 — copy command behavior against an already-populated target
- Options: refuse by default, explicit flag to replace (like the blog's `--truncate`) / replace
  automatically
- Decision: refuse by default; an explicit flag replaces.
- Reason: the safer default for a command that will eventually run against production-adjacent
  data — an accidental re-run can't silently duplicate or corrupt anything.
- Date: 2026-09-05

## Q4 — command naming
- Options: match the blog's names (`db:copy-from-legacy`, `db:verify-copy`) / different names
- Decision: match the blog's names.
- Reason: consistency across the owner's projects — anyone (including a future session) who
  worked on the blog's migration already knows what these commands do here.
- Date: 2026-09-05
