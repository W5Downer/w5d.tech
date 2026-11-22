<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Content, Csrf, DB, Integrations, RateLimit, Request, Response, Session};

/**
 * Public forms. Each core*() method is shared by the HTML form and the JSON API:
 * it returns ['ok'=>bool, 'errors'=>[field=>msg], 'data'=>[...]].
 */
final class Forms
{
    public const DESKS = ['investors', 'managers', 'partners', 'press', 'general', 'corrections'];
    public const MACROS = ['cultures', 'built', 'sciences', 'governance', 'technology'];
    public const DIPLOMAS = ['licence', 'maitrise', 'engineer', 'phd', 'other'];

    /* ---------- tiny validation kit ---------- */
    public static function str(array $in, string $k, int $max = 200): string
    {
        $v = $in[$k] ?? '';
        if (!is_scalar($v)) return '';
        $v = trim((string)$v);
        return mb_substr($v, 0, $max);
    }

    public static function err(string $en, string $fr, string $ar): array { return ['en' => $en, 'fr' => $fr, 'ar' => $ar]; }

    public static function required(array $in, array $keys, array &$errors): void
    {
        foreach ($keys as $k) if (self::str($in, $k, 5000) === '') $errors[$k] ??= self::err('This field is required.', 'Ce champ est obligatoire.', 'هذا الحقل مطلوب.');
    }

    public static function email(array $in, string $k, array &$errors, bool $required = true): string
    {
        $v = self::str($in, $k, 190);
        if ($v === '' && !$required) return '';
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $errors[$k] = self::err('Enter a valid email address.', 'Saisissez une adresse e-mail valide.', 'أدخل عنوان بريد إلكتروني صالحا.');
        return mb_strtolower($v);
    }

    public static function accepted(array $in, array &$errors): void
    {
        if (empty($in['accept'])) $errors['accept'] = self::err('Please confirm you have read the declared limits.', 'Veuillez confirmer avoir lu les limites déclarées.', 'يرجى تأكيد قراءتك للحدود المعلنة.');
    }

    public static function ref(string $prefix): string { return $prefix . '-' . strtoupper(bin2hex(random_bytes(4))); }

    /** Guard shared by every public write: honeypot, DB, rate limit. Returns an error string or null. */
    public static function guard(Request $q, string $bucket, int $max = 12, int $window = 600): ?string
    {
        if (!empty($q->post['website'])) return 'bot';
        if (!DB::available()) return 'db';
        if (!RateLimit::hit($bucket . ':' . $q->ip(), $max, $window)) return 'rate';
        // Google reCAPTCHA (owner-configured, off by default). Runs after the cheap checks so Google is only called for plausible requests.
        if (Integrations::captcha($q, str_starts_with($bucket, 'api-') ? 'api' : 'form') !== null) return 'captcha';
        return null;
    }

    public static function fail(Request $q, string $back, array $errors, string $kind = 'validation'): Response
    {
        if ($q->wantsJson()) return Response::json(['ok' => false, 'error' => $kind, 'errors' => $errors], $kind === 'rate' ? 429 : 422);
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = array_map(fn($v) => is_scalar($v) ? (string)$v : '', array_diff_key($q->post, ['_csrf' => 1, 'website' => 1]));
        $_SESSION['_flash_error'] = $kind;
        return Response::redirect(url($back));
    }

    public static function done(Request $q, array $data, string $kind): Response
    {
        if ($q->wantsJson()) return Response::json(['ok' => true] + $data, 201);
        $_SESSION['_thanks'] = ['kind' => $kind] + $data;
        return Response::redirect(url('/join/thanks/'));
    }

    public static function check(Request $q, string $back, string $bucket): ?Response
    {
        if (!$q->isPost()) return null;
        if (!$q->wantsJson() && !Csrf::check($q)) return self::fail($q, $back, ['_form' => self::err('Session expired. Reload and try again.', 'Session expirée. Rechargez et réessayez.', 'انتهت الجلسة. أعد التحميل وحاول مجددا.')], 'csrf');
        $g = self::guard($q, $bucket);
        if ($g === 'bot') return $q->wantsJson() ? Response::json(['ok' => true]) : self::done($q, ['ref' => '—'], 'ok');
        if ($g === 'rate') return self::fail($q, $back, ['_form' => self::err('Too many attempts. Try again later.', 'Trop de tentatives. Réessayez plus tard.', 'محاولات كثيرة. حاول لاحقا.')], 'rate');
        if ($g === 'captcha') return self::fail($q, $back, ['_form' => Integrations::message()], 'captcha');
        if ($g === 'db') return self::fail($q, $back, ['_form' => self::err('The database is not initialised (run bin/migrate.php).', 'La base n’est pas initialisée (lancez bin/migrate.php).', 'قاعدة البيانات غير مهيأة (شغّل bin/migrate.php).')], 'db');
        return null;
    }

    /* =================== contact =================== */
    public static function coreContact(array $in): array
    {
        $e = [];
        self::required($in, ['name', 'message'], $e);
        $email = self::email($in, 'email', $e);
        $desk = self::str($in, 'desk', 40) ?: 'general';
        if (!in_array($desk, self::DESKS, true)) $e['desk'] = self::err('Unknown desk.', 'Bureau inconnu.', 'مكتب غير معروف.');
        if ($e) return ['ok' => false, 'errors' => $e];
        $id = DB::insert('contact_messages', ['country_id' => 'tn', 'desk' => $desk, 'name' => self::str($in, 'name', 120), 'email' => $email, 'subject' => self::str($in, 'subject', 160), 'body' => self::str($in, 'message', 4000)]);
        $ref = 'MSG-' . $id;
        \W5D\Mail::send($email, 'form_ack', ['kind' => 'contact', 'name' => self::str($in, 'name', 120), 'ref' => $ref]);
        \W5D\Mail::staff('Contact desk: ' . $desk, ['ref' => $ref, 'from' => self::str($in, 'name', 120) . ' <' . $email . '>', 'subject' => self::str($in, 'subject', 160), 'message' => self::str($in, 'message', 1500)]);
        return ['ok' => true, 'errors' => [], 'data' => ['ref' => $ref, 'desk' => $desk]];
    }

    public static function contact(Request $q): Response
    {
        if (!$q->isPost()) return Pages::page($q, 'contact');
        if ($r = self::check($q, '/contact/', 'contact')) return $r;
        $x = self::coreContact($q->post);
        return $x['ok'] ? self::done($q, $x['data'], 'contact') : self::fail($q, (string)($q->post['desk'] ?? '') === 'corrections' ? '/trust/corrections/' : '/contact/', $x['errors']);
    }

    /* =================== newsletter =================== */
    public static function newsletter(Request $q): Response
    {
        if (!$q->isPost()) return Pages::page($q, 'newsletter');
        if ($r = self::check($q, '/newsletter/', 'newsletter')) return $r;
        $e = [];
        $email = self::email($q->post, 'email', $e);
        self::accepted($q->post, $e);
        if ($e) return self::fail($q, '/newsletter/', $e);
        if (!DB::one('SELECT id FROM newsletter_subscribers WHERE country_id = ? AND email = ?', ['tn', $email]))
            { DB::insert('newsletter_subscribers', ['country_id' => 'tn', 'email' => $email, 'lang' => lang()]); \W5D\Mail::send($email, 'form_ack', ['kind' => 'newsletter', 'name' => '', 'ref' => '']); }
        return self::done($q, ['ref' => 'NL'], 'newsletter');
    }

    /* =================== register (pre-registration) =================== */
    public static function coreRegister(array $in): array
    {
        $e = [];
        self::required($in, ['name', 'region', 'field'], $e);
        $email = self::email($in, 'email', $e);
        $dip = self::str($in, 'diploma', 20);
        if (!in_array($dip, self::DIPLOMAS, true)) $e['diploma'] = self::err('Choose a diploma level.', 'Choisissez un niveau de diplôme.', 'اختر مستوى الشهادة.');
        $region = self::str($in, 'region', 60);
        $slugs = array_column(Content::file('countries/tn.json')['regions'] ?? [], 'slug');
        if ($region !== '' && !in_array($region, $slugs, true)) $e['region'] = self::err('Unknown governorate.', 'Gouvernorat inconnu.', 'ولاية غير معروفة.');
        self::accepted($in, $e);
        $svc = Content::get('services', self::str($in, 'service', 80));
        if ($e) return ['ok' => false, 'errors' => $e];
        if (DB::one('SELECT id FROM applicants WHERE country_id = ? AND email = ?', ['tn', $email]))
            return ['ok' => false, 'errors' => ['email' => self::err('This email is already registered.', 'Cet e-mail est déjà inscrit.', 'هذا البريد مسجل مسبقا.')]];
        $id = DB::insert('applicants', ['country_id' => 'tn', 'cin' => 'PRE-' . strtoupper(bin2hex(random_bytes(5))), 'full_name' => self::str($in, 'name', 120), 'email' => $email,
            'phone' => self::str($in, 'phone', 40), 'macro_slug' => $svc['macro'] ?? null, 'service_slug' => $svc['slug'] ?? null, 'diploma' => $dip, 'status' => 'registered', 'lang' => lang()]);
        $ref = 'REG-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
        \W5D\Mail::send($email, 'form_ack', ['kind' => 'register', 'name' => self::str($in, 'name', 120), 'ref' => $ref]);
        \W5D\Mail::staff('New applicant', ['ref' => $ref, 'name' => self::str($in, 'name', 120), 'email' => $email, 'service' => (string)($svc['slug'] ?? '')]);
        return ['ok' => true, 'errors' => [], 'data' => ['ref' => $ref, 'next' => 'verification']];
    }

    public static function register(Request $q): Response
    {
        if ($r = \W5D\Gate::need($q, 'register', '/app/register/')) return $r;
        if (!$q->isPost()) return Pages::page($q, 'app-register');
        \W5D\Gate::bind($q);
        if ($r = self::check($q, '/app/register/', 'register')) return $r;
        $x = self::coreRegister($q->post);
        return $x['ok'] ? self::done($q, $x['data'], 'register') : self::fail($q, '/app/register/', $x['errors']);
    }

    /* =================== eligibility =================== */
    public static function coreEligibility(array $in): array
    {
        $e = [];
        self::required($in, ['field'], $e);
        $dip = self::str($in, 'diploma', 20);
        if (!in_array($dip, self::DIPLOMAS, true)) $e['diploma'] = self::err('Choose a diploma level.', 'Choisissez un niveau de diplôme.', 'اختر مستوى الشهادة.');
        if ($e) return ['ok' => false, 'errors' => $e];
        $field = mb_strtolower(self::str($in, 'field', 120));
        $macro = self::str($in, 'macro', 30);
        $words = array_filter(preg_split('/[\s,;\/\-]+/u', $field) ?: [], fn($w) => mb_strlen($w) >= 3);
        $scored = [];
        foreach (Content::all('services') as $s) {
            if ($macro !== '' && $s['macro'] !== $macro) continue;
            $hay = mb_strtolower(t($s['fields'], 'en') . ' ' . t($s['fields'], 'fr') . ' ' . t($s['title'], 'en') . ' ' . t($s['title'], 'fr'));
            $sc = 0;
            foreach ($words as $w) if (str_contains($hay, $w)) $sc += 2;
            if (in_array($dip, array_map('trim', explode('|', mb_strtolower((string)($s['diploma'] ?? '')))), true) || ($dip === 'maitrise' && str_contains((string)$s['diploma'], 'ma')) ) $sc += 1;
            if ($sc > 0) $scored[] = [$sc, $s];
        }
        usort($scored, fn($a, $b) => $b[0] <=> $a[0] ?: $a[1]['id'] <=> $b[1]['id']);
        $top = array_slice($scored, 0, 6);
        $suggested = $top[0][1]['macro'] ?? null;
        if (DB::available()) DB::insert('eligibility_checks', ['country_id' => 'tn', 'diploma' => $dip, 'field' => mb_substr($field, 0, 120), 'age_band' => null, 'region_slug' => null, 'suggested_macro' => $suggested]);
        return ['ok' => true, 'errors' => [], 'data' => ['suggested_macro' => $suggested, 'matches' => array_map(fn($x) => ['slug' => $x[1]['slug'], 'id' => $x[1]['id'], 'macro' => $x[1]['macro'], 'score' => $x[0]], $top)]];
    }

    public static function eligibility(Request $q): Response
    {
        if ($r = \W5D\Gate::need($q, 'eligibility', '/join/eligibility/')) return $r;
        if (!$q->isPost()) return Pages::page($q, 'join-eligibility');
        if ($r = self::check($q, '/join/eligibility/', 'elig')) return $r;
        $x = self::coreEligibility($q->post);
        if (!$x['ok']) return self::fail($q, '/join/eligibility/', $x['errors']);
        if ($q->wantsJson()) return Response::json($x);
        $svc = Content::all('services');
        $L = [Pages::class, 'L'];
        $cards = array_map(fn($m) => ['title' => $svc[$m['slug']]['title'], 'body' => $svc[$m['slug']]['short'], 'href' => '/app/register/?service=' . $m['slug'], 'tag' => '#' . str_pad((string)$m['id'], 3, '0', STR_PAD_LEFT), 'icon' => 'layers', 'gradient' => $m['macro']], $x['data']['matches']);
        $page = Content::page('join-eligibility') ?? ['title' => 'Eligibility', 'sections' => []];
        $res = $cards
            ? ['type' => 'cards', 'id' => 'elig-results', 'tier' => 'i', 'focus' => 4, 'cols' => 3, 'title' => $L('Services that fit your profile', 'Services adaptés à votre profil', 'خدمات تناسب ملفك'), 'lead' => $L('Indicative only: a human reviewer confirms eligibility at verification.', 'Indicatif seulement : un relecteur humain confirme l’éligibilité lors de la vérification.', 'للإرشاد فقط: يؤكد مراجع بشري الأهلية عند التحقق.'), 'items' => $cards]
            : ['type' => 'notice', 'id' => 'elig-none', 'title' => $L('No close match yet', 'Aucune correspondance proche', 'لا يوجد تطابق قريب بعد'), 'items' => [$L('Try broader field words, or leave the macro work empty.', 'Essayez des mots plus larges, ou laissez la macro-œuvre vide.', 'جرّب كلمات أوسع، أو اترك العمل الكبير فارغا.')]];
        array_splice($page['sections'], 1, 0, [$res]);
        return Pages::render($q, $page);
    }

    /* =================== manager applications =================== */
    /** Triage score 0–100 (admin sort order only; never an automatic rejection). */
    public static function managerScore(array $in): array
    {
        $years = max(0, min(40, (int)($in['years'] ?? 0)));
        $team = max(0, min(1000, (int)($in['team'] ?? 0)));
        $scen = mb_strtolower(self::str($in, 'scenario', 5000));
        $mot = self::str($in, 'motivation', 5000);
        $terms = ['evidence', 'review', 'escalat', 'deadline', 'document', 'sampl', 'feedback', 'prioriti', 'honest', 'transparen',
                  'preuve', 'relecture', 'escalad', 'délai', 'échantillon', 'retour', 'priorit', 'honnêt',
                  'دليل', 'مراجعة', 'تصعيد', 'موعد', 'توثيق', 'عينة', 'ملاحظ', 'أولوية', 'صدق', 'شفاف'];
        $hits = 0;
        foreach ($terms as $t) if (str_contains($scen, $t)) $hits++;
        $parts = [
            'experience' => (int)min(25, $years * 2.5),
            'team' => (int)min(20, $team * 2),
            'scenario_depth' => (int)min(15, mb_strlen($scen) / 40),
            'scenario_rubric' => min(25, $hits * 5),
            'motivation' => (int)min(10, mb_strlen($mot) / 40),
            'profile' => self::str($in, 'profile', 300) !== '' ? 5 : 0,
        ];
        return ['score' => array_sum($parts), 'parts' => $parts];
    }

    public static function coreManager(array $in): array
    {
        $e = [];
        self::required($in, ['name', 'region', 'motivation', 'scenario'], $e);
        $email = self::email($in, 'email', $e);
        $macro = self::str($in, 'macro', 30);
        if (!in_array($macro, self::MACROS, true)) $e['macro'] = self::err('Choose a macro work.', 'Choisissez une macro-œuvre.', 'اختر عملا كبيرا.');
        foreach (['years' => 40, 'team' => 1000] as $k => $mx) {
            $v = $in[$k] ?? '';
            if (!is_numeric($v) || (int)$v < 0 || (int)$v > $mx) $e[$k] = self::err("Enter a number from 0 to $mx.", "Saisissez un nombre de 0 à $mx.", "أدخل رقما من 0 إلى $mx.");
        }
        $profile = self::str($in, 'profile', 300);
        if ($profile !== '' && !preg_match('#^https?://#i', $profile)) $e['profile'] = self::err('Enter a full URL starting with https://', 'Saisissez une URL complète commençant par https://', 'أدخل رابطا كاملا يبدأ بـ https://');
        if (mb_strlen(self::str($in, 'scenario', 5000)) < 80) $e['scenario'] ??= self::err('Please answer in at least a few sentences (80+ characters).', 'Répondez en quelques phrases (80 caractères minimum).', 'أجب في بضع جمل على الأقل (80 حرفا فأكثر).');
        self::accepted($in, $e);
        if ($e) return ['ok' => false, 'errors' => $e];
        $sc = self::managerScore($in);
        $ref = self::ref('MGR');
        DB::insert('manager_applications', ['country_id' => 'tn', 'ref' => $ref, 'full_name' => self::str($in, 'name', 120), 'email' => $email, 'phone' => self::str($in, 'phone', 40),
            'macro_slug' => $macro, 'years' => (int)$in['years'], 'team_size_led' => (int)$in['team'], 'region_slug' => self::str($in, 'region', 60), 'profile_url' => $profile,
            'motivation' => self::str($in, 'motivation', 5000), 'scenario_answer' => self::str($in, 'scenario', 5000), 'score' => $sc['score'], 'status' => 'received', 'lang' => lang()]);
        \W5D\Mail::send($email, 'form_ack', ['kind' => 'manager', 'name' => self::str($in, 'name', 120), 'ref' => $ref]);
        \W5D\Mail::staff('New manager application', ['ref' => $ref, 'name' => self::str($in, 'name', 120), 'email' => $email, 'macro' => $macro, 'score' => (string)$sc['score']]);
        return ['ok' => true, 'errors' => [], 'data' => ['ref' => $ref, 'status' => 'received']];
    }

    public static function managers(Request $q): Response
    {
        if ($r = \W5D\Gate::need($q, 'manager', '/careers/managers/')) return $r;
        if (!$q->isPost()) return Pages::page($q, 'careers-managers');
        \W5D\Gate::bind($q);
        if ($r = self::check($q, '/careers/managers/', 'mgr')) return $r;
        $x = self::coreManager($q->post);
        return $x['ok'] ? self::done($q, $x['data'], 'manager') : self::fail($q, '/careers/managers/', $x['errors']);
    }
}
