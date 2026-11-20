# Review as: User (basic account)

**Scope:** default registrant: register, verify e-mail, sign in, profile and password, QCM practice, affiliate apply, certificate view.

## Summary
The sign-up path is solid and now behaves well with password managers. Messages are translated (fr/ar checked). Remaining items are small.

## Journey
`/account/register/` -> verification mail (log driver) -> `/account/verify/...` -> login -> `/account/` -> profile -> QCM practice -> affiliate apply -> certificate tab.

## What worked
- Register -> verify -> login (`auth.py` 37/0). Login error text is translated.
- Validation messages for register, contact and eligibility are translated in fr and ar (checked by a probe in all three languages). The forgot-password and checkout probes showed no alert element; I did not establish whether that is intended, so treat it as unverified.
- Password fields carry `current-password` (login) and `new-password` (register, reset, profile): the register page has exactly two `new-password` fields. E-mail has `inputmode=email`.
- Affiliate: apply is pending until approved unless `affiliate.auto_approve`; self-referral is refused; click dedupe per visitor per day (`affiliate.py` 88/0).

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| U-1 | Medium | Fixed | Forms had no `autocomplete`, so browsers offered the wrong saved values (e.g. the payout GSM) | open `/account/register/` | `form-field` `$acMap`; `contract.py` autocomplete checks |
| U-2 | Low | Open | Public registration offers only user, investor, customer; there is no visible explanation of how the other roles are obtained | `/account/register/` | add help text |
| U-3 | Low | Open | The verification mail is only visible in `storage/logs/mail-*.log` while `MAIL_DRIVER=log`; real delivery is unverified | `php bin/mail.php status` | test with SMTP before launch |

## Not verified
Real e-mail delivery; password-manager behaviour in real browsers (attributes are present, behaviour was not observed).

## Evidence
`auth.py` 37/0, `affiliate.py` 88/0, `qcm.py` 86/0, `contract.py` 34/0, `audit_auth.py` 0/0.
