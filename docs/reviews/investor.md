# Review as: Investor

**Scope:** `/pricing/`, `/investors/`, `/checkout/`, D17 payment, subscription look-up, receipts, `/api/price.json`, `/api/quote`.

## Summary
The money-times-time pricing and checkout flow work and the dashboard shows parts and price. Honest tone is kept ("a path, not a job"; no promised returns). The investor-facing finds were an unmarked Invest tab on phones (fixed) and an English-only API validation message (open).

## Journey
Guest -> `/pricing/` -> `/investors/` -> Invest (302 to sign-in with `?next=`) -> register as investor -> `/checkout/` -> D17 reference -> owner verifies -> subscription look-up.

## What worked
- `/api/price.json` and `/api/quote` agree (`flows.py`); numbers come from `config/variables.php` via tokens, never hard-coded in content (lint enforces).
- Guest on `/checkout/` is redirected to login with the return path preserved.
- D17 flow end-to-end (`d17.py` 25/0): wrong key shows "No subscription matches" with no details leaked (`flows.py` assertion).
- Investor tiles: Parts ordered, Price today (`contract.py`).
- Active menu: the Invest mega trigger is selected on `/pricing/`, `/investors/`; phone tab bar now marks Invest there too.

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| I-1 | Medium | Fixed | Phone tab bar showed no current tab on `/pricing/` and `/investors/` | 390px, open `/pricing/` | `tabbar.blade.php` matches checkout/pricing/invest; `contract.py` |
| I-2 | Low | Open | `/api/quote` validation message is English in every language: `"Choose 1 to 1000 parts."` | `curl -X POST /api/quote -d '{"parts":0}'` | pass `lang()` into `Api::quote` messages via `lang/*` keys |
| I-3 | Low | Open | `for-investor` copy was reviewed for promises; keep checking any future return/ROI wording | `/for/investor/` | keep the lint rule and human review of money claims |
| I-4 | Info | Open | `/checkout/` redirect for guests appears as 12 INFO redirects in `audit.py`; expected | `audit.py` | none |

## Not verified
Real D17 gateway, tax/legal wording by a lawyer, behaviour under many simultaneous orders.

## Evidence
`d17.py` 25/0, `flows.py` 69/0, `contract.py` tile and tab-bar checks, `audit_auth.py` 0/0. Probe: `POST /api/quote {"parts":0}` returns `{"error":"validation","errors":{"parts_per_day":"Choose 1 to 1000 parts.","days":"Choose 1 to 365 days."}}` regardless of language.
