# Review as: Manager (staff account role)

**Scope:** a public account promoted to `manager` (member of `Account::STAFF`), its `/account/` overview and the portal menus its ACL allows.

## Summary
After the round-1 fix the manager sees a staff overview instead of the applicant file, and reaches the portal. Staff roles share one tile set, so manager-specific work queues are not surfaced on the overview. That is a design gap, not a defect.

## Journey
Register -> verify -> promote before login -> login -> `/account/` -> portal -> a menu the role may open -> a menu it may not.

## What worked
- The role is honoured at login and the portal opens (`Dash::STAFF_PORTAL` includes `manager`).
- Tiles: Unread notifications, Services (130), Portal menus (count). No "Applicant file".
- ACL denials render a proper page, not a blank 403 (`gate.py`, `audit_auth.py`).

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| M-1 | Medium | Fixed | Overview showed the applicant file for a staff role | sign in, open `/account/` | staff tiles in `Dash::account()`; `contract.py` checks `manager` |
| M-2 | Low | Open | Staff roles share identical tiles; no queue counts (pending referrals, passes to review, applications) | open `/account/` as manager | add per-role counters from the tables the `Dash` desks already query |
| M-3 | Low | Open | Role is read at login only (see admin A-2) | promote while signed in | per-request role read |

## Not verified
The full permission matrix for manager. A real team workflow with several staff acting on one queue.

## Evidence
`contract.py` 34/0 (tile checks for admin/leader/manager); `dash.py` 142/0; `audit_auth.py` 0/0.
