<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Content, DB, Pricing, Request, Response, Rollup, Variables};

/** Public read-only JSON (GET) plus a few guarded write endpoints (POST). No auth, no PII in any GET. */
final class Api
{
    private static function localise(mixed $v, string $l): mixed
    {
        if (is_array($v)) {
            $keys = array_keys($v);
            if ($keys && !array_diff($keys, ['en', 'fr', 'ar']) && isset($v['en'])) return t($v, $l);
            return array_map(fn($x) => self::localise($x, $l), $v);
        }
        return is_string($v) ? Variables::resolve($v) : $v;
    }

    private static function L(Request $q): string
    {
        $l = (string)($q->query['lang'] ?? '');
        return in_array($l, ['en', 'fr', 'ar'], true) ? $l : 'en';
    }

    private static function ok(array $d, Request $q, array $meta = []): Response
    {
        return Response::json(['data' => self::localise($d, self::L($q)), 'meta' => $meta + ['lang' => self::L($q), 'as_of' => Variables::get('dates.as_of')]], 200, ['Cache-Control' => 'public, max-age=60']);
    }

    private static function notFound(): Response { return Response::json(['error' => 'not_found'], 404); }

    public static function index(Request $q): Response
    {
        $b = \W5D\Env::baseUrl();
        $e = fn(string $p) => $b . url($p);
        return Response::json(['name' => 'W5D public API', 'version' => '1', 'docs' => ['private' => true, 'where' => 'Sign in and open API & Docs in your dashboard: ' . $e('/account/api/')],
            'languages' => ['en', 'fr', 'ar'], 'note' => 'Append ?lang=fr|ar to any GET. Read endpoints are public and cached 60s.',
            'endpoints' => [
                ['GET', '/api/price.json', 'Live price per part'], ['GET', '/api/price/curve.json', 'Published price curve'], ['GET', '/api/quote?parts=&days=&start=&cadence=', 'Schedule and total'],
                ['GET', '/api/kpis.json', 'Dated programme KPIs (rollups only)'], ['GET', '/api/macros.json', 'Five macro works'], ['GET', '/api/macros/{slug}.json', 'One macro work'],
                ['GET', '/api/services.json', 'All services'], ['GET', '/api/services/{slug}.json', 'One service'], ['GET', '/api/projects.json', 'All projects'], ['GET', '/api/projects/{slug}.json', 'One project'],
                ['GET', '/api/sliders.json', 'Slider names'], ['GET', '/api/sliders/{name}.json', 'One slider'], ['GET', '/api/pages/{slug}.json', 'Page structure'], ['GET', '/api/governorates.json', 'Governorates'],
                ['POST', '/api/subscriptions', 'Create a pledge / daily subscription (demo)'], ['POST', '/api/managers/apply', 'Manager application'], ['POST', '/api/contact', 'Contact desk'], ['POST', '/api/eligibility', 'Eligibility check'],
            ]]);
    }

    public static function price(Request $q): Response
    {
        $v = Variables::all();
        return self::ok(['price' => Pricing::snapshot($v), 'formula' => 'price = base * (1 + alpha * p^p_prog) * (1 + beta * t^p_time), capped at cap * base', 'constants' => $v['pricing'], 'base' => ['day' => $v['money']['base_day'], 'week' => $v['money']['base_week']], 'currency' => $v['money']['currency'], 'honesty' => 'A part is an investment in a path, not a job. No return is promised.'], $q);
    }

    public static function curve(Request $q): Response { return self::ok(['curve' => Pricing::curve(20)], $q); }

    public static function kpis(Request $q): Response
    {
        $d = Variables::get('d');
        return self::ok(['programme' => Rollup::kpis(), 'design_targets' => ['experts' => $d['experts_target'], 'managers' => $d['managers_target'], 'leaders' => $d['leaders_target'], 'note' => 'Design equation, not a headcount.'], 'experts_current' => $d['experts_current'], 'developers_today' => Variables::get('experts.developers_today'), 'sampling_percent' => Variables::get('quality.sampling_percent')], $q);
    }

    public static function macros(Request $q): Response { return self::ok(['macros' => array_values(Content::all('macros'))], $q); }
    public static function macro(Request $q): Response { $x = Content::get('macros', (string)$q->params['slug']); return $x ? self::ok($x + ['services' => array_keys(Content::servicesOf($x['slug']))], $q) : self::notFound(); }

    public static function services(Request $q): Response
    {
        $m = (string)($q->query['macro'] ?? '');
        $list = array_filter(Content::all('services'), fn($s) => $m === '' || $s['macro'] === $m);
        return self::ok(['count' => count($list), 'services' => array_values(array_map(fn($s) => ['slug' => $s['slug'], 'id' => $s['id'], 'macro' => $s['macro'], 'title' => $s['title'], 'short' => $s['short'], 'url' => url('/services/' . $s['slug'] . '/')], $list))], $q);
    }
    public static function service(Request $q): Response { $x = Content::get('services', (string)$q->params['slug']); return $x ? self::ok($x, $q) : self::notFound(); }

    public static function projects(Request $q): Response { return self::ok(['count' => Content::count('projects'), 'projects' => array_values(Content::all('projects'))], $q); }
    public static function project(Request $q): Response { $x = Content::get('projects', (string)$q->params['slug']); return $x ? self::ok($x, $q) : self::notFound(); }

    public static function sliders(Request $q): Response { return self::ok(['sliders' => array_keys(Content::all('sliders'))], $q); }
    public static function slider(Request $q): Response { $x = Content::slider((string)$q->params['name']); return $x ? self::ok($x, $q) : self::notFound(); }
    public static function pageJson(Request $q): Response { $x = Content::page((string)$q->params['slug']); return $x ? self::ok($x, $q) : self::notFound(); }
    public static function governorates(Request $q): Response { return self::ok(['governorates' => Content::file('countries/tn.json')['regions'] ?? []], $q); }

    /** GET or POST. Pure computation: nothing is stored. */
    public static function quote(Request $q): Response
    {
        [$parts, $days, $start, $cad, $e] = Checkout::inputs($q->query + $q->post);
        if ($e) return Response::json(['error' => 'validation', 'errors' => self::localise($e, self::L($q))], 422);
        return Response::json(Checkout::quote($parts, $days, $start, $cad), 200, ['Cache-Control' => 'no-store']);
    }

    private static function write(Request $q, string $bucket, callable $core, array $extra = []): Response
    {
        $g = Forms::guard($q, 'api-' . $bucket, 10, 600);
        if ($g === 'bot') return Response::json(['ok' => true]);
        if ($g === 'rate') return Response::json(['ok' => false, 'error' => 'rate_limited'], 429, ['Retry-After' => '600']);
        if ($g === 'db') return Response::json(['ok' => false, 'error' => 'database_unavailable'], 503);
        $x = $core($q->post);
        if (!$x['ok']) return Response::json(['ok' => false, 'error' => 'validation', 'errors' => self::localise($x['errors'], self::L($q))], 422);
        return Response::json(['ok' => true] + $x['data'] + $extra, 201);
    }

    public static function subscribe(Request $q): Response { $mode = (($q->post['mode'] ?? 'subscription') === 'checkout') ? 'checkout' : 'subscription'; return self::write($q, 'sub', fn($in) => Checkout::core($in, $mode)); }
    public static function managerApply(Request $q): Response { return self::write($q, 'mgr', [Forms::class, 'coreManager']); }
    public static function contact(Request $q): Response { return self::write($q, 'contact', [Forms::class, 'coreContact']); }
    public static function eligibility(Request $q): Response { return self::write($q, 'elig', [Forms::class, 'coreEligibility']); }
}
