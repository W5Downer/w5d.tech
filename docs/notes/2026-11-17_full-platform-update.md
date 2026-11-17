# W5D - status and pre-upload checklist (2026-11-17, final for this session)

## Done
Auto-migrate DB, /language/{code}, Apache fixes, accounts (login, register + verification, forgot/reset, remember-me, social path), user dashboard, mail backend + pro responsive templates (5 types x 3 languages), unsubscribe footer + List-Unsubscribe + /unsubscribe/ + suppression, D17 payment with admin verification, SEO head + JSON-LD, sitemap/robots/security/humans/manifest, share + user asides in the navbar, full-width opaque mega menu, 7 audience homepages, social image prompts + placeholders, friendly production error page, `bin/mail.php`.

## Test results
lint OK; smoke 720/0; auth 37/0; d17 25/0; mail 88/0; ui (Playwright) 64/0; flows 43 passed, 1 failed.

## Known open items
1. `tests/flows.py` "quote 1 part x 1 day starting today = published unit price" fails (quote schedule total vs today's unit differ). Not investigated; check `/api/quote` rounding against `Pricing.php` before relying on it.
2. Newsletter admin list does not yet show unsubscribed rows (data is in `mail_suppressions` / `newsletter_subscribers.unsubscribed_at`; use `php bin/mail.php suppressed`).
3. Social images are generated placeholders; replace with final art following `docs/social-share-image-prompts.md`.
4. Not verified on the real production host (Apache, ModSecurity), only on the PHP dev server.
5. Fixed this session: closed mobile menu inflated the fixed header to ~686px and swallowed clicks on desktop pages; `scroll-padding-top` added.

## Before uploading to https://w5d.tech
- `.env`: APP_ENV=production, APP_URL=https://w5d.tech, strong WEBMASTER_PASSWORD_HASH (remove demo credential `w5d-demo-owner`), MAIL_DRIVER=smtp + credentials + MAIL_FOOTER_ADDRESS, PAYMENT_D17_* (GSM number), SOCIAL_*, storage/ writable.
- Run `php bin/mail.php test you@yourdomain fr all` and confirm delivery and the unsubscribe link.
- `VIEW_CACHE_CHECK=false` then `php bin/view.php cache`.
- After upload verify: `/language/ar?r=%2F`, `/checkout/`, `/account/register/`, `/api/openapi.json`, `/.well-known/security.txt`, `/sitemap.xml`, `/unsubscribe/` (400 friendly page without params).
