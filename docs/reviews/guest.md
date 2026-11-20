# Review as: Guest (public visitor, not signed in)

**Scope:** 229 public paths x en/fr/ar (675 page-views), 1210 internal links, desktop 1366 and phone 390, menus, forms, SEO head, error pages.

## Summary
The public site is consistent and fast to scan. The first pass found several details that lower perceived professionalism
(menu not highlighting its page, two competing h1s, bare error pages). All were fixed and are now guarded. Remaining items are low severity except language URLs.

## Journey
Home -> Macro Works -> a service -> Pricing -> Join/eligibility -> Contact -> FAQ -> Certification verify -> Affiliate info -> `/account/login/`.

## What worked
- Every route answers 200 in all three languages (smoke 713/0); `audit.py` found 0 broken internal links.
- Arabic renders RTL with mirrored layout (`audit_browser`: 0 issues, 26 pages x 2 viewports).
- Skip link, visible focus ring on the first Tab stops, no horizontal overflow, no failed images or console errors (`audit_browser` 0/0).
- Guests hitting `/checkout/`, `/account/`, `/panel-x9z/` are redirected to sign-in; `/openapi.json` is 401.

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| G-0a | Medium | Fixed | The header menu item was not selected on its own page (e.g. Pricing, Services, Join) | open `/pricing/`; no mega trigger marked | longest-match `$navBest` in `partials/header.blade.php`, `active_prefix` in `nav/main.json`; `contract.py` "active menu marker" checks |
| G-0b | Medium | Fixed | Phone tab bar marked no tab on `/pricing/` and `/investors/` (Invest tab only matched `/checkout`) | 390px viewport, open `/pricing/` | `partials/tabbar.blade.php` now matches `/checkout|/pricing|/invest`; `contract.py` tab bar checks |
| G-0c | Medium | Fixed | Home slider's first slide and page hero both rendered an `h1`; macro cards and aside skipped heading levels | view source `/` | slider first slide `h1`, others `h2`; `blocks/macro-cards`, `partials/aside` fixed |
| G-0d | Medium | Fixed | Phone horizontal scroll: coarse-pointer rule inflated slider dots | 390px, `/` | rule removed from `attention.css`; `mobile.py` 435/0 |
| G-0e | Medium | Fixed | 404 and 405 were bare; API paths returned HTML | `curl /api/nope`, `POST /pricing/` | Router 405 + `Allow`, JSON 404/405 for `/api/*` and `*.json`; `pages/405.blade.php` |
| G-0f | Low | Fixed | Meta descriptions inconsistent in length/suffix | `audit.py` | normaliser in `layouts/app.blade.php`, `brand.desc_suffix` |
| G-0g | Low | Fixed | FAQ "is this a job?" answer over-promised | `/faq/` | rewritten in `Pages.php` ("a path, not a job") |
| G-0h | Low | Fixed | Forms had no `autocomplete` / `inputmode`; password managers mis-filled | `/account/register/` | `components/form-field` `$acMap`; `contract.py` |
| G-1 | **Medium** | Open | No language URLs: `/ar/`, `/fr/` -> 404, `?lang=` ignored, sitemap has no `hreflang` alternates | `curl -I /ar/`; `/sitemap.xml` | add `/{lang}/` prefix routing or `?lang=` support, then emit alternates in `Seo` + sitemap |
| G-2 | Low | Open | 2 French titles > 70 chars (`/` is 73) | `python3 tests/audit.py` -> `title-long` | shorten `data/content` fr titles |
| G-3 | Low | Open | `/account/login/`, `/register/`, `/forgot/` share one meta description (noindex) | `audit.py` -> `dup-description` | per-page descriptions in `lang/*` |
| G-4 | Low | Open | PHP built-in server sends no immutable cache headers for assets (only `.htaccess` does) | `curl -I /assets/_base/css/tailwind.css` | verify on the real host; add to `bin/preflight.php` |

## Not verified
Screen-reader behaviour, real-device touch, RTL by a human eye, page-speed numbers under load.

## Evidence
`audit.py`: 675 page-views, 0 errors, 5 warnings (G-2, G-3, plus 12 expected redirects as INFO). `audit_browser.py` 0/0. `smoke.sh` 713/0. `contract.py` 34/0.
Mega menu with keyboard (Playwright, 1366px): Enter opens (`aria-expanded` true), Tab moves into the panel, Escape closes and returns focus to the trigger, Space opens.
