<?php
declare(strict_types=1);

namespace W5D;

use W5D\Controllers\{Pages, Forms, Checkout, Api, Docs, Admin, Accounts, Unsubscribe, Programs, PayHooks};

final class App
{
    public static function boot(): void
    {
        Env::load(ROOT . '/.env');
        date_default_timezone_set('Africa/Tunis');
        mb_internal_encoding('UTF-8');
        if (PHP_SAPI !== 'cli') Session::start();
        else $_SESSION = $_SESSION ?? [];
        if (PHP_SAPI !== 'cli') { try { Account::restore(); } catch (\Throwable $e) { /* DB not ready: stay a guest */ } }
        ViewEnv::share('currentLang', Lang::current());
    }

    public static function run(): void
    {
        self::boot();
        self::handle(new Request())->send();
    }

    public static function handle(Request $req): Response
    {
        Affiliate::track($req); // ?ref=CODE: first-touch cookie + one click per visitor per day (never throws)
        try {
            $res = self::router()->dispatch($req);
        } catch (\Throwable $e) {
            @file_put_contents(ROOT . '/storage/logs/app-' . date('Y-m-d') . '.log', '[' . date('c') . '] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
            $dbg = Env::get('APP_ENV') !== 'production';
            $res = $dbg ? Response::html('<pre>' . e($e) . '</pre>', 500) : self::errorPage($e instanceof \PDOException);
        }
        $res->headers += [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
        ];
        // HSTS only in production and only when the public origin is https (never lock a dev/http host into https).
        if (Env::get('APP_ENV') === 'production' && str_starts_with((string)Env::get('APP_URL', ''), 'https://')) $res->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        if (str_starts_with($res->headers['Content-Type'] ?? '', 'text/html')) $res->headers['Cache-Control'] = 'no-store';
        return $res;
    }

    /** Production-safe error page: no internals, no layout dependency (works even when views/DB are broken). */
    private static function errorPage(bool $db): Response
    {
        $ar = (Lang::current() === 'ar');
        $fr = (Lang::current() === 'fr');
        [$h, $p, $b, $home] = $ar
            ? ['الخدمة غير متاحة مؤقتا', 'حدث خطأ من جانبنا. جرّب مرة أخرى بعد لحظات.', 'إعادة المحاولة', 'الرئيسية']
            : ($fr
                ? ['Service momentanément indisponible', 'Une erreur est survenue de notre côté. Réessayez dans un instant.', 'Réessayer', 'Accueil']
                : ['Temporarily unavailable', 'Something went wrong on our side. Please try again in a moment.', 'Try again', 'Home']);
        $html = '<!DOCTYPE html><html lang="' . Lang::current() . '" dir="' . ($ar ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . $h . ' · W5D</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#070711;color:#e2e8f0;font-family:system-ui,Segoe UI,Tahoma,sans-serif}main{max-width:30rem;padding:2rem;text-align:center}h1{font-size:1.6rem;margin:0 0 .75rem;color:#fff}p{color:#94a3b8;line-height:1.7}a{display:inline-block;margin:.5rem;padding:.75rem 1.4rem;border-radius:.75rem;background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;text-decoration:none;font-weight:600}a.g{background:none;border:1px solid #334155;color:#cbd5e1}</style></head>'
            . '<body><main><h1>' . $h . '</h1><p>' . $p . '</p><a href="">' . $b . '</a><a class="g" href="/">' . $home . '</a></main></body></html>';
        return Response::html($html, $db ? 503 : 500)->withHeaders(['Retry-After' => '30', 'X-Robots-Tag' => 'noindex']);
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
                foreach (['join', 'how-it-works', 'countries/tunisia', 'partners', 'investors', 'pricing', 'about', 'ontology', 'careers', 'team', 'faq', 'status', 'trust', 'join/thanks', 'affiliate', 'certification'] as $slug) {
            $r->get('/' . $slug, fn(Request $q) => Pages::page($q, $slug));
        }
        $r->get('/for', fn(Request $q) => Pages::page($q, 'for'));
        $r->get('/for/{aud}', [Pages::class, 'audience']);
        $r->get('/invest', fn() => Response::redirect(url('/investors/'), 301));
        $r->get('/sitemap', [Pages::class, 'sitemap']);
        $r->get('/trust/{page}', [Pages::class, 'trust']);
        $r->any('/certification/verify', [Programs::class, 'certVerify']);
        $r->get('/certificate/{code}', [Programs::class, 'certPrint']);
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

        // ---- accounts ----
        $r->any('/account/login', [Accounts::class, 'login']);
        $r->post('/account/logout', [Accounts::class, 'logout']);
        $r->any('/account/register', [Accounts::class, 'register']);
        $r->get('/account/modal/{mode}', [Accounts::class, 'modalForm']);
        $r->post('/account/resend', [Accounts::class, 'resend']);
        $r->get('/account/verify/{token}', [Accounts::class, 'verify']);
        $r->any('/account/forgot', [Accounts::class, 'forgot']);
        $r->any('/account/reset/{token}', [Accounts::class, 'reset']);
        $r->get('/account/social/{provider}', [Accounts::class, 'social']);
        $r->get('/account/social/{provider}/callback', [Accounts::class, 'socialCallback']);
        $r->post('/account/profile', [Accounts::class, 'profile']);
        $r->post('/account/security', [Accounts::class, 'security']);
        $r->post('/account/qcm/start', [Programs::class, 'qcmStart']);
        $r->post('/account/qcm/answer', [Programs::class, 'qcmAnswer']);
        $r->post('/account/qcm/buy', [Programs::class, 'qcmBuy']);
        $r->post('/account/qcm/task', [Programs::class, 'qcmTask']);
        $r->get('/account/qcm/attempt/{id}', [Programs::class, 'qcmAttempt']);
        $r->post('/account/qcm/attempt/{id}/submit', [Programs::class, 'qcmSubmit']);
        $r->any('/account/qcm/pay/{ref}', [Programs::class, 'qcmPay']);
        $r->post('/account/affiliate/apply', [Programs::class, 'affApply']);
        $r->post('/account/affiliate/payout', [Programs::class, 'affPayout']);
        $r->get('/account', [Accounts::class, 'panel']);
        $r->get('/account/{panel}', [Accounts::class, 'panel']);
        $r->any('/pay/{ref}', [Accounts::class, 'pay']);
        // online gateways: buyer return (signed, re-verified) and server-to-server webhooks (signature + re-verification; no session, no CSRF by design)
        $r->get('/pay/return/{ref}', [PayHooks::class, 'back']);
        $r->any('/pay/hook/{provider}', [PayHooks::class, 'hook']);
        $r->any('/unsubscribe', [Unsubscribe::class, 'handle']);

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
        $r->get('/api/certificates/{code}.json', [Programs::class, 'certApi']);
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
        $r->get('/api/openapi.json', [Docs::class, 'openapi']);
        $r->get('/api/agents.json', [Docs::class, 'agentManifest']);
        $r->get('/security.txt', [Docs::class, 'securityTxt']);
        $r->get('/.well-known/security.txt', [Docs::class, 'securityTxt']);
        $r->get('/humans.txt', [Docs::class, 'humans']);
        $r->get('/manifest.webmanifest', [Docs::class, 'manifest']);
        $r->get('/.well-known/agents.json', [Docs::class, 'agentManifest']);
        $r->get('/llms.txt', [Docs::class, 'llms']);
        $r->get('/llms-full.txt', [Docs::class, 'llmsFull']);
        $r->get('/sitemap.xml', [Docs::class, 'sitemap']);
        $r->get('/robots.txt', [Docs::class, 'robots']);

        // ---- language ----
        // /language/{code} is the canonical URL: a top-level /lang/ folder exists on disk (translations) and some Apache setups
        // answer /lang/* themselves (403/404). /lang/{code} stays as a legacy alias for the setups where it reaches PHP.
        $setLang = function (Request $q) {
            Lang::set((string)$q->params['code']);
            $to = (string)($q->query['r'] ?? '/');
            if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || str_contains($to, '\\') || preg_match('#^/(language|lang)/#', $to)) $to = '/';
            return Response::redirect(url($to));
        };
        $r->get('/language/{code}', $setLang);
        $r->get('/lang/{code}', $setLang);

        // ---- owner portal (obfuscated mount) ----
        $m = '/' . trim((string)Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/');
        $r->any($m . '/login', [Admin::class, 'login']);
        $r->post($m . '/logout', [Admin::class, 'logout']);
        $r->get($m, [Admin::class, 'dashboard']);
        $r->post($m . '/progressive-pricing/publish', [Admin::class, 'publishPricing']);
        $r->post($m . '/variables/save', [Admin::class, 'saveVariables']);
        $r->post($m . '/status', [Admin::class, 'setStatus']);
        $r->post($m . '/seed', [Admin::class, 'seed']);
        $r->post($m . '/payments/review', [Admin::class, 'reviewPayment']);
        $r->post($m . '/payment-methods/save', [Admin::class, 'savePayMethods']);
        // QCM bank / review, affiliates, certificates (src/controllers/Programs.php)
        $r->get($m . '/qcm-bank/export', [Programs::class, 'bankExport']);
        foreach (['save' => 'bankSave', 'toggle' => 'bankToggle', 'delete' => 'bankDelete', 'import' => 'bankImport'] as $seg => $fn) $r->post($m . '/qcm-bank/' . $seg, [Programs::class, $fn]);
        $r->post($m . '/qcm-review/pass', [Programs::class, 'passReview']);
        $r->post($m . '/qcm-review/task', [Programs::class, 'taskReview']);
        $r->post($m . '/affiliates/status', [Programs::class, 'affStatus']);
        $r->post($m . '/affiliates/referral', [Programs::class, 'refDecide']);
        $r->post($m . '/affiliates/payout', [Programs::class, 'payoutSettle']);
        $r->post($m . '/certificates/issue', [Programs::class, 'certIssue']);
        $r->post($m . '/certificates/revoke', [Programs::class, 'certRevoke']);
        // CRUD with ACL (src/Crud.php): static segments first, then the generic menu
        $r->get($m . '/{menu}/new', [Admin::class, 'crudNew']);
        $r->get($m . '/{menu}/{id}/edit', [Admin::class, 'crudEdit']);
        $r->post($m . '/{menu}/save', [Admin::class, 'crudSave']);
        $r->post($m . '/{menu}/delete', [Admin::class, 'crudDelete']);
        $r->get($m . '/{menu}', [Admin::class, 'menu']);
        return $r;
    }
}
