# Clarify log — 001-postgresql-compatibility

## Q1 — bundle the coverage driver (pcov) into this feature?
- Options: no (separate follow-up) / yes (bundle it here, since the container image is already
  being touched)
- Decision: yes, bundle it here.
- Reason: the Dockerfile/image is already in scope for this feature (blocker 1); installing pcov
  alongside is a small marginal cost and closes the coverage-measurement gap recorded in
  `test-inventory.md` without a second image rebuild later.
- Date: 2026-09-05

## Q2 — set up a CI pipeline as part of this feature?
- Options: no (separate follow-up) / yes (minimal pipeline, e.g. GitHub Actions, running the
  suite against PostgreSQL on every push)
- Decision: no, separate follow-up.
- Reason: acceptance criterion 5 only requires the suite to run locally against PostgreSQL
  through existing dev tooling; automating that on every push is a distinct, larger concern (CI
  provider choice, secrets handling) that deserves its own feature slice rather than riding along.
- Date: 2026-09-05

## Q3 — exact PostgreSQL version/tag for local test tooling
- Options: any `18.x` image / pin exactly `18.6` (production's verified version)
- Decision: any `18.x` image.
- Reason: local test tooling doesn't need to track production's patch version exactly; pinning
  only the major version (18) is simpler to maintain and still catches any real 18-vs-earlier
  incompatibility this feature exists to find.
- Date: 2026-09-05

## Q4 — does closing this feature include rebuilding/redeploying the container to the VPS?
- Options: separate, explicitly-triggered step / included in this feature's close
- Decision: separate step.
- Reason: keeps this feature's blast radius local (build + test only); the actual VPS
  rebuild/redeploy is deliberately left as something the user triggers explicitly after
  reviewing the merged change, consistent with the project's general "confirm before
  outward-facing/hard-to-reverse actions" rule.
- Date: 2026-09-05
