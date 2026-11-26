# Installing and configuring w5d.tech

This guide has three parts, in the order you will need them:

1. **First upload** – for the *webmaster*: get the site running on a server (about an hour, mostly waiting for DNS, TLS and mail).
2. **After the first sign-in** – for the *owner*: configure the portal, in the portal.
3. **Later** – updates, backups, recovery, and what to do when something is wrong.

The same two guides (webmaster and owner) are inside the portal, in three languages, under **Setup guide**, with live "set / empty" badges and direct links to each screen. Use whichever is more comfortable; they say the same thing.

> **Honest limits.** The installer and the server snippets below were written from documentation and tested only on a development machine and in a throw-away copy (`tests/install.py`). They have **not** been run on your real host. Nothing here is legal, tax or accounting advice: wording about registration, VAT and payment providers is research to confirm with a Tunisian accountant and lawyer (`docs/LEGAL-REVIEW.md`). No page of the site promises income, returns or jobs, and this guide does not either.

---

## 0. What you need

| Need | Detail |
|---|---|
| PHP | **8.4 or newer**, for the web site *and* for the command line (they are often different versions on shared hosting: check both) |
| Required extensions | `pdo_sqlite`, `mbstring`, `json`, `openssl`, `curl` |
| Optional extensions | `gd` **with WebP** and `zip` – only for the owner *Image library*; the public site works without them |
| Database | SQLite, created automatically in `storage/w5d.sqlite`. No database server |
| Composer / Node | **Not needed on the server.** Compiled CSS and JavaScript are committed. Node is only for a development machine (`cd tools && node build.mjs`) |
| Domain + HTTPS | A domain whose document root you can point at `htdocs/`, and a TLS certificate |
| Mailbox | A real SMTP account (host, port, user, password) on a domain with SPF/DKIM. **Team sign-in codes travel by e-mail** |
| Shell | SSH access, or at least a way to run PHP scripts from the command line and to edit crontab |

---

## 1. First upload (webmaster)

### 1.1 Upload the project

```bash
git clone https://github.com/W5Downer/w5d.tech.git w5d
cd w5d
php -v            # must print 8.4 or higher
```

Without git: upload every folder by SFTP (`src`, `config`, `data`, `db`, `lang`, `views`, `bin`, `htdocs`, `tools`, `docs`) plus `.env.example`, `index.php`.
**Do not upload** your development `.env`, `storage/*.sqlite` or `node_modules`.

### 1.2 Run the installer

```bash
php bin/install.php --check     # read-only: shows what is missing, changes nothing
php bin/install.php             # guided
```

Non-interactive (provisioning scripts):

```bash
echo 'a-long-owner-passphrase' | php bin/install.php --yes \
  --url=https://your-domain --owner=owner@your-domain --password-stdin
```

What it does, step by step (each line prints `OK`, `WARN` or `FAIL`; it is safe to run again):

1. Checks PHP ≥ 8.4 and the extensions (missing `gd`/`zip` are warnings only).
2. Creates `storage/{logs,sessions,cache,framework/views,uploads,archive,backups,incoming}` and tightens permissions.
3. Creates `.env` from `.env.example` **only if none exists** (`--force-env` rebuilds it and keeps `.env.bak-<time>`), sets `APP_ENV=production`, `APP_URL`, `OWNER_EMAIL`, `STAFF_EMAIL_DOMAIN`, a **random** `ADMIN_URL_PATH`, `PAYMENT_MODE=demo`, and – if you type one – stores the owner password **as an Argon2id hash only**. File mode becomes `600`.
4. Applies the database migrations and synchronises the content reference tables.
5. Creates the signing key `storage/app.key` (mode `600`).
6. Compiles the view cache and runs `bin/preflight.php`.
7. Prints the portal address, the cron lines with your real paths, and the checklist below.

It never sends e-mail, never touches payment settings and never prints a secret. The **portal address** it prints is shown in full only there: write it down.

Common refusals: `APP_URL` must be `https://host` without a path; the owner address must be on the team domain; the portal path must be 6–60 letters/digits/`-`/`_` and not `admin`, `panel`, `panel-admin` or `dashboard`.

### 1.3 Point the web server at `htdocs/`

The domain's **document root must be `htdocs/`**, never the project folder – `.env`, `storage/`, `src/` and `bin/` must not be reachable.

**Apache** works as uploaded: `htdocs/.htaccess` carries the front-controller rewrite, the denied file types and the security headers. The virtual host needs `AllowOverride All`, `mod_rewrite` and `mod_headers`.

**nginx** (adapt paths and the PHP-FPM socket):

```nginx
server {
  listen 443 ssl;
  server_name your-domain;
  root /var/www/w5d/htdocs;
  index index.php;
  location / { try_files $uri /index.php?$query_string; }
  location = /index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; fastcgi_pass unix:/run/php/php8.4-fpm.sock; }
  location ~ /\.(?!well-known) { deny all; }
  location ~* \.(env|sqlite|sql|log|md|bak)$ { deny all; }
  location /assets/ { expires 1y; add_header Cache-Control "public, immutable"; try_files $uri =404; }
}
```

nginx ignores `.htaccess`, so the security headers (CSP, `X-Frame-Options`, …) are added by the application itself (`src/App.php`, `Integrations::csp()`); you only need to keep the deny rules above.

**Hosts with a fixed public folder** (`public_html`, `www`): ask the host to point the domain at `htdocs/`, or replace that folder by a symbolic link to it, with the project itself *outside* the public folder.

**Check:** `https://your-domain/.env`, `/storage/w5d.sqlite` and `/src/Env.php` must answer **403 or 404**, never a download.

### 1.4 HTTPS

Install a certificate (host panel or Let's Encrypt) and redirect http → https. In production the session cookies are `Secure`, so sign-in does not work over plain http. If a proxy terminates TLS it must send `X-Forwarded-Proto: https`.

### 1.5 Make e-mail send

Edit `.env` (permissions `600`):

```ini
MAIL_DRIVER=smtp
MAIL_HOST=smtp.provider.example
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=no-reply@your-domain
MAIL_FOOTER_ADDRESS=<your real postal / company line>
```

```bash
php bin/mail.php test you@your-domain en all
php bin/mail.php status
```

Until a test message arrives, nobody can receive a team sign-in code. **Locked out?** From the server shell only: `php bin/staff-code.php owner@your-domain` prints a one-time code for an address that is *already* authorised (it cannot create access).

### 1.6 Scheduled jobs

`crontab -e` as the PHP user (the installer prints these with your absolute paths):

```cron
*/10 * * * *  php /path/to/w5d/bin/payments-sync.php   # re-check online payments whose webhook was missed
15 6 * * *    php /path/to/w5d/bin/billing.php         # plan renewal invoices and grace periods
5 * * * *     php /path/to/w5d/bin/rollup.php          # dashboard figures
30 3 * * *    php /path/to/w5d/bin/backup.php          # consistent database snapshot
45 3 * * 0    php /path/to/w5d/bin/archive.php         # weekly clean-up
*/15 * * * *  php /path/to/w5d/bin/health.php         # storage thresholds
```

`billing.php` never charges anybody: it sends invoices that the buyer pays like any other bill.

### 1.7 Backups

A copy on the same server is not a backup. Every day pull `storage/backups/latest.sqlite` **and** `storage/app.key` to another place (`scp`, `rsync`, host backup tool), keep several dated copies and try one restore. Without `app.key`, old payment and unsubscribe links stop working.

### 1.8 Verify and hand over

```bash
php bin/preflight.php     # must show no FAIL
php bin/view.php cache
```

Open the public site in English, French and Arabic; open `/sitemap.xml` and `/robots.txt`; open the portal address; request a sign-in code with the owner address and confirm it arrives. Then send the owner the portal address by a private channel.

### First-upload checklist

- [ ] PHP 8.4+ for web **and** CLI
- [ ] Project uploaded; no development `.env`/database copied
- [ ] `php bin/install.php` ended with *no FAIL*; portal address noted
- [ ] Document root = `htdocs/`; `/.env`, `/storage/…`, `/src/…` give 403/404
- [ ] HTTPS on, http redirects
- [ ] SMTP test message received
- [ ] Cron lines added; `storage/backups/latest.sqlite` appears after the night run
- [ ] Off-server backup of the database **and** `storage/app.key` tested once
- [ ] `php bin/preflight.php` shows no FAIL
- [ ] Owner signed in with an e-mailed code
- [ ] `PAYMENT_MODE` still `demo` and **no payment method switched on yet**

---

## 2. After the first sign-in (owner)

Open `https://your-domain/<portal-path>/`, enter the owner e-mail and your password *or* the 6-digit code from the e-mail. Every link below is a portal screen; every change is written to the audit log with your name. Money screens stay empty or switched off until you fill and switch them on.

| # | Step | Where | Notes |
|---|---|---|---|
| 1 | Read the dashboard | `/<portal>/command-center/` | Figures, open tasks, shortcuts |
| 2 | Add your team | `/<portal>/team/` | Address on the team domain + role (admin, super manager, manager, member). Disable people the day they leave |
| 3 | Check variables | `/<portal>/variables/` | Every number the site shows or uses: currency, company status (**keep `pending` until a registration really exists**), certificate validity, billing delays, affiliate programme |
| 4 | Payment methods | `/<portal>/payment-methods/` | Switch on **only** methods you can really receive (D17, bank transfer, Flouci wallet, local peer-to-peer; online Flouci/Konnect/Paymee after the merchant contract). Secrets are stored encrypted and never shown again. Register `…/pay/hook/flouci`, `…/pay/hook/konnect`, `…/pay/hook/paymee` in each provider's dashboard. Test in the provider sandbox, then with one small real payment you could refund |
| 5 | Pricing curve | `/<portal>/progressive-pricing/` | Published constants; changes are dated and logged |
| 6 | Revenue streams | `/<portal>/milestones/`, `/plans/`, `/cert-fees/`, `/placements/` | Each starts off or empty. Open **one at a time**: fill, switch on, test-purchase with a test account, check the invoice, then the next |
| 7 | Invoices & payments | `/<portal>/invoices/`, `/payments-d17/` | A manual payment is marked received only after a person checks it against your own bank/wallet statement |
| 8 | Affiliates | `/<portal>/affiliates/` | Approve or suspend (or set automatic approval in Variables) |
| 9 | Pictures, pages, integrations | `/<portal>/images/`, `/content/`, `/integrations/` | Image library turns pictures/ZIP into uniform `.webp`; analytics/ads load only after the visitor accepts the matching cookies |
| 10 | Go-live check | `/<portal>/system/` | See the checklist below |

### Affiliate share links

A subscriber who is **signed in**, has an **active** affiliate profile and has paid for at least `affiliate.share_min_parts` part(s) (default 1, editable in Variables) gets their `?ref=CODE` added to **every** Share button on every page: *native share*, *copy link*, the link shown in the panel, and the WhatsApp, Facebook, X, LinkedIn, Telegram, Reddit and e-mail links. Everyone else shares the clean URL. Pending or suspended affiliates, unpaid or pledged orders, and a paused programme (`affiliate.enabled` = 0) add nothing. The canonical and Open Graph tags are never changed. Commissions are recorded only for verified payments and are paid out by hand.

### Go-live checklist

- [ ] System screen shows no error
- [ ] Mail test received
- [ ] Every payment method you offer was tested with a small refundable real payment
- [ ] Your accountant and lawyer read `docs/LEGAL-REVIEW.md`
- [ ] Legal pages contain only real company details; the footer states the true company status
- [ ] When in doubt, leave a revenue stream switched off

### `PAYMENT_MODE`

`PAYMENT_MODE` is only a **label** shown in *System* and checked by the preflight. It does not switch anything on or off. What decides whether money can move is which payment methods are switched on **and complete** in *Payment methods*; a method that is off or incomplete is never offered to buyers.

---

## 3. Later

### Updating

```bash
php bin/backup.php            # 1. backup first
git pull                      # 2. new files (or upload them)
php bin/migrate.php           # 3. new database migrations
php bin/view.php cache        # 4. production trusts compiled views: skip this and old pages stay
php bin/preflight.php         # 5. no FAIL
```

Never edit `htdocs/assets/_base/css/bundle*.css` or `js/*.min.js`: edit the sources (`attention.css`, `site.js`, `forms.js`, `portal.js`, `cookies.js`) on a development machine and run `cd tools && node build.mjs`, then commit the result.

### Restoring

Stop writes, replace `storage/w5d.sqlite` with a backup copy (delete `-wal`/`-shm` next to it), put the matching `storage/app.key` back, run `php bin/preflight.php`.

### Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| White page / 500 | `storage/logs/app-<date>.log`; PHP version differs between web and CLI; missing extension |
| Portal address gives 404 | `ADMIN_URL_PATH` in `.env` differs from the address you use; view cache stale (`php bin/view.php cache`) |
| Sign-in code never arrives | `MAIL_DRIVER=log` or wrong SMTP values; check `php bin/mail.php status` and the spam folder; break-glass `php bin/staff-code.php <address>` |
| Cannot stay signed in | Site served over http, or a proxy not sending `X-Forwarded-Proto: https` |
| Old page after an update | `php bin/view.php cache` |
| Images tool says it cannot convert | `gd` with WebP missing (preflight shows it as a warning) |
| Payment stuck "pending" | Webhook not registered at the provider, or `payments-sync.php` not in cron |
| Share links without `?ref=` | Not signed in, affiliate not active, no paid part yet, programme paused, or `affiliate.share_min_parts` higher than the parts paid |

### What this installer cannot know

- Your host's PHP selection, `open_basedir`, disabled functions (`exec`, `shell_exec` are used only by the installer, never by the site) and cron limits.
- Whether your host honours `.htaccess`, symbolic links or per-site PHP versions.
- Your mail provider's rules (SPF/DKIM, sender limits).
- Anything about payment-provider contracts, VAT or company status.

Treat the first run on a real host as a rehearsal: use `--check`, read each line, and keep the preflight green.
