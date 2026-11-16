<?php
declare(strict_types=1);

namespace W5D;

use W5D\Controllers\{Pages, Forms, Checkout, Api, Docs, Admin};

final class App
{
    public static function boot(): void
    {
        Env::load(ROOT . '/.env');
        date_default_timezone_set('Africa/Tunis');
        mb_internal_encoding('UTF-8');
        if (PHP_SAPI !== 'cli') Session::start();
        else $_SESSION = $_SESSION ?? [];
        ViewEnv::share('currentLang', Lang::current());
    }

    public static function run(): void
    {
        self::boot();
        self::handle(new Request())->send();
    }

    public static function handle(Request $req): Response
    {
        try {
            $res = self::router()->dispatch($req);
        } catch (\Throwable $e) {
            @file_put_contents(ROOT . '/storage/logs/app-' . date('Y-m-d') . '.log', '[' . date('c') . '] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
            $dbg = Env::get('APP_ENV') !== 'production';
            $res = Response::html($dbg ? '<pre>' . e($e) . '</pre>' : 'Server error', 500);
        }
        $res->headers += [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
        ];
        if (str_starts_with($res->headers['Content-Type'] ?? '', 'text/html')) $res->headers['Cache-Control'] = 'no-store';
        return $res;
    }

    public static function router(): Router
    {
        $r = new Router();

        // ---- public site ----
        $r->get('/', [Pages::class, 'home']);
        $r->get('/macro-works', [Pages::class, 'macroIndex']);
        $r->get('/macro-works/{slug}', [Pages::class, 'macro']);
        $r->get('/services', [Pages::class, 'serviceIndex']);
        $r->get('/services/{slug}', [Pages::class, 'service']);
        $r->get('/projects', [Pages::class, 'projectIndex']);
        $r->get('/projects/{slug}', [Pages::class, 'project']);
        $r->get('/tn', fn() => Response::redirect(url('/countries/tunisia/'), 301));
        $r->get('/tn/{gov}', [Pages::class, 'governorate']);
                foreach (['join', 'how-it-works', 'countries/tunisia', 'partners', 'investors', 'pricing', 'about', 'ontology', 'careers', 'faq', 'status', 'trust', 'join/thanks'] as $slug) {
            $r->get('/' . $slug, fn(Request $q) => Pages::page($q, $slug));
        }
        $r->get('/invest', fn() => Response::redirect(url('/investors/'), 301));
        $r->get('/sitemap', [Pages::class, 'sitemap']);
        $r->get('/trust/{page}', [Pages::class, 'trust']);
        $r->any('/join/eligibility', [Forms::class, 'eligibility']);
        $r->any('/app/register', [Forms::class, 'register']);
        $r->any('/contact', [Forms::class, 'contact']);
        $r->any('/careers/managers', [Forms::class, 'managers']);
        $r->any('/newsletter', [Forms::class, 'newsletter']);

        // ---- investor demo flows ----
        $r->any('/checkout', [Checkout::class, 'wizard']);
        $r->any('/subscription', [Checkout::class, 'subscription']);
        $r->get('/checkout/done/{ref}', [Checkout::class, 'done']);
        $r->any('/subscription/manage', [Checkout::class, 'manage']);
        $r->post('/subscription/action', [Checkout::class, 'action']);

        // ---- docs (human) ----
        $r->get('/developers', [Docs::class, 'developers']);
        $r->get('/agents', [Docs::class, 'agents']);

        // ---- machine endpoints ----
        $r->get('/api', fn() => Response::redirect(url('/api/index.json')));
        $r->get('/api/index.json', [Api::class, 'index']);
        $r->get('/api/price.json', [Api::class, 'price']);
        $r->get('/api/price/curve.json', [Api::class, 'curve']);
        $r->get('/api/kpis.json', [Api::class, 'kpis']);
        $r->get('/api/macros.json', [Api::class, 'macros']);
        $r->get('/api/macros/{slug}.json', [Api::class, 'macro']);
        $r->get('/api/services.json', [Api::class, 'services']);
        $r->get('/api/services/{slug}.json', [Api::class, 'service']);
        $r->get('/api/projects.json', [Api::class, 'projects']);
        $r->get('/api/projects/{slug}.json', [Api::class, 'project']);
        $r->get('/api/sliders.json', [Api::class, 'sliders']);
        $r->get('/api/sliders/{name}.json', [Api::class, 'slider']);
        $r->get('/api/pages/{slug}.json', [Api::class, 'pageJson']);
        $r->get('/api/governorates.json', [Api::class, 'governorates']);
        $r->post('/api/quote', [Api::class, 'quote']);
        $r->get('/api/quote', [Api::class, 'quote']);
        $r->post('/api/subscriptions', [Api::class, 'subscribe']);
        $r->post('/api/managers/apply', [Api::class, 'managerApply']);
        $r->post('/api/contact', [Api::class, 'contact']);
        $r->post('/api/eligibility', [Api::class, 'eligibility']);
        $r->get('/api/docs/developers.json', [Docs::class, 'openapi']);
        $r->get('/api/docs/agents.json', [Docs::class, 'agentManifest']);
        $r->get('/api/docs/agents/tools.json', [Docs::class, 'agentToolsJson']);
        $r->get('/openapi.json', [Docs::class, 'openapi']);
        $r->get('/.well-known/agents.json', [Docs::class, 'agentManifest']);
        $r->get('/llms.txt', [Docs::class, 'llms']);
        $r->get('/llms-full.txt', [Docs::class, 'llmsFull']);
        $r->get('/sitemap.xml', [Docs::class, 'sitemap']);
        $r->get('/robots.txt', [Docs::class, 'robots']);

        // ---- language ----
        $r->get('/lang/{code}', function (Request $q) {
            Lang::set($q->params['code']);
            $to = (string)($q->query['r'] ?? '/');
            if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || str_contains($to, '\\')) $to = '/';
            return Response::redirect($to);
        });

        // ---- owner portal (obfuscated mount) ----
        $m = '/' . trim((string)Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/');
        $r->any($m . '/login', [Admin::class, 'login']);
        $r->post($m . '/logout', [Admin::class, 'logout']);
        $r->get($m, [Admin::class, 'dashboard']);
        $r->post($m . '/progressive-pricing/publish', [Admin::class, 'publishPricing']);
        $r->post($m . '/status', [Admin::class, 'setStatus']);
        $r->post($m . '/seed', [Admin::class, 'seed']);
        $r->get($m . '/{menu}', [Admin::class, 'menu']);
        return $r;
    }
}
