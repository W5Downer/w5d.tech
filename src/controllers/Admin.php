<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{ACL, Auth, Content, Csrf, DB, Env, Pricing, Request, Response, Rollup, Variables, ViewEnv};

/** Owner portal. Mounted at /{ADMIN_URL_PATH}. Every action re-checks session, permission and CSRF. */
final class Admin
{
    /** status values an admin may set, per table (whitelist) */
    private const STATUSES = [
        'manager_applications' => ['received', 'shortlisted', 'interview', 'accepted', 'declined'],
        'contact_messages' => ['new', 'read', 'answered', 'archived'],
        'investor_subscriptions' => ['active', 'paused', 'cancelled'],
    ];
    private const STATUS_PERM = ['manager_applications' => 'managers', 'contact_messages' => 'messages', 'investor_subscriptions' => 'investors.read'];

    private static function page(string $view, array $data, int $status = 200): Response
    {
        ViewEnv::share('canonicalPath', '/');
        $role = Auth::role();
        $data += ['menus' => ACL::menus($role), 'user' => Auth::user(), 'title' => 'W5D', 'active' => ''];
        return Response::html(view($view, $data), $status)->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store']);
    }

    private static function flash(string $kind, string $msg): void { $_SESSION['_admin_flash'] = ['kind' => $kind, 'msg' => $msg]; }

    private static function audit(string $action, string $subject = '', string $detail = ''): void
    {
        try { DB::insert('audit_log', ['country_id' => 'tn', 'actor' => (string)(Auth::user()['name'] ?? 'system'), 'action' => $action, 'subject' => $subject, 'detail' => mb_substr($detail, 0, 1000)]); } catch (\Throwable) {}
    }

    /* ================= auth ================= */
    public static function login(Request $q): Response
    {
        if (Auth::check()) return Response::redirect(admin_url());
        $error = null;
        if ($q->isPost()) {
            if (!Csrf::check($q)) $error = __('msg.csrf');
            elseif (Auth::attempt((string)($q->post['username'] ?? ''), (string)($q->post['password'] ?? ''), $q->ip())) return Response::redirect(admin_url());
            else $error = 'Invalid credentials or too many attempts.';
        }
        return self::page('admin.login', ['title' => __('admin.login'), 'error' => $error]);
    }

    public static function logout(Request $q): Response
    {
        if (Csrf::check($q)) Auth::logout();
        return Response::redirect(admin_url('login'));
    }

    /* ================= command center ================= */
    private static function count(string $sql, array $p = []): int { try { return (int)DB::val($sql, $p); } catch (\Throwable) { return 0; } }

    public static function dashboard(Request $q): Response
    {
        if ($r = Auth::require('dashboard')) return $r;
        $dbOk = DB::available();
        $k = [];
        $recentOrders = $topManagers = $series = [];
        if ($dbOk) {
            $k = [
                'investors' => self::count('SELECT COUNT(*) FROM investors'),
                'orders' => self::count('SELECT COUNT(*) FROM investor_orders'),
                'pledged_total' => (float)DB::val('SELECT COALESCE(SUM(total),0) FROM investor_orders'),
                'subs_active' => self::count("SELECT COUNT(*) FROM investor_subscriptions WHERE status='active'"),
                'subs_paused' => self::count("SELECT COUNT(*) FROM investor_subscriptions WHERE status='paused'"),
                'parts_day' => self::count("SELECT COALESCE(SUM(parts_per_day),0) FROM investor_subscriptions WHERE status='active'"),
                'mgr_new' => self::count("SELECT COUNT(*) FROM manager_applications WHERE status='received'"),
                'mgr_all' => self::count('SELECT COUNT(*) FROM manager_applications'),
                'inbox_new' => self::count("SELECT COUNT(*) FROM contact_messages WHERE status='new'"),
                'newsletter' => self::count('SELECT COUNT(*) FROM newsletter_subscribers'),
                'applicants' => self::count('SELECT COUNT(*) FROM applicants'),
                'elig' => self::count('SELECT COUNT(*) FROM eligibility_checks'),
            ];
            $recentOrders = DB::all('SELECT o.ref, o.parts_per_day, o.days, o.total, o.status, o.created_at, i.full_name FROM investor_orders o JOIN investors i ON i.id=o.investor_id ORDER BY o.id DESC LIMIT 8');
            $topManagers = DB::all("SELECT ref, full_name, macro_slug, years, team_size_led, score, status FROM manager_applications WHERE status IN ('received','shortlisted') ORDER BY score DESC, id DESC LIMIT 8");
            $rows = DB::all("SELECT substr(created_at,1,10) AS d, COUNT(*) AS n, COALESCE(SUM(total),0) AS s FROM investor_orders WHERE created_at >= date('now','-13 day') GROUP BY d");
            $by = array_column($rows, null, 'd');
            for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $series[] = ['d' => $d, 'n' => (int)($by[$d]['n'] ?? 0), 's' => (float)($by[$d]['s'] ?? 0)]; }
        }
        return self::page('admin.dashboard', ['title' => 'Command Center', 'active' => 'command-center', 'db' => $dbOk, 'k' => $k, 'recentOrders' => $recentOrders, 'topManagers' => $topManagers, 'series' => $series,
            'price' => Variables::get('price'), 'rollup' => Rollup::kpis(), 'd' => Variables::get('d'), 'flash' => $_SESSION['_admin_flash'] ?? null, 'canSeed' => Auth::can('system')]);
    }

    /* ================= generic menu ================= */
    public static function menu(Request $q): Response
    {
        $slug = (string)($q->params['menu'] ?? '');
        $menu = null;
        foreach (ACL::matrix()['menus'] as $m) if ($m['slug'] === $slug) $menu = $m;
        if (!$menu) return Response::html(view('pages.404', ['title' => '404']), 404);
        if ($r = Auth::require($menu['perm'])) return $r;
        $view = match ($slug) {
            'progressive-pricing' => self::vPricing(),
            'manager-applications' => self::vTable('SELECT id, ref, full_name, email, macro_slug AS macro, years, team_size_led AS team, region_slug AS region, score, status, created_at FROM manager_applications ORDER BY score DESC, id DESC LIMIT 300', 'manager_applications'),
            'subscriptions' => self::vTable('SELECT s.id, s.order_ref AS ref, i.full_name, i.email, s.parts_per_day AS parts, s.macro_slug AS macro, s.next_charge_on AS next_charge, s.status, s.created_at FROM investor_subscriptions s JOIN investors i ON i.id=s.investor_id ORDER BY s.id DESC LIMIT 300', 'investor_subscriptions'),
            'inbox' => self::vTable('SELECT id, desk, name, email, subject, substr(body,1,160) AS message, status, created_at FROM contact_messages ORDER BY id DESC LIMIT 300', 'contact_messages'),
            'investors' => self::vTable('SELECT i.id, i.full_name, i.email, i.kind, i.organisation, COUNT(o.id) AS orders, COALESCE(SUM(o.total),0) AS pledged, i.created_at FROM investors i LEFT JOIN investor_orders o ON o.investor_id=i.id GROUP BY i.id ORDER BY pledged DESC LIMIT 300'),
            'applicants' => self::vTable('SELECT id, full_name, email, macro_slug AS macro, service_slug AS service, diploma, status, created_at FROM applicants ORDER BY id DESC LIMIT 300'),
            'communications' => self::vTable('SELECT id, email, lang, created_at FROM newsletter_subscribers ORDER BY id DESC LIMIT 300'),
            'audit' => self::vTable('SELECT id, actor, action, subject, detail, created_at FROM audit_log ORDER BY id DESC LIMIT 300'),
            'services' => self::vList(array_map(fn($s) => ['#' => $s['id'], 'slug' => $s['slug'], 'macro' => $s['macro'], 'title' => t($s['title'], 'en'), 'cohort' => $s['cohort'], 'diploma' => $s['diploma']], array_values(Content::all('services'))), ['note' => 'Source of truth: data/content/services/*.json (read-only here; edit JSON, then run bin/sync-content.php).']),
            'projects' => self::vList(array_map(fn($p) => ['#' => $p['id'], 'slug' => $p['slug'], 'status' => $p['status'], 'dated' => $p['status_date'] ?? '', 'name' => t($p['name'], 'en')], array_values(Content::all('projects'))), ['note' => 'Source: data/content/projects/*.json. Status changes are dated and must carry evidence.']),
            'regions-hubs' => self::vList(array_map(fn($r) => ['#' => $r['order'] ?? '', 'slug' => $r['slug'], 'en' => t($r['name'], 'en'), 'fr' => t($r['name'], 'fr'), 'ar' => t($r['name'], 'ar')], Content::file('countries/tn.json')['regions'] ?? [])),
            'content' => self::vList(array_map(fn($p) => ['slug' => $p['slug'], 'title' => t($p['title'], 'en'), 'sections' => count($p['sections'] ?? []), 'robots' => $p['robots'] ?? 'index,follow'], array_values(Content::all('pages'))), ['note' => 'Pages are JSON section lists rendered by blocks. Edit data/content/pages/*.json.']),
            'variables' => self::vVariables(),
            'system' => self::vSystem(),
            default => ['kind' => 'preview'],
        };
        return self::page('admin.menu', ['title' => t($menu['label']), 'active' => $slug, 'menu' => $menu, 'v' => $view, 'flash' => $_SESSION['_admin_flash'] ?? null, 'back' => $slug]);
    }

    private static function vTable(string $sql, ?string $statusTable = null): array
    {
        try { $rows = DB::available() ? DB::all($sql) : []; } catch (\Throwable $e) { return ['kind' => 'table', 'rows' => [], 'error' => $e->getMessage()]; }
        return ['kind' => 'table', 'rows' => $rows, 'status_table' => $statusTable, 'statuses' => $statusTable ? self::STATUSES[$statusTable] : []];
    }

    private static function vList(array $rows, array $extra = []): array { return ['kind' => 'table', 'rows' => $rows, 'readonly' => true] + $extra; }

    private static function vVariables(): array
    {
        $flat = [];
        $walk = function ($v, string $p) use (&$walk, &$flat) {
            if (is_array($v)) foreach ($v as $k => $x) $walk($x, $p === '' ? (string)$k : "$p.$k"); else $flat[] = ['token' => '{{ ' . $p . ' }}', 'value' => is_bool($v) ? ($v ? 'true' : 'false') : (string)$v];
        };
        $walk(Variables::all(), '');
        return ['kind' => 'table', 'rows' => $flat, 'readonly' => true, 'note' => 'Resolved tokens. Edit config/variables.php; pricing constants are published under Progressive Pricing.'];
    }

    private static function vSystem(): array
    {
        $mig = []; try { $mig = array_column(DB::all('SELECT name FROM schema_migrations ORDER BY name'), 'name'); } catch (\Throwable) {}
        $dbFile = ROOT . '/storage/' . basename((string)Env::get('DB_DATABASE', 'w5d.sqlite'));
        $rows = [
            ['check' => 'PHP', 'value' => PHP_VERSION], ['check' => 'Database', 'value' => DB::available() ? 'connected (' . Env::get('DB_DRIVER', 'sqlite') . ')' : 'unavailable'],
            ['check' => 'Migrations', 'value' => implode(', ', $mig) ?: 'none'], ['check' => 'Payment mode', 'value' => (string)Env::get('PAYMENT_MODE', 'demo')],
            ['check' => 'storage/ writable', 'value' => is_writable(ROOT . '/storage') ? 'yes' : 'NO'], ['check' => 'Content', 'value' => Content::count('services') . ' services · ' . Content::count('projects') . ' projects · ' . Content::count('pages') . ' pages'],
            ['check' => 'Compiled assets', 'value' => is_file(ROOT . '/htdocs/assets/_base/css/tailwind.css') ? 'present' : 'missing (run tools/build.mjs)'],
        ];
        return ['kind' => 'system', 'rows' => $rows, 'can_seed' => Auth::can('system')];
    }

    private static function vPricing(): array
    {
        $v = Variables::all();
        $hist = []; try { $hist = DB::available() ? DB::all("SELECT changed_by, changed_at, alpha, beta, p_prog, p_time, cap, note FROM pricing_audit WHERE country_id='tn' ORDER BY id DESC LIMIT 20") : []; } catch (\Throwable) {}
        return ['kind' => 'pricing', 'k' => $v['pricing'], 'snap' => Pricing::snapshot($v), 'curve' => Pricing::curve(10), 'hist' => $hist, 'base' => $v['money']['base_day']];
    }

    /* ================= actions ================= */
    public static function publishPricing(Request $q): Response
    {
        if ($r = Auth::require('pricing')) return $r;
        $back = admin_url('progressive-pricing/');
        if (!Csrf::check($q)) { self::flash('error', __('msg.csrf')); return Response::redirect($back); }
        $in = fn(string $k) => $q->post[$k] ?? '';
        $lim = ['alpha' => [0, 5], 'beta' => [0, 5], 'p_prog' => [0.5, 5], 'p_time' => [0.5, 5], 'cap' => [1, 10]];
        $row = [];
        foreach ($lim as $k => [$lo, $hi]) {
            $x = $in($k);
            if (!is_numeric($x) || (float)$x < $lo || (float)$x > $hi) { self::flash('error', "$k must be a number from $lo to $hi."); return Response::redirect($back); }
            $row[$k] = (float)$x;
        }
        $note = trim((string)$in('note'));
        if (mb_strlen($note) < 10) { self::flash('error', 'A reason of at least 10 characters is required: every price change is audited and public.'); return Response::redirect($back); }
        DB::insert('pricing_audit', ['country_id' => 'tn', 'changed_by' => (string)Auth::user()['name']] + $row + ['note' => mb_substr($note, 0, 500)]);
        self::audit('pricing.publish', 'pricing_audit', json_encode($row) . ' · ' . $note);
        Variables::reset();
        self::flash('ok', 'Published. The new function is live on /pricing/, /api/price.json and the checkout.');
        return Response::redirect($back);
    }

    public static function setStatus(Request $q): Response
    {
        $t = (string)($q->post['table'] ?? ''); $st = (string)($q->post['status'] ?? ''); $id = (int)($q->post['id'] ?? 0);
        $back = (string)($q->post['back'] ?? '');
        $to = preg_match('/^[a-z\-]+$/', $back) ? admin_url($back . '/') : admin_url();
        if (!isset(self::STATUSES[$t])) return Response::redirect($to);
        if ($r = Auth::require(self::STATUS_PERM[$t])) return $r;
        if (!Csrf::check($q)) { self::flash('error', __('msg.csrf')); return Response::redirect($to); }
        if (!in_array($st, self::STATUSES[$t], true) || $id < 1) return Response::redirect($to);
        DB::q("UPDATE $t SET status = ? WHERE id = ?", [$st, $id]);
        self::audit('status.set', "$t#$id", $st);
        self::flash('ok', "$t #$id → $st");
        return Response::redirect($to);
    }

    /** Seed or purge clearly-marked demo data (emails @example.test). Never touches rollups, so public KPIs stay honest. */
    public static function seed(Request $q): Response
    {
        if ($r = Auth::require('system')) return $r;
        if (!Csrf::check($q)) return Response::redirect(admin_url());
        $act = (string)($q->post['do'] ?? 'seed');
        if ($act === 'purge') {
            foreach (['investor_subscriptions' => "investor_id IN (SELECT id FROM investors WHERE email LIKE '%@example.test')", 'investor_orders' => "investor_id IN (SELECT id FROM investors WHERE email LIKE '%@example.test')", 'investors' => "email LIKE '%@example.test'", 'manager_applications' => "email LIKE '%@example.test'", 'contact_messages' => "email LIKE '%@example.test'", 'newsletter_subscribers' => "email LIKE '%@example.test'", 'applicants' => "email LIKE '%@example.test'"] as $tb => $w) DB::q("DELETE FROM $tb WHERE $w");
            self::audit('demo.purge'); self::flash('ok', 'Demo data removed.');
            return Response::redirect(admin_url());
        }
        if (self::count("SELECT COUNT(*) FROM investors WHERE email LIKE '%@example.test'") > 0) { self::flash('error', 'Demo data already present. Purge first.'); return Response::redirect(admin_url()); }
        $names = ['Amel Trabelsi', 'Karim Ben Salah', 'Sonia Mansouri', 'Yassine Gharbi', 'Leila Haddad', 'Omar Jebali', 'Ines Chaabane', 'Mehdi Ayari', 'Rania Bouazizi', 'Slim Khelifi', 'Nour Hamdi', 'Walid Sassi'];
        $i = 0;
        foreach ($names as $n) {
            $i++;
            $x = Checkout::core(['full_name' => $n, 'email' => 'investor' . $i . '@example.test', 'kind' => $i % 4 === 0 ? 'company' : 'individual', 'parts_per_day' => (string)(1 + ($i * 3) % 9), 'days' => (string)(($i % 3 + 1) * 30), 'start_on' => date('Y-m-d', strtotime('+' . ($i % 5 + 1) . ' day')), 'cadence' => 'daily', 'macro' => Forms::MACROS[$i % 5], 'accept' => '1'], $i % 3 === 0 ? 'checkout' : 'subscription');
            if ($x['ok']) DB::q("UPDATE investor_orders SET created_at = datetime('now', ?) WHERE ref = ?", ['-' . ($i % 12) . ' day', $x['data']['ref']]);
        }
        $govs = array_column(Content::file('countries/tn.json')['regions'] ?? [], 'slug');
        $scen = 'I would set a clear weekly rubric, ask for evidence on every report, escalate blockers before the deadline, and give written feedback; sampled reviews would be documented honestly.';
        foreach (['Hela Kacem' => [9, 40], 'Fares Mejri' => [4, 8], 'Dorra Ferchichi' => [12, 25], 'Anis Zouari' => [2, 0], 'Maryem Bejaoui' => [7, 15], 'Tarek Rezgui' => [15, 60]] as $n => [$yrs, $team]) {
            $i++;
            Forms::coreManager(['name' => $n, 'email' => 'manager' . $i . '@example.test', 'region' => $govs[$i % max(1, count($govs))] ?? 'tunis', 'macro' => Forms::MACROS[$i % 5], 'years' => (string)$yrs, 'team' => (string)$team, 'profile' => 'https://example.test/' . $i, 'motivation' => 'I want to build a verifiable, honest delivery culture for graduates in my region and have led similar programmes.', 'scenario' => $scen, 'accept' => '1']);
        }
        foreach (['investors' => 'Is the 5% sampling fixed?', 'managers' => 'How is a team of ten formed?', 'press' => 'Can we quote the design equation?', 'partners' => 'Co-funding a cohort in Kasserine'] as $desk => $subj) {
            Forms::coreContact(['name' => 'Demo Sender', 'email' => 'sender' . (++$i) . '@example.test', 'desk' => $desk, 'subject' => $subj, 'message' => 'Demo message: ' . $subj . ' Please reply with the public source.']);
        }
        foreach (range(1, 5) as $j) DB::insert('newsletter_subscribers', ['country_id' => 'tn', 'email' => "news$j@example.test", 'lang' => ['en', 'fr', 'ar'][$j % 3]]);
        self::audit('demo.seed', '', 'investors, orders, subscriptions, manager applications, messages');
        self::flash('ok', 'Demo data seeded (all @example.test). Public KPIs are unchanged: rollups are never seeded.');
        return Response::redirect(admin_url());
    }
}
