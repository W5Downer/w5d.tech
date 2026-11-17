# W5D social share images — prompts & aliases

Every public page maps to an **alias** (file name) in `htdocs/assets/_base/images/social/{alias}.jpg`. The mapping lives in `config/social.php` (`images`) and `src/Social.php` (prefix rules). This repository ships **generated placeholder cards** with the same names: replace a file with your final artwork (same name, 1200×630 JPG) and nothing else changes. Pages whose alias file is missing fall back to `og-default.jpg` (`SOCIAL_DEFAULT_IMAGE` in `.env`).

> Regenerate the placeholders (and this file) with `python3 tools/gen/brand_assets.py`. Re-running overwrites the placeholder JPGs, so keep your final artwork out of that script’s way by running it only once on a fresh clone.

## Style guide

Style guide (applies to every image): 1200×630 px (1.91:1), JPG, safe area 60 px, deep ink background #070711, violet #7c3aed / indigo #6366f1 / cyan #06b6d4 accents, soft glow, flat-3D premium illustration, no photographs of real people, no real brand logos, no stock-market charts, no currency symbols or promised numbers, no text inside the image except where stated (text is added by the page title). Honest tone: investing in a path, not a job, no promised returns.

## Images

| Alias file | Page(s) |
|---|---|
| `og-default.jpg` | `Fallback for any page without its own image` |
| `og-home.jpg` | `/` |
| `og-investors.jpg` | `/investors/` |
| `og-pricing.jpg` | `/pricing/` |
| `og-checkout.jpg` | `/checkout/` |
| `og-subscription.jpg` | `/subscription/` |
| `og-how-it-works.jpg` | `/how-it-works/` |
| `og-join.jpg` | `/join/` |
| `og-join-eligibility.jpg` | `/join/eligibility/` |
| `og-app-register.jpg` | `/app/register/` |
| `og-careers.jpg` | `/careers/` |
| `og-careers-managers.jpg` | `/careers/managers/` |
| `og-partners.jpg` | `/partners/` |
| `og-about.jpg` | `/about/` |
| `og-ontology.jpg` | `/ontology/` |
| `og-countries-tunisia.jpg` | `/countries/tunisia/` |
| `og-faq.jpg` | `/faq/` |
| `og-contact.jpg` | `/contact/` |
| `og-newsletter.jpg` | `/newsletter/` |
| `og-status.jpg` | `/status/` |
| `og-macro-works.jpg` | `/macro-works/` |
| `og-services.jpg` | `/services/` |
| `og-projects.jpg` | `/projects/` |
| `og-developers.jpg` | `/developers/` |
| `og-agents.jpg` | `/agents/` |
| `og-sitemap.jpg` | `/sitemap/` |
| `og-for.jpg` | `/for/` |
| `og-for-owner.jpg` | `/for/owner/` |
| `og-for-admin.jpg` | `/for/admin/` |
| `og-for-leader.jpg` | `/for/leader/` |
| `og-for-member.jpg` | `/for/member/` |
| `og-for-investor.jpg` | `/for/investor/` |
| `og-for-customer.jpg` | `/for/customer/` |
| `og-for-user.jpg` | `/for/user/` |
| `og-macro-built.jpg` | `/macro-works/built/` |
| `og-macro-cultures.jpg` | `/macro-works/cultures/` |
| `og-macro-governance.jpg` | `/macro-works/governance/` |
| `og-macro-sciences.jpg` | `/macro-works/sciences/` |
| `og-macro-technology.jpg` | `/macro-works/technology/` |
| `og-service.jpg` | `/services/{slug}/ (all 130 services)` |
| `og-project.jpg` | `/projects/{slug}/ (all 25 projects)` |
| `og-tunisia-governorate.jpg` | `/tn/{governorate}/` |
| `og-trust.jpg` | `/trust/ and /trust/{page}/ (statement, privacy, terms, security, sampling, corrections)` |
| `og-account.jpg` | `/account/* (sign in, register, dashboard; these pages are noindex)` |

## Prompts (copy-paste, English)

### og-default.jpg
- **Used on:** `Fallback for any page without its own image`
- **Alt text:** Five macro works. One path. — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Five macro works. One path.”: a luminous five-pointed constellation of connected nodes over a deep ink-violet background, subtle grid, cyan and violet light trails. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-home.jpg
- **Used on:** `/`
- **Alt text:** Take a part. Fund a path. — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Take a part. Fund a path.”: five glowing macro-work orbs (violet, indigo, cyan, pink, teal) orbiting one central daily-sunrise disc, deep ink background, premium fintech-meets-education feel. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-investors.jpg
- **Used on:** `/investors/`
- **Alt text:** A part every day — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “A part every day”: a calendar strip of thirty small glowing tiles filling up one by one, a single bright tile for today, calm confident mood, no money piles, no charts promising profit. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-pricing.jpg
- **Used on:** `/pricing/`
- **Alt text:** One public price function — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “One public price function”: an elegant smooth curve rising gently to a flat cap line, one marked point labelled today, minimal axes, violet-to-cyan gradient line on ink background. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-checkout.jpg
- **Used on:** `/checkout/`
- **Alt text:** Take a part today — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Take a part today”: a clean three-step wizard card (details, parts, review) floating over a soft glow, with a small smartphone showing a payment confirmation. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-subscription.jpg
- **Used on:** `/subscription/`
- **Alt text:** A part, every day — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “A part, every day”: a circular arrow loop around a sun icon, daily rhythm, small calendar ticks, glowing indigo. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-how-it-works.jpg
- **Used on:** `/how-it-works/`
- **Alt text:** From a part to a certified path — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “From a part to a certified path”: four connected stages as stepping stones across a glowing river: fund, assign, work, audit with a random 5 percent magnifier. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-join.jpg
- **Used on:** `/join/`
- **Alt text:** Join the path — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Join the path”: an open door of light with five silhouettes of different people walking toward it, inclusive and hopeful. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-join-eligibility.jpg
- **Used on:** `/join/eligibility/`
- **Alt text:** Check your eligibility — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Check your eligibility”: a friendly checklist card with ticks and a graduation cap, soft violet glow. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-app-register.jpg
- **Used on:** `/app/register/`
- **Alt text:** Register as an applicant — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Register as an applicant”: a form card with a name field and a verified badge, small laptop, cyan accents. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-careers.jpg
- **Used on:** `/careers/`
- **Alt text:** A path, not a job — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “A path, not a job”: a branching path of light splitting into five trails, with small briefcase and compass icons, honest and grounded mood. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-careers-managers.jpg
- **Used on:** `/careers/managers/`
- **Alt text:** Lead a macro work — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Lead a macro work”: a leader silhouette on a hill overlooking five glowing districts connected by bridges. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-partners.jpg
- **Used on:** `/partners/`
- **Alt text:** Build with W5D — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Build with W5D”: two interlocking rings of light, one violet one cyan, with a handshake silhouette, institutional tone. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-about.jpg
- **Used on:** `/about/`
- **Alt text:** Who builds W5D — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Who builds W5D”: a workshop table seen from above with blueprint sheets, a compass and a glowing five-sided emblem. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-ontology.jpg
- **Used on:** `/ontology/`
- **Alt text:** One shared vocabulary — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “One shared vocabulary”: a knowledge graph of labelled nodes forming a brain-like network, cyan and violet edges. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-countries-tunisia.jpg
- **Used on:** `/countries/tunisia/`
- **Alt text:** Rooted in Tunisia — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Rooted in Tunisia”: abstract map of Tunisia outlined by glowing dots with a Mediterranean-blue gradient, 24 governorate markers. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-faq.jpg
- **Used on:** `/faq/`
- **Alt text:** Straight answers — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Straight answers”: stacked speech bubbles with question and answer marks, friendly, glowing indigo. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-contact.jpg
- **Used on:** `/contact/`
- **Alt text:** Write to the right desk — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Write to the right desk”: an envelope opening into five coloured desks icons (investors, partners, press, support, security). Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-newsletter.jpg
- **Used on:** `/newsletter/`
- **Alt text:** The weekly note — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “The weekly note”: a paper plane leaving a glowing envelope trailing a gentle weekly calendar. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-status.jpg
- **Used on:** `/status/`
- **Alt text:** Live programme status — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Live programme status”: a calm dashboard of five green status lights and a heartbeat line on dark glass. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-macro-works.jpg
- **Used on:** `/macro-works/`
- **Alt text:** Five domains — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Five domains”: five tall luminous pillars of different colours standing in a row like a skyline, each topped with a small icon. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-services.jpg
- **Used on:** `/services/`
- **Alt text:** Services in five domains — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Services in five domains”: a dense but tidy honeycomb of small glowing hexagons grouped in five colours. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-projects.jpg
- **Used on:** `/projects/`
- **Alt text:** Projects you can follow — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Projects you can follow”: a roadmap timeline with milestone flags and small prototype screens floating above. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-developers.jpg
- **Used on:** `/developers/`
- **Alt text:** Open API & OpenAPI — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Open API & OpenAPI”: a code editor window with a JSON response and a glowing endpoint badge, dark theme, cyan syntax colours. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-agents.jpg
- **Used on:** `/agents/`
- **Alt text:** Machine-readable by design — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Machine-readable by design”: a friendly abstract robot head made of nodes reading a manifest scroll, violet and cyan. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-sitemap.jpg
- **Used on:** `/sitemap/`
- **Alt text:** Every page, one list — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Every page, one list”: a tree diagram of connected page cards fanning out from a home icon. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-for.jpg
- **Used on:** `/for/`
- **Alt text:** One W5D, seven doors — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “One W5D, seven doors”: seven doors of light in a curved corridor, each a different colour, welcoming. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-for-owner.jpg
- **Used on:** `/for/owner/`
- **Alt text:** W5D for the owner — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “W5D for the owner”: a captain at the helm of a glowing ship made of five sails. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-for-admin.jpg
- **Used on:** `/for/admin/`
- **Alt text:** W5D for the admin — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “W5D for the admin”: a control panel console with toggles, shields and a calm operator silhouette. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-for-leader.jpg
- **Used on:** `/for/leader/`
- **Alt text:** W5D for the leader — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “W5D for the leader”: a leader figure holding a lantern guiding a small team across a bridge. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-for-member.jpg
- **Used on:** `/for/member/`
- **Alt text:** W5D for the member — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “W5D for the member”: a team of people seated around a glowing table, collaborative. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-for-investor.jpg
- **Used on:** `/for/investor/`
- **Alt text:** W5D for the investor — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “W5D for the investor”: one bright tile added to a calendar each day, a hand placing it gently. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-for-customer.jpg
- **Used on:** `/for/customer/`
- **Alt text:** W5D for the customer — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “W5D for the customer”: a client at a counter receiving a certified work package box with a ribbon. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-for-user.jpg
- **Used on:** `/for/user/`
- **Alt text:** W5D for the user — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “W5D for the user”: a curious visitor with a lantern at a crossroads with five signposts. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-macro-built.jpg
- **Used on:** `/macro-works/built/`
- **Alt text:** Built Environment — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Built Environment”: a futuristic yet humble city skyline of bridges and green buildings, cyan glow. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-macro-cultures.jpg
- **Used on:** `/macro-works/cultures/`
- **Alt text:** Cultures & Humanities — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Cultures & Humanities”: an ancient arch and an open book releasing pink-violet light. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-macro-governance.jpg
- **Used on:** `/macro-works/governance/`
- **Alt text:** Governance — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Governance”: balanced scales above a round table, violet-indigo glow. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-macro-sciences.jpg
- **Used on:** `/macro-works/sciences/`
- **Alt text:** Sciences — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Sciences”: a flask, an atom and a telescope arranged around a glowing beaker, indigo-cyan. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-macro-technology.jpg
- **Used on:** `/macro-works/technology/`
- **Alt text:** Technology — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Technology”: a luminous chip with circuit traces fanning out like roots, violet-blue. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-service.jpg
- **Used on:** `/services/{slug}/ (all 130 services)`
- **Alt text:** A weekly certified package — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “A weekly certified package”: a single glowing hexagon package box on a plinth with a ribbon marked with a week calendar. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-project.jpg
- **Used on:** `/projects/{slug}/ (all 25 projects)`
- **Alt text:** A published W5D project — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “A published W5D project”: a prototype screen floating above a blueprint with a status flag, cyan glow. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-tunisia-governorate.jpg
- **Used on:** `/tn/{governorate}/`
- **Alt text:** A governorate hub — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “A governorate hub”: a single highlighted governorate outline on a dark Tunisia map with a soft pin. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-trust.jpg
- **Used on:** `/trust/ and /trust/{page}/ (statement, privacy, terms, security, sampling, corrections)`
- **Alt text:** Declared limits, in writing — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Declared limits, in writing”: a transparent glass shield with a checkmark and a visible 5 percent sample magnifier, serious calm mood. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

### og-account.jpg
- **Used on:** `/account/* (sign in, register, dashboard; these pages are noindex)`
- **Alt text:** Your W5D account — W5D
- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “Your W5D account”: a rounded avatar badge inside a shield with a lock, soft indigo glow. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.

## Other brand files

| File | Size | Purpose |
|---|---|---|
| `images/icon-192.png`, `icon-512.png` | 192/512 | PWA manifest, Organization logo (JSON-LD) |
| `images/icon-maskable-512.png` | 512 | Maskable PWA icon |
| `images/apple-touch-icon.png` | 180 | iOS home screen |
| `images/favicon.svg`, `favicon-32.png`, `/favicon.ico` | — | Browser tabs |
