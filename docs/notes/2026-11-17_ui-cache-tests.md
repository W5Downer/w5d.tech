# Note: UI/UX realignment, view cache, tests (2026-11-17)

> Saved verbatim from the assistant's previous answer. See the later note
> `2026-11-17_full-platform-update.md` for the adjusted/extended version.

I fixed the console errors, realigned the design to the static template, and added a Laravel-style view cache. I also ran the full test suite and checked the UI/UX. The admin menus beyond the first few are still read-only previews, and I didn't build real screens for them.

## What was fixed and added

**1. Console errors (404s and wrong MIME type).** PHP's built-in server was sending `/assets/...` requests to `index.php`, which returned HTML. `htdocs/index.php` now serves existing static files directly, the same as the Apache rewrite rule does. Playwright (headless Chromium) shows no console errors on the pages I checked, and `site.js`, `theme-init.js` and the CSS load with the right MIME types.

**2. Design now matches the static template.**
- **Visuals:** dark ink background, grid, blurred violet/cyan/pink orbs, glass nav, gradient text, and violet->indigo buttons.
- **Blocks:** I reworked the hero, slider, call-to-action banner, macro-works cards, footer and language panel to match.
- **Layout fixes from the audit:** the mega-menu is wider, the footer has six columns, icon buttons and the hamburger button render correctly, and the checkout wizard step labels are now translated in en/fr/ar.
- **Visually checked:** home in en/ar/mobile/light mode, pricing (fr), checkout (fr), subscription (ar), and the admin login and dashboard.

**3. View cache (`src/ViewCache.php`).**
- **Storage:** compiled views go to `storage/framework/views/`.
- **Commands:** `php bin/view.php cache`, `clear` and `status`.
- **Settings:** `VIEW_CACHE` and `VIEW_CACHE_CHECK` in `.env`. By default it recompiles when the source is newer, except in production.
- **Verified:** I tested all three modes: cache on, cache off, and cache trusted without mtime checks. The trusted mode does serve a stale view until you run `view.php cache` (or `clear`).

**4. Tests.**
- `tests/smoke.sh` ran 663 route and language checks with 0 failures.
- `tests/flows.py` (new) ran 44 checks with 0 failures. It covers CSRF, validation, the honeypot, the rate limit, checkout, subscription with its management key, contact, eligibility, register, manager application, newsletter, the JSON API, price consistency, and admin login/seed/logout.
- `bin/lint.php` (new) checks that tokens resolve and fr/ar are complete. It also flags hard-coded prices, `130,000` and `2027`, CDN use, more than one primary call to action per section, and templates that don't compile. It passes now, and it caught every violation I injected as a test.

**5. README.** I corrected the pricing table. The old mid-rows were wrong, and the 0.90 row now shows the 3x cap at 30.00/210.00 TND. I added sections on the `htdocs` layout, templates, the view cache and the test commands.

## Pull request
PR #1 is updated with the squashed commit `4ca65df`: https://github.com/W5Downer/w5d.tech/pull/1

## Not done
- **Admin screens:** the remaining menus (verification, academies, teams, weekly, clients, quality, payroll, support, users, reports, compliance, countries, campaigns) are still read-only previews.
- **UI audit not finished:** I did not check the fr and ar versions of every inner page, form error states, the wizard step interaction, light mode on inner pages, or mobile on inner pages.
- **Docs:** `docs/templates.md`, `design.md`, `pricing.md` and `ontology-six-layers.md` are still missing.
- **Cache unit tests:** there are no PHP unit tests for the cache itself.
