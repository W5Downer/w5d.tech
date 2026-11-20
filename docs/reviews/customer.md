# Review as: Customer

**Scope:** customer account: service request/checkout, subscription look-up, requests tile, contact form.

## Summary
The customer path works and is access-controlled. Customer tiles ("Requests sent") are role-appropriate. The prior wrong-key assertion in the tests was weak and has been tightened. No customer-specific defect remains open beyond general ones.

## Journey
Services -> a service page -> register as customer -> request/checkout -> `/account/` -> look up a subscription by key -> contact.

## What worked
- Customer tile set includes "Requests sent" (`contract.py`).
- Subscription look-up with a wrong key shows a friendly "No subscription matches" message and no details (`flows.py`).
- Contact and eligibility forms: CSRF, honeypot, rate limit (`flows.py` 69/0), messages translated in fr/ar.
- No horizontal overflow on 390px for service pages (`mobile.py` 435/0).

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| C-1 | Medium | Fixed | Test for the wrong-key look-up matched the wrong text and failed | `python3 tests/flows.py` | assertion checks the real message and absence of subscription details |
| C-2 | Low | Open | The `/api/quote` error text is English only (see I-2); customers using the quote widget in fr/ar see English | quote with 0 parts in fr | localise |

## Not verified
Order fulfilment, refunds, real notification delivery.

## Evidence
`flows.py` 69/0, `contract.py` 34/0, `mobile.py` 435/0, `audit_auth.py` 0/0.
