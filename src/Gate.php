<?php
declare(strict_types=1);

namespace W5D;

/**
 * Sign-in gate for forms that create a record tied to a person (pledge, subscription, applicant file, manager application).
 * A guest is sent to /account/login/?next=<form page incl. query string> and returns there after sign-in.
 * The owner switches each gate in Admin > Variables & Vocabularies (flags.gate_*); contact and newsletter are never gated.
 */
final class Gate
{
    /** form key => [flag, tri-lingual reason shown on the sign-in page] */
    private const FORMS = [
        'checkout'     => ['gate_forms', ['Sign in to take a part. Your pledge, its schedule and its payments are kept in your account.', 'Connectez-vous pour prendre une part. Votre engagement, son calendrier et ses paiements sont conservés dans votre compte.', 'سجّل الدخول لاقتناء حصة. يُحفظ التزامك وجدوله ودفعاته في حسابك.']],
        'subscription' => ['gate_forms', ['Sign in to start a daily subscription. You can pause or cancel it from your account.', 'Connectez-vous pour démarrer un abonnement quotidien. Vous pourrez le suspendre ou l’annuler depuis votre compte.', 'سجّل الدخول لبدء اشتراك يومي. يمكنك إيقافه أو إلغاؤه من حسابك.']],
        'register'     => ['gate_forms', ['Sign in to open your applicant file. You will follow its status and every notification in your account.', 'Connectez-vous pour ouvrir votre dossier de candidat. Vous suivrez son état et chaque notification dans votre compte.', 'سجّل الدخول لفتح ملف المتقدم. ستتابع حالته وكل إشعار في حسابك.']],
        'manager'      => ['gate_forms', ['Sign in to apply as a manager. Your application stays linked to your account and a person reads it in full.', 'Connectez-vous pour postuler comme manager. Votre candidature reste liée à votre compte et une personne la lit en entier.', 'سجّل الدخول للتقدم كمدير. يبقى طلبك مرتبطا بحسابك ويقرؤه شخص بالكامل.']],
        'eligibility'  => ['gate_eligibility', ['Sign in to save your eligibility check to your account.', 'Connectez-vous pour enregistrer votre vérification d’admissibilité dans votre compte.', 'سجّل الدخول لحفظ فحص الأهلية في حسابك.']],
    ];

    public static function enabled(string $form): bool
    {
        $f = self::FORMS[$form][0] ?? null;
        return $f !== null && (int)Variables::get('flags.' . $f, 0) === 1;
    }

    /** Forms and the flag that governs each (used by the dashboards and the docs). */
    public static function map(): array
    {
        $o = [];
        foreach (self::FORMS as $k => [$flag]) $o[$k] = ['flag' => 'flags.' . $flag, 'on' => self::enabled($k)];
        return $o;
    }

    public static function allowed(): bool { return Account::check() || Auth::check(); }

    /**
     * Returns a redirect to sign-in when the form is gated and the visitor is a guest; null otherwise.
     * @param string $page canonical form page, e.g. /checkout/ (a POST from a guest returns to this page, not to the POST)
     */
    public static function need(Request $q, string $form, string $page): ?Response
    {
        if (!self::enabled($form) || self::allowed()) return null;
        $next = $page;
        if (!$q->isPost()) {
            $qs = http_build_query(array_filter($q->query, fn($v) => is_scalar($v) && (string)$v !== ''));
            $next = rtrim($q->path, '/') . '/' . ($qs !== '' ? '?' . $qs : '');
        }
        $next = Account::safeNext($next) ?? $page;
        $why = self::FORMS[$form][1];
        Session::flash('acct', ['kind' => 'info', 'msg' => ['en' => $why[0], 'fr' => $why[1], 'ar' => $why[2]]]);
        return Response::redirect(url('/account/login/') . '?next=' . rawurlencode($next));
    }

    /** Values to pre-fill a form for the signed-in account (empty for guests / owner). */
    public static function prefill(): array
    {
        $u = Account::check() ? Account::row() : null;
        if (!$u) return [];
        return ['name' => (string)$u['name'], 'full_name' => (string)$u['name'], 'email' => (string)$u['email'], 'phone' => (string)($u['phone'] ?? '')];
    }

    /** True when the e-mail field must show the account address read-only (the record is tied to the account). */
    public static function lockEmail(): bool { return Account::check(); }

    /** Force the account e-mail into a submitted form so the record links to the dashboard. */
    public static function bind(Request $q): void
    {
        if (!Account::check()) return;
        $u = Account::user();
        $q->post['email'] = (string)$u['email'];
    }
}
