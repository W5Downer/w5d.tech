<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Account, Content, Env, Request, Response, Variables};

/**
 * Documentation: one API for developers (OpenAPI + HTML), one for AI agents (manifest, tools, llms.txt).
 * PRIVATE since v2: every documentation page and machine file below requires a signed-in session (account or owner)
 * and is noindex. Nothing is deleted: the same content is also embedded, per audience, in each dashboard (ApiGuide).
 * The data endpoints under /api/*.json stay public: they are the API itself, not its documentation.
 */
final class Docs
{
    private static function base(): string { return Env::baseUrl() . rtrim(url(''), '/'); }


    /** Sign-in gate for documentation. Pages redirect to the sign-in page; machine files answer 401 (never cached, never indexed). */
    public static function gate(Request $q): ?Response
    {
        if (Account::signedIn()) return null;
        $h = ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store'];
        $path = rtrim($q->path, '/');
        if ($path === '/developers' || $path === '/agents') {
            return Response::redirect(url('/account/login/') . '?next=' . rawurlencode($path . '/'))->withHeaders($h);
        }
        $login = Env::baseUrl() . url('/account/login/');
        $msg = 'API documentation is private. Sign in, then open API & Docs in your dashboard.';
        if (str_ends_with($path, '.txt')) return Response::text($msg . "\nSign in: $login\n", 'text/plain', 401)->withHeaders($h);
        return Response::json(['error' => 'sign_in_required', 'message' => $msg, 'login_url' => $login], 401)->withHeaders($h);
    }

    /** Single source of truth for the public API surface. */
    public static function endpoints(): array
    {
        $lang = ['name' => 'lang', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['en', 'fr', 'ar'], 'default' => 'en'], 'description' => 'Content language'];
        $slug = fn(string $d) => ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => $d];
        $G = fn(string $id, string $sum, array $params = []) => ['get' => ['operationId' => $id, 'summary' => $sum, 'parameters' => array_merge($params, [$lang]), 'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]], '404' => ['description' => 'Not found']]]];
        $form = fn(string $id, string $sum, array $props, array $req) => ['post' => ['operationId' => $id, 'summary' => $sum, 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => $req, 'properties' => $props]]]], 'responses' => ['201' => ['description' => 'Created'], '422' => ['description' => 'Validation errors'], '429' => ['description' => 'Rate limited (10 writes / 10 min / IP)']]]];
        $s = ['type' => 'string']; $i = ['type' => 'integer'];
        return [
            '/api/index.json' => $G('index', 'API index'),
            '/api/price.json' => $G('getPrice', 'Live price per part from the published function'),
            '/api/price/curve.json' => $G('getPriceCurve', 'The published price curve (p and t moving together)'),
            '/api/quote' => ['get' => ['operationId' => 'getQuote', 'summary' => 'Price schedule and total for a commitment (nothing is stored)', 'parameters' => [
                ['name' => 'parts', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => (int)Variables::get('investor.max_parts_per_day')]],
                ['name' => 'days', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => (int)Variables::get('investor.max_days')]],
                ['name' => 'start', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                ['name' => 'cadence', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['daily', 'weekly']]]], 'responses' => ['200' => ['description' => 'Schedule'], '422' => ['description' => 'Validation']]]],
            '/api/kpis.json' => $G('getKpis', 'Dated programme KPIs from daily rollups'),
            '/api/macros.json' => $G('listMacros', 'The five macro works'),
            '/api/macros/{slug}.json' => $G('getMacro', 'One macro work', [$slug('cultures|built|sciences|governance|technology')]),
            '/api/services.json' => $G('listServices', 'All services (filter ?macro=)', [['name' => 'macro', 'in' => 'query', 'schema' => $s]]),
            '/api/services/{slug}.json' => $G('getService', 'One service in full', [$slug('Service slug')]),
            '/api/projects.json' => $G('listProjects', 'All projects with dated status'),
            '/api/projects/{slug}.json' => $G('getProject', 'One project', [$slug('Project slug')]),
            '/api/sliders.json' => $G('listSliders', 'Slider names'),
            '/api/sliders/{name}.json' => $G('getSlider', 'One slider (slides, CTAs)', [['name' => 'name', 'in' => 'path', 'required' => true, 'schema' => $s]]),
            '/api/pages/{slug}.json' => $G('getPage', 'A page as structured sections', [$slug('Page slug')]),
            '/api/governorates.json' => $G('listGovernorates', 'The 24 governorates'),
            '/api/subscriptions' => $form('createSubscription', 'Create a pledge or daily subscription (demo: no money moves)', ['full_name' => $s, 'email' => ['type' => 'string', 'format' => 'email'], 'parts_per_day' => $i, 'days' => $i, 'start_on' => ['type' => 'string', 'format' => 'date'], 'cadence' => ['type' => 'string', 'enum' => ['daily', 'weekly']], 'macro' => $s, 'mode' => ['type' => 'string', 'enum' => ['subscription', 'checkout']], 'accept' => ['type' => 'boolean']], ['full_name', 'email', 'parts_per_day', 'days', 'accept']),
            '/api/managers/apply' => $form('applyManager', 'Apply as a team manager', ['name' => $s, 'email' => $s, 'region' => $s, 'macro' => $s, 'years' => $i, 'team' => $i, 'profile' => $s, 'motivation' => $s, 'scenario' => $s, 'accept' => ['type' => 'boolean']], ['name', 'email', 'region', 'macro', 'years', 'team', 'motivation', 'scenario', 'accept']),
            '/api/contact' => $form('sendContact', 'Write to a desk', ['name' => $s, 'email' => $s, 'desk' => ['type' => 'string', 'enum' => Forms::DESKS], 'subject' => $s, 'message' => $s], ['name', 'email', 'message']),
            '/api/eligibility' => $form('checkEligibility', 'Indicative service matches for a profile', ['diploma' => ['type' => 'string', 'enum' => Forms::DIPLOMAS], 'field' => $s, 'macro' => $s], ['diploma', 'field']),
        ];
    }

    public static function openapi(Request $q): Response
    {
        if ($g = self::gate($q)) return $g;
        return Response::json(['openapi' => '3.0.3',
            'info' => ['title' => 'W5D public API', 'version' => '1.0.0', 'description' => "Read endpoints are public and cached for 60 seconds. Write endpoints are rate-limited and honeypot-guarded; in demo mode no payment is taken. Honesty constraint: a part is an investment in a path, not a job; no return is promised. The 130,000 experts figure is a design equation; certified today: " . Variables::get('d.experts_current') . '.'],
            'servers' => [['url' => self::base() ?: '/']],
            'paths' => self::endpoints(),
            'tags' => [['name' => 'public']]]);
    }

    /* ---------- AI agents ---------- */
    public static function agentTools(): array
    {
        $T = fn(string $n, string $d, string $m, string $p, array $props = [], array $req = []) => ['name' => $n, 'description' => $d, 'method' => $m, 'path' => $p, 'input_schema' => ['type' => 'object', 'properties' => $props ?: new \stdClass(), 'required' => $req]];
        $s = ['type' => 'string']; $i = ['type' => 'integer'];
        return [
            $T('get_price', 'Return today\'s price per part (day and week) and the constants of the published function.', 'GET', '/api/price.json'),
            $T('get_quote', 'Compute the schedule and total for N parts per day over D days. Always show the full schedule to the human before they commit.', 'GET', '/api/quote', ['parts' => $i, 'days' => $i, 'start' => $s, 'cadence' => ['type' => 'string', 'enum' => ['daily', 'weekly']]], ['parts', 'days']),
            $T('get_kpis', 'Return dated programme KPIs. experts_current is the honest certified count.', 'GET', '/api/kpis.json'),
            $T('list_macros', 'List the five macro works.', 'GET', '/api/macros.json'),
            $T('list_services', 'List services, optionally filtered by macro slug.', 'GET', '/api/services.json', ['macro' => $s]),
            $T('get_service', 'Fetch one service in full (deliverables, evidence, boundary).', 'GET', '/api/services/{slug}.json', ['slug' => $s], ['slug']),
            $T('list_projects', 'List projects with dated status (live, building, concept).', 'GET', '/api/projects.json'),
            $T('check_eligibility', 'Indicative service matches for a diploma and field. A human reviewer confirms.', 'POST', '/api/eligibility', ['diploma' => $s, 'field' => $s, 'macro' => $s], ['diploma', 'field']),
            $T('send_contact', 'Send a message to a desk on behalf of a human who asked you to.', 'POST', '/api/contact', ['name' => $s, 'email' => $s, 'desk' => $s, 'message' => $s], ['name', 'email', 'message']),
        ];
    }

    public static function rules(): array
    {
        return [
            'Never state or imply a return, dividend, resale value or employment. A part is an investment in a path, not a job.',
            'Quote the price only from /api/price.json or /api/quote. Never compute or remember a price.',
            'Say "' . Variables::get('d.experts_target') . ' experts" only as a design equation. Certified today: ' . Variables::get('d.experts_current') . '. Developers delivering today: ' . Variables::get('experts.developers_today') . '.',
            'Show the full schedule and total before a human confirms any commitment. Writes require the human\'s explicit instruction.',
            'Respect rate limits (10 writes / 10 min / IP). Do not retry a 429 immediately.',
            'Cite the as_of date from meta.as_of when giving figures.',
            'Demo mode: no payment is taken. Say so.',
        ];
    }

    public static function agentManifest(Request $q): Response
    {
        if ($g = self::gate($q)) return $g;
        $b = self::base();
        return Response::json(['schema_version' => '1', 'name' => 'W5D', 'description' => 'World Five Dimensions: five macro works, ' . Variables::get('counts.services') . ' services. Investors take a daily part; experts and managers join a certified path.',
            'languages' => ['en', 'fr', 'ar'], 'contact' => Variables::get('investor.contact'),
            'api' => ['base_url' => $b . '/api', 'openapi' => $b . '/api/openapi.json', 'index' => $b . '/api/index.json', 'auth' => 'none (public, rate-limited)'],
            'tools_url' => $b . '/api/docs/agents/tools.json', 'llms_txt' => $b . '/llms.txt', 'llms_full' => $b . '/llms-full.txt',
            'rules' => self::rules(), 'tools' => array_map(fn($t) => ['name' => $t['name'], 'description' => $t['description']], self::agentTools())]);
    }

    public static function agentToolsJson(Request $q): Response
    {
        if ($g = self::gate($q)) return $g;
        return Response::json(['tools' => self::agentTools(), 'base_url' => self::base()]);
    }

    public static function llms(Request $q): Response
    {
        if ($g = self::gate($q)) return $g;
        $b = self::base();
        $o = "# W5D — World Five Dimensions\n\n> Five macro works, " . Variables::get('counts.services') . " services, one weekly work engine. Investors take at least a part of W5D every day; experts and team managers join a certified path. A part is an investment in a path, not a job. No return is promised.\n\n";
        $o .= "## Rules for AI agents\n" . implode("\n", array_map(fn($r) => '- ' . $r, self::rules())) . "\n\n";
        $o .= "## Key pages\n- [Investors]($b/investors/)\n- [Pricing]($b/pricing/)\n- [Checkout]($b/checkout/)\n- [Daily subscription]($b/subscription/)\n- [Become a manager]($b/careers/managers/)\n- [How it works]($b/how-it-works/)\n- [Trust and declared limits]($b/trust/statement/)\n- [Status]($b/status/)\n- [Sitemap]($b/sitemap/)\n\n";
        $o .= "## Machine interfaces\n- [OpenAPI]($b/api/openapi.json)\n- [Agent manifest]($b/api/agents.json)\n- [Agent tools]($b/api/docs/agents/tools.json)\n- [API index]($b/api/index.json)\n- [Full text]($b/llms-full.txt)\n";
        return Response::text($o, 'text/plain');
    }

    public static function llmsFull(Request $q): Response
    {
        if ($g = self::gate($q)) return $g;
        $b = self::base();
        $o = "# W5D — full reference (English)\n\nFigures as of " . Variables::get('dates.as_of') . ".\n\n## Honest numbers\n- Design equation: " . Variables::get('counts.macros') . ' macros × ' . Variables::get('experts.services_per_macro') . ' services × ' . Variables::get('experts.per_service') . ' = ' . Variables::get('d.experts_target') . " experts (not a headcount).\n- Certified today: " . Variables::get('d.experts_current') . ".\n- Sampling: a fixed " . Variables::get('quality.sampling_percent') . "% random audit.\n- Price today: " . Variables::get('price.day') . ' ' . Variables::get('money.currency') . " per part per day.\n\n## Macro works\n";
        foreach (Content::all('macros') as $m) $o .= "\n### " . t($m['name'], 'en') . "\n" . t($m['tagline'], 'en') . "\n$b/macro-works/{$m['slug']}/\n";
        $o .= "\n## Services\n";
        foreach (Content::all('services') as $s) $o .= '- #' . str_pad((string)$s['id'], 3, '0', STR_PAD_LEFT) . ' [' . t($s['title'], 'en') . "]($b/services/{$s['slug']}/) — " . t($s['short'], 'en') . "\n";
        $o .= "\n## Projects\n";
        foreach (Content::all('projects') as $p) $o .= '- [' . t($p['name'], 'en') . "]($b/projects/{$p['slug']}/) (" . t($p['status_label'], 'en') . ', ' . ($p['status_date'] ?? '') . ') — ' . t($p['tagline'], 'en') . "\n";
        return Response::text($o, 'text/plain');
    }

    public static function sitemap(Request $q): Response
    {
        $b = Env::baseUrl();
        $asOf = (string)Variables::get('dates.as_of');
        $static = ['/' => 1.0, '/investors/' => .9, '/pricing/' => .8, '/checkout/' => .9, '/subscription/' => .7, '/for/' => .8, '/careers/' => .6, '/careers/managers/' => .6, '/join/' => .7, '/join/eligibility/' => .6,
            '/app/register/' => .6, '/how-it-works/' => .8, '/partners/' => .6, '/about/' => .6, '/ontology/' => .5, '/countries/tunisia/' => .6, '/faq/' => .6, '/contact/' => .5, '/newsletter/' => .4,
            '/status/' => .5, '/trust/' => .6, '/trust/statement/' => .6, '/trust/sampling/' => .5, '/trust/privacy/' => .4, '/trust/terms/' => .4, '/trust/security/' => .4, '/trust/corrections/' => .4,
            '/macro-works/' => .8, '/services/' => .7, '/projects/' => .7, '/sitemap/' => .3];
        foreach (Pages::AUDIENCES as $a) $static['/for/' . $a . '/'] = .7;
        $urls = [];
        foreach ($static as $u => $pr) $urls[$u] = $pr;
        foreach (Content::all('macros') as $m) $urls["/macro-works/{$m['slug']}/"] = .8;
        foreach (Content::all('services') as $s) $urls["/services/{$s['slug']}/"] = .5;
        foreach (Content::all('projects') as $p) $urls["/projects/{$p['slug']}/"] = .6;
        foreach (Content::file('countries/tn.json')['regions'] ?? [] as $r) $urls["/tn/{$r['slug']}/"] = .4;
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($urls as $u => $pr) {
            $freq = $u === '/' || $u === '/status/' ? 'daily' : ($pr >= .7 ? 'weekly' : 'monthly');
            $img = \W5D\Social::imageUrl($u);
            $x .= '  <url><loc>' . htmlspecialchars($b . url($u)) . '</loc><lastmod>' . $asOf . '</lastmod><changefreq>' . $freq . '</changefreq><priority>' . number_format($pr, 1) . '</priority>'
                . '<image:image><image:loc>' . htmlspecialchars($img) . '</image:loc></image:image></url>' . "\n";
        }
        return Response::text($x . '</urlset>', 'application/xml');
    }

    public static function securityTxt(Request $q): Response
    {
        $mail = (string)Env::get('SEO_ORG_EMAIL', Env::get('CONTACT_EMAIL', 'owner@w5d.tech'));
        $exp = (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s\Z');
        return Response::text("Contact: mailto:$mail\nExpires: $exp\nPreferred-Languages: en, fr, ar\nCanonical: " . self::base() . "/.well-known/security.txt\nPolicy: " . self::base() . "/trust/security/\n", 'text/plain');
    }

    public static function humans(Request $q): Response
    {
        $o = "/* TEAM */\nSite: " . Env::get('SEO_ORG_NAME', 'W5D TECH') . "\nContact: " . Env::get('SEO_ORG_EMAIL', 'owner@w5d.tech') . "\nLocation: Tunisia\n\n/* SITE */\nLast update: " . Variables::get('dates.as_of') . "\nStandards: HTML5, CSS3, PHP 8.4, SQLite\nNo third-party scripts, fonts or trackers.\n";
        return Response::text($o, 'text/plain');
    }

    public static function manifest(Request $q): Response
    {
        $i = fn(string $f, string $sz, string $purpose = 'any') => ['src' => url('assets/_base/images/' . $f), 'sizes' => $sz, 'type' => 'image/png', 'purpose' => $purpose];
        $j = json_encode([
            'name' => 'W5D — World Five Dimensions', 'short_name' => 'W5D', 'description' => t(__('brand.tagline')), 'id' => url('/'), 'start_url' => url('/'), 'scope' => url('/'),
            'display' => 'standalone', 'background_color' => '#070711', 'theme_color' => '#070711', 'lang' => lang(), 'dir' => dir_attr(),
            'icons' => [$i('icon-192.png', '192x192'), $i('icon-512.png', '512x512'), $i('icon-maskable-512.png', '512x512', 'maskable')],
            'shortcuts' => [['name' => __('cta.invest'), 'url' => url('/checkout/')], ['name' => __('acct.account'), 'url' => url('/account/')]],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return Response::text((string)$j, 'application/manifest+json');
    }

    public static function robots(Request $q): Response
    {
        $admin = '/' . trim((string)Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/');
        $dis = [$admin . '/', '/account/', '/pay/', '/language/', '/lang/', '/subscription/manage/', '/checkout/done/', '/unsubscribe/', '/api/quote', '/developers/', '/agents/', '/api/openapi.json', '/openapi.json', '/api/agents.json', '/api/docs/', '/llms.txt', '/llms-full.txt', '/.well-known/agents.json'];
        $o = "User-agent: *\nAllow: /\n" . implode('', array_map(fn($d) => "Disallow: $d\n", $dis)) . "\nSitemap: " . Env::baseUrl() . "/sitemap.xml\n# API documentation is private: sign in and open API & Docs in your dashboard.\n";
        return Response::text($o, 'text/plain');
    }

    /* ---------- human pages ---------- */
    private static function code(string $s): string { return $s; }

    public static function developers(Request $q): Response
    {
        if ($g = self::gate($q)) return $g;
        $L = [Pages::class, 'L'];
        $b = self::base();
        $rows = [];
        foreach (self::endpoints() as $path => $ops) {
            foreach ($ops as $m => $op) $rows[] = [['en' => strtoupper($m)], ['en' => '`' . $path . '`'], ['en' => $op['summary']], ['en' => '`' . $op['operationId'] . '`']];
        }
        return Pages::render($q, ['robots' => 'noindex,nofollow', 'title' => $L('Developer API', 'API développeurs', 'واجهة المطورين'), 'description' => $L('Public JSON API and OpenAPI description for W5D.', 'API JSON publique et description OpenAPI de W5D.', 'واجهة JSON عامة ووصف OpenAPI لـ W5D.'), 'sections' => [
            ['type' => 'hero', 'id' => 'dev-hero', 'variant' => 'classic', 'gradient' => 'technology', 'icon' => 'code', 'eyebrow' => $L('For developers', 'Pour les développeurs', 'للمطورين'),
                'title' => $L('One public API. **Same data as the site.**', 'Une API publique. **Les mêmes données que le site.**', 'واجهة عامة واحدة. **بيانات الموقع نفسها.**'),
                'lead' => $L('Read endpoints are public, cached for 60 seconds and available in English, French and Arabic. Write endpoints are rate-limited and guarded.', 'Les points de lecture sont publics, mis en cache 60 secondes et disponibles en anglais, français et arabe. Les points d’écriture sont limités et protégés.', 'نقاط القراءة عامة ومخزنة 60 ثانية ومتاحة بالإنجليزية والفرنسية والعربية. نقاط الكتابة محدودة ومحمية.'),
                'ctas' => [['label' => $L('OpenAPI JSON', 'OpenAPI JSON', 'OpenAPI JSON'), 'href' => '/api/openapi.json', 'style' => 'peak', 'arrow' => true], ['label' => $L('AI-agent docs', 'Docs agents IA', 'وثائق الوكلاء'), 'href' => '/agents/', 'style' => 'ghost']]],
            ['type' => 'steps', 'id' => 'dev-start', 'tier' => 'i', 'focus' => 2, 'title' => $L('Quick start', 'Démarrage rapide', 'بداية سريعة'), 'items' => [
                ['title' => $L('Read the price', 'Lire le prix', 'اقرأ السعر'), 'body' => ['en' => '`curl ' . $b . '/api/price.json`']],
                ['title' => $L('Quote a commitment', 'Chiffrer un engagement', 'احسب التزاما'), 'body' => ['en' => '`curl "' . $b . '/api/quote?parts=3&days=30"`']],
                ['title' => $L('Switch language', 'Changer de langue', 'غيّر اللغة'), 'body' => ['en' => 'Append `?lang=fr` or `?lang=ar` to any GET.']],
                ['title' => $L('Write (demo)', 'Écrire (démo)', 'اكتب (تجريبي)'), 'body' => ['en' => '`POST /api/contact` with JSON. Honeypot field `website` must stay empty.']]]],
            ['type' => 'table', 'id' => 'dev-endpoints', 'tier' => 'i', 'focus' => 3, 'title' => $L('Endpoints', 'Points d’accès', 'نقاط الوصول'), 'columns' => [$L('Method', 'Méthode', 'الطريقة'), $L('Path', 'Chemin', 'المسار'), $L('Summary', 'Résumé', 'الملخص'), 'operationId'], 'rows' => $rows],
            ['type' => 'definitions', 'id' => 'dev-conventions', 'tier' => 'i', 'focus' => 2, 'title' => $L('Conventions', 'Conventions', 'الاتفاقيات'), 'items' => [
                ['term' => $L('Envelope', 'Enveloppe', 'الغلاف'), 'def' => ['en' => 'Reads return `{ data, meta:{ lang, as_of } }`.']],
                ['term' => $L('Errors', 'Erreurs', 'الأخطاء'), 'def' => ['en' => '`404 {error:"not_found"}`, `422 {error:"validation", errors:{field:msg}}`, `429 {error:"rate_limited"}`.']],
                ['term' => $L('Rate limit', 'Limite', 'الحد'), 'def' => ['en' => '10 writes per 10 minutes per IP.']],
                ['term' => $L('Pricing', 'Tarification', 'التسعير'), 'def' => ['en' => 'Prices come only from the published function in `Pricing.php`; never hard-code them.']],
                ['term' => $L('Honesty', 'Honnêteté', 'الصدق'), 'def' => $L('A part is an investment in a path, not a job.', 'Une part est un investissement dans un parcours, pas un emploi.', 'الحصة استثمار في مسار لا وظيفة.')]]],
            ['type' => 'notice', 'id' => 'dev-limits'],
        ]]);
    }

    public static function agents(Request $q): Response
    {
        if ($g = self::gate($q)) return $g;
        $L = [Pages::class, 'L'];
        $b = self::base();
        $tools = array_map(fn($t) => [['en' => '`' . $t['name'] . '`'], ['en' => $t['method'] . ' `' . $t['path'] . '`'], ['en' => $t['description']]], self::agentTools());
        return Pages::render($q, ['robots' => 'noindex,nofollow', 'title' => $L('AI agents', 'Agents IA', 'وكلاء الذكاء الاصطناعي'), 'description' => $L('Manifest, tools and rules for AI agents that read or act on W5D.', 'Manifeste, outils et règles pour les agents IA qui lisent ou agissent sur W5D.', 'بيان وأدوات وقواعد لوكلاء الذكاء الاصطناعي الذين يقرؤون W5D أو يتصرفون عليه.'), 'sections' => [
            ['type' => 'hero', 'id' => 'ag-hero', 'variant' => 'classic', 'gradient' => 'governance', 'icon' => 'bot', 'eyebrow' => $L('For AI agents', 'Pour les agents IA', 'للوكلاء الأذكياء'),
                'title' => $L('Machine-readable. **Honest by rule.**', 'Lisible par machine. **Honnête par règle.**', 'مقروء آليا. **صادق بالقاعدة.**'),
                'lead' => $L('A manifest, a tool list and seven rules. Agents may quote the price, compute a schedule and relay a human\'s request, but may never promise a return or a job.', 'Un manifeste, une liste d’outils et sept règles. Les agents peuvent citer le prix, calculer un calendrier et relayer une demande humaine, mais jamais promettre un rendement ou un emploi.', 'بيان وقائمة أدوات وسبع قواعد. يمكن للوكلاء ذكر السعر وحساب جدول ونقل طلب إنسان، لكن لا يعدون بعائد أو وظيفة.'),
                'ctas' => [['label' => $L('Agent manifest', 'Manifeste', 'البيان'), 'href' => '/api/agents.json', 'style' => 'peak', 'arrow' => true], ['label' => 'llms.txt', 'href' => '/llms.txt', 'style' => 'ghost']]],
            ['type' => 'cards', 'id' => 'ag-rules', 'tier' => 'i', 'focus' => 3, 'cols' => 3, 'title' => $L('Rules every agent follows', 'Règles de tout agent', 'قواعد كل وكيل'), 'items' => array_map(fn($r) => ['icon' => 'shield-check', 'title' => ['en' => $r]], self::rules())],
            ['type' => 'table', 'id' => 'ag-tools', 'tier' => 'i', 'focus' => 2, 'title' => $L('Tools', 'Outils', 'الأدوات'), 'columns' => [$L('Tool', 'Outil', 'الأداة'), $L('Call', 'Appel', 'الاستدعاء'), $L('Purpose', 'Usage', 'الغرض')], 'rows' => $tools],
            ['type' => 'definitions', 'id' => 'ag-urls', 'tier' => 'i', 'focus' => 2, 'title' => $L('Discovery', 'Découverte', 'الاكتشاف'), 'items' => [
                ['term' => ['en' => 'Manifest'], 'def' => ['en' => '`' . $b . '/api/agents.json` (also `/.well-known/agents.json`)']], ['term' => ['en' => 'Tools'], 'def' => ['en' => '`' . $b . '/api/docs/agents/tools.json`']],
                ['term' => ['en' => 'llms.txt'], 'def' => ['en' => '`' . $b . '/llms.txt`']], ['term' => ['en' => 'llms-full.txt'], 'def' => ['en' => '`' . $b . '/llms-full.txt`']], ['term' => ['en' => 'OpenAPI'], 'def' => ['en' => '`' . $b . '/openapi.json`']]]],
            ['type' => 'notice', 'id' => 'ag-limits'],
        ]]);
    }
}
