# w5d.tech

**A dynamic PHP + SQLite CMS for the W5D Team website — five macro works, 130 services, six ontology layers, one canonical front-end template, three languages, country-scoped, single domain, progressive pricing.**

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![SQLite](https://img.shields.io/badge/SQLite-3-003B57?logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![Template](https://img.shields.io/badge/template-w5d-0A5EFF)](https://github.com/Kaddoussi/w5d)
[![Languages](https://img.shields.io/badge/languages-en%20%C2%B7%20fr%20%C2%B7%20ar-0A5EFF)](#languages)
[![Sampling](https://img.shields.io/badge/sampling-5%25-0A5EFF)](#quality--sampling)
[![Pricing](https://img.shields.io/badge/pricing-progressive-0A5EFF)](#progressive-pricing)
[![Footprint](https://img.shields.io/badge/VPS-100%20MB-0A5EFF)](#storage-footprint)
[![License](https://img.shields.io/badge/License-610.5%20TND%2Fh%20(before%202027)-0A5EFF)](#license)

> **W5D** — *World Five Dimensions* — is a company and a working ontology operating across five macro works: **Cultures/Humanities**, **Built Environment**, **Sciences**, **Governance**, and **Technology**. This CMS renders that ontology as a live website with a registration and certification portal attached.

---

## Table of Contents

- [What this is](#what-this-is)
- [The owner's strategy (130,000 experts)](#the-owners-strategy-130000-experts)
- [Progressive pricing (money × time)](#progressive-pricing-money--time)
- [Deployment model](#deployment-model)
- [Database driver](#database-driver)
- [Storage footprint](#storage-footprint)
- [Architecture](#architecture)
- [The one front-end template: `w5d`](#the-one-front-end-template-w5d)
- [Design system](#design-system)
- [Pages](#pages)
- [Owner dashboard](#owner-dashboard)
- [Quality & sampling](#quality--sampling)
- [Single source of truth](#single-source-of-truth)
- [Data model](#data-model)
- [Requirements](#requirements)
- [Installation](#installation)
- [Security](#security)
- [Directory structure](#directory-structure)
- [Languages](#languages)
- [License](#license)
- [AI Agent Prompt](#ai-agent-prompt)
- [Author](#author)

---

## What this is

W5D PHP CMS is a **dynamic content management system**: PHP 8.2 + SQLite 3 by default (MySQL or PostgreSQL optionally, via `.env`), no framework, no Composer, no third‑party runtime requests. Every page is rendered at request time from two layers:

- **Content** — JSON files under `data/content/` (pages, macros, services, projects, ontology layers, audiences, variables). Content is versioned in git, reviewed by the owner, and edited as files.
- **State** — SQLite tables (users, applicants, teams, weekly reports, payroll, tickets, audit). State is edited through the portal.

Both layers are joined by **slug** at render time. A service JSON defines what the service *is*; the `services_country` table says whether it is *active* in Tunisia and how many seats it has.

Three rules the engine enforces:

1. **One fact, one place.** Every number, vocabulary, country, and service fact is declared once — in `config/variables.php`, `config/vocab.php`, `data/countries/{iso}.json`, or the content JSON. Duplicates fail the linter.
2. **One canonical front‑end template.** The site renders in the single template **`w5d`** — imported from `Kaddoussi/w5d`, the owner's original work. Blocks resolve two‑tier: `views/templates/w5d/blocks/{type}.php` → `views/_blocks/{type}.php`. The template restyles; it never restates content.
3. **Every number is a token.** Page JSON refers to variables as `{{ counts.services }}`. Tokens resolve at render; unresolved tokens fail the linter.

**No CDN, no jQuery, no icon font.** The carousel, theme switch, language switcher, and map are dependency‑free in `assets/_base/js/site.js` and `assets/_base/css/attention.css`. The `w5d` template's original Tailwind + Lucide sources are compiled/vendored locally during onboarding (see §The one front‑end template).

**Honesty constraint.** `experts_current` is **0** today. The real footprint is **26 developers in Tunisia**. "130,000 experts" is a **design equation** (5 × 26 × 1,000), not a claim of employment. Every page that mentions the number links to `/trust/statement/`, and no page promises a job. See `docs/ontology-six-layers.md`.

---

## The owner's strategy (130,000 experts)

The owner runs a **single dynamic strategy**: turn 130,000 unemployed Tunisian higher‑education graduates into certified W5D team members, then use those teams to solve problems inside the five macro works. The CMS models this as a **four‑stage pipeline**, each stage a collection the owner edits from the portal.

### Stage 1 — Ontology (what needs solving)
- **5 macro works** — `data/content/macros/*.json`
- **130 services** — 26 per macro, `data/content/services/*.json`
- **Meta solutions** — `data/content/layers/meta-solutions.json`
- **Projects** — 25 records, `data/content/projects/*.json`

### Stage 2 — Certification (how a member becomes an expert)
Each service declares a **certification task set** in `data/content/certification/{service_slug}.json`. A member registers → completes the task set → the owner (or a delegate) marks each task `approved` → the member becomes a **certified expert team member** for that service.

### Stage 3 — Delivery (what the certified team produces)
Each service carries a **delivery contract** in `data/content/delivery/{service_slug}.json` — deliverable unit, weekly target, evidence rule. This is what the team actually ships once certified.

### Stage 4 — Reporting (how the owner sees it)
Every delivery row feeds the weekly report engine. `bin/rollup.php` aggregates totals into `rollups_daily`. `bin/archive.php` then moves raw `weekly_reports` out of the live database per `ARCHIVE_DRIVER`. Every dashboard reads **only** from `rollups_daily`. No page loads the raw 130,000 rows.

**Realistic claim:** one month is enough to **register and verify** 130,000 people (~4,334/day, ~1,000 per service). Certification takes longer. Every CTA below promises a **path**, never a job.

---

## Progressive pricing (money × time)

W5D's funding strategy combines **money** and **time** into a single published curve. The price of a part is **not fixed**. It rises as the programme approaches its goal.

### The two factors

- **Progress** — `p = experts_current / experts_target` (0 → 1). Read from `rollups_daily` at render.
- **Time pressure** — `t = 1 − (deadline − now) / (deadline − start)` (0 → 1). Read from `now` and the dated constants in `config/variables.php`.

### The published price function

```
price_per_day(p, t)  = base_day  × (1 + α·p^p_prog) × (1 + β·t^p_time)
price_per_week(p, t) = base_week × (1 + α·p^p_prog) × (1 + β·t^p_time)
```

with canonical defaults set in `config/variables.php` (owner‑tunable, but **published**):

| Symbol | Meaning | Default |
|---|---|---|
| `base_day` | price at p = 0, t = 0 | **10 TND** |
| `base_week` | price at p = 0, t = 0 | **70 TND** |
| `α` | progress multiplier | **1.0** |
| `β` | time multiplier | **1.0** |
| `p_prog` | progress exponent | **2.0** |
| `p_time` | time exponent | **2.0** |
| **Cap** | maximum multiple of base | **3.0×** |

**Property of the curve.** At p = 0 and t = 0 the price is exactly the advertised base (10 TND/day, 70 TND/week). At p = 1 and t = 1 the price is exactly `base × (1 + α) × (1 + β)`, capped at 3× base. In between, the curve is smooth, monotonic, and **published as a chart** on `/pricing/` and `/investors/` so no investor can claim the price was hidden.

### Why this is the money×time strategy

- **Early investors are rewarded.** A part bought at p = 0.1 costs about 1.01× base. A part bought at p = 0.9 costs about 1.81× base. The difference is the reward for time.
- **Late investors fund the last, most expensive stretch.** The part price rises fastest near the deadline — exactly when payroll pressure is highest. The curve is steepest where the funding need is largest.
- **The revenue curve accelerates.** Total revenue per part rises with progress and time, so the platform can fund more payroll in the final quarter without raising the advertised base.
- **No hidden price.** The function is in `config/variables.php`; the linter forbids any block from hard‑coding a part price. The published price on `/pricing/` is computed from the same function the checkout will use.
- **The owner can tune, not hide.** Changing α, β, `p_prog`, `p_time`, or the cap is a git‑tracked change in `config/variables.php` with a dated commit. The chart regenerates on the next request.

### What this means for the honesty constraint

The progressive price is a **declared** curve, not a scarcity trick. It is shown on `/trust/statement/` alongside the design‑equation constraint. It does not promise employment; it prices a part of the programme. The declared limit remains: **a part is an investment in a path, not a job.**

### Worked example (canonical defaults)

| Progress `p` | Time `t` | Day price | Week price |
|---:|---:|---:|---:|
| 0.0 | 0.0 | 10.00 TND | 70.00 TND |
| 0.25 | 0.25 | 10.09 TND | 70.64 TND |
| 0.50 | 0.50 | 10.63 TND | 74.38 TND |
| 0.75 | 0.75 | 12.23 TND | 85.63 TND |
| 0.90 | 0.90 | 14.14 TND | 98.95 TND |
| 1.00 | 1.00 | 30.00 TND (capped at 3×) | 210.00 TND (capped) |

The cap at 3× base means the price can never exceed **30 TND/day** or **210 TND/week** under the canonical defaults. The owner may change α, β, and the cap; the cap is the only protection against runaway pricing, and it is enforced in `config/variables.php` and in the linter.

---

## Deployment model

**One domain, one repository root, one entry point.**

```
https://w5d.tech/
├── /                    → public site (dynamic)
├── /macro-works/{slug}/ → public site (dynamic)
├── /services/{slug}/    → public site (dynamic)
├── /projects/{slug}/    → public site (dynamic)
├── /join/, /app/*       → registration flow (dynamic)
├── /{ADMIN_URL_PATH}/*  → owner portal (dynamic, auth required)
└── /assets/*            → static assets (served directly by Apache)
```

- **Repository root = Apache document root.**
- **`index.php` is at the root.** One front controller for the public site, the registration flow, and the portal.
- **Sensitive folders are denied by `.htaccess`**: `src/`, `config/`, `data/`, `lang/`, `db/`, `bin/`, `storage/`, `docs/`, and every `.env`, `.sqlite`, `.sql`, `.log`, `.md` file.
- **No `dist/`, no build step, no upload.**
- **Portal on the same domain**, mounted at `/{ADMIN_URL_PATH}` — obfuscated, set in `.env`, default `/panel-x9z`.

---

## Database driver

The app talks to a single PDO connection chosen at boot by `src/DB.php` from `.env`.

```dotenv
# Default — SQLite. No server required. Single file under storage/.
DB_DRIVER=sqlite
DB_PATH=storage/w5d.sqlite

# Optional — external provider. Used only if DB_DRIVER != sqlite.
# DB_DRIVER=mysql
# DB_HOST=db.provider.example
# DB_PORT=3306
# DB_DATABASE=w5d
# DB_USERNAME=w5d
# DB_PASSWORD=
# DB_CHARSET=utf8mb4
```

- **SQLite is the default** and the only supported driver on the 100 MB VPS.
- **Switching drivers is a `.env` change**, not a code change.
- **SQLite tuning on connect:** `journal_mode = WAL`, `foreign_keys = ON`, `busy_timeout = 5000`, `synchronous = NORMAL`, `wal_autocheckpoint = 1000`.

---

## Storage footprint

The VPS has **100 MB of disk**. This is the doctrine that keeps the deployment inside that budget.

| Component | Budget | Notes |
|---|---:|---|
| Code (`src/`, `views/`, `config/`, `lang/`, `db/`, `bin/`) | ~3 MB | Pure PHP; no `vendor/` |
| Assets (`assets/_base/`) | ~2 MB | CSS + JS + images; no framework |
| Content JSON (`data/`) | ~5 MB | 130 services + 25 projects + pages + countries |
| SQLite live DB (`storage/w5d.sqlite`) | **~40 MB** | Current week + all applicants + rollups |
| SQLite WAL + SHM | ~5 MB | Grows and shrinks with `wal_autocheckpoint` |
| Logs (`storage/logs/`) | ~2 MB | Rotated daily, 7‑day retention |
| Uploads (`storage/uploads/`) | **0 MB local** | Off‑server by default — `STORAGE_DRIVER` |
| Backups (`storage/backups/`) | **0 MB local** | Off‑server only — `BACKUP_DRIVER` |
| Headroom | ~40 MB | For a temporary spike during a campaign day |
| **Total** | **~100 MB** | |

### What this means in practice

- **Only the current week's `weekly_reports` live in SQLite** (~26 MB at 130,000 rows).
- **`bin/archive.php`** moves the previous week's raw reports out per `ARCHIVE_DRIVER`, then `VACUUM` reclaims the space.
- **Uploads do not stay on the VPS.** `STORAGE_DRIVER` controls this.
- **`bin/health.php`** warns at 60 MB and refuses new uploads at 90 MB.

### The honest limitation

At full operational scale (130,000 weekly reports per week for a year) the raw history is **~1.4 GB** — 14× the VPS budget. Three legitimate answers, all supported by the code:

1. **`ARCHIVE_DRIVER=external`** — keep full history off the VPS (S3‑compatible). Recommended for production.
2. **`ARCHIVE_DRIVER=prune`** — keep only `rollups_daily` on the VPS. Legitimate for a pilot.
3. **Switch `DB_DRIVER`** to an external MySQL/PostgreSQL provider. No code change.

The README does not pretend the VPS can host a year of raw reports. It documents the trade‑off and forces the choice in `.env`.

---

## Architecture

```
Browser
  │
  ▼
Apache + .htaccess
  ├── /assets/*            → served directly
  ├── /src/*, /config/*,
  │   /data/*, /lang/*,
  │   /db/*, /bin/*,
  │   /storage/*, /docs/*  → 403 (denied)
  └── everything else      → index.php
        │
        ▼
src/App.php — bootstrap
  ├── Env::load('.env')
  ├── Session::start()
  ├── Lang detection (cookie → Accept-Language → env)
  ├── DB::connect()      → PDO SQLite (default) or provider DSN
  ├── Content::load('services')  → data/content/services/*.json
  ├── Variables::load()          → config/variables.php
  ├── Vocab::load()              → config/vocab.php
  └── Router::dispatch()
      ├── /                        → public site
      ├── /macro-works/{slug}/     → 5 macro pages
      ├── /services/{slug}/        → 130 service pages
      ├── /projects/{slug}/        → 25 project pages
      ├── /{ADMIN_URL_PATH}/*      → portal (auth required)
      ├── /app/*                   → registration + role dashboards
      └── /api/*                   → JSON APIs (rollups, eligibility, price)
```

**Rollups.** `bin/rollup.php` hourly (every 15 min on Fridays). `bin/archive.php` immediately after. Public KPI tiles, `/api/kpis.json`, and every dashboard read only from `rollups_daily`. **`/api/price.json`** returns the progressive price computed from the same function the checkout uses.

**No external dependencies at runtime.**

---

## The one front‑end template: `w5d`

The site has **one canonical front‑end template**, named **`w5d`**, imported from:

> **https://github.com/Kaddoussi/w5d** — *W5D — AI Workspace Marketing Site*, the owner's original work.

### What the source template contains

- **3 homepage variants** (`index.html`, `home-2.html`, `home-3.html`) — classic SaaS, product‑led, editorial[reference:3].
- **14 inner pages** — about, pricing, features, integrations, contact, blog, blog‑post, careers, changelog, security, privacy, terms, 404, and developer docs[reference:4].
- **Blocks library** — 30+ ready‑to‑copy sections (`pages/blocks.html`)[reference:5].
- **Heroes library** — 12 animated hero variants (`pages/heros.html`)[reference:6].
- **Sections library** — 28 animated content sections (`pages/sections.html`)[reference:7].
- **Mega‑menu** — Product / Solutions / Resources / Pages, keyboard‑accessible, with hover‑delay bridge[reference:8].
- **Shell renderer** — auto‑injects the same nav + footer into every inner page from one source of truth[reference:9].
- **Theme system** — dark mode default, `prefers-color-scheme`, `localStorage` persistence[reference:10].
- **Forms wiring** — newsletter, contact, demo, and careers forms, all posting to a RESTful Table API[reference:11].
- **Animation library** — aurora, conic‑spin, stars, mesh‑bg, beam, noise, grid‑bg, glass, grad‑border, gradient‑text, tilt, shine, float‑y, marquees, scroll‑reveal, tabs, copy‑buttons, spotlight[reference:12].
- **W5D brand language** — Space Grotesk + Inter, glass surfaces, gradient borders, animated grid backgrounds, Lucide icons[reference:13].

### How the template is converted

The template is converted **once**, during onboarding, into PHP blocks and components. No content is restated.

| Template artifact | CMS artifact |
|---|---|
| `pages/blocks.html` (30+ blocks) | `views/_blocks/{type}.php` — one PHP block per section type |
| `pages/heros.html` (12 heroes) | `views/_blocks/hero.php` with 12 variants selected by `$data.variant` |
| `pages/sections.html` (28 sections) | `views/_blocks/{type}.php` — matched to the canonical block types |
| `js/megamenu.js` + nav markup | `views/_shared/partials/header.php` + `assets/_base/js/site.js` |
| `js/shell.js` (nav + footer injection) | `views/_shared/layouts/app.php` + `views/_shared/partials/{header,footer}.php` |
| `js/main.js` (theme, toggle, counters, FAQ) | `assets/_base/js/site.js` |
| `js/api.js` (form wiring) | `src/controllers/FormController.php` → MySQL/SQLite tables |
| `js/logos.js` (animated logo + loader) | `views/_shared/partials/preloader.php` + `assets/_base/js/site.js` |
| `css/style.css` (glass, orbs, marquee) | `assets/_base/css/attention.css` (absorbed, no duplication) |
| Tailwind CDN + Lucide UMD | **Compiled locally**: `assets/_base/css/tailwind.css` + `assets/_base/js/lucide.js` (or inline SVG). **No CDN at runtime** |

### Design system reconciliation

The template's Tailwind + custom CSS becomes the base of `attention.css`. The reading tiers (`E`/`I`/`X`) and focus levels (`F1`–`F5`) are added **on top** of the template's existing surfaces, not in place of them. The template's tokens (`Space Grotesk`, `Inter`, `brand-500` → `indigo-500` → `cyan-400`, `ink-900/950`) are kept as CSS variables so every block reads from one palette.

### What the template brings that the CMS keeps

- The **3 home variants** become the three featured variants on the homepage hero block (`hero.variant = classic | product | editorial`).
- The **12 hero variants** become `hero.php`'s 12 render paths.
- The **28 animated sections** become the canonical block library.
- The **30+ ready‑to‑copy blocks** become the block vocabulary in `config/vocab.php`.
- The **mega‑menu** becomes the site header partial.
- The **shell renderer** becomes the layout.
- The **theme system** becomes the dark/light toggle.
- The **forms** become the CMS form blocks, wired to real tables instead of the Table API.
- The **animation library** becomes part of `attention.css` + `site.js`.

### What the CMS adds on top

- **Multi‑language** — `en / fr / ar` with RTL, which the source template does not ship[reference:14].
- **Dynamic rendering** — the source template is static HTML; the CMS renders every page at request time.
- **The ontology** — 5 macro works, 130 services, 25 projects, 6 layers.
- **The portal** — 8 roles, 24 menus, 5% sampling.
- **The progressive price function** — the source template has a fixed monthly/annual pricing toggle; the CMS replaces it with the published money×time curve.
- **SQLite + archive doctrine** — the source template has no backend; the CMS adds the 100 MB budget discipline.

**No other template ships.** The previous `ledger`, `atlas`, and `folio` skins are removed. Adding a second template later is possible but out of scope for this README.

---

## Design system

One CSS file: `assets/_base/css/attention.css`. It absorbs the `w5d` template's `css/style.css` and adds the CMS layer on top.

### Reading tiers — every section gets exactly one

| Tier | Code | Voice | Example |
|---|---|---|---|
| Easy | `E` | Short sentences, Darija‑friendly Arabic, warm | «شهادتك تنجم تولّي خدمة» |
| In‑between | `I` | Explains how it works: steps, numbers, dates | "4 certification levels, weekly reports due Fri 12:00" |
| Expert | `X` | Ontology, evidence levels, schemas | "L2 service, E1 evidence, sampled at 5%" |

### Focus levels — F1 to F5 (peak‑end rule)

| Focus | Purpose |
|---|---|
| `F1` | Background context |
| `F2` | Supporting detail |
| `F3` | Explaining section (warm) |
| `F4` | Secondary action |
| `F5` | **The** main action — max one per screen |

### Gradient tokens (Tunisian palette)

```css
--g-calm:  linear-gradient(135deg,#0E3B5C,#1B6F8A);
--g-warm:  linear-gradient(135deg,#F7EEDD,#E9C37A);
--g-focus: linear-gradient(135deg,#1B6F8A,#2BB3A3);
--g-peak:  linear-gradient(135deg,#C8102E,#F05A28);
--g-cultures:   #B5532E → #E39B5B;
--g-built:      #5E6B3A → #B8A36A;
--g-sciences:   #2B2F7A → #5A6CF0;
--g-governance: #0F4D3A → #C9A227;
--g-technology: #0B7FA6 → #7B4DE0;
```

### Button classes

| Class | Focus | Use |
|---|---|---|
| `btn-peak` | F5 | The single main action |
| `btn-focus` | F4 | Next step |
| `btn-ghost` | F2 | Explore |
| `btn-link` | F1 | Expert detail |
| `sticky-cta` | F5 | Mobile bottom bar after 40% scroll |

**Honest psychology only:**
- Social proof uses real rollup numbers.
- Deadlines come from dated variables.
- Every CTA carries its matching declared limit one click away.
- The linter enforces **one `btn-peak` per section**.

---

## Pages

Every page is a route. Every route is a template + a content JSON + (optionally) a SQLite query. Section IDs are fixed — the linter checks presence.

### Homepage `/`

| # | Section ID | Block | Tier | Focus | Main CTA |
|---|---|---|---|---|---|
| 1 | `home-hero` | hero (variant = classic / product / editorial) | E | F5 · g-peak | **Check my eligibility** → `/join/eligibility/` |
| 2 | `home-trust-strip` | kpis | E | F2 · g-calm | — |
| 3 | `home-problem` | prose | E | F3 · g-warm | Is this me? → `#home-fit` |
| 4 | `home-fit` | cards | E | F3 | Find my field → macro page |
| 5 | `home-pipeline` | **pipeline** | I | F3 | See the certification path → `/how-it-works/` |
| 6 | `home-macros` | carousel | I | F3 · macro gradients | Explore the 26 services → macro |
| 7 | `home-mid-cta` | cta | E | F5 · g-peak | **Register now** → `/app/register` |
| 8 | `home-gov-map` | **gov-map** | E | F3 | See my governorate → `/tn/{gov}/` |
| 9 | `home-stack` | stack | X | F1 | See the ontology → `/ontology/` |
| 10 | `home-projects` | cards | I | F2 | View project → project page |
| 11 | `home-founder` | quote | E | F2 · g-warm | Read declarations |
| 12 | `home-limits` | notice | I | F1 | Read our limits → `/trust/statement/` |
| 13 | `home-faq` | **faq** | E | F2 | Ask us → contact |
| 14 | `home-final` | cta | E | F5 · g-peak | **Start my registration** |
| — | `sticky` | **sticky-cta** | E | F5 | Check eligibility |

### Macro work landing pages `/macro-works/{slug}/` × 5

| # | Section ID | Block | Tier | Focus | Main CTA |
|---|---|---|---|---|---|
| 1 | `mw-hero` | hero | E | F5 · g-{macro} | **Find my service here** → `#mw-catalog` |
| 2 | `mw-why-tn` | prose | E | F3 | — |
| 3 | `mw-kpis` | kpis | I | F2 | — |
| 4 | `mw-diplomas` | table | E | F3 · g-warm | Check my diploma → eligibility |
| 5 | `mw-catalog` | service-catalog | I | F4 | Open service → service page |
| 6 | `mw-mid-cta` | cta | E | F5 · g-peak | **Register in {macro}** |
| 7 | `mw-projects` | cards | I | F2 | View project |
| 8 | `mw-stack` | stack (highlight L3) | X | F1 | — |
| 9 | `mw-leaders` | roster | I | F2 | Apply as leader → careers |
| 10 | `mw-faq` | faq | E | F2 | — |
| 11 | `mw-final` | cta | E | F5 · g-peak | **Join {macro} now** |

### Service pages `/services/{slug}/` × 130

| # | Section ID | Block | Tier | Focus | Main CTA |
|---|---|---|---|---|---|
| 1 | `svc-hero` | hero | E | F5 · g-{macro} | **Apply to this service** → `/app/register?service={slug}` |
| 2 | `svc-problem` | prose | E | F3 | — |
| 3 | `svc-fit` | **eligibility** | E | F4 · g-focus | Check in 30 seconds |
| 4 | `svc-path` | steps | I | F3 | See L1 tasks → `#svc-tasks` |
| 5 | `svc-tasks` | cards | X | F2 | — |
| 6 | `svc-week` | definitions | I | F2 | — |
| 7 | `svc-seats` | bars | I | F3 | — |
| 8 | `svc-mid-cta` | cta | E | F5 · g-peak | **Reserve my place in the L1 cohort** |
| 9 | `svc-delivery` | definitions | X | F1 | — |
| 10 | `svc-limits` | notice | I | F1 | — |
| 11 | `svc-projects` | cards | I | F2 | View project |
| 12 | `svc-related` | cards (generated) | I | F2 | Compare |
| 13 | `svc-faq` | faq | E | F2 | — |
| 14 | `svc-final` | cta | E | F5 · g-peak | **Apply now** |

### Project pages `/projects/{slug}/` × 25

| # | Section ID | Block | Tier | Focus | Main CTA |
|---|---|---|---|---|---|
| 1 | `prj-hero` | hero | E | F5 · g-peak | **Contribute to this project** |
| 2 | `prj-outcome` | prose | E | F3 | — |
| 3 | `prj-status` | register | X | F2 | Read evidence |
| 4 | `prj-meta` | definitions | X | F1 | — |
| 5 | `prj-services` | cards | I | F3 | Apply to a needed service |
| 6 | `prj-team` | bars | I | F3 | — |
| 7 | `prj-timeline` | timeline | I | F2 | — |
| 8 | `prj-split-cta` | cta (3 actions) | E | F5 / F4 / F4 | **Join as expert** / Become a client / Invest |
| 9 | `prj-limits` | notice | I | F1 | — |
| 10 | `prj-final` | cta | E | F5 | **Join the team** |

### Basic pages

| Page | URL | Main CTA |
|---|---|---|
| Join | `/join/` | **Register now** |
| Eligibility | `/join/eligibility/` | **Continue to registration** |
| How it works | `/how-it-works/` | Start L1 |
| Country hub (Tunisia) | `/countries/tunisia/` | See my governorate |
| Governorate × 24 | `/tn/{gov}/` | Register from {gov} |
| Partners / Clients | `/partners/` | Book a scoping call |
| Investors | `/investors/` | Request the dossier |
| **Pricing** | `/pricing/` | **See the progressive price** → live chart |
| About | `/about/` | Meet the founder |
| Ontology | `/ontology/` | Read the expert docs |
| Careers | `/careers/` | Apply as manager |
| FAQ | `/faq/` | Ask a question |
| Contact | `/contact/` | Write to the right desk |
| Status | `/status/` | — |
| Trust | `/trust/*` | File a correction |
| Thank you | `/join/thanks/` | Share with a friend |
| 404 | `/404` | Back home |

The **Pricing** page uses the `w5d` template's pricing section as its skeleton but renders the **progressive price chart** from `config/variables.php` and `rollups_daily` instead of the fixed monthly/annual toggle.

---

## Owner dashboard

**Mount path:** `/{ADMIN_URL_PATH}` — obfuscated, set in `.env`, default `/panel-x9z`.

**Global header:** country switcher, language switch, quick search by CIN / email / ID. Every menu filters by the selected country.

| # | Menu | Key owner actions |
|---|---|---|
| 1 | **Command Center** | 30‑day burn‑down, today's registrations, funnel, per‑macro heatmap, alerts |
| 2 | **Countries** | Add country wizard, activate/pause |
| 3 | **Regions & Hubs** | 24 governorates → delegations, universities, hubs, coordinators, quotas |
| 4 | **Recruitment Campaigns** | Waves, channels, UTM, daily quotas |
| 5 | **Applicants** | Pipeline board |
| 6 | **Identity & Verification** | CIN checks, diploma review, duplicates, Dyon records |
| 7 | **Macro Works & Services** | Activate 130 services per country, capacity, leaders |
| 8 | **Academies & Certification** | Task sets L1–L4, review queues, pass rates, certificates |
| 9 | **Teams & Org** | Tree leader → managers → experts; cap 12/manager; orphan alerts |
| 10 | **Weekly Work Engine** | Directives, reports, approvals, lock, rollup, archive — timed per country TZ |
| 11 | **Projects** | 25 projects, services consumed, gaps, milestones |
| 12 | **Clients & Contracts** | Clients, scopes, quotes, invoices, licence 610.5 TND/h |
| 13 | **Quality & Sampling** | RAG scores (red < 70, amber < 90), **5% sampling queue**, blockers past SLA |
| 14 | **Progressive Pricing** | Live price curve (α, β, p_prog, p_time, cap), preview chart, publish button, audit log of every price change |
| 15 | **Payroll & Finance** | Salary model (600 TND/post), payroll simulation, cash forecast |
| 16 | **Investors** | Investor list, parts, reports, dividends (modelled) |
| 17 | **Communications** | SMS, email, WhatsApp templates, Ramadan‑aware |
| 18 | **Support & Tickets** | Applicant questions, blockers, SLA timers |
| 19 | **Website & Content** | Pages, blocks, CTA variants, cache purge |
| 20 | **Variables & Vocabularies** | Counts, money, time, doctrine values, pricing constants (derived read‑only) |
| 21 | **Users, Roles & Permissions** | 8 roles, 35 permissions, scoped by country and region |
| 22 | **Reports & Analytics** | Rollups only; CTA conversion per section ID; **price‑vs‑progress chart** |
| 23 | **Compliance & Privacy** | Consent, retention, DSR, country law profile |
| 24 | **Audit Log & Corrections** | Every approval/edit with user and timestamp; 14‑day correction SLA |
| 25 | **System & Storage** | Storage health, DB driver, archive status, backup status, queues |

The **Progressive Pricing** menu (14) shows the current α, β, `p_prog`, `p_time`, and cap; a live preview of the price curve at 0 / 0.25 / 0.5 / 0.75 / 1.0 progress; and a **Publish** button that writes a dated commit to `config/variables.php`. Every published change is logged in `audit_log` with the previous and new constants.

**Role dashboards.** `leader`, `manager`, `member`, `investor`, `customer`, `user` land on a scoped dashboard. Sidebar items are filtered by `ACL::can($role, $perm)` and `Scope::canActOn($actor, $target)`.

---

## Quality & sampling

**Sampling rule: 5%.** Every week, 5% of submitted reports are re‑reviewed.

- **Scope:** 5% of `weekly_reports` where `status = 'submitted'` for the current week.
- **Selection:** deterministic per `(country_id, week_id)`, reproducible.
- **Reviewer:** the service leader or a delegate (no self‑review).
- **Outcome:** `sampled_pass` or `sampled_fail`; failures follow the rework path.
- **Honesty:** the 5% is published on `/status/` and each service page.

**Why 5%:** 6,500 re‑reviews/week ≈ 1,300/day across 130 leaders and 13,000 managers — under 1 sample per manager per week.

**No alternate rule exists.** This repository uses **5%**.

---

## Single source of truth

| Problem | Fix |
|---|---|
| Three audience vocabularies | `config/vocab.php`: `audience` vs `role` |
| Three token syntaxes | One syntax `{{ counts.services }}` |
| Broken keys | Lint unresolved tokens |
| Derived numbers | `'derive'` expressions at render |
| Counts not computed | `Content::count()` from `data/content/*` |
| Block lists disagree | Generated from `views/_blocks/*.php` |
| Template paths disagree | One rule, documented in `docs/templates.md` |
| Static vs dynamic contradiction | Both dynamic. No `dist/` |
| Service files repeat data | Number once in `macros/*.json`; siblings generated |
| Tunisia hard‑coded | `data/countries/tn.json` |
| **Pricing hard‑coded** | Price is a function in `config/variables.php`; no block may hard‑code a part price |

---

## Data model

Single SQLite file by default: `storage/w5d.sqlite`. Every table carries `country_id`.

### Content tables (mirror of `data/content/`)
`pages`, `macros`, `services`, `projects`, `layers`, `audiences`, `certification_tasks`, `delivery_contracts`

### Platform tables
`countries`, `regions`, `users`, `roles`, `permissions`, `applicants`, `documents`, `verifications`, `services_country`, `cohorts`, `cert_submissions`, `teams`, `team_members`, `directives`, `weekly_reports`, `approvals`, `clients`, `contracts`, `invoices`, `payroll_runs`, `campaigns`, `messages`, `tickets`, `audit_log`, `consents`, `rollups_daily`

### Pricing audit table (new)
`pricing_audit` — `id`, `changed_by`, `changed_at`, `alpha`, `beta`, `p_prog`, `p_time`, `cap`, `note`. Append‑only. Every publish from the Progressive Pricing menu writes one row.

### Scale & indexing
- `applicants` — ~150,000 rows; indexes on `(country_id, status)`, `(country_id, macro_slug, status)`, `(country_id, region_id, status)`, `UNIQUE(country_id, cin)`.
- `weekly_reports` — live only for the current week (~130,000 rows). Indexes on `(country_id, week_id, status)` and `(country_id, manager_id, week_id)`.
- `rollups_daily` — every dashboard and `/api/kpis.json` reads here.
- **PRAGMA on connect:** `journal_mode = WAL`, `foreign_keys = ON`, `busy_timeout = 5000`, `synchronous = NORMAL`, `wal_autocheckpoint = 1000`.

### Migration discipline
One migration file per schema change under `db/migrations/`. SQLite‑compatible SQL only when `DB_DRIVER=sqlite`.

---

## Requirements

- **PHP ≥ 8.2** with `pdo_sqlite`, `mbstring`, `json`, `session`, `openssl`, `curl`
- **SQLite ≥ 3.35**
- **Apache 2.4+** with `mod_rewrite` (or Nginx equivalent)
- **No Composer at runtime**
- **No MySQL** required on the VPS. Optional external provider only.
- **Zero third‑party runtime requests** (no CDN, no jQuery, no icon font)

---

## Installation

```bash
git clone https://github.com/W5Downer/w5d.tech.git
cd w5d.tech

# 1. Create the data folder if it isn't already there
mkdir -p storage/{cache/views,logs,uploads,archive}

# 2. Configure
cp .env.example .env
# Edit .env:
#   APP_URL, APP_BASE_PATH, ADMIN_URL_PATH
#   WEBMASTER_USERNAME, WEBMASTER_PASSWORD_HASH
#   DB_DRIVER=sqlite (default)
#   STORAGE_DRIVER, ARCHIVE_DRIVER, BACKUP_DRIVER
#   (S3 fields if any driver = external)
#   PRICING_ALPHA=1.0, PRICING_BETA=1.0,
#   PRICING_P_PROG=2.0, PRICING_P_TIME=2.0, PRICING_CAP=3.0

# 3. Migrate + seed
php bin/migrate.php
php bin/sync-content.php

# 4. Generate the webmaster password hash
php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
# Paste into .env → WEBMASTER_PASSWORD_HASH

# 5. Point Apache document root at the repository root
```

**Cron:**

```cron
*/15 * * * 5  php /path/to/w5d/bin/rollup.php && php /path/to/w5d/bin/archive.php
0    * * * *  php /path/to/w5d/bin/rollup.php && php /path/to/w5d/bin/archive.php
*/15 * * * *  php /path/to/w5d/bin/health.php
0    3 * * *  php /path/to/w5d/bin/backup.php
```

**First login:** `https://w5d.tech/{ADMIN_URL_PATH}`.

---

## Security

- CSRF token on every state‑changing form
- Session fixation protection — `session_regenerate_id(true)` on login/logout
- HttpOnly, SameSite=Lax, Secure in production
- Argon2id password hashing
- Prepared statements everywhere (PDO)
- Rate limiting on login and registration (per IP, per CIN)
- OAuth state validated against `$_SESSION['oauth_state']` (Google + GitHub only)
- Path traversal prevention in the router
- `.htaccess` denies `src/`, `config/`, `data/`, `lang/`, `db/`, `bin/`, `storage/`, `docs/`, and every `.env`, `.sqlite`, `.sql`, `.log`, `.md`
- HTTP headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
- Append‑only `audit_log` + `pricing_audit`
- **SQLite file permissions** — `storage/w5d.sqlite` and its `-wal` / `-shm` siblings must be `0600`, owned by the web user
- **Uploads go off‑server by default** (`STORAGE_DRIVER=null`)
- **Nightly backup** to `BACKUP_DRIVER` (external by default)
- Privacy: personal data on ~150,000 people — Tunisia Organic Law 2004‑63, INPDP (confirm with counsel)

---

## Directory structure

```
w5d.tech/                    ← Apache document root
├── .env                        (denied)
├── .env.example
├── .htaccess
├── index.php                   ← single entry point
│
├── assets/
│   └── _base/
│       ├── css/                ← attention.css, tailwind.css (compiled),
│       │                          templates/w5d.css (additive)
│       └── js/                 ← site.js, lucide.js (vendored)
│
├── src/                        (denied)
│   ├── App.php · Env.php · DB.php · Lang.php · View.php · Router.php
│   ├── ACL.php · Auth.php · Auth/OAuth.php · Scope.php
│   ├── Content.php · Variables.php · Vocab.php · Country.php
│   ├── Rollup.php · Archive.php · Health.php · Lint.php
│   ├── Pricing.php             ← NEW: the money×time price function
│   ├── controllers/
│   └── models/
│
├── views/                      (denied)
│   ├── _blocks/                ← canonical block library (converted from
│   │                              Kaddoussi/w5d's blocks.html, heros.html,
│   │                              sections.html)
│   ├── _shared/{layouts,partials}/
│   └── templates/
│       └── w5d/                ← the ONE default front-end template
│           ├── blocks/         ← optional overrides only
│           └── css/            ← additive rules only
│
├── data/                       (denied — KEPT FROM REPO)
│   ├── content/{pages,macros,services,projects,layers,audiences,
│   │            certification,delivery}/
│   └── countries/tn.json
│
├── config/                     (denied)
│   ├── app.php · variables.php · vocab.php
│
├── lang/                       (denied)
│   ├── en.php · fr.php · ar.php
│
├── db/                         (denied)
│   └── migrations/
│
├── bin/                        (denied)
│   ├── migrate.php · sync-content.php
│   ├── rollup.php · archive.php · backup.php
│   └── health.php · lint.php
│
├── docs/                       (denied)
│   ├── ontology-six-layers.md · templates.md · design.md
│
└── storage/                    (denied)
    ├── w5d.sqlite
    ├── w5d.sqlite-wal
    ├── w5d.sqlite-shm
    ├── cache/views/
    ├── logs/
    ├── uploads/                (empty if STORAGE_DRIVER != local)
    └── archive/                (empty if ARCHIVE_DRIVER != local)
```

---

## Languages

Content is `{en, fr, ar}` maps inside JSON. UI chrome lives only in `lang/{code}.php`. Arabic is RTL with its own font stack and `assets/_base/css/rtl.css`. Switching is cookie‑based. No `?lang=xy` URLs.

---

## License

See [LICENSE](LICENSE) for the full text.

**Summary:**

- **W5D PHP CMS** is licensed at **610.5 TND per hour of work**.
- Promotional offer valid **before 2027**.
- **Sampling rule: 5%** — fixed.
- **Progressive pricing** — the part price is a published function of progress and time; the exact constants live in `config/variables.php` and every change is logged in `pricing_audit`.
- **Honesty constraint** — "130,000 experts" is a design equation, not a claim of employment.
- **Storage‑fit is the licensee's responsibility** — configure `STORAGE_DRIVER`, `ARCHIVE_DRIVER`, `BACKUP_DRIVER` before launch.

Every rendered page carries the license footer:

```html
<p>W5D Admin Dashboard licensed at 610.5 TND/hour of work.
   Promotional offer valid before 2027.</p>
```

---

## AI Agent Prompt

> **Copy this block verbatim into an AI coding assistant's system prompt.**
>
> **18 atomic tasks across 4 phases**, each ending in a PASS/FAIL check. Do not skip. Do not start Phase N+1 until Phase N passes.

```text
================================================================================
PROJECT: w5d.tech
ROLE: Senior PHP 8.2 engineer working on a dynamic CMS.
STYLE: Sequential tasks. One at a time. Every task has a PASS/FAIL check.
       Do not loop. Do not skip. Do not redesign. Do not add features.

ARCHITECTURE (fixed, do not change)
  Runtime:    PHP 8.2, SQLite 3 (DEFAULT), optional MySQL/PostgreSQL via
              .env DB_DRIVER.
  Deployment: SINGLE DOMAIN — index.php at the repository root.
              assets/* served directly. src/, config/, data/, lang/, db/,
              bin/, storage/, docs/ and every .env/.sqlite/.sql/.log/.md
              are denied by .htaccess.
  Content:    JSON under data/content/ (KEPT — do not delete, do not rename).
  State:      SQLite tables.
  Render:     Dynamic at request time — no static build, no dist/.
  Portal:     /{ADMIN_URL_PATH} + /app/* — SAME domain.
  Template:   ONE canonical front-end template: `w5d`, imported from
              https://github.com/Kaddoussi/w5d (the owner's original work).
              Blocks resolve two-tier:
                views/templates/w5d/blocks/{type}.php
                views/_blocks/{type}.php
  Sampling:   5%, fixed.
  Pricing:    PROGRESSIVE — price per part is a function of progress (p) and
              time (t), published from config/variables.php.
  Footprint:  100 MB VPS. Live SQLite target <= 60 MB. Only current week of
              weekly_reports live. bin/archive.php moves the rest out.
  Constraint: zero third-party runtime requests (no CDN, no jQuery, no icon
              font). Tailwind compiled locally; Lucide vendored locally.

READ FIRST — the three rules
  1. One fact, one place. Duplicates fail the linter.
  2. Any page renders in the single `w5d` template. Blocks resolve two-tier.
  3. Every number is a token {{ counts.services }}. Unresolved tokens
     fail the linter.

STOP CONDITIONS
  - Any PASS/FAIL reports FAIL: stop and report.
  - Any test requires deleting a file under data/content/: stop and report.
  - Any task requires a Composer dependency: stop and report.
  - Any task requires a CDN or jQuery: stop and report.
  - Any task tries to introduce a public/ folder or a dist/ folder:
    stop and report.
  - Any task hard-codes the DB driver: stop and report.
  - Any task hard-codes a part price: stop and report.

================================================================================
PHASE A — SINGLE SOURCE OF TRUTH (7 tasks)
================================================================================

TASK A1 — Baseline
  Run:      php bin/migrate.php && php bin/lint.php
  PASS if:  both exit 0.
  FAIL if:  any error — stop and report.

TASK A2 — Vocabularies
  Create config/vocab.php with:
    audience (owner|admin|leader|member|investor|customer|user),
    role (8 values matching audiences),
    status (registered|verified|screened|assigned|l1|l2|l3|l4|certified),
    evidence (E0|E1|E2|E3),
    block (list — see Task A5; generated from views/_blocks/*.php),
    ontology_layer (L0|L1|L2|L3|L4|L5),
    meta_solution (read from data/content/layers/meta-solutions.json),
    world (real|between|virtual),
    pricing_factor (progress|time|alpha|beta|p_prog|p_time|cap).
  Remove duplicated arrays from config/variables.php.
  PASS if: grep shows each vocabulary defined exactly once.
  FAIL if: any vocabulary appears in more than one file.

TASK A3 — Token syntax
  Standardize on {{ path.to.var }}. Migrate every JSON under data/content/.
  Remove {{ var.* }} prefix. Fix money.salary_month -> money.salary_monthly.
  Fix license_footer to use an existing token for the deadline year.
  Extend bin/lint.php to fail on unresolved tokens.
  PASS if: php bin/lint.php exits 0.
  FAIL if: any token is unresolved, or any JSON still uses {{ var.* }}.

TASK A4 — Derived variables + progressive pricing
  Extend config/variables.php with 'derive' expressions for:
    accounts_total   = experts_target + managers_target + leaders_target + 1
    reports_per_year = experts_target * 52
    payroll_monthly  = experts_target * money.salary_monthly
    part_monthly     = money.part_day * 30
    managers_target  = leaders_target * 100
  Add PROGRESSIVE PRICING constants:
    money.base_day = 10
    money.base_week = 70
    pricing.alpha = 1.0
    pricing.beta = 1.0
    pricing.p_prog = 2.0
    pricing.p_time = 2.0
    pricing.cap = 3.0
  Create src/Pricing.php with a single function:
    Pricing::perPart(string $unit, float $p, float $t): float
    where p = experts_current / experts_target
          t = 1 - (deadline - now) / (deadline - start)
    returns base * (1 + alpha * p^p_prog) * (1 + beta * t^p_time),
    capped at cap * base.
  Expose /api/price.json returning { day, week, p, t, cap, computed_at }.
  No block may compute a price independently — all blocks call Pricing.
  PASS if: changing experts_current via a synthetic rollup changes /api/price.json;
           at p=0,t=0 the day price is exactly 10.00 TND and the week price
           is exactly 70.00 TND; at p=1,t=1 the day price is exactly 30.00 TND
           (3x cap) and the week price is exactly 210.00 TND.
  FAIL if: any block hard-codes 10, 70, or any price.

TASK A5 — Block vocabulary and paths
  Generate the block vocabulary from views/_blocks/*.php.
  Document the template path rule in docs/templates.md:
    Template blocks: views/templates/w5d/blocks/{type}.php
    Template CSS:    assets/_base/css/templates/w5d.css
    Fallback block:  views/_blocks/{type}.php
  PASS if: config/vocab.php 'block' list matches ls views/_blocks/*.php.
  FAIL if: any block file has no vocabulary entry, or vice versa.

TASK A6 — Service JSON cleanup
  For each data/content/services/*.json:
    - Remove the repeated service number (store once in macros/*.json).
    - Remove macro name translations (belong in macros/*.json).
    - Remove "Other services" cards that copy sibling titles — generated
      at render from macro membership.
    - Add fr and ar fields where missing (TODO placeholders copied from en).
  Extend bin/lint.php to report duplicated literals, missing fr/ar fields,
  and services whose macro_slug is missing.
  PASS if: php bin/lint.php exits 0.
  FAIL if: any service has an unresolved macro_slug or empty en.

TASK A7 — Country entity
  Create data/countries/tn.json with: iso, names {en,fr,ar}, currency TND,
  timezone Africa/Tunis, locale ar_TN, regions (24 governorates),
  legal_profile (Organic Law 2004-63, INPDP), salary_model ref, calendar
  (Ramadan, Eid), default_languages [ar, fr, en].
  Remove every Tunisia-specific literal from config/, src/, views/_shared/.
  PASS if: grep for "Tunisia", "TND", "Africa/Tunis", "ar_TN" outside
           data/countries/tn.json returns zero hits in config/, src/,
           views/_shared/.
  FAIL if: any Tunisia literal remains outside data/countries/tn.json.

================================================================================
PHASE B — THE w5d TEMPLATE, DESIGN SYSTEM & PAGES (6 tasks)
================================================================================

TASK B1 — Import the w5d template
  Clone https://github.com/Kaddoussi/w5d into a scratch folder.
  Read its README, pages/blocks.html, pages/heros.html, pages/sections.html,
  pages/documentations.html, and its js/ and css/ folders.
  Do NOT modify data/content/.
  Produce a mapping table (in docs/templates.md) of:
    template artifact -> CMS artifact
  covering at minimum:
    - 3 home variants -> hero.php variants (classic / product / editorial)
    - 12 hero variants -> hero.php render paths
    - 30+ blocks -> views/_blocks/{type}.php
    - 28 sections -> views/_blocks/{type}.php
    - megamenu.js -> views/_shared/partials/header.php + site.js
    - shell.js -> views/_shared/layouts/app.php + partials
    - main.js -> site.js (theme, toggle, counters, FAQ)
    - api.js -> src/controllers/FormController.php
    - logos.js -> partials/preloader.php + site.js
    - css/style.css -> assets/_base/css/attention.css (absorbed)
    - Tailwind CDN + Lucide UMD -> compiled tailwind.css + vendored lucide.js
  PASS if: the mapping table exists and covers every artifact listed above.
  FAIL if: any artifact is missing from the table.

TASK B2 — Convert template to PHP blocks
  Using the mapping from B1, create one PHP block per canonical type under
  views/_blocks/. Each block:
    - accepts $data with id, tier (E|I|X), focus (1-5), gradient, cta, and
      its own content props,
    - echoes HTML only (no business logic),
    - renders server-side (no client-side hydration required),
    - uses assets/_base/css/tailwind.css and assets/_base/js/lucide.js
      (both local; no CDN),
    - has a graceful fallback if $data is incomplete (renders the template's
      default copy from lang/en.php).
  The block list must include at least:
    hero (3 home variants), kpis, cards, bars, carousel, contact-desks,
    cta, definitions, form, stack, prose, table, steps, notice, quote,
    register, timeline, roster, service-catalog, pipeline, eligibility,
    faq, gov-map, sticky-cta, progress, country-switch.
  PASS if: php bin/lint.php exits 0; every block in config/vocab.php has a
           matching views/_blocks/{type}.php; rendering / in en/fr/ar
           returns 200.
  FAIL if: any block is missing, or any block references an external URL.

TASK B3 — Compile Tailwind and vendor Lucide locally
  Compile Tailwind from the template's source to assets/_base/css/tailwind.css
  (one-time build; no CDN at runtime).
  Vendor Lucide locally to assets/_base/js/lucide.js.
  Vendor the template's fonts (Space Grotesk, Inter) locally under
  assets/_base/fonts/ and reference them from attention.css.
  PASS if: grep -r "cdn.tailwindcss.com" assets/ src/ views/ returns zero
           hits; grep -r "unpkg.com" assets/ src/ views/ returns zero hits;
           grep -r "fonts.googleapis.com" assets/ src/ views/ returns zero
           hits.
  FAIL if: any CDN URL remains in the runtime output.

TASK B4 — attention.css
  Create assets/_base/css/attention.css that:
    - imports tailwind.css (compiled),
    - imports assets/_base/fonts/,
    - absorbs the template's css/style.css (glass, orbs, marquee,
      gradient-border, scroll-reveal, tabs, copy-buttons, spotlight),
    - adds the CMS layer: --g-calm, --g-warm, --g-focus, --g-peak,
      --g-cultures, --g-built, --g-sciences, --g-governance, --g-technology,
      .tier-e / .tier-i / .tier-x, .focus-1 .. .focus-5,
      .btn-peak / .btn-focus / .btn-ghost / .btn-link / .sticky-cta.
  Extend bin/lint.php to fail if a rendered page contains more than one
  .btn-peak inside a single <section>.
  Verify contrast for all gradient tokens (scrim or darker stops to reach
  4.5:1). Add the computed ratios to docs/design.md.
  PASS if: lint enforces one .btn-peak per section; docs/design.md has
           contrast proof for every token.
  FAIL if: any gradient fails contrast without a scrim.

TASK B5 — Pages
  Implement routes exactly as specified in README §Pages with the section
  IDs listed there. Controllers in src/controllers/. CTAs to /app/register.
  Required routes:
    / , /macro-works/{slug}/ x 5, /services/{slug}/ x 130,
    /projects/{slug}/ x 25, /join/ , /join/eligibility/ ,
    /join/thanks/ , /how-it-works/ , /countries/tunisia/ ,
    /tn/{gov}/ x 24, /partners/ , /investors/ , /pricing/ ,
    /about/ , /ontology/ , /careers/ , /faq/ , /contact/ , /status/ ,
    /trust/* , /404.
  The /pricing/ page MUST render the progressive price chart from
  /api/price.json (no fixed price table). The /investors/ page MUST show
  the same chart plus the current p and t values.
  PASS if: every route returns HTTP 200 in en, fr, ar; /pricing/ renders
           the live chart with the current p and t values.
  FAIL if: any route returns non-200, or /pricing/ shows a hard-coded price.

TASK B6 — Honesty constraints + progressive pricing UI
  - No countdown or scarcity claim unless backed by a dated variable or a
    value from rollups_daily.
  - Every page renders the limits notice.
  - Every CTA to /app/register carries its declared limit one click away.
  - /pricing/ shows the price chart AND the declared limit:
    "A part is an investment in a path, not a job."
  - /trust/statement/ explains the progressive pricing function in one
    paragraph and links to docs/pricing.md.
  PASS if: grep finds no hard-coded "2027" or "130,000" outside
           config/variables.php and data/countries/tn.json; grep finds no
           hard-coded "10 TND" or "70 TND" outside config/variables.php;
           /pricing/ and /investors/ render the live chart.
  FAIL if: any price is hard-coded.

================================================================================
PHASE C — PORTAL & PROGRESSIVE PRICING (5 tasks)
================================================================================

TASK C1 — SQLite schema + DB driver abstraction
  Create src/DB.php: reads DB_DRIVER from .env, builds the correct PDO DSN
  (sqlite|mysql|pgsql), sets PRAGMA for sqlite:
    journal_mode=WAL, foreign_keys=ON, busy_timeout=5000,
    synchronous=NORMAL, wal_autocheckpoint=1000.
  Create db/migrations/001_initial.sql — SQLITE-COMPATIBLE SQL ONLY:
    countries, regions, users, roles, permissions, applicants, documents,
    verifications, services_country, cohorts, cert_submissions, teams,
    team_members, directives, weekly_reports, approvals, clients,
    contracts, invoices, payroll_runs, campaigns, messages, tickets,
    audit_log, consents, rollups_daily, pricing_audit.
  Every table carries country_id.
  Indexes (SQLite-compatible):
    applicants: (country_id, status), (country_id, macro_slug, status),
                (country_id, region_id, status), UNIQUE(country_id, cin)
    weekly_reports: (country_id, week_id, status),
                    (country_id, manager_id, week_id)
    rollups_daily: (country_id, day), (country_id, macro_slug, day)
    pricing_audit: (country_id, changed_at)
  NO PARTITION BY. NO stored procedures. NO vendor-specific types.
  PASS if: php bin/migrate.php applies 001 cleanly on an empty SQLite file.
  FAIL if: migration errors, or any table lacks country_id.

TASK C2 — Registration
  Build /app/register:
    - 4-minute flow: eligibility -> account -> CIN + diploma upload ->
      service choice
    - ar/fr/en, mobile-first
    - OTP by SMS (interface only — stub adapter)
    - duplicate CIN guard (UNIQUE index)
    - consent capture
    - rate limited 5/hour/IP
    - upload handler respects STORAGE_DRIVER
  Load target: 5,000 registrations/day; p95 < 500 ms.
  PASS if: load test reaches 5,000/day with p95 < 500 ms, every CIN
           duplicate is rejected with 409, and no file exceeds the
           upload size cap from .env.
  FAIL if: p95 > 500 ms, or a duplicate CIN is accepted.

TASK C3 — Owner dashboard
  Implement the 25 menus from README §Owner dashboard at
  /{ADMIN_URL_PATH}/. Each menu:
    - is scoped by the currently selected country,
    - calls Auth::require($perm) and Scope::canActOn($actor, $row),
    - reads list data only from rollups_daily or from indexed, paginated
      SQLite queries,
    - paginates at 50 rows/page.
  Menu #14 "Progressive Pricing" shows the current alpha, beta, p_prog,
  p_time, cap; a live preview chart at 0/0.25/0.5/0.75/1.0 progress;
  and a Publish button that writes a dated commit to config/variables.php
  AND an append-only row to pricing_audit.
  Menu #25 "System & Storage" reads from bin/health.php.
  PASS if: the sidebar shows only permitted items for each of the 8 roles;
           no menu loads > 50 rows; the country switcher filters every
           menu; publishing a price change writes one pricing_audit row and
           updates /api/price.json within one request.
  FAIL if: any menu > 50 rows, or a role sees an item it cannot access,
           or a price change is not logged.

TASK C4 — Country wizard
  Implement /{ADMIN_URL_PATH}/countries/new:
    - writes data/countries/{iso}.json,
    - inserts a countries row,
    - clones Tunisia defaults (roles, permissions, 130 services as
      inactive, salary model, calendar, 5% sampling rule, progressive
      pricing constants).
  PASS if: adding 'ma' works end-to-end, and deleting it removes both
           the row and the file.
  FAIL if: any menu leaks data from another country when 'ma' is selected.

TASK C5 — Rollup, archiving, health
  Implement:
    bin/rollup.php    — aggregate weekly_reports -> rollups_daily.
                        Cron: */15 * * * 5 and 0 * * * *.
    bin/archive.php   — move expired raw weekly_reports out per
                        ARCHIVE_DRIVER, then VACUUM on SQLite.
    bin/health.php    — every 15 min: check storage/w5d.sqlite size
                        (warn > 60 MB, refuse new uploads > 90 MB),
                        check oldest live weekly_reports age
                        (trigger archive if > 7 days), report to
                        Menu #25.
  Expose /api/kpis.json (from rollups_daily only) and /api/price.json
  (from Pricing.php only).
  PASS if: after a synthetic load of 10,000 weekly_reports, rollups_daily
           is correct; archive.php removes expired rows and reduces the
           SQLite file; /api/kpis.json returns < 50 ms; /api/price.json
           returns < 50 ms; health.php reports the live size.
  FAIL if: /api/kpis.json queries weekly_reports directly, or archive.php
           leaves rows behind, or health.php misses the size threshold.

================================================================================
PHASE D — VERIFY & REPORT (1 task)
================================================================================

TASK D1 — Final verification
  Run:
    - php bin/migrate.php (fresh SQLite file)
    - php bin/sync-content.php
    - php bin/lint.php
    - Browser pass on /, /macro-works/technology/,
      /services/heritage-digitisation/, /projects/meta-x-academy/,
      /join/, /join/eligibility/, /tn/tunis/, /app/register,
      /pricing/, /investors/,
      /{ADMIN_URL_PATH}/, /{ADMIN_URL_PATH}/applicants,
      /{ADMIN_URL_PATH}/progressive-pricing
    - 3 languages (en/fr/ar) on /, /macro-works/technology/,
      /services/heritage-digitisation/, /pricing/
    - Load test on /app/register POST (p95 < 500 ms)
    - Archive test: insert 1,000 fake weekly_reports from last week,
      run bin/archive.php, confirm removed + VACUUM
    - Health test: bin/health.php reports the live SQLite size
    - Pricing test: at p=0,t=0 the day price is exactly 10.00 TND; at
      p=1,t=1 the day price is exactly 30.00 TND (3x cap); /api/price.json
      returns the same values as /pricing/
    - Security: no third-party requests in any HTML; license footer on
      every page; .htaccess denies src/, config/, data/, lang/, db/,
      bin/, storage/, docs/, .env, .sqlite, .sql, .log, .md
    - Confirm index.php lives at the repository root (no public/ folder)
    - Confirm no MySQL is required on the VPS
    - Confirm no CDN URL appears in rendered HTML
  Produce a final report:
    Files created      : (list)
    Files modified     : (list)
    Files deleted      : (must be empty — data/content/ intact)
    Migrations applied : (list)
    Lint result        : PASS/FAIL
    Load p95           : (ms)
    Archive test       : PASS/FAIL
    Health test        : PASS/FAIL
    Pricing test       : PASS/FAIL
    CDN check          : PASS/FAIL
    License footer     : PASS/FAIL
    Sampling rule      : 5% (confirmed fixed)
    DB driver          : sqlite (confirmed default)
    Template           : w5d (single canonical template)
    Open items         : (list — nothing in Phase A-C should be open)
  PASS if: every line above reports PASS.
  FAIL if: any line reports FAIL — stop and report.
  STOP after Phase D.

================================================================================
COMMON LOOP TRIGGERS — do not do these
================================================================================
  x Do not create a public/ folder. index.php is at the repository root.
  x Do not bring back a static build (bin/build.php, dist/).
  x Do not require MySQL on the VPS. SQLite is the default.
  x Do not hard-code the DB driver. Read DB_DRIVER from .env.
  x Do not use PARTITION BY or any SQLite-incompatible SQL in migrations.
  x Do not store uploads on the VPS. STORAGE_DRIVER controls this.
  x Do not keep raw weekly_reports beyond the current week.
  x Do not add a second front-end template. The template is `w5d` only.
  x Do not use a CDN. Compile Tailwind locally, vendor Lucide locally,
    self-host fonts.
  x Do not rename a service, a macro, or a project.
  x Do not create all 130 certification files or all 25 project state rows.
  x Do not change the 5% sampling rule. It is fixed.
  x Do not hard-code a part price. All prices come from Pricing.php.
  x Do not split the site onto app.w5d.tech. One domain, one index.php.
================================================================================
```

---

## Author

**Maher Kaddoussi** — Founder and owner, W5D
📧 [owner@w5d.tech](mailto:owner@w5d.tech) · 🌐 https://w5d.tech · 💻 https://github.com/W5Downer/w5d.tech
