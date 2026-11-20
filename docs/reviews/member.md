# Review as: Member (promoted account)

**Scope:** an account promoted to `member` before login: dashboard, QCM, certificates, API tab. `member` has only the `dashboard` permission in `config/roles.php`, and public registration cannot create it (`Account::SELF_ROLES` = user, investor, customer).

## Summary
A real member sees "Your dashboard · Member" and the member API tab; the journeys work. An earlier "member sees the visitor view" alarm turned out to be a test artifact (the account had been promoted after login, and the role is read at login). The one real gap is that the overview tiles are the generic ones.

## Journey
Register -> verify -> promoted in DB -> login -> `/account/` -> `?tab=qcm` -> `?tab=certificates` -> `?tab=developer`.

## What worked
- Dashboard title and role label are correct; unread-notification tile shows a count.
- QCM and certificate tabs return 200; QCM gating and scoring pass `qcm.py` (86/0).
- API tab shows the Member text (not the user text).
- The portal is not reachable (302 to login), as expected for a role with no staff permission.

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| M-1 | Low | Open | Member overview tiles are the generic default: "Your next step", "Applicant file", "status", "Unread notifications", "Services 130" | promote before login, open `/account/` | add a `'member' =>` arm in `Dash::account()` (certificates held, next QCM allowed at, affiliate balance) |
| M-2 | Low | Open | The role is read at login only; promoting a signed-in user shows no change until they sign in again (caused the false alarm) | promote after login, reload | per-request role read, or end the session on role change |
| M-3 | Info | n/a | No self-service route to become a member; promotion is an owner action, and no screen explains that to the user | n/a | add one line to the user dashboard once the policy is decided |

## Not verified
A long-lived member session over several QCM rounds; a human check of the member API tab wording in fr/ar.

## Evidence
Throw-away promoted account: `/account/` 200, text "Your dashboard · Member", tiles list above; test user deleted afterwards. `qcm.py` 86/0, `cert.py` 55/0, `audit_auth.py` 0/0.
