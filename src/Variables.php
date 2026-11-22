<?php
declare(strict_types=1);

namespace W5D;

/** Token store: one fact, one place. {{ path.to.var }} resolved at render. */
final class Variables
{
    private static ?array $flat = null;
    private static array $unresolved = [];

    private static function base(): array
    {
        $v = require ROOT . '/config/variables.php';
        // .env overrides for the published pricing constants (git-tracked change policy applies)
        foreach (['alpha' => 'PRICING_ALPHA', 'beta' => 'PRICING_BETA', 'p_prog' => 'PRICING_P_PROG', 'p_time' => 'PRICING_P_TIME', 'cap' => 'PRICING_CAP'] as $k => $env) {
            $x = Env::get($env);
            if ($x !== null && is_numeric($x)) $v['pricing'][$k] = (float)$x;
        }
        // Published pricing constants (Admin > Progressive Pricing) override config; .env stays the bootstrap default.
        try {
            if (DB::available() && ($pa = DB::one("SELECT alpha, beta, p_prog, p_time, cap FROM pricing_audit WHERE country_id = 'tn' ORDER BY id DESC LIMIT 1"))) {
                foreach ($pa as $k => $x) $v['pricing'][$k] = (float)$x;
            }
        } catch (\Throwable) {}
        $v = self::applyOverrides($v);
        $v['experts']['current'] = Rollup::expertsCurrent($v['experts']['current']);
        return $v;
    }

    /** Paths the owner may NOT edit as plain variables (records, or governed elsewhere). */
    public const LOCKED = ['experts.current', 'quality.sampling_percent'];
    public const LOCKED_PREFIX = ['pricing.'];

    public static function editable(string $path): bool
    {
        if (in_array($path, self::LOCKED, true)) return false;
        foreach (self::LOCKED_PREFIX as $p) if (str_starts_with($path, $p)) return false;
        return true;
    }

    /** Flatten config into dotted scalar leaves. @return array<string,scalar> */
    public static function leaves(array $a, string $p = ''): array
    {
        $o = [];
        foreach ($a as $k => $v) {
            $key = $p === '' ? (string)$k : "$p.$k";
            if (is_array($v)) $o += self::leaves($v, $key); else $o[$key] = $v;
        }
        return $o;
    }

    /** Cast an input string to the type of the config default, or null when invalid. */
    public static function cast(mixed $default, string $raw): int|float|string|null
    {
        $raw = trim($raw);
        if (is_int($default)) return preg_match('/^-?\d+$/', $raw) ? (int)$raw : null;
        if (is_float($default)) return is_numeric($raw) ? (float)$raw : null;
        if (is_string($default) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $default)) {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
            return $d && $d->format('Y-m-d') === $raw ? $raw : null;
        }
        if (is_string($default)) return mb_strlen($raw) <= 200 && $raw !== '' ? $raw : null;
        return null;
    }

    private static function applyOverrides(array $v): array
    {
        try {
            if (!DB::available()) return $v;
            $rows = DB::all('SELECT path, value FROM variable_overrides');
        } catch (\Throwable) { return $v; }
        $def = self::leaves($v);
        foreach ($rows as $r) {
            $path = (string)$r['path'];
            if (!array_key_exists($path, $def) || !self::editable($path)) continue;
            $val = self::cast($def[$path], (string)$r['value']);
            if ($val === null) continue;
            $ref = &$v;
            foreach (explode('.', $path) as $seg) $ref = &$ref[$seg];
            $ref = $val;
            unset($ref);
        }
        return $v;
    }

    /** Config defaults only (no DB), for the editor's "default" column. */
    public static function defaults(): array { return require ROOT . '/config/variables.php'; }

    public static function all(): array
    {
        if (self::$flat !== null) return self::$flat;
        $v = self::base();
        $ex = $v['experts'];
        $services = Content::count('services');
        $macros = Content::count('macros');
        $c = [
            'macros' => $macros, 'services' => $services,
            'projects' => Content::count('projects'), 'layers' => Content::count('layers'),
            'languages' => count(Lang::SUPPORTED), 'governorates' => count((Content::file('countries/tn.json')['regions'] ?? [])),
        ];
        $d = [];
        $d['experts_target']   = $services * $ex['per_service'];
        $d['leaders_target']   = $services;
        $d['managers_target']  = $d['leaders_target'] * $ex['managers_per_service'];
        $d['accounts_total']   = $d['experts_target'] + $d['managers_target'] + $d['leaders_target'] + 1;
        $d['reports_per_year'] = $d['experts_target'] * 52;
        $d['payroll_monthly']  = $d['experts_target'] * $v['money']['salary_monthly'];
        $d['part_monthly']     = $v['money']['base_day'] * 30;
        $d['parts_per_post']   = (int)ceil($v['money']['salary_monthly'] / max(1, $d['part_monthly']));
        $d['reviews_per_week'] = (int)round($d['experts_target'] * $v['quality']['sampling_percent'] / 100);
        $d['experts_current']  = $ex['current'];
        $d['progress_pct']     = $d['experts_target'] > 0 ? round($ex['current'] / $d['experts_target'] * 100, 2) : 0;
        $v['auth'] = ['remember_days' => max(1, (int)Env::get('AUTH_REMEMBER_DAYS', '30'))]; // sign-in cookie lifetime, same source as Account
        $v['dates']['year_now'] = (int)date('Y'); // computed, never stored: copyright range end
        $v['counts'] = $c;
        $v['d'] = $d;
        $v['experts']['target'] = $d['experts_target'];
        $v['experts']['managers_target'] = $d['managers_target'];
        $v['experts']['leaders_target'] = $d['leaders_target'];
        $v['price'] = Pricing::snapshot($v);
        return self::$flat = $v;
    }

    public static function get(string $path, mixed $d = null): mixed
    {
        $v = self::all();
        foreach (explode('.', $path) as $p) {
            if (!is_array($v) || !array_key_exists($p, $v)) return $d;
            $v = $v[$p];
        }
        return $v;
    }

    /** Replace {{ a.b }} tokens. Unresolved tokens are collected for the linter and left visible. */
    public static function resolve(string $s): string
    {
        if (!str_contains($s, '{{')) return $s;
        return preg_replace_callback('/\{\{\s*([a-z_][a-z0-9_.]*)\s*(?:\|\s*(\w+))?\s*\}\}/i', function ($m) {
            $v = self::get($m[1]);
            if ($v === null || is_array($v)) { self::$unresolved[] = $m[1]; return $m[0]; }
            return match ($m[2] ?? '') {
                'num' => number_format((float)$v, 0, '.', ','),
                'money' => number_format((float)$v, 2, '.', ',') . ' ' . self::get('money.currency'),
                'pct' => rtrim(rtrim(number_format((float)$v, 2), '0'), '.') . '%',
                'first' => (float)$v <= 0 ? __('ux.be_first') : number_format((float)$v, 0, '.', ','),
                'devs' => self::devs((int)$v),
                'certs' => (int)$v <= 0 ? __('ux.cert_zero') : ((int)$v === 1 ? __('ux.cert_one') : sprintf(__('ux.cert_many'), (int)$v)),
                default => is_numeric($v) && !is_string($v) && (float)$v >= 10000 ? number_format((float)$v, 0, '.', ',') : (string)$v,
            };
        }, $s);
    }

    /** "1 developer" / "N developers" in the active language (Arabic has dual and few forms). */
    public static function devs(int $n): string
    {
        if ($n <= 0) return __('ux.be_first');
        if ($n === 1) return __('ux.dev_one');
        if (Lang::current() === 'ar') {
            if ($n === 2) return __('ux.dev_two');
            return sprintf($n <= 10 ? __('ux.dev_few') : __('ux.dev_many'), $n);
        }
        return sprintf(__('ux.dev_many'), $n);
    }

    public static function unresolved(): array { return array_values(array_unique(self::$unresolved)); }
    public static function reset(): void { self::$flat = null; }
}
