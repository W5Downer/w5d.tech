# Review checklist: what only a person can sign off

Automated checks cannot judge these. Each row says what to read, where it lives, what the machine already guarantees, and what is still
open. Nothing below has been reviewed by a native speaker, a lawyer or an accountant. Record reviewer, date and verdict at the bottom.

## What `python3 tests/i18n.py` already guarantees (so reviewers can skip it)

For every en/fr/ar triple in templates (`tr3()`), `config/help.php`, all content JSON and the rendered Setup guide:

- the same numbers, links, `code` spans, ENV_VAR names, `:placeholders` and `{{ tokens }}` in all three languages;
- Arabic is written in Arabic script (Latin only for brand/technical names), French is not a copy of the English;
- no empty or truncated translation (length outliers), same steps, variables and links in the three Setup guides;
- `<html lang dir>` is right per language (RTL for Arabic).

Known, reviewed differences are listed in `KNOWN` inside that file with the reason. Add to it only after reading both texts.

## A. French and Arabic wording (native speaker)

| # | What to read | Where | Look for |
|---|---|---|---|
| A1 | Help texts (54 screens, tooltip and popup) | `config/help.php` | Natural register for staff, correct technical terms (cache, preflight, SMTP, webhook), no calques |
| A2 | Owner Setup guide (every step, three languages) | `views/templates/w5d/admin/setup.blade.php`, page `/panel/setup-guide/` | Steps can be followed by someone who has never edited `.env`; Google Cloud Console step uses the console's real Arabic/French menu names |
| A3 | Team sign-in page and the code e-mail | `views/templates/w5d/admin/login.blade.php`, `mail/staff_code.blade.php` | Clear that there is no password, that nobody will ask for the code, how long it lives |
| A4 | Google purpose, data-use and NCII statements | `home.json` section `home-purpose`, `trust-privacy.json` `pv-google`, `trust-terms.json` `tm-content`, the note under the Google button | Same meaning as the English; Limited Use sentence is not softened |
| A5 | Arabic numerals and plurals | any text with counts | "two" as dual (ملفين) is intended; check Arabic plural agreement after `{{ tokens }}` are substituted |
| A6 | Arabic typography | any page in Arabic | Mixed Latin/Arabic runs (brand, ENV names) keep their order; punctuation does not jump to the wrong end |
| A7 | Weekday and time in service KPI text | `data/content/services/*.json` `kpi` | "Friday 12:00" is the cut-off in all three languages (fixed in this round; confirm the cut-off itself with the owner) |

## B. Legal and fiscal wording (lawyer / accountant)

Extends `docs/LEGAL-REVIEW.md`. Items added or touched in this round:

| # | Claim | Where | Question |
|---|---|---|---|
| B1 | Status words on the registry rows: **Live**, **Awaiting**, **Pending** | `data/content/pages/status.json`, section `st-register` (and the French/Arabic equivalents *Actif / En attente*, *نشط / قيد الانتظار*) | Is each word accurate in law for what exists today? "Live" must never imply a registration that has not been issued. Which Tunisian registrations must be shown on a commercial site, and in which form? |
| B2 | "No registration number, no address published" | `/trust/imprint/` | Required by law before taking payment? If yes, the site must not go live with payments before they exist |
| B3 | Google API Services User Data Policy statements (Limited Use, no AI training, no sale/advertising) | `/`, `/trust/privacy/#pv-google` | Match what the code does (guarded by `tests/google.py`); confirm they are enough for Tunisian data-protection law (INPDP) as well as Google |
| B4 | Content rules: no intimate imagery, report to authorities | `/trust/terms/` (`tm-content`) | Which authority, which duty to report, retention of evidence |
| B5 | Staff access: e-mailed one-time code, 12 h absolute / 30 min idle, audit log | `/trust/security/`, `docs/ARCHITECTURE.md` | Employee-monitoring and retention rules for the audit log |
| B6 | Console break-glass (`bin/staff-code.php`) | `AGENTS.md` | Internal policy: who may hold shell access, and that every use is logged |

## C. Things no agent can verify (owner / device)

| # | Item | Why it cannot be automated | How to check |
|---|---|---|---|
| C1 | Google accepts the branding | Google decides | After deploy, open the home page logged out on the real https domain, then retry verification in Google Cloud Console, Branding |
| C2 | Real SMTP provider delivery and TLS | `tests/smtp.py` uses a local relay without TLS; STARTTLS/SSL certificate checks, SPF/DKIM/DMARC and inbox placement need the real provider | `php bin/mail.php test you@yourmailbox` then request a team code; read headers in the mailbox |
| C3 | Real-device install of the portal as an app | Chromium confirms there are no installability errors and the install events work; the OS install dialog, home-screen icon and iOS Add-to-Home need a phone | Android Chrome: menu, Install app. iPhone Safari: Share, Add to Home Screen. Open it, sign in, switch to airplane mode |
| C4 | Screen-reader and RTL feel | Needs a person using VoiceOver/TalkBack/NVDA | Walk sign-in, the Setup guide and one dashboard page in Arabic |

## Sign-off

| Area | Reviewer | Date | Verdict / changes requested |
|---|---|---|---|
| A1-A7 French | | | |
| A1-A7 Arabic | | | |
| B1-B6 legal | | | |
| C1-C4 owner | | | |
