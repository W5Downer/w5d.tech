# AGENTS.md: handoff for the next AI agent

Read this file first, then `README.md` (product + doctrine) and the `docs/` pages it links. Last verified: **2026-11-18**, branch `genspark_ai_developer`.

## 1. What this project is (30 seconds)

**w5d.tech**: a PHP 8.4 + SQLite CMS. No framework, no Composer, no CDN, no jQuery. 5 macro works x 26 = 130 services, 25 projects, 3 languages (en/fr/ar, RTL), one template (`w5d`), progressive "money x time" pricing, accounts, D17 payments (manual verification), mail with unsubscribe, owner portal with 31 menus, public API + OpenAPI.

Content = JSON in `data/content/`. State = SQLite in `storage/w5d.sqlite` (auto-migrated from `db/migrations/*.sql`). Templates = Blade-style, compiled by `src/Blade.php`, cached in `storage/framework/views/`.

## 2. Run it

```bash
cd /home/user/webapp
php -S 0.0.0.0:8080 -t htdocs htdocs/index.php       # dev server (must be started with setsid/background in the sandbox)
# owner portal: /panel-x9z/login/   user: owner   dev password: w5d-demo-owner  (DEV ONLY; production rejects it, see preflight)
```

Docroot is `htdocs/`. A root `.htaccess` + `index.php` exist only for hosts that force docroot = repo root.

## 3. Tests: the contract. Run ALL before and after every change

| Command | What | Expected (2026-11-18) |
|---|---|---|
| `php bin/lint.php` | tokens resolve, fr/ar complete, no hard-coded price/130,000/2027, no CDN, one `btn-peak` per section, templates compile | `LINT OK: 0 violation(s)` |
| `php tests/viewcache.php` | unit tests of `ViewCache` (no server) | 27/0 |
| `bash tests/smoke.sh` | every public route x en/fr/ar = 200 | `checked 720, failed 0` |
| `python3 tests/flows.py` | CSRF, forms, honeypot, rate limit, checkout, subscription, API, pricing, **30 admin menus**, seed, logout | 69/0 |
| `python3 tests/auth.py` | register/verify/login/remember/forgot/reset/safety | 37/0 |
| `python3 tests/d17.py` | D17 payment flow + admin verification + mails | 25/0 |
| `python3 tests/dash.py` | audience hub, per-role dashboards, API guides | 105/0 |
| `python3 tests/gate.py` | sign-in gate for forms (`gate_forms` flag, `next` redirect) | 38/0 |
| `python3 tests/mail.py` | 5 mail types x 3 languages, unsubscribe, List-Unsubscribe, suppression | 88/0 |
| `python3 tests/ui.py` | Playwright: desktop UI, SEO head, JSON-LD, console errors | 64/0 |
| `python3 tests/mobile.py` | Playwright: 320/360/390 px, no overflow, public + admin | 330/0 |
| **`bash tests/production.sh`** | **full production simulation** (see 3.1) | `ALL GREEN`, 17 steps, ~4 min |

The functional suites need the dev server on `:8080` and write demo rows (`@example.test` emails) into the dev DB. Never run them against a real database. Run them sequentially (shared rate limiter + `storage/cache/rl`).

### 3.1 Production test (`tests/production.sh`)

Builds a throw-away copy in `storage/prodtest/` (gitignored, deleted afterwards unless `--keep`), with `APP_ENV=production`, `APP_URL=https://localhost:8443`, fresh DB, Argon2id owner password, `VIEW_CACHE_CHECK=false` (trusted pre-compiled cache), PHP server on `:8081` behind a local TLS proxy (`tests/tls_proxy.py`, adds `X-Forwarded-Proto: https`). It then runs: view-cache build, `bin/preflight.php`, lint, viewcache unit tests, `tests/production.py` (security headers on HTML/JSON/404, HSTS, cookie flags, 24 hidden-path probes, traversal/NUL, error pages without internals, canonical/hreflang/og absolute https, sitemap completeness, robots, security.txt, openapi, noindex on private pages, open-redirect + header injection, demo password rejected, no secrets in admin screens), smoke over HTTPS, all functional suites, both Playwright suites, and a final scan of the app log for exceptions. Flags: `--quick` (skip browsers), `--keep`. Env overrides: `PROD_PHP_PORT`, `PROD_TLS_PORT`.

The functional suites accept `W5D_OWNER_PASS` (owner password) and `SMOKE_CURL_OPTS` (e.g. `-k`); `tests/_prod/sitecustomize.py` makes Python trust the self-signed test cert (only via `PYTHONPATH` set by `production.sh`).

### 3.2 Deploy readiness (`php bin/preflight.php [--json]`)

Run on the real host after editing `.env`. Exit 1 on any FAIL. The dev `.env` is EXPECTED to fail 3 checks (APP_ENV, APP_URL, demo password). `PREFLIGHT_ALLOW_LOCAL_URL=1` is used only by `production.sh`.

## 4. Where things are

```
htdocs/index.php            front controller (+ static passthrough for php -S)
src/App.php                 boot, router table (all routes), security headers, HSTS, error page
src/controllers/            Pages Forms Checkout Accounts Admin Api Docs Unsubscribe
src/Pricing.php             THE price function (only place a price is computed)  -> docs/pricing.md
src/Blade.php ViewEnv.php View.php ViewCache.php                                  -> docs/templates.md
src/Variables.php + config/variables.php   every number the site prints (tokens {{ money.base_day }})
src/ACL.php config/roles.php               8 roles, 31 menus, permissions
src/Mail.php Payments.php Account.php Auth.php Gate.php Seo.php Social.php OAuth.php
views/templates/w5d/        layouts, partials, components, 33 blocks, pages, account, admin, mail
data/content/               pages(37) macros services(130) projects(25) layers(6) sliders nav forms
lang/{en,fr,ar}.php         UI strings (fr/ar must have every key)
db/migrations/              001..005 (auto-run by Migrator on first DB use; DB_AUTO_MIGRATE=false to disable)
bin/                        lint view preflight migrate mail rollup archive backup health sync-content
tools/                      Node asset build (Tailwind, Lucide subset, fonts) + Python generators (tools/gen)
docs/                       pricing.md templates.md design.md ontology-six-layers.md social-share-image-prompts.md notes/
```

## 5. Rules that must not be broken (the linter and tests enforce most)

1. **One fact, one place.** Never type a price, a count, a year, or `130,000` in a template/content/lang file. Use tokens (`{{ money.base_day }}`, `{{ counts.services }}`, `{{ dates.offer_year }}`). Prices come only from `Pricing::perPart()`.
2. **Honesty (Sidq).** `experts.current` is 0; real footprint is 26 developers. 130,000 is a design equation, not employment. No page promises a job; pages that mention the figure link to `/trust/statement/`.
3. **Three languages always.** Every user-facing string/content field needs en + fr + ar. Add to all three or lint fails. Use logical CSS (`ms-/me-/text-start`), not `ml-/mr-`.
4. **One `btn-peak` per section**; each section has exactly one reading tier (e/i/x) and focus level (1-5). See `docs/design.md`.
5. **No third-party runtime requests** (CSP is `default-src 'self'`; `script-src 'self'`, no inline scripts). New JS goes in `htdocs/assets/_base/js/site.js`.
6. **Every POST needs `csrf_field()`**; forms go through `Forms::guard()` (honeypot + rate limit). New write endpoints need a test in `tests/flows.py`.
7. **Never select `password_hash`, tokens or `.env` values into any admin screen** (tested: no `$argon2`/`password_hash` in any menu).
8. After editing a template: `php bin/view.php cache` if `VIEW_CACHE_CHECK=false`. After editing `Blade.php`: bump `ViewCache::COMPILER`.
9. Server timezone logic is **Africa/Tunis**; the test runner is UTC. Compute "today" in Africa/Tunis in tests (this was the cause of the old `quote` test failure).
10. Do not commit `.env`, `storage/*`, `node_modules`. `.gitignore` covers them.

## 5.1 Recipes

- **New page**: `data/content/pages/<slug>.json` (en/fr/ar) + route if not a generic page + `tests/smoke.sh` path list + sitemap source; `php bin/lint.php`.
- **New block type**: `views/templates/w5d/blocks/<type>.blade.php`; add to `docs/templates.md` list.
- **New admin screen**: add an arm in `Admin::menu()` (`vTable($sql)` = read-only list; `vTable($sql, 'table')` + `STATUSES` for status editing). Add the slug to `ADMIN_MENUS` in `tests/flows.py` if new.
- **New migration**: `db/migrations/00N_name.sql` (never edit applied ones). Production applies it on first request or `php bin/migrate.php`.
- **Change pricing constants**: admin `progressive-pricing` (audited) or `config/variables.php` (+ dated commit). Never inline.
- **Icons**: use `icon('name')`; then `cd tools && node build.mjs` to rebuild the subset (output committed).

## 6. Current state (what is DONE)

Everything from the 2026-11-17 notes plus, this session: all 31 admin menus are real screens (13 formerly "Read-only preview" now list real rows: countries, campaigns, verification, academies, teams, weekly, clients, quality, payroll, support, users, reports, compliance); newsletter list shows unsubscribed state (verified); `flows.py` quote test fixed (timezone); `docs/{pricing,templates,design,ontology-six-layers}.md` written; `tests/viewcache.php`; `tests/production.sh` + `production.py` + `tls_proxy.py`; `bin/preflight.php`; HSTS (production + https `APP_URL` only); `X-Powered-By` removed (PHP + both `.htaccess`).

## 7. OPEN ITEMS (honest list, highest value first)

1. **Not tested on the real host** (Apache + ModSecurity + real TLS + real SMTP). `production.sh` simulates production on PHP's built-in server only. After upload run: `php bin/preflight.php`, `php bin/mail.php test you@domain fr all`, then the checklist in section 8.
2. **Owner action required before go-live** (cannot be done by an agent): real `WEBMASTER_PASSWORD_HASH`; SMTP credentials + `MAIL_FOOTER_ADDRESS`; real `PAYMENT_D17_GSM` (dev value `+21620123456` is a demo); `SOCIAL_*` profile URLs; cron for `bin/rollup.php` and `bin/backup.php`.
3. **Social share images are generated placeholders** (`htdocs/assets/_base/images/social/`). Replace with final art per `docs/social-share-image-prompts.md` (same filenames).
4. **Admin screens are read-only lists** except: status editing (manager applications, inbox, subscriptions), D17 review, pricing publish, variables, seed. No create/edit UI for teams, cohorts, payroll, tickets, campaigns, clients, quality. Tables and ACL permissions exist; building edit forms is the next feature area (follow the `reviewPayment` pattern: permission check, CSRF, audit row, flash).
5. **Admin table headers/notes are English only** (owner-only screens); lint exempts them. i18n them if non-English staff will use the portal.
6. **UI audit gaps**: Playwright covers desktop guest/signed-in flows, JSON-LD, console errors, and 320-390 px overflow. Not covered: visual regression, form error states per language on every page, light theme on every inner page, keyboard-only navigation, axe/WCAG automated scan.
7. **Load/performance not measured** (php -S is single-threaded). Expected host: PHP-FPM/Apache with OPcache; enable `opcache.enable=1`, keep `VIEW_CACHE_CHECK=false`.
8. **Rate limiter and sessions are file-based** (`storage/cache/rl`, `storage/sessions`): fine for one VPS; not for multi-node.
9. `tests/` suites reset `storage/cache/rl` and seed `@example.test` rows: dev DB only. Use "Purge demo data" in the owner portal to clean.
10. `tools/node_modules` (87 MB) is gitignored but lives in the working dir; do not upload it to the host.

## 8. Post-upload verification checklist (https://w5d.tech)

`.env` from `.env.example` (production values) -> `chmod 600 .env` -> `chmod -R u+rwX storage` -> `php bin/view.php cache` -> `php bin/preflight.php` (0 FAIL) -> then check: `/language/ar?r=%2F` (302 to `/`), `/checkout/` (302 to sign-in for guests), `/account/register/`, `/api/openapi.json`, `/.well-known/security.txt`, `/sitemap.xml` (https locs), `/unsubscribe/` (400 friendly page), `/.env` `/src/App.php` `/storage/w5d.sqlite` `/.git/config` (all 404), response headers include HSTS + CSP, `/panel-x9z/login/` works with the NEW password and the demo password is refused. Optionally run `W5D_OWNER_PASS=... python3 tests/production.py https://w5d.tech` (read-only checks; do NOT run the write suites against production).

## 9. Working agreements for agents

- Work on branch `genspark_ai_developer`; commit after each coherent change (`type(scope): message`); before a PR: `git fetch origin main`, rebase/merge (prefer remote on conflicts), squash into one commit (`git reset --soft HEAD~N && git commit`), `git push -f origin genspark_ai_developer`, open/update the PR to `main`, share the URL.
- Start servers detached: `setsid -f php -S 0.0.0.0:8080 -t htdocs htdocs/index.php > /tmp/php8080.log 2>&1 < /dev/null`. Stop servers by port (`ss -ltnpH "sport = :8080"`), never `pkill -f` patterns that can match your own shell (it killed a tool call once).
- Prefer editing existing files; keep docs in `docs/`; add a dated note to `docs/notes/` when a session ends with open items.
- Never claim a test passed without running it; paste the pass/fail counts. If a number in this file drifts, fix this file in the same commit.
- Secrets: never print `.env` values, hashes, or `$GSK_TOKEN`.
