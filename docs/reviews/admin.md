# Review as: Admin (staff, ACL-limited)

**Scope:** Staff role with permission-scoped menus.

## Summary
The audience's main journeys render and are access-controlled correctly in all automated checks. The largest risk is missing automated coverage for the new QCM / certification / affiliate subsystems.

## What worked
- gate/crud suites check 403 for missing permissions.
- Desk queues are permission-gated (`qcm`, `affiliates`).

## Findings
| ID | Severity | Finding | Repro | Suggested fix |
|----|----------|---------|-------|---------------|
| A-1 | Medium | No test proving admin without `qcm.delete` cannot use bank import mode=replace | POST import with mode=replace as admin lacking perm | Add to tests/qcm.py |
| G-1 | High (coverage) | No automated tests for QCM, certificates, affiliate flows | `ls tests/` has no qcm/cert/affiliate | Author `tests/qcm.py`, `tests/cert.py`, `tests/affiliate.py` using `_acct.signed_in` (see docs/reviews/README.md checklist) |

## Honest coverage statement
This review is based on the automated suites plus a per-role HTTP probe. The dedicated suites `tests/qcm.py`, `tests/cert.py` and `tests/affiliate.py` do NOT exist yet, so the QCM timeout/scoring, requirement gating, affiliate commission/payout and certificate revoke/expiry paths have no end-to-end automated coverage. Treat statements about those flows as unverified beyond status codes and page rendering. No human visual review was done for this audience.

## Test evidence (2026-11-20, dev server :8080, MAIL_DRIVER=log)
- `smoke.sh` 713 checked / 0 failed; `flows` 69/0; `auth` 37/0; `d17` 25/0; `dash` 142/0; `gate` 38/0; `mail` 136/0; `crud` 60/0; `ui` 64/0; `browser` 65/0; `mobile` 435/0; `viewcache` 27/0.
- `php bin/lint.php`: LINT OK (215 content files, 92 templates, 3 languages). `php bin/view.php cache`: 92 compiled.
- Role probe (`/tmp/probe.py`, throw-away accounts via `tests/_acct.py`): for roles user, customer, investor, member, leader, manager, GET `/account/`, `?tab=qcm`, `?tab=affiliate`, `?tab=certificates`, `?tab=developer`, `/certification/`, `/affiliate/` all returned 200; `/panel-x9z/` returned 302 (login redirect); unknown certificate code returned 404.
- Guest probe: `/` 200, `/sitemap.xml` 200, `/account/` 302, `/panel-x9z/` 302, `/openapi.json` 401, `/nope/` 404, `/ar/` and `/fr/` 404.
