# Templates (Blade-style, compiled in-house)

One canonical template: `views/templates/w5d/`. No Composer, no Laravel: `src/Blade.php` compiles `*.blade.php` to plain PHP, `src/ViewCache.php` stores the result, `src/ViewEnv.php` resolves names and runs sections/loops/widgets.

## Directory layout

```
views/templates/w5d/
  layouts/app.blade.php        public shell (head, header, aside, footer, scripts)
  partials/                    header, footer, aside, loader, limits, api-guide
  components/                  <x-...> tags: section, cta, ctas, form-field, price-pill, mail-*
  blocks/                      one file per JSON section "type" (see list below)
  pages/                       page.blade.php (renders sections[]), 404, manage
  account/                     auth, panel, pay, unsubscribe
  admin/                       layout, login, dashboard, menu, forbidden
  mail/                        layout + 5 transactional templates (x en/fr/ar)
views/_blocks/                 fallback blocks (second lookup tier)
views/_shared/                 fallback shared views (third tier)
views/static/w5d-main/         ORIGINAL static source template (design reference only, never rendered, excluded from lint/cache)
```

Name resolution (`ViewEnv::find`): `views/templates/w5d/<dot.path>` -> (for `blocks.*`) `views/_blocks/<type>` -> `views/_shared/<dot.path>`. `view('pages.page', $data)` => `pages/page.blade.php`.

## Syntax supported by `src/Blade.php`

`{{ }}` (escaped), `{!! !!}` (raw), `{{-- --}}`, `@extends/@section/@endsection/@show/@yield`, `@include/@includeIf/@includeWhen`, `@push/@stack/@once`, `@if/@elseif/@else/@unless/@isset/@empty`, `@foreach` (+`$loop`), `@forelse`, `@for`, `@while`, `@switch`, `@php ... @endphp`, `@json`, `@class`, `@props`, `@csrf`, `@widget('type', $data)`, `@icon('name','class')`, `@t('key')`, `<x-component prop="..." :bound="$expr" />`.

Escape a literal `{{` with `@{{`.

## Helpers available in every view (`src/helpers.php`)

| Helper | Purpose |
|---|---|
| `__('group.key')` | UI string from `lang/{en,fr,ar}.php` (fr/ar must be complete; linted) |
| `t($v, $lang = null)` | pick the current language from a `{en,fr,ar}` map |
| `url('/path')`, `asset('x')`, `admin_url('x')` | absolute-safe URLs (honours `APP_BASE_PATH`; admin mount is `ADMIN_URL_PATH`) |
| `icon('name', 'w-5 h-5')` | inline SVG from the locally built Lucide/brand subset (`htdocs/assets/_base/icons.json`) |
| `csrf_field()` | hidden `_csrf` input (every POST needs it) |
| `money($v)`, `num($n)` | number formatting with the configured currency |
| `old('field')` | previous form value after a failed POST |
| `g_macro($slug)` | CSS gradient variable for a macro work |

## Page = JSON list of sections

A page file (`data/content/pages/<slug>.json`) holds `{slug, title{en,fr,ar}, description, robots, sections:[{type, id, tier, focus, ...}]}`. `pages/page.blade.php` loops over `sections` and calls `ViewEnv::widget($type, $data)` which renders `blocks/<type>.blade.php` with `$d`/`$data`. An unknown type renders `blocks/unknown` (never a fatal error).

Block types: bars, cards, contact-desks, cta, dash-hub, dash-preview, definitions, eligibility, faq, form, gov-map, hero, investor-strip, kpis, macro-cards, notice, pipeline, price-chart, price-table, project-cards, prose, quote, register, roster, service-catalog, slider, stack, status-live, steps, sticky-cta, table, timeline, wizard.

Every section carries exactly one reading tier (`tier`: e|i|x) and one focus level (`focus`: 1-5). At most ONE `btn-peak` (focus 5) per section (linted).

## Content tokens

Content and lang strings may contain `{{ path.to.variable }}` resolved from `config/variables.php` (+ DB overrides) at render. An unresolved token fails `php bin/lint.php`. Never type a price, a count, a year or `130,000` in prose.

## View cache

Compiled output: `storage/framework/views/<sha1(source|COMPILER)>.php`. Bump `ViewCache::COMPILER` whenever `Blade.php` output changes.

```bash
php bin/view.php cache     # clear + precompile all templates (deploy step; also syntax-checks each one)
php bin/view.php clear
php bin/view.php status
php tests/viewcache.php    # unit tests for the cache (27 checks)
```

| `.env` | Effect |
|---|---|
| `VIEW_CACHE=false` | compile in memory only (debug) |
| `VIEW_CACHE_CHECK=` empty | recompile when source is newer, except when `APP_ENV=production` |
| `VIEW_CACHE_CHECK=false` | trust the cache (production). After editing any template run `php bin/view.php cache` or the old compiled view is served |

## Adding things

- **New block**: create `blocks/<type>.blade.php` (read `$d`), add a section with that `type` to a page JSON, add fr/ar text, run `php bin/lint.php && php bin/view.php cache`.
- **New page**: add `data/content/pages/<slug>.json` + route (static pages are routed by slug in `src/App.php` / `Pages::show`) + add path to `tests/smoke.sh` if not auto-discovered, update sitemap source.
- **New admin screen**: add a `match` arm in `Admin::menu()` returning `vTable($sql)` (read-only list) or a custom `kind` + a branch in `admin/menu.blade.php`; never `SELECT` `password_hash` or tokens (tested).
- **Mail**: templates in `mail/` extend `mail.layout`; the footer with unsubscribe + `List-Unsubscribe` header is added by `src/Mail.php`.
- **Tailwind classes**: Tailwind is compiled once (`cd tools && npm ci && node build.mjs`), output committed. A class that never appeared in a scanned file does not exist in the CSS: rebuild after adding new utility classes.
