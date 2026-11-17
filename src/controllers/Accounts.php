<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Account, Auth, Csrf, DB, Env, Mail, OAuth, Payments, RateLimit, Request, Response, Secret, Session, Variables, ViewEnv};

/** Public accounts: sign in/up, e-mail verification, password reset, social login, user dashboard, D17 payment page. */
final class Accounts
{
    private static function m(string $en, string $fr, string $ar): array { return ['en' => $en, 'fr' => $fr, 'ar' => $ar]; }
    private static function flash(string $kind, array $msg): void { Session::flash('acct', ['kind' => $kind, 'msg' => $msg]); }

    private static function devLink(string $url): void
    {
        // local/staging convenience only: with MAIL_DRIVER=log the e-mail goes nowhere, so show the link on screen. Never in production.
        if (Env::get('APP_ENV', 'production') !== 'production' && Env::get('MAIL_DRIVER', 'log') === 'log') $_SESSION['_dev_link'] = $url;
    }

    private static function page(Request $q, string $view, array $data = [], int $status = 200): Response
    {
        ViewEnv::share('canonicalPath', rtrim($q->path, '/') . '/');
        $html = view($view, $data + ['errors' => $_SESSION['_errors'] ?? [], 'robots' => 'noindex,nofollow', 'flash' => Session::flash('acct'), 'devLink' => $_SESSION['_dev_link'] ?? null]);
        unset($_SESSION['_errors'], $_SESSION['_old'], $_SESSION['_dev_link']);
        return Response::html($html, $status)->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store']);
    }

    private static function bounce(Request $q, string $back, array $errors): Response
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = array_map(fn($v) => is_scalar($v) ? (string)$v : '', array_diff_key($q->post, ['_csrf' => 1, 'website' => 1, 'password' => 1, 'password2' => 1, 'current' => 1]));
        return Response::redirect(url($back));
    }

    private static function guard(Request $q, string $bucket, string $back, int $max = 10, int $win = 600): ?Response
    {
        if (!Csrf::check($q)) return self::bounce($q, $back, ['_form' => self::m('Session expired. Reload and try again.', 'Session expirée. Rechargez et réessayez.', 'انتهت الجلسة. أعد التحميل وحاول مجددا.')]);
        if (!empty($q->post['website'])) return Response::redirect(url($back));
        if (!DB::available()) return self::bounce($q, $back, ['_form' => self::m('The service is temporarily unavailable.', 'Le service est momentanément indisponible.', 'الخدمة غير متاحة مؤقتا.')]);
        if (!RateLimit::hit($bucket . ':' . $q->ip(), $max, $win)) return self::bounce($q, $back, ['_form' => self::m('Too many attempts. Try again later.', 'Trop de tentatives. Réessayez plus tard.', 'محاولات كثيرة. حاول لاحقا.')]);
        return null;
    }

    private static function nextQ(?string $n): string { return $n ? '?next=' . rawurlencode($n) : ''; }

    /* ======================= sign in ======================= */
    public static function login(Request $q): Response
    {
        if (Account::signedIn()) return Response::redirect(Account::check() ? Account::home(Account::user()) : admin_url());
        $next = Account::safeNext((string)$q->input('next', ''));
        $back = '/account/login/' . self::nextQ($next);
        if (!$q->isPost()) return self::page($q, 'account.auth', ['mode' => 'login', 'next' => $next, 'title' => __('acct.login'), 'providers' => OAuth::enabled(), 'resend' => $_SESSION['_resend'] ?? null]);
        if ($r = self::guard($q, 'acct-login', $back, 20, 900)) return $r;
        $id = trim((string)($q->post['identifier'] ?? '')); $pw = (string)($q->post['password'] ?? '');
        $bad = ['_form' => self::m('Wrong e-mail or password.', 'E-mail ou mot de passe incorrect.', 'البريد أو كلمة المرور غير صحيحة.')];
        $owner = (string)Env::get('WEBMASTER_USERNAME', 'owner');
        if ($id !== '' && !str_contains($id, '@') && hash_equals($owner, $id)) {
            return Auth::attempt($id, $pw, $q->ip()) ? Response::redirect(admin_url()) : self::bounce($q, $back, $bad);
        }
        $email = mb_strtolower($id);
        if (!RateLimit::hit('acct-login-e:' . sha1($email), 8, 900)) return self::bounce($q, $back, ['_form' => self::m('Too many attempts. Try again later.', 'Trop de tentatives. Réessayez plus tard.', 'محاولات كثيرة. حاول لاحقا.')]);
        $u = filter_var($email, FILTER_VALIDATE_EMAIL) ? Account::byEmail($email) : null;
        if (!$u || !$u['password_hash'] || !password_verify($pw, $u['password_hash'])) { usleep(250000); return self::bounce($q, $back, $bad); }
        if (!(int)$u['active']) return self::bounce($q, $back, ['_form' => self::m('This account is disabled. Contact the desk.', 'Ce compte est désactivé. Contactez le bureau.', 'هذا الحساب معطّل. تواصل مع المكتب.')]);
        if (!$u['email_verified_at'] && Env::bool('AUTH_REQUIRE_VERIFIED', true)) {
            $_SESSION['_resend'] = $email;
            return self::bounce($q, $back, ['_form' => self::m('Confirm your e-mail first: open the link we sent you.', 'Confirmez d’abord votre e-mail : ouvrez le lien que nous vous avons envoyé.', 'أكّد بريدك أولا: افتح الرابط الذي أرسلناه إليك.')]);
        }
        if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) DB::q('UPDATE users SET password_hash = ? WHERE id = ?', [Account::hash($pw), $u['id']]);
        unset($_SESSION['_resend']);
        Account::login($u, !empty($q->post['remember']));
        return Response::redirect($next ? url($next) : Account::home($u));
    }

    public static function logout(Request $q): Response
    {
        if (Csrf::check($q)) Account::logout();
        return Response::redirect(url('/'));
    }

    /* ======================= register + verify ======================= */
    public static function register(Request $q): Response
    {
        if (Account::signedIn()) return Response::redirect(Account::home(Account::user()));
        if (!$q->isPost()) return self::page($q, 'account.auth', ['mode' => 'register', 'title' => __('acct.register'), 'providers' => OAuth::enabled(), 'next' => null]);
        if ($r = self::guard($q, 'acct-register', '/account/register/', 8, 3600)) return $r;
        $e = [];
        Forms::required($q->post, ['name'], $e);
        $email = Forms::email($q->post, 'email', $e);
        $pw = (string)($q->post['password'] ?? '');
        $e += Account::passwordErrors($pw, $email);
        if ($pw !== (string)($q->post['password2'] ?? '')) $e['password2'] = self::m('The two passwords differ.', 'Les deux mots de passe diffèrent.', 'كلمتا المرور غير متطابقتين.');
        $role = (string)($q->post['role'] ?? 'user');
        if (!in_array($role, Account::SELF_ROLES, true)) $role = 'user';
        Forms::accepted($q->post, $e);
        if ($e) return self::bounce($q, '/account/register/', $e);
        $u = Account::byEmail($email);
        if (!$u) {
            $id = Account::create($email, Forms::str($q->post, 'name', 120), $pw, $role, false, lang());
            $u = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
            Account::sendVerification($u);
            Mail::staff('New account', ['email' => $email, 'role' => $role]);
        } elseif (!$u['email_verified_at'] && RateLimit::hit('acct-verify:' . sha1($email), 3, 3600)) {
            Account::sendVerification($u);
        }
        if ($u && !$u['email_verified_at']) { $tok = DB::one("SELECT 1 FROM user_tokens WHERE user_id = ? AND type = 'verify' AND used_at IS NULL", [$u['id']]); }
        self::flash('ok', self::m('Almost done: we sent a confirmation link to this address (valid 24 hours). If the address was already registered, sign in or reset your password.', 'Presque fini : nous avons envoyé un lien de confirmation à cette adresse (valable 24 heures). Si l’adresse était déjà inscrite, connectez-vous ou réinitialisez votre mot de passe.', 'أوشكنا: أرسلنا رابط تأكيد إلى هذا العنوان (صالح 24 ساعة). إن كان العنوان مسجلا مسبقا فسجّل الدخول أو أعد تعيين كلمة المرور.'));
        return Response::redirect(url('/account/login/'));
    }

    public static function resend(Request $q): Response
    {
        if ($r = self::guard($q, 'acct-resend', '/account/login/', 6, 3600)) return $r;
        $email = mb_strtolower(trim((string)($q->post['email'] ?? '')));
        $u = filter_var($email, FILTER_VALIDATE_EMAIL) ? Account::byEmail($email) : null;
        if ($u && !$u['email_verified_at'] && RateLimit::hit('acct-verify:' . sha1($email), 3, 3600)) Account::sendVerification($u);
        unset($_SESSION['_resend']);
        self::flash('ok', self::m('If this address is waiting for confirmation, a new link was sent.', 'Si cette adresse attend une confirmation, un nouveau lien a été envoyé.', 'إن كان هذا العنوان بانتظار التأكيد فقد أُرسل رابط جديد.'));
        return Response::redirect(url('/account/login/'));
    }

    public static function verify(Request $q): Response
    {
        $t = DB::available() ? Account::readToken((string)($q->params['token'] ?? ''), 'verify') : null;
        if (!$t) return self::page($q, 'account.auth', ['mode' => 'notice', 'title' => __('acct.verify'), 'ok' => false, 'text' => self::m('This confirmation link is invalid, expired or already used. Request a new one from the sign-in page.', 'Ce lien de confirmation est invalide, expiré ou déjà utilisé. Demandez-en un nouveau depuis la page de connexion.', 'رابط التأكيد غير صالح أو منتهٍ أو مستعمل. اطلب رابطا جديدا من صفحة الدخول.')], 410);
        DB::q('UPDATE users SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP) WHERE id = ?', [$t['user_id']]);
        Account::notify((int)$t['user_id'], tr3('Welcome to W5D', 'Bienvenue sur W5D', 'مرحبا بك في W5D'), tr3('Your e-mail is confirmed. Complete your profile to get tailored next steps.', 'Votre e-mail est confirmé. Complétez votre profil pour obtenir des étapes adaptées.', 'تم تأكيد بريدك. أكمل ملفك للحصول على خطوات مناسبة.'), '/account/profile/');
        self::flash('ok', self::m('E-mail confirmed. You can sign in now.', 'E-mail confirmé. Vous pouvez vous connecter.', 'تم تأكيد البريد. يمكنك تسجيل الدخول الآن.'));
        return Response::redirect(url('/account/login/'));
    }

    /* ======================= password reset ======================= */
    public static function forgot(Request $q): Response
    {
        if (!$q->isPost()) return self::page($q, 'account.auth', ['mode' => 'forgot', 'title' => __('acct.forgot')]);
        if ($r = self::guard($q, 'acct-forgot', '/account/forgot/', 6, 3600)) return $r;
        $email = mb_strtolower(trim((string)($q->post['email'] ?? '')));
        $u = filter_var($email, FILTER_VALIDATE_EMAIL) ? Account::byEmail($email) : null;
        if ($u && (int)$u['active'] && RateLimit::hit('acct-reset:' . sha1($email), 3, 3600)) Account::sendReset($u);
        self::flash('ok', self::m('If an account uses this address, a reset link was sent (valid 60 minutes).', 'Si un compte utilise cette adresse, un lien de réinitialisation a été envoyé (valable 60 minutes).', 'إن كان هناك حساب بهذا العنوان فقد أُرسل رابط إعادة التعيين (صالح 60 دقيقة).'));
        return Response::redirect(url('/account/forgot/'));
    }

    public static function reset(Request $q): Response
    {
        $tok = (string)($q->params['token'] ?? '');
        $back = '/account/reset/' . $tok . '/';
        if (!DB::available() || !Account::readToken($tok, 'reset', false)) return self::page($q, 'account.auth', ['mode' => 'notice', 'title' => __('acct.reset'), 'ok' => false, 'text' => self::m('This reset link is invalid, expired or already used.', 'Ce lien de réinitialisation est invalide, expiré ou déjà utilisé.', 'رابط إعادة التعيين غير صالح أو منتهٍ أو مستعمل.')], 410);
        if (!$q->isPost()) return self::page($q, 'account.auth', ['mode' => 'reset', 'title' => __('acct.reset'), 'token' => $tok]);
        if ($r = self::guard($q, 'acct-reset-set', $back, 10, 3600)) return $r;
        $pw = (string)($q->post['password'] ?? '');
        $row = Account::readToken($tok, 'reset', false);
        $u = DB::one('SELECT * FROM users WHERE id = ?', [$row['user_id']]);
        $e = Account::passwordErrors($pw, (string)$u['email']);
        if ($pw !== (string)($q->post['password2'] ?? '')) $e['password2'] = self::m('The two passwords differ.', 'Les deux mots de passe diffèrent.', 'كلمتا المرور غير متطابقتين.');
        if ($e) return self::bounce($q, $back, $e);
        Account::readToken($tok, 'reset', true);
        DB::q('UPDATE users SET password_hash = ?, password_changed_at = CURRENT_TIMESTAMP, email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP) WHERE id = ?', [Account::hash($pw), $u['id']]);
        Account::revokeAll((int)$u['id']);
        self::flash('ok', self::m('Password updated. Sign in with the new password.', 'Mot de passe mis à jour. Connectez-vous avec le nouveau.', 'تم تحديث كلمة المرور. سجّل الدخول بها.'));
        return Response::redirect(url('/account/login/'));
    }

    /* ======================= social login ======================= */
    public static function social(Request $q): Response
    {
        $p = (string)($q->params['provider'] ?? '');
        $url = OAuth::authorizeUrl($p, Account::safeNext((string)$q->input('next', '')));
        if (!$url) { self::flash('error', self::m('This sign-in method is not available.', 'Ce mode de connexion n’est pas disponible.', 'طريقة الدخول هذه غير متاحة.')); return Response::redirect(url('/account/login/')); }
        return Response::redirect($url);
    }

    public static function socialCallback(Request $q): Response
    {
        $p = (string)($q->params['provider'] ?? '');
        $fail = function () { self::flash('error', self::m('Social sign-in failed. Try again or use your e-mail.', 'La connexion sociale a échoué. Réessayez ou utilisez votre e-mail.', 'فشل الدخول الاجتماعي. حاول مجددا أو استخدم بريدك.')); return Response::redirect(url('/account/login/')); };
        if (!RateLimit::hit('acct-social:' . $q->ip(), 20, 900) || !DB::available()) return $fail();
        $code = (string)$q->input('code', ''); $state = (string)$q->input('state', '');
        if ($code === '' || $state === '' || $q->input('error')) { unset($_SESSION['oauth']); return $fail(); }
        $pr = OAuth::profile($p, $code, $state);
        if (!$pr || $pr['uid'] === '') return $fail();
        $link = DB::one('SELECT user_id FROM social_accounts WHERE provider = ? AND provider_uid = ?', [$p, $pr['uid']]);
        $u = $link ? DB::one('SELECT * FROM users WHERE id = ? AND active = 1', [$link['user_id']]) : null;
        if (!$u) {
            if (!$pr['email'] || !$pr['verified']) { self::flash('error', self::m('The provider did not share a verified e-mail address.', 'Le fournisseur n’a pas partagé d’adresse e-mail vérifiée.', 'لم يشارك المزوّد بريدا إلكترونيا موثّقا.')); return Response::redirect(url('/account/login/')); }
            $u = Account::byEmail($pr['email']);
            if ($u && !in_array($u['role_slug'], Account::SELF_ROLES, true)) { self::flash('error', self::m('Staff accounts must sign in with their password.', 'Les comptes du personnel doivent se connecter avec leur mot de passe.', 'حسابات الفريق تسجّل الدخول بكلمة المرور.')); return Response::redirect(url('/account/login/')); }
            if (!$u) { $id = Account::create($pr['email'], $pr['name'], null, 'user', true, lang()); $u = DB::one('SELECT * FROM users WHERE id = ?', [$id]); }
            elseif (!$u['email_verified_at']) { DB::q('UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE id = ?', [$u['id']]); $u['email_verified_at'] = date('Y-m-d H:i:s'); }
            DB::q('INSERT OR IGNORE INTO social_accounts(user_id, provider, provider_uid, email) VALUES (?,?,?,?)', [$u['id'], $p, $pr['uid'], $pr['email']]);
            if ($pr['avatar'] && !$u['avatar_url'] && preg_match('#^https://#', (string)$pr['avatar'])) DB::q('UPDATE users SET avatar_url = ? WHERE id = ?', [$pr['avatar'], $u['id']]);
        }
        Account::login($u, false);
        $n = Account::safeNext($pr['next'] ?? null);
        return Response::redirect($n ? url($n) : Account::home($u));
    }

    /* ======================= dashboard ======================= */
    private static function need(Request $q): ?Response
    {
        if (Account::check()) return null;
        if (Auth::check()) return Response::redirect(admin_url());
        return Response::redirect(url('/account/login/') . '?next=' . rawurlencode(rtrim($q->path, '/') . '/'));
    }

    /** Everything the dashboard needs for the signed-in user (by verified e-mail). */
    public static function data(array $u): array
    {
        $inv = DB::one('SELECT * FROM investors WHERE country_id = ? AND email = ?', ['tn', $u['email']]);
        $orders = $subs = $pays = [];
        if ($inv) {
            $orders = DB::all('SELECT o.*, (SELECT status FROM payments p WHERE p.order_ref = o.ref ORDER BY p.id DESC LIMIT 1) AS pay_status FROM investor_orders o WHERE o.investor_id = ? ORDER BY o.id DESC LIMIT 50', [$inv['id']]);
            $subs = DB::all('SELECT * FROM investor_subscriptions WHERE investor_id = ? ORDER BY id DESC LIMIT 20', [$inv['id']]);
            $pays = DB::all('SELECT * FROM payments WHERE investor_id = ? ORDER BY id DESC LIMIT 50', [$inv['id']]);
        }
        return [
            'investor' => $inv, 'orders' => $orders, 'subs' => $subs, 'pays' => $pays,
            'applicant' => DB::one('SELECT * FROM applicants WHERE country_id = ? AND email = ? ORDER BY id DESC', ['tn', $u['email']]),
            'managers' => DB::all('SELECT ref, macro_slug, status, score, created_at FROM manager_applications WHERE email = ? ORDER BY id DESC LIMIT 5', [$u['email']]),
            'notes' => DB::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$u['id']]),
        ];
    }

    /** The single "next best action" for this person. */
    public static function nextAction(array $u, array $d): array
    {
        $L = [Pages::class, 'L'];
        foreach ($d['pays'] as $p) {
            if ($p['status'] === 'pending' && !$p['txn_ref']) return ['icon' => 'smartphone', 'title' => $L('Send your D17 transaction reference', 'Envoyez votre référence de transaction D17', 'أرسل مرجع عملية D17'), 'body' => $L('Order ' . $p['order_ref'] . ' waits for your D17 payment details.', 'La commande ' . $p['order_ref'] . ' attend vos détails de paiement D17.', 'الطلب ' . $p['order_ref'] . ' بانتظار تفاصيل دفع D17.'), 'href' => Payments::link($p['order_ref']), 'cta' => $L('Open payment page', 'Ouvrir la page de paiement', 'افتح صفحة الدفع')];
            if ($p['status'] === 'rejected') return ['icon' => 'triangle-alert', 'title' => $L('A payment was rejected', 'Un paiement a été rejeté', 'تم رفض دفعة'), 'body' => $L('Check the reference and resubmit.', 'Vérifiez la référence et soumettez à nouveau.', 'تحقق من المرجع وأعد الإرسال.'), 'href' => Payments::link($p['order_ref']), 'cta' => $L('Fix it', 'Corriger', 'صحّح')];
        }
        if (!$u['phone'] || $u['phone'] === '') return ['icon' => 'user-round', 'title' => $L('Complete your profile', 'Complétez votre profil', 'أكمل ملفك'), 'body' => $L('A phone number lets us reach you about orders and D17 payments.', 'Un numéro de téléphone nous permet de vous joindre pour les commandes et les paiements D17.', 'رقم الهاتف يتيح لنا التواصل معك بشأن الطلبات ودفعات D17.'), 'href' => url('/account/profile/'), 'cta' => $L('Edit profile', 'Modifier le profil', 'عدّل الملف')];
        return match ($u['role_slug']) {
            'investor' => $d['orders'] ? ['icon' => 'repeat', 'title' => $L('Keep a daily part going', 'Gardez une part quotidienne', 'واصل حصة يومية'), 'body' => $L('See today’s price and extend your schedule.', 'Voyez le prix du jour et prolongez votre calendrier.', 'اطّلع على سعر اليوم ومدّد جدولك.'), 'href' => url('/subscription/'), 'cta' => $L('Daily subscription', 'Abonnement quotidien', 'اشتراك يومي')]
                : ['icon' => 'coins', 'title' => $L('Take your first part', 'Prenez votre première part', 'احصل على حصتك الأولى'), 'body' => $L('Three steps, a full schedule, a reference you can verify.', 'Trois étapes, un calendrier complet, une référence vérifiable.', 'ثلاث خطوات وجدول كامل ومرجع يمكنك التحقق منه.'), 'href' => url('/checkout/'), 'cta' => $L('Take a part today', 'Prendre une part', 'احصل على حصة')],
            'customer' => ['icon' => 'briefcase', 'title' => $L('Describe the work you need', 'Décrivez le travail dont vous avez besoin', 'صف العمل الذي تحتاجه'), 'body' => $L('Browse the services, then write to the partners desk.', 'Parcourez les services puis écrivez au bureau des partenaires.', 'تصفّح الخدمات ثم اكتب إلى مكتب الشركاء.'), 'href' => url('/contact/') . '?desk=partners', 'cta' => $L('Request a quote', 'Demander un devis', 'اطلب عرض سعر')],
            default => $d['applicant'] ? ['icon' => 'badge-check', 'title' => $L('Your applicant file is ' . $d['applicant']['status'], 'Votre dossier est « ' . $d['applicant']['status'] . ' »', 'حالة ملفك: ' . $d['applicant']['status']), 'body' => $L('A human reviewer checks identity and eligibility next.', 'Un relecteur humain vérifie ensuite l’identité et l’éligibilité.', 'يتحقق مراجع بشري بعد ذلك من الهوية والأهلية.'), 'href' => url('/how-it-works/'), 'cta' => $L('How it works', 'Comment ça marche', 'كيف يعمل')]
                : ['icon' => 'graduation-cap', 'title' => $L('Check which service fits you', 'Voyez quel service vous convient', 'اعرف أي خدمة تناسبك'), 'body' => $L('Eligibility is indicative; a person confirms it at verification.', 'L’éligibilité est indicative ; une personne la confirme à la vérification.', 'الأهلية استرشادية ويؤكدها شخص عند التحقق.'), 'href' => url('/join/eligibility/'), 'cta' => $L('Check my eligibility', 'Vérifier mon éligibilité', 'تحقق من أهليتي')],
        };
    }

    public static function panel(Request $q): Response
    {
        if ($r = self::need($q)) return $r;
        $panel = (string)($q->params['panel'] ?? 'overview');
        if ($panel === '') $panel = 'overview';
        if (!in_array($panel, ['overview', 'orders', 'payments', 'applications', 'notifications', 'profile', 'security'], true)) return Pages::notFound($q);
        $u = Account::row();
        if (!$u) return Response::redirect(url('/account/login/'));
        $d = self::data($u);
        if ($panel === 'notifications' && $q->isPost() === false && ($d['notes'])) DB::q('UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read_at IS NULL', [$u['id']]);
        $titles = ['overview' => __('acct.overview'), 'orders' => __('acct.orders'), 'payments' => __('acct.payments'), 'applications' => __('acct.applications'), 'notifications' => __('acct.notifications'), 'profile' => __('acct.profile'), 'security' => __('acct.security')];
        return self::page($q, 'account.panel', ['panel' => $panel, 'title' => $titles[$panel], 'u' => $u, 'd' => $d, 'next' => self::nextAction($u, $d), 'price' => Variables::get('price'), 'Lbl' => [Pages::class, 'L']]);
    }

    public static function profile(Request $q): Response
    {
        if ($r = self::need($q)) return $r;
        if ($r = self::guard($q, 'acct-profile', '/account/profile/', 30, 600)) return $r;
        $e = [];
        Forms::required($q->post, ['name'], $e);
        $phone = Forms::str($q->post, 'phone', 40);
        if ($phone !== '' && !preg_match('/^\+?[0-9 ().-]{6,20}$/', $phone)) $e['phone'] = self::m('Enter a valid phone number.', 'Saisissez un numéro valide.', 'أدخل رقم هاتف صالحا.');
        $lang = (string)($q->post['lang'] ?? 'en');
        if (!in_array($lang, \W5D\Lang::SUPPORTED, true)) $lang = 'en';
        if ($e) return self::bounce($q, '/account/profile/', $e);
        DB::q('UPDATE users SET name = ?, phone = ?, lang = ? WHERE id = ?', [Forms::str($q->post, 'name', 120), $phone, $lang, Account::id()]);
        $_SESSION['acct']['name'] = Forms::str($q->post, 'name', 120);
        \W5D\Lang::set($lang);
        self::flash('ok', self::m('Profile saved.', 'Profil enregistré.', 'تم حفظ الملف.'));
        return Response::redirect(url('/account/profile/'));
    }

    public static function security(Request $q): Response
    {
        if ($r = self::need($q)) return $r;
        if ($r = self::guard($q, 'acct-security', '/account/security/', 10, 900)) return $r;
        $u = Account::row();
        $act = (string)($q->post['do'] ?? 'password');
        if ($act === 'signout-all') {
            Account::revokeAll((int)$u['id']);
            self::flash('ok', self::m('Remember-me sessions on every device were revoked.', 'Les sessions « se souvenir de moi » de tous les appareils ont été révoquées.', 'تم إلغاء جلسات «تذكرني» على كل الأجهزة.'));
            return Response::redirect(url('/account/security/'));
        }
        $cur = (string)($q->post['current'] ?? ''); $pw = (string)($q->post['password'] ?? '');
        $e = [];
        if ($u['password_hash'] && !password_verify($cur, $u['password_hash'])) $e['current'] = self::m('Current password is wrong.', 'Le mot de passe actuel est incorrect.', 'كلمة المرور الحالية خاطئة.');
        $e += Account::passwordErrors($pw, (string)$u['email']);
        if ($pw !== (string)($q->post['password2'] ?? '')) $e['password2'] = self::m('The two passwords differ.', 'Les deux mots de passe diffèrent.', 'كلمتا المرور غير متطابقتين.');
        if ($e) return self::bounce($q, '/account/security/', $e);
        DB::q('UPDATE users SET password_hash = ?, password_changed_at = CURRENT_TIMESTAMP WHERE id = ?', [Account::hash($pw), $u['id']]);
        Account::revokeAll((int)$u['id']);
        session_regenerate_id(true);
        self::flash('ok', self::m('Password changed. Other devices were signed out.', 'Mot de passe modifié. Les autres appareils ont été déconnectés.', 'تم تغيير كلمة المرور وفُصلت الأجهزة الأخرى.'));
        return Response::redirect(url('/account/security/'));
    }

    /* ======================= D17 payment page ======================= */
    public static function pay(Request $q): Response
    {
        $ref = (string)($q->params['ref'] ?? '');
        $sig = (string)$q->input('s', '');
        $o = preg_match('/^ORD-[A-F0-9]{8}$/', $ref) && Payments::enabled() ? DB::one('SELECT o.*, i.full_name, i.email, i.phone FROM investor_orders o JOIN investors i ON i.id = o.investor_id WHERE o.ref = ?', [$ref]) : null;
        if (!$o || !Secret::verify('pay:' . $ref, $sig)) return Pages::notFound($q);
        $p = DB::one('SELECT * FROM payments WHERE order_ref = ? ORDER BY id DESC', [$ref]);
        if (!$p) return Pages::notFound($q);
        $back = '/pay/' . $ref . '/?s=' . $sig;
        if ($q->isPost()) {
            if ($r = self::guard($q, 'pay-submit', $back, 12, 3600)) return $r;
            $e = [];
            $gsm = Payments::tnGsm((string)($q->post['sender_gsm'] ?? ''));
            if (!$gsm) $e['sender_gsm'] = self::m('D17 is for Tunisian numbers only: use +216 followed by 8 digits.', 'D17 est réservé aux numéros tunisiens : +216 suivi de 8 chiffres.', 'D17 للأرقام التونسية فقط: +216 متبوعا بـ 8 أرقام.');
            $txn = strtoupper(trim((string)($q->post['txn_ref'] ?? '')));
            if (!preg_match('/^[A-Z0-9\-]{6,32}$/', $txn)) $e['txn_ref'] = self::m('Enter the D17 transaction reference (6 to 32 letters or digits).', 'Saisissez la référence de transaction D17 (6 à 32 lettres ou chiffres).', 'أدخل مرجع عملية D17 (من 6 إلى 32 حرفا أو رقما).');
            if ($p['status'] === 'verified') $e['_form'] = self::m('This payment is already verified.', 'Ce paiement est déjà vérifié.', 'تم التحقق من هذه الدفعة مسبقا.');
            if ($DUP = ($txn !== '' ? DB::one("SELECT id FROM payments WHERE txn_ref = ? AND id != ? AND status != 'rejected'", [$txn, $p['id']]) : null)) $e['txn_ref'] = self::m('This transaction reference was already used.', 'Cette référence de transaction a déjà été utilisée.', 'مرجع العملية هذا مستعمل مسبقا.');
            if ($e) return self::bounce($q, $back, $e);
            DB::q("UPDATE payments SET sender_gsm = ?, txn_ref = ?, status = 'pending', note = NULL WHERE id = ?", [$gsm, $txn, $p['id']]);
            $u = DB::one('SELECT id FROM users WHERE email = ?', [$o['email']]);
            if ($u) Account::notify((int)$u['id'], tr3('D17 reference received', 'Référence D17 reçue', 'تم استلام مرجع D17'), $p['ref'] . ' · ' . $txn, '/account/payments/');
            Mail::staff('D17 payment to verify', ['payment' => $p['ref'], 'order' => $ref, 'amount' => number_format((float)$p['amount'], 2) . ' ' . $p['currency'], 'sender' => $gsm, 'txn' => $txn]);
            self::flash('ok', self::m('Thank you. An administrator will verify the transfer by hand and we will e-mail you.', 'Merci. Un administrateur vérifiera le virement manuellement et nous vous écrirons.', 'شكرا. سيتحقق مسؤول من التحويل يدويا وسنراسلك.'));
            return Response::redirect(url($back));
        }
        $p = DB::one('SELECT * FROM payments WHERE id = ?', [$p['id']]);
        return self::page($q, 'account.pay', ['title' => __('acct.pay_d17'), 'o' => $o, 'p' => $p, 'd17' => Payments::instructions((float)$p['amount']), 'sig' => $sig]);
    }

    /* ======================= admin: D17 verification + user roles ======================= */
    public static function review(Request $q, callable $audit): Response
    {
        $id = (int)($q->post['id'] ?? 0); $do = (string)($q->post['do'] ?? '');
        $p = $id > 0 ? DB::one('SELECT p.*, i.full_name, i.email, i.lang FROM payments p LEFT JOIN investors i ON i.id = p.investor_id WHERE p.id = ?', [$id]) : null;
        if (!$p || !in_array($do, ['verify', 'reject'], true) || $p['status'] === 'verified') return Response::redirect(admin_url('payments-d17/'));
        $status = $do === 'verify' ? 'verified' : 'rejected';
        $note = mb_substr(trim((string)($q->post['note'] ?? '')), 0, 300);
        DB::q('UPDATE payments SET status = ?, note = ?, verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE id = ?', [$status, $note ?: null, (string)(Auth::user()['name'] ?? 'admin'), $id]);
        DB::q('UPDATE investor_orders SET status = ? WHERE ref = ?', [$status === 'verified' ? 'paid' : 'awaiting_payment', $p['order_ref']]);
        $audit('payment.' . $status, $p['ref'], $p['order_ref'] . ' ' . $note);
        if ($p['email']) {
            Mail::send($p['email'], 'payment_status', ['name' => $p['full_name'], 'ref' => $p['ref'], 'order_ref' => $p['order_ref'], 'amount' => $p['amount'], 'currency' => $p['currency'], 'status' => $status, 'note' => $note, 'pay_url' => Payments::absLink($p['order_ref'])], $p['lang'] ?? null);
            $u = DB::one('SELECT id FROM users WHERE email = ?', [$p['email']]);
            if ($u) Account::notify((int)$u['id'], $status === 'verified' ? tr3('Payment verified', 'Paiement vérifié', 'تم التحقق من الدفعة') : tr3('Payment rejected', 'Paiement rejeté', 'تم رفض الدفعة'), $p['ref'] . ' · ' . $p['order_ref'], '/account/payments/');
        }
        $_SESSION['_admin_flash'] = ['kind' => 'ok', 'msg' => $p['ref'] . ' → ' . $status];
        return Response::redirect(admin_url('payments-d17/'));
    }
}
