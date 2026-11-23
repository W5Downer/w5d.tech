# W5D website architecture

PHP 8.4 + SQLite CMS. No framework, no Composer, no CDN, no jQuery. Written 2026-11-20 for AI developer agents. Verify details against the code, because this file is a map and not a spec.

## 1. Directory layout
| Path | Purpose |
|---|---|
| `htdocs/` | Web root: `index.php` front controller and `assets/_base/{css,js,images,icons.json}` |
| `src/` | Core classes (App, Router, Blade, View, ViewCache, Content, Lang, Variables, DB, Migrator, Auth, Account, ACL, Crud, Csrf, Session, RateLimit, Gate, Pricing, Payments, Mail, Dash, ApiGuide, Affiliate, Certificate, Qcm, Seo, Social/OAuth, …) |
| `src/controllers/` | Request handlers (Admin, Accounts, Docs, Programs, …) |
| `views/templates/w5d/` | The single Blade-style template (layouts, pages, account, admin, components, mail) |
| `data/content/` | Page/nav JSON, one file per page; every leaf is `{en,fr,ar}` |
| `data/qcm/` | QCM bank, one JSON file per service (130 files, demo 5,200 questions) |
| `config/` | `app.php`, `variables.php` (single source of numbers), `roles.php` (roles + 39 menus), `resources.php` (CRUD), `mail.php`, `certification.php`, `apidocs/*`, `oauth.php`, `social.php` |
| `lang/` | UI strings en/fr/ar; the linter enforces identical keys |
| `db/migrations/` | `001`..`006` SQL migrations applied by `Migrator` |
| `storage/` | SQLite DB, logs (`mail-*.log`), view cache, incoming uploads (gitignored) |
| `bin/` | `lint.php`, `preflight.php`, `view.php` (cache) |
| `tools/` | `build.mjs` (Tailwind and Lucide icon vendoring), `gen/*.py` content generators |
| `tests/` | stdlib test suites and `production.sh/.py` |
| `docs/` | Prompts, notes, this file, `reviews/` |

## 2. Request lifecycle
`htdocs/index.php` → bootstrap (Env, Config, Session, DB + Migrator) → `App` builds the `Router` → first matching route wins (order in `src/App.php` matters) → controller → `view()` renders a compiled Blade template (`ViewCache`) inside the layout → `Response` with CSP and security headers. CSP: `default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline'`, so there are no inline scripts and no CDN.

### 2.1 Router behaviour worth knowing
- GET pages are canonicalised with a trailing-slash 301. Routes are regexes; first match wins.
- A path that matches with another method gives **405 + `Allow`**. Under `/api/*` or `*.json` the body is JSON `{"error":"method_not_allowed","allow":[...]}`; elsewhere the `pages.405` view.
- `OPTIONS` -> **204** with `Allow`; on `/api/` and `*.json` it also sends CORS preflight headers (`Access-Control-Allow-Origin: *`, Methods, Headers `Content-Type, X-CSRF-Token`, Max-Age 86400). `HEAD` is served through GET.
- `not_found()` (in `src/helpers.php`) answers JSON `{"error":"not_found",...}` with `X-Robots-Tag: noindex` for API paths and the 404 page otherwise.
- `App::handle` adds security headers and `Cache-Control: no-store` on HTML; HSTS only in production with an https `APP_URL`. Timezone is `Africa/Tunis`.

## 3. Templates and content
- `src/Blade.php` compiles `.blade.php` to PHP. Components: `<x-section>`, `<x-form-field>`, `<x-ctas>`, `<x-cta>`, `<x-card-media>`, `<x-mail-*>`. Clear and rebuild the cache with `php bin/view.php cache`.
- Helpers: `__()`, `t()`, `tr3()`, `rich()`, `url()`, `abs_url()`, `asset()`, `icon()`, `csrf_field()`, `admin_url()`, `not_found()`, `view()`.
- Content pages are JSON section lists: hero, prose, kpis, steps, definitions, faq, cards, cta, notice, form, table, wizard, team. Tokens like `{{ path|filter }}` resolve against `config/variables.php` (filters: num, money, pct, first, devs, certs). Content must never hard-code prices or counts.
- **Active menu.** `partials/header.blade.php` computes the longest-matching nav entry (`$navBest`, `$navExact`, `$navLinkOn`). `data/content/nav/main.json` items may carry `active_prefix` (`/macro-works`, `/invest`, `/join`, `/about`) so child pages keep their parent selected. The mega trigger gets `data-active`, links get `data-active-link` and `aria-current`. The phone tab bar (`partials/tabbar.blade.php`) marks Home (`/`), Invest (`/checkout`, `/pricing`, `/invest*`) and Account (`/account*`). The owner portal sidebar marks `$active` with `aria-current="page"`. A new top-level section needs an `active_prefix` or a tab-bar pattern, and a `tests/contract.py` case.
- **Meta description normaliser** in `layouts/app.blade.php` (length, `brand.desc_suffix`), so page descriptions stay uniform.
- **Forms.** `<x-form-field>` takes `autocomplete` and derives `autocomplete`/`inputmode` from the field name (`$acMap`); password fields use `current-password` / `new-password`.
- **Heading order.** The home slider's first slide is the page `h1`, the other slides `h2`; do not add a second `h1`.
- Language: cookie `w5d_lang` or `/language/{code}`. `?lang=` and `/ar/` prefixes are not supported. Arabic renders RTL.

## 4. Auth, ACL, CRUD
- **Owner and staff:** `Auth`, portal at `/{ADMIN_URL_PATH}` (default `panel-x9z`), 41 menus defined in `config/roles.php`, permission checks via `ACL::can` and `Auth::require($perm)`.

- **Third-party integrations (owner menu 38, `config/integrations.php`, `src/Integrations.php`):** Google Analytics 4, Tag Manager, Google Ads, Meta Pixel, reCAPTCHA (v2/v3) and search-engine verification tags, each with an Enabled switch and required-field validation (same `SettingsForm` engine as payment methods; secrets encrypted, every change logged with a reason). Trackers load only through `js/integrations.js` after the visitor accepts the cookie banner (Consent Mode default `denied`) and never on the portal, account area, payment pages, unsubscribe, certificate or API. The Content-Security-Policy is built per request by `Integrations::csp()`: strict first-party by default, each vendor's domains added only while it is live and only on the paths where it can run. reCAPTCHA is verified server-side in `Forms::guard`, `Accounts::guard` (anonymous buckets), the subscription lookup, and optionally the JSON write API and the portal sign-in; the outage policy (let through / block) is the owner's choice. Tests: `tests/integrations.py` (fake siteverify in `tests/_gwstub.py`).
- **Team sign-in (`src/StaffAuth.php`):** owner (`OWNER_EMAIL`) and team users (`users.role_slug` in admin/leader/manager/member, active, e-mail on `STAFF_EMAIL_DOMAIN`, default `w5d.tech`) sign in with a 6-digit code e-mailed to the address. No password exists for them. Codes are HMAC-hashed in `staff_codes`, single use, `STAFF_CODE_TTL_MIN` minutes, 5 attempts, rate-limited per IP and per address; unknown addresses get the same answer (no enumeration). Sessions end after `STAFF_IDLE_MINUTES` idle or 12 h absolute. A portal session is valid only if `StaffAuth::signIn` opened it (`Auth::check()` also calls `StaffAuth::alive()`). Team addresses are refused by public registration, login and password reset. Console break-glass: `StaffAuth::issueLocal()`.
- **Private doors:** `/for/owner|admin|leader/` answer 404 to anyone who is not that role (`Pages::canSeeAudience`); robots.txt lists them as Disallow but never prints the portal path.
- **Setup guide (menu 41, owner only):** `Admin::vSetup()` + `admin/setup.blade.php`: step-by-step `.env` variables with live set/unset state (values are never shown) and payment paths for each registry status.
- **Per-page help (`config/help.php`, `help_btn()`):** every portal menu and account panel has a help icon, as a tooltip (`tip`) or a popup dialog (`pop`), in en/fr/ar. `tests/help.py` enforces coverage.
- **Installable portal (`src/controllers/Pwa.php`, `assets/_base/js/portal.js`):** manifest, service worker and offline page live under the portal mount, so the scope is the portal only and no public file mentions it. The worker caches only the offline page and static assets, never HTML, forms or JSON. `html.is-offline` + a banner show the connection state; a POST is held back while offline. `tests/pwa.py` checks it in Chromium.
- **Public accounts:** `Account`, `$_SESSION['acct']`, e-mail verification, roles user/customer/investor/member/leader/manager (investor/leader/member/customer/user appear as `Pages::AUDIENCES`, along with owner and admin).
- **CRUD:** `config/resources.php` + `src/Crud.php` generate list/edit/delete screens with ACL. Every POST carries a CSRF token (`Csrf`). Password hashes and tokens are never selected for display.
- Rate limiting: `RateLimit` (login, certificate verify, QCM answers).

## 5. Business modules
- **Pricing:** `Pricing` computes progressive money × time prices from `config/variables.php`.
- **Payments:** D17 manual flow (`Payments`); the owner verifies in the portal. Mail through `Mail::send`; `MAIL_DRIVER=log` writes `storage/logs/mail-*.log`; `@example.test` addresses are never mailed.
- **Catalogue:** 5 macro works × 26 = 130 services, 25 projects.
- **Affiliate** (`Affiliate`, tables affiliates, affiliate_clicks, affiliate_referrals, affiliate_payouts; `users.referred_by`): apply → approve → `?ref=` click tracking → signup bonus → commission with hold → payout request → settlement. Self-referral is refused.
- **QCM** (`Qcm`, `data/qcm/{service}.json`, tables qcm_rules, qcm_attempts, qcm_passes, qcm_tasks): ids `service.L#.###`, exact-set scoring, purposes practice/certification/upgrade, owner-configured rules (cooldown, max per day, min level, min tasks, min practice %, min affiliates, fee), fee passes `QP-XXXXXXXX`. The server keeps the timer (+`qcm.grace_seconds`); graded rounds hide answers. The front-end wizard is in `site.js`. Import limits 2 MB / 5000 questions; replace mode needs `qcm.delete`.
- **Certificates** (`Certificate`, `config/certification.php`): codes `W5D-XXXX-XXXX-XXXX`, public verify page and `/api/certificates/{code}.json` (holder name masked, rate-limited), print page, revoke and expiry states.

## 6. API and docs
Public JSON API plus the private OpenAPI at `/openapi.json` (login required), generated from `Docs::endpoints()`. Private API-guide tabs inside dashboards (`ApiGuide`, `config/apidocs/{public,session,panels,screens}.php`). Agent tool `verify_certificate`. `Dash` builds the dashboards and desk queues (permissions `qcm`, `affiliates`).

## 7. Asset pipeline
`cd tools && node build.mjs` builds `tailwind.css` and vendors Lucide icons into `icons.json`. Only literal `icon('name'` calls, `'icon' =>`, and `'i'|'ico' =>` are scanned, so ternaries inside `icon()` hide names. Client JS is a single IIFE, `site.js`; custom CSS is `attention.css`.

## 8. Quality gates
`php bin/lint.php` (tri-lingual key parity, no hard-coded numbers, one `btn-peak` per section, CSRF on POST) and `php bin/preflight.php`. Suites (all stdlib Python except the Playwright ones; helpers in `tests/_acct.py` and `tests/_prog.py`):
- Site and flows: `smoke.sh`, `flows`, `auth`, `d17`, `dash`, `gate`, `mail`, `crud`, `viewcache.php`.
- Browser (Playwright + chromium): `ui`, `browser`, `mobile`.
- Programmes: `qcm` (86), `cert` (55), `affiliate` (88).
- Contract: `contract` (78): 404/405/OPTIONS/CORS, staff overview tiles, autocomplete, active menu markers, phone tab bar, chromeless pages, reveal wiring, five-door menu, per-subject CTA.
- Also: `sitemap` (28), `motion` (32, Playwright: desktop vs phone animation, reduce-motion opt-in, chromeless pages), `integrations` (112), `gateways` (129), `qcmgen.php`.
- Professionalism audits: `audit.py` (every public path x 3 languages: titles, descriptions, headings, links), `audit_auth.py` (signed-in views per role), `audit_browser.py` (console, overflow, images, tap targets, focus, active menu, RTL).
- `production.sh`: APP_ENV=production, HTTPS proxy, trusted view cache, every suite, security checks.
Run them all before and after every change. Never name a test file after a stdlib module (`http.py` shadowed the stdlib). Open items live in `docs/reviews/README.md`.

### 8a. Front-end chrome, motion, sitemap, navigation (turn 6)
- **Chromeless pages.** A view that declares `@section('chromeless', '1')` (sign-in/register/forgot/reset, pay, QCM round and QCM pay, unsubscribe, certificate, subscription manage) renders `partials/minibar` (brand, way out, language, theme) and `partials/footer-mini` instead of the mega-menu header, tab bar, quick-find and auth dialog. Verification page, 404 and every content page keep the full chrome.
- **Motion.** `<x-section>` wraps its content in `data-rv="up"`; `site.js` reveals it with an IntersectionObserver (threshold 0, 6 s failsafe). OS `prefers-reduced-motion` now means *calm*: travelling/looping decoration stops, orbs slow to 40 s, reveals become a fade, counters still count. A footer button (`data-motion-toggle`, only shown when the OS asks for reduced motion) stores `w5d-motion=full` in localStorage and sets `html[data-motion=full]` for full motion. A hover/pointer layer (`@media (hover:hover) and (pointer:fine)`) lifts cards and buttons on desktop.
- **Sitemap.** `Docs::sitemapUrls()` derives every public indexable URL from content (pages JSON minus `robots: noindex`, audiences, macros, services, projects, regions), drops guest-gated forms while their `Gate` flag is on, uses file mtime for `lastmod`, and declares no hreflang (language is cookie-based on the same URL). `tests/sitemap.py` crawls every entry in 3 languages.
- **Navigation = five doors.** `data/content/nav/main.json`: Programme, Learn and certify, Lead and partner, Invest, Company and trust; `footer.json` mirrors them. `src/Cta.php` picks the header button, mobile-menu button and phone tab for the page subject (invest, learn, lead, client, affiliate, or the neutral "Choose your door"); a service page links to eligibility for that service. Page bodies carry their own CTAs in the content JSON.

### 8b. Trust pages, cookie notice, funding and advertising (turn 7)
- **Trust pages are generated, not hand-edited.** `tools/gen/trust/` holds one python module per page (`p_<slug>.py`) written with the small DSL in `lib.py`; every text is an `(en, fr, ar)` tuple. `python3 tools/gen/trust/build.py [slug...]` writes `data/content/pages/<slug>.json` and prints word counts; `count.py` prints counts for every trust page. `status` keeps its live blocks (`st-live`, `st-funding`) by id. `trust-corrections` embeds its form. Edit the module and rebuild; do not edit the JSON.
- **Pages:** `trust` (index), `statement`, `sampling`, `editorial`, `corrections`, `privacy`, `cookies`, `data-rights`, `terms`, `refunds`, `security`, `accessibility`, `ads-policy`, `imprint`, plus `/status/`. Each has at least 900 words in each language, a "locally in Tunisia, and globally" section, and (for legal pages) the `VERIFY` not-legal-advice caveat. Legal and fiscal values are tokens in `config/variables.php` (`privacy.*`, `legal.*`, `quality.*`, `company.*`); `docs/LEGAL-REVIEW.md` lists every one to confirm with a Tunisian lawyer or accountant. `tests/trust.php` (in `production.sh`) checks word counts, tokens, links in 3 languages, footer and sitemap coverage, the corrections form and the status live blocks.
- **Cookie notice.** `partials/cookies.blade.php` + `js/cookies.js`: always shown until a choice is made. With no measurement tag enabled it offers a single OK (essential cookies only); with one enabled it offers Accept all / Essential only and a measurement switch. Stored in `localStorage` (`w5d-cookies`, remembered `privacy.consent_months`); the footer button `data-cookie-open` reopens it. Trackers load through `js/integrations.js` only after consent. `Integrations::csp()` still builds the per-request policy.
- **Funding fail-closed** (`src/Funding.php`, `src/Ads.php`, owner menu 39, `tests/funding.php`): `Funding::channelAvailable('F1'..'F5')` reads `.env`, and a channel is open only when its condition holds; the status page (`funding-status` block) shows each channel, the reason and the totals. `Ads::canApply()` reads `storage/cache/ads-gate.json` written by `php bin/ads-gate.php` (rendered `<main>` word counts by kind, pages under `ADS_MIN_PAGE_WORDS` do not count, stale when content changes); `Ads::canWithdraw()` needs the switch and a matricule fiscal; `Ads::allowedOn()` blocks trust, status, checkout, verification, account and owner paths. F2 and F4 PDF templates live in `docs/funding/`.
- **Footer and animation.** The footer's "Company and trust" column links every trust page (`data/content/nav/footer.json`) and shows `footer.status_{pending|auto_entrepreneur|company}` from `company.legal_form`. Card and section reveal animations are automatic (IntersectionObserver); the footer motion toggle only matters when the OS asks for reduced motion. `Docs::sitemapUrls()` gives every trust page a priority; `Pages::sitemap()` lists them for humans.

## 9. Data model (SQLite)
Core accounts, orders, subscriptions and payments are in 001–005. The `variable_overrides` table (path, value, changed_by, note) lets the owner override `config/variables.php` values at run time. Mail suppressions are in 004 and variable overrides in 005. Programs (affiliate, certification, QCM) are in 006.

## 10. Operations
Dev server: `php -S 0.0.0.0:8080 -t htdocs` (start detached with `setsid -f`). Secrets live in `.env` (never committed). Production notes are in `README.md` and `AGENTS.md`.

## Verification map added 2026-11-23
| Concern | Code | Guard |
|---|---|---|
| Google OAuth data use / branding | `config/oauth.php`, `src/OAuth.php`, `Accounts::socialCallback`, home/privacy/terms sections | `tests/google.py` |
| SMTP transport | `Mail::smtp()` | `tests/smtp.py` + `tests/_smtpstub.py` (no TLS) |
| PWA installability | `Pwa` controller, `portal.js`, admin layout | `tests/pwa.py`, `tests/pwa_install.py` |
| UX friction | templates, content links | `tests/ux.py` |
| Console code (mail down) | `bin/staff-code.php`, `StaffAuth::issueLocal()` | `tests/breakglass.py` |
| Users vs Team screens | `config/resources.php` (`users` where-clause, `team`) | `tests/breakglass.py` |
| FR/AR consistency | `tr3()`, `config/help.php`, content JSON | `tests/i18n.py`, `docs/review-checklist.md` |
