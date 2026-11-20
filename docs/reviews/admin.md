# Review as: Admin (staff, ACL-limited)

**Scope:** staff role `admin` signed in through the public account, plus the portal permissions from `config/roles.php` (admin includes `qcm`, `affiliates`, `certificates`).

## Summary
Admin gets a staff overview in `/account/` and is kept out of owner-only actions. The first pass showed the generic "Applicant file" tile for staff, which looked wrong for a staff member. This is fixed.

## Journey
Register -> verify -> (role promoted) -> login -> `/account/` overview -> portal menus -> a permitted desk -> an owner-only action (denied).

## What worked
- Staff roles `admin, leader, manager` are in `Dash::STAFF_PORTAL` and reach the portal; the role is read at login.
- Owner-only actions (QCM rules, certificate revoke, replace-import of the QCM bank) are refused with the ACL message; every POST needs a CSRF token (`cert.py`, `qcm.py`).
- 414 signed-in page-views across roles with 0 errors (`audit_auth.py`).

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| A-1 | Medium | Fixed | Staff overview showed the applicant tiles ("Applicant file", status) | sign in as admin, open `/account/` | `Dash::account()` staff tiles: Unread notifications, Services, Portal menus (count of ACL-visible menus); `contract.py` |
| A-2 | Low | Open | The role is read at login only. A role change made by an owner does not apply to a session that is already open | promote a signed-in user in the DB, reload | re-read the role per request, or force logout on role change |
| A-3 | Low | Open | Public registration cannot create staff. That is correct, but there is no portal screen that says how to promote someone | n/a | document in the owner "Users" menu help text |

## Not verified
Every individual permission combination in `config/roles.php`; only the role/menu count and the representative denials were exercised.

## Evidence
`contract.py` tile checks (admin, leader, manager); `audit_auth.py` 414 page-views 0/0; `gate.py` 38/0.
