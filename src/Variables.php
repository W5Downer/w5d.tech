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
        $v['experts']['current'] = Rollup::expertsCurrent($v['experts']['current']);
        return $v;
    }

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
                default => is_numeric($v) && !is_string($v) && (float)$v >= 10000 ? number_format((float)$v, 0, '.', ',') : (string)$v,
            };
        }, $s);
    }

    public static function unresolved(): array { return array_values(array_unique(self::$unresolved)); }
    public static function reset(): void { self::$flat = null; }
}
