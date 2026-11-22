<?php
declare(strict_types=1);

use W5D\{Html, Lang, Variables, Content, ViewEnv, Csrf, Config};

/** Escape for HTML. Html instances pass through untouched. */
function e(mixed $v): string
{
    if ($v instanceof Html) return (string)$v;
    if ($v === null || is_array($v)) return '';
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string)W5D\Env::get('APP_BASE_PATH', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $f = ROOT . '/htdocs/assets/_base/' . ltrim($path, '/');
    $v = is_file($f) ? substr((string)filemtime($f), -6) : '0';
    return url('assets/_base/' . ltrim($path, '/')) . '?v=' . $v;
}

function lang(): string { return Lang::current(); }
function dir_attr(): string { return Lang::dir(); }

/** UI chrome string from lang/{code}.php (dotted key). Falls back to en, then the key. */
function __(string $key, array $r = []): string
{
    $s = Lang::line($key);
    foreach ($r as $k => $v) $s = str_replace(':' . $k, (string)$v, $s);
    return Variables::resolve($s);
}

/** Localised content value: {"en":..,"fr":..,"ar":..} | string. Tokens {{ a.b }} are resolved. */
function t(mixed $v, ?string $l = null): string
{
    if ($v instanceof Html) return (string)$v;
    if (is_array($v)) {
        $l ??= Lang::current();
        $v = $v[$l] ?? $v['en'] ?? (is_string(reset($v)) ? reset($v) : '');
        if (is_array($v)) return '';
    }
    return Variables::resolve((string)($v ?? ''));
}

/** True when the value has no native translation for the active language. */
function t_missing(mixed $v): bool
{
    return is_array($v) && !isset($v[Lang::current()]) && Lang::current() !== 'en';
}

/** Tiny inline markup: **bold**, *em*, [text](url), `code`. Output is escaped HTML. */
function rich(mixed $v): Html
{
    $s = e(t($v));
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s);
    $s = preg_replace('/`([^`]+)`/', '<code class="font-mono text-[0.9em] px-1 rounded bg-slate-500/10">$1</code>', $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $href = html_entity_decode($m[2]);
        if (!preg_match('#^(/|https?://|mailto:|\#)#', $href)) return $m[0];
        return '<a class="underline decoration-brand-500/50 hover:decoration-brand-500" href="' . e(str_starts_with($href, '/') ? url($href) : $href) . '">' . $m[1] . '</a>';
    }, $s);
    return new Html($s);
}

function link_to(string $path): string
{
    if (str_contains($path, '{{')) $path = Variables::resolve($path); // hrefs may carry tokens, e.g. mailto:{{ company.privacy_contact }}
    return preg_match('#^(https?:|mailto:|tel:|\#)#', $path) ? $path : url($path);
}

/** Images registered for a card key (data/cards/manifest.json, built by tools/gen/card_images.py). Returns [] when none. */
function card_images(string $key): array
{
    static $m = null;
    $m ??= is_file(ROOT . '/data/cards/manifest.json') ? (json_decode((string)file_get_contents(ROOT . '/data/cards/manifest.json'), true)['cards'] ?? []) : [];
    return $m[$key]['images'] ?? [];
}

/** Inline Lucide SVG from the locally vendored icon set (no CDN, no icon font, no JS). */
function icon(string $name, string $class = 'w-5 h-5'): Html
{
    static $set = null;
    $set ??= is_file(ROOT . '/htdocs/assets/_base/icons.json') ? json_decode((string)file_get_contents(ROOT . '/htdocs/assets/_base/icons.json'), true) : [];
    $inner = $set[$name] ?? $set['circle'] ?? '<circle cx="12" cy="12" r="9"/>';
    return new Html('<svg xmlns="http://www.w3.org/2000/svg" class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>');
}

function csrf_token(): string { return Csrf::token(); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">'; }

function view(string $name, array $data = []): string
{
    return (new ViewEnv())->make($name, $data)->render();
}

function not_found(): W5D\Response
{
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (PHP_SAPI !== 'cli' && (str_starts_with($path, '/api/') || str_ends_with($path, '.json'))) {
        return W5D\Response::json(['error' => 'not_found', 'message' => 'No such API resource. See /developers/ for the endpoints.'], 404, ['X-Robots-Tag' => 'noindex']);
    }
    return W5D\Response::html(view('pages.404', ['title' => '404']), 404);
}

function money(float $v, int $dec = 2): string
{
    return number_format($v, $dec, '.', ',') . ' ' . Variables::get('money.currency', 'TND');
}

function num(int|float $n, int $dec = 0): string { return number_format((float)$n, $dec, '.', ','); }

function old(string $k, string $d = ''): string { return (string)($_SESSION['_old'][$k] ?? $d); }

/** Gradient CSS variable name for a macro slug. */
function g_macro(?string $slug): string
{
    return match ($slug) {
        'cultures' => 'var(--g-cultures)', 'built' => 'var(--g-built)', 'sciences' => 'var(--g-sciences)',
        'governance' => 'var(--g-governance)', 'technology' => 'var(--g-technology)', default => 'var(--g-focus)',
    };
}

function json_out(mixed $v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

function admin_url(string $p = ''): string { return url(trim((string)W5D\Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/') . '/' . ltrim($p, '/')); }

/** Headline with accent: **x** becomes a gradient span. Output is escaped HTML. */
function hl(mixed $v): Html
{
    $s = e(t($v));
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<span class="gradient-text">$1</span>', $s);
    return new Html($s);
}

/** Numeric value for counters: numbers pass through, token strings are resolved. */
function numval(mixed $v): float
{
    if (is_int($v) || is_float($v)) return (float)$v;
    $s = Variables::resolve((string)$v);
    return (float)str_replace([',', ' '], '', $s);
}

/** Inline 3-language literal for the active language. */
function tr3(string $en, string $fr, string $ar): string { return t(['en' => $en, 'fr' => $fr, 'ar' => $ar]); }

/** Inline brand SVG (simple-icons subset, vendored locally). */
function brand(string $name, string $class = 'w-5 h-5'): Html
{
    static $set = null;
    $set ??= is_file(ROOT . '/htdocs/assets/_base/brands.json') ? json_decode((string)file_get_contents(ROOT . '/htdocs/assets/_base/brands.json'), true) : [];
    $d = $set[$name] ?? null;
    if ($d === null) return icon('circle', $class);
    return new Html('<svg xmlns="http://www.w3.org/2000/svg" class="' . e($class) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . e($d) . '"/></svg>');
}

/** Absolute URL for a site path. */
function abs_url(string $path = ''): string { return W5D\Env::baseUrl() . url($path); }
