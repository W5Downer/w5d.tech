# W5D full-platform update: status and pre-upload note (2026-11-17)

## Origin of the two reported errors
Both errors came from **https://w5d.tech** (Apache, old code), not from the sandbox preview.

| Error | Cause | Fix in this repo (live only after upload) |
|---|---|---|
| `no such table: applicants` (`/app/register`, `/checkout/`) | The production SQLite file was empty or never migrated | `Migrator` auto-migrates on boot, and `RefData::sync()` loads the reference data. Verified on a fresh DB. Also available: `php bin/migrate.php` |
| `/lang/ar?r=%2F` returned "requested URL was not found" | A real `lang/` folder exists, so Apache answers `/lang/*` itself | Canonical route is `/language/{code}` (the language aside already uses it). `/lang/{code}` is still routed where Apache lets it through. Root `.htaccess` and `index.php` shim added |

Other production problems found by read-only probes and fixed in the repo:
- `/openapi.json` and `/.well-known/*` returned 403. The FilesMatch regex is corrected, and `/api/openapi.json` and `/api/agents.json` aliases are added.
- `APP_URL` is empty on production. `Env::baseUrl()` now falls back to the request host.

## Done in this branch
- Auto-migrate and reference data.
- Accounts: login, register with e-mail verification, forgot/reset password, remember me, social-login code path, user dashboard with panels and notifications.
- Mail: drivers log/smtp/mail, 5 types (verify_email, password_reset, order_receipt, payment_status, form_ack) in en/fr/ar, outbox table, hooks in the forms.
- D17 payment for Tunisia: GSM from `.env`, signed pay link, admin verify/reject screen.
- `security.txt`, `humans.txt` and `manifest.webmanifest` routes.
- `config/social.php` and `src/Social.php` (share networks and image aliases from `.env`).
- `src/Seo.php` (Organization, WebSite, WebPage, BreadcrumbList, FAQPage, Product/Offer, Service JSON-LD builder; not yet wired into the layout).
- `nav.home`, `nav.audiences`, `nav.account`, `nav.user` and `nav.share` lang keys in en/fr/ar.
- Tests passing on the dev preview: `tests/auth.py` 37/37, `tests/d17.py` 25/25, `bin/lint.php` OK.

## NOT done yet (do not assume these exist)
1. Layout `<head>` wiring: og:image/url/site_name/locale, Twitter cards, JSON-LD output, favicon/manifest links (`Seo.php` is written but not called).
2. Header share icon with `#aside-share`, and user icon with `#aside-user` (guest and authenticated).
3. Desktop mega menu: full width, 100% opaque, same animation as mobile.
4. The 7 audience homepages (`/for/{owner|admin|leader|member|investor|customer|user}/`) with their CTAs.
5. `sitemap.xml` rewrite, `robots.txt` additions (Disallow `/account/`, `/pay/`, `/language/`, `/lang/`), and generated PNG/JPG assets (icon-192/512/maskable, apple-touch-icon, favicon, `social/og-*.jpg`). The manifest currently points to icons that do not exist yet.
6. `docs/social-share-image-prompts.md`.
7. Friendly 503 page when the database fails.
8. Asset rebuild (`cd tools && node build.mjs`), view cache rebuild, a full crawl test, README update.
9. Known nits: dead `$tok` line in `Accounts::register`; `pay.blade.php` uses `data-copy` where it needs `data-copy-text`.

## Pre-upload checklist for https://w5d.tech
- Set `APP_ENV=production` and `APP_URL=https://w5d.tech`.
- Replace `WEBMASTER_PASSWORD_HASH`. Remove the demo credential `w5d-demo-owner`.
- Make `storage/` and the SQLite file's directory writable. The first request creates the schema.
- Set `MAIL_DRIVER=smtp` and the SMTP credentials, then send a test.
- Set `PAYMENT_D17_ENABLED`, `PAYMENT_D17_GSM`, `PAYMENT_D17_NAME` and `PAYMENT_D17_NOTE`.
- Keep `SOCIAL_SHARE_*` as needed.
- After upload, re-check `/language/ar?r=%2F`, `/checkout/`, `/app/register/`, `/api/openapi.json` and `/.well-known/security.txt`.
