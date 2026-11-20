# Review as: Owner (portal super-user)

**Scope:** `/panel-x9z/` (path from `ADMIN_URL_PATH`), 36 menus, CRUD with ACL, D17 verification, QCM bank/rules/review, affiliates, certificates, mail outbox, variables.

## Summary
Every menu I opened rendered, on desktop and on a 390px phone, and the active portal item was marked correctly each time. The owner-only desks (QCM review, affiliate referrals and payouts, certificate issue/revoke) work end-to-end under the new suites. No data leak or ACL gap was found. Findings are mostly about guarding and polish.

## Journey
Login -> Command Center -> Services -> QCM bank (import/export/CRUD) -> QCM rules -> QCM review (fee pass, task approve) -> Affiliates (approve, settle payout) -> Certificates (issue, revoke) -> Variables -> Mail outbox.

## What worked
- Sidebar item for the current page carries `aria-current="page"` and the highlighted style on desktop and phone (probe: `/`, `/services/`, `/qcm-bank/`, `/affiliates/`; no horizontal overflow on either viewport).
- QCM: rules are owner-only; bank import/export/CRUD work; fee-pass flow QP-XXXXXXXX -> D17 -> `qcm-review/pass` (`tests/qcm.py` 86/0).
- Certificates: issue, revoke, expiry, CSRF and ACL denial for non-owners (`tests/cert.py` 55/0).
- Affiliates: approve/reject, hold period, payout settle (`tests/affiliate.py` 88/0).
- Mail goes through the log driver; `@example.test` is never mailed.
- Variable overrides are written with `changed_by` for audit.

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| O-1 | High (coverage) | Fixed | No automated coverage for QCM, certificates and affiliates | `ls tests/` | `qcm.py`, `cert.py`, `affiliate.py` written (229 checks) |
| O-2 | Low | Open | `crud.py` has no case for the owner-only `qcm-rules` resource (rules are covered by `qcm.py`, but not through generic CRUD) | read `tests/crud.py` | add a create/update/denied-for-admin case |
| O-3 | Low | Open | Default portal path `panel-x9z` and demo owner password appear in tests/docs. Safe only if production `.env` overrides both | `grep ADMIN_URL_PATH .env.example` | `bin/preflight.php` already warns; keep the post-upload checklist (AGENTS.md section 8) |
| O-4 | Low | Open | Test suites seed `@example.test` rows into the dev DB | `tests/*.py` | use "Purge demo data" in the portal; never run write suites against production |

## Not verified
Real SMTP delivery, real D17 gateway callbacks, concurrent owner sessions, a human pass over all 36 screens in Arabic.

## Evidence
`dash` 142/0, `crud` 60/0, `gate` 38/0, `qcm` 86/0, `cert` 55/0, `affiliate` 88/0. Playwright probe on the owner portal at 390px and 1366px: active item present and visible on 4 of 4 pages tried, no overflow.
