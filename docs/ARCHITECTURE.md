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
| `config/` | `app.php`, `variables.php` (single source of numbers), `roles.php` (roles + 36 menus), `resources.php` (CRUD), `mail.php`, `certification.php`, `apidocs/*`, `oauth.php`, `social.php` |
| `lang/` | UI strings en/fr/ar; the linter enforces identical keys |
| `db/migrations/` | `001`..`006` SQL migrations applied by `Migrator` |
| `storage/` | SQLite DB, logs (`mail-*.log`), view cache, incoming uploads (gitignored) |
| `bin/` | `lint.php`, `preflight.php`, `view.php` (cache) |
| `tools/` | `build.mjs` (Tailwind and Lucide icon vendoring), `gen/*.py` content generators |
| `tests/` | stdlib test suites and `production.sh/.py` |
| `docs/` | Prompts, notes, this file, `reviews/` |

## 2. Request lifecycle
`htdocs/index.php` → bootstrap (Env, Config, Session, DB + Migrator) → `App` builds the `Router` → first matching route wins (order in `src/App.php` matters) → controller → `view()` renders a compiled Blade template (`ViewCache`) inside the layout → `Response` with CSP and security headers. CSP: `default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline'`, so there are no inline scripts and no CDN.

## 3. Templates and content
- `src/Blade.php` compiles `.blade.php` to PHP. Components: `<x-section>`, `<x-form-field>`, `<x-ctas>`, `<x-cta>`, `<x-card-media>`, `<x-mail-*>`. Clear and rebuild the cache with `php bin/view.php cache`.
- Helpers: `__()`, `t()`, `tr3()`, `rich()`, `url()`, `abs_url()`, `asset()`, `icon()`, `csrf_field()`, `admin_url()`, `not_found()`, `view()`.
- Content pages are JSON section lists: hero, prose, kpis, steps, definitions, faq, cards, cta, notice, form, table, wizard, team. Tokens like `{{ path|filter }}` resolve against `config/variables.php` (filters: num, money, pct, first, devs, certs). Content must never hard-code prices or counts.
- Language: cookie `w5d_lang` or `/language/{code}`. `?lang=` and `/ar/` prefixes are not supported. Arabic renders RTL.

## 4. Auth, ACL, CRUD
- **Owner and staff:** `Auth`, portal at `/{ADMIN_URL_PATH}` (default `panel-x9z`), 36 menus defined in `config/roles.php`, permission checks via `ACL::can` and `Auth::require($perm)`.
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
`php bin/lint.php` (tri-lingual key parity, no hard-coded numbers, one `btn-peak` per section, CSRF on POST) and `php bin/preflight.php`. Suites: `tests/smoke.sh`, `flows`, `auth`, `d17`, `dash`, `gate`, `mail`, `crud`, `ui`, `browser`, `mobile`, `viewcache.php`, `production.sh`. Run them all before and after every change. See `docs/reviews/README.md` for the open test backlog (no QCM/cert/affiliate suites yet).

## 9. Data model (SQLite)
Core accounts, orders, subscriptions and payments are in 001–005. Mail suppressions are in 004 and variable overrides in 005. Programs (affiliate, certification, QCM) are in 006.

## 10. Operations
Dev server: `php -S 0.0.0.0:8080 -t htdocs` (start detached with `setsid -f`). Secrets live in `.env` (never committed). Production notes are in `README.md` and `AGENTS.md`.
