# Ontology: six layers (L0-L5)

Content lives in `data/content/layers/l0.json` ... `l5.json` (`{slug, code, order, name, body}`; bodies may contain `{{ tokens }}`), shown on `/ontology/` and `/api/*`. Counts are linted against the files on disk.

| Code | Name | What it is | Source of truth |
|---|---|---|---|
| L0 | Doctrine and dimensions | The five dimensions of W5D and the rule of **Sidq**: no page asserts more than its evidence supports | `layers/l0.json`, `config/variables.php` (`dates.doctrine`) |
| L1 | Meta solutions | Cross-cutting tools used by every service: identity (KernID, Dyon), training (MetaX Academy), self-training agents (SelfAI), publishing (WebBooks) | `layers/l1.json` |
| L2 | Services | 130 registered services (26 per macro), each with deliverable unit, evidence rule, declared boundary | `data/content/services/*.json`, `counts.services` |
| L3 | Macro works | 5 top-level demand areas: Cultures/Humanities, Built Environment, Sciences, Governance, Technology | `data/content/macros/*.json`, `counts.macros` |
| L4 | Projects | 25 projects consuming services; each publishes a dated status: live / building / concept / draft | `data/content/projects/*.json`, `counts.projects` |
| L5 | Programme and horizon | The 2030B horizon and the weekly work engine: reports, approvals, 5% sampling, rollups, archive | `layers/l5.json`, `bin/rollup.php`, `bin/archive.php` |

## Pipeline that uses the layers

1. **Ontology** (L1-L4) defines what can be solved.
2. **Certification**: a member registers (`applicants`), completes the service task set (`cert_submissions`), a reviewer approves.
3. **Delivery**: certified teams (`teams`, `team_members`) ship the service's deliverable unit weekly (`weekly_reports`).
4. **Reporting**: `bin/rollup.php` aggregates into `rollups_daily`; dashboards read ONLY the rollup (never the raw rows); `bin/archive.php` prunes raw rows per `ARCHIVE_DRIVER`.

## Honesty constraint (do not weaken)

- `experts.current` is **0** today (overridden from `rollups_daily` when data exists); real footprint is **26 developers** in Tunisia (`experts.developers_today`).
- "130,000 experts" is a **design equation** (5 macros x 26 services x 1,000 posts per service), **not** a claim of employment or a promise of a job.
- Every page that mentions the figure links to `/trust/statement/`. Sampling is fixed at 5% (`quality.sampling_percent`).
- Linter rejects hard-coded `130,000`; use tokens.

## Editing rules

- Add/modify a service or project = edit the JSON (en, fr, ar all required), then `php bin/sync-content.php` and `php bin/lint.php`.
- `meta_services` (130) must equal the number of files in `data/content/services` (linted).
- Never restate a count in prose; use `{{ counts.* }}`.
