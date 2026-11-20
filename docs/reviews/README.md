# W5D audience reviews (for AI developer agents)

Generated 2026-11-20 (turn 5, final round). Each file is a review written *acting as* one audience. Findings come from a
general-to-detailed test pass: whole-site audits first, then per-role journeys, then pixel/keyboard-level checks.
Nothing here is a template: every "Fixed" row was found by a test or probe and is now guarded by a regression check;
every "Open" row is reproducible with the steps given.

| Audience | File | Main journey tested |
|---|---|---|
| Owner (portal super-user) | [owner.md](owner.md) | 36 portal menus, CRUD, D17 verify, QCM/affiliate/certificate desks |
| Admin (staff, ACL-limited) | [admin.md](admin.md) | Role-limited portal, overview tiles, ACL denials |
| Leader | [leader.md](leader.md) | Staff account dashboard, team queues |
| Manager | [manager.md](manager.md) | Staff account dashboard, manager applications |
| Member | [member.md](member.md) | Promoted account: dashboard, QCM, certificates, API tab |
| Investor | [investor.md](investor.md) | Pricing, checkout, D17, subscription, receipts |
| Customer | [customer.md](customer.md) | Requests, checkout, subscription look-up |
| User (basic account) | [user.md](user.md) | Register, verify, profile, QCM, affiliate, certificate |
| Developer / API consumer | [developer.md](developer.md) | Public JSON API, CORS, errors, private OpenAPI |
| Guest / public visitor | [guest.md](guest.md) | 229 public paths x en/fr/ar, menus, forms, mobile |

## How to read a finding
`ID | Severity | Status | Finding | Repro | Fix / guard`. Severity: High (breaks a journey or leaks data), Medium (visible
professionalism or correctness defect), Low (polish). Status: **Fixed** (with the regression check that guards it) or **Open**.

## Test inventory (all run against the dev server :8080, `MAIL_DRIVER=log`)
| Suite | Result (final run) | What it covers |
|---|---|---|
| `tests/smoke.sh` | 713 / 0 | every public route x en/fr/ar |
| `flows` 69, `auth` 37, `d17` 25, `dash` 142, `gate` 38, `mail` 136, `crud` 60 | 0 failed | forms, accounts, payments, dashboards, gate, mail, CRUD+ACL |
| `ui` 64, `browser` 65, `mobile` 435 | 0 failed | Playwright: modals, quick-find, tab bar, responsive audit |
| `viewcache.php` | 27 / 0 | Blade cache |
| `qcm` 86, `cert` 55, `affiliate` 88 | 0 failed | the three programmes end-to-end (new this turn) |
| `contract` | 34 / 0 | 404/405/OPTIONS/CORS, staff tiles, autocomplete, active menu, phone tab bar (new) |
| `audit.py` | 0 errors, 5 warnings | 675 page-views (229 paths x 3 langs), 1210 internal links |
| `audit_auth.py` | 0 / 0 | 414 signed-in page-views across roles |
| `audit_browser.py` | 0 / 0 | 26 pages x 2 viewports x 2 languages: console, overflow, images, tap targets, focus, active menu |
| `bash tests/production.sh` | 19 steps, 0 failed, ALL GREEN (run after the last code change) | APP_ENV=production + HTTPS proxy + trusted view cache + every suite |

## Cross-cutting open items (priority order)
1. **Language URLs / hreflang.** Language is cookie-based; `/ar/`, `/fr/` return 404 and `?lang=` is ignored, so the sitemap cannot declare `hreflang` alternates and search engines see one language per URL. Decide on prefixed URLs or `?lang=` support. (guest G-1, developer D-3)
2. **Localised API errors.** `/api/quote` validation text is English in all languages. (developer D-1, investor I-2)
3. **Role-differentiated member overview.** Member falls back to the generic applicant tiles. (member M-1)
4. **Auth pages share one meta description** (noindex, so low). (guest G-3)
5. **Two French titles exceed 70 chars** (home is 73). (guest G-2)
6. **Immutable cache headers** are sent only by Apache `.htaccess`, not PHP's built-in server; verify on the real host. (guest G-4)
7. Not covered by any automation: human visual review of RTL pages, real SMTP delivery, real D17 gateway, load/performance, screen-reader pass.

## Honest coverage statement
Automated checks plus Playwright probes (keyboard on the mega menu, phone tab bar, portal drawer). No human visual review,
no screen-reader session, no real mail or payment provider, no load test. Statements about those are unverified.
