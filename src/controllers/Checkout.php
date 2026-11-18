<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Content, Csrf, DB, Gate, Mail, Payments, Pricing, Request, Response, Variables, ViewEnv};

/** Investor demo flows: pledge checkout, daily subscription, management by reference + key. No money moves in demo mode. */
final class Checkout
{
    /** Price quote for N parts per period. Cadence 'daily' = one charge per day, 'weekly' = one per 7 days (weekly unit). */
    public static function quote(int $parts, int $days, \DateTimeInterface $start, string $cadence = 'daily'): array
    {
        $v = Variables::all();
        $p = Pricing::progress($v);
        $tz = new \DateTimeZone('Africa/Tunis');
        $d0 = \DateTimeImmutable::createFromInterface($start)->setTimezone($tz)->setTime(12, 0);
        $now = new \DateTimeImmutable('now', $tz);
        $weekly = $cadence === 'weekly';
        $step = $weekly ? 7 : 1;
        $n = $weekly ? (int)ceil($days / 7) : $days;
        $rows = [];
        $total = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $d = $d0->modify('+' . ($i * $step) . ' day');
            // A charge dated today is priced at the live instant, so it equals the published price;
            // later charges are forecasts at noon (the real price on the day is recomputed).
            $t = Pricing::timeShare($d->format('Y-m-d') === $now->format('Y-m-d') ? $now : $d, $v);
            $unit = Pricing::perPart($weekly ? 'week' : 'day', $p, $t, $v);
            $amount = round($unit * $parts, 2);
            $total += $amount;
            $rows[] = ['date' => $d->format('Y-m-d'), 'unit' => $unit, 'amount' => $amount];
        }
        $snap = Pricing::snapshot($v);
        return ['today' => ['unit' => $weekly ? $snap['week'] : $snap['day']], 'schedule' => ['total' => round($total, 2), 'rows' => $rows, 'charges' => $n],
                'currency' => $v['money']['currency'], 'p' => $snap['p'], 't' => $snap['t'], 'parts' => $parts, 'days' => $days, 'cadence' => $cadence];
    }

    /** Normalise quote inputs; returns [parts, days, start, cadence, errors]. */
    public static function inputs(array $in): array
    {
        $inv = Variables::get('investor');
        $e = [];
        $parts = (int)($in['parts_per_day'] ?? $in['parts'] ?? 0);
        $days = (int)($in['days'] ?? 0);
        $cad = (string)($in['cadence'] ?? 'daily');
        if (!in_array($cad, ['daily', 'weekly'], true)) $cad = 'daily';
        if ($parts < $inv['min_parts_per_day'] || $parts > $inv['max_parts_per_day']) $e['parts_per_day'] = Forms::err("Choose {$inv['min_parts_per_day']} to {$inv['max_parts_per_day']} parts.", "Choisissez de {$inv['min_parts_per_day']} à {$inv['max_parts_per_day']} parts.", "اختر من {$inv['min_parts_per_day']} إلى {$inv['max_parts_per_day']} حصة.");
        if ($days < 1 || $days > $inv['max_days']) $e['days'] = Forms::err("Choose 1 to {$inv['max_days']} days.", "Choisissez de 1 à {$inv['max_days']} jours.", "اختر من 1 إلى {$inv['max_days']} يوما.");
        $tz = new \DateTimeZone('Africa/Tunis');
        $startRaw = (string)($in['start_on'] ?? $in['start'] ?? '');
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $startRaw, $tz) ?: null;
        $today = new \DateTimeImmutable('today', $tz);
        if (!$start || $start < $today || $start > $today->modify('+400 day')) {
            if ($startRaw === '') $start = $today->modify('+1 day');
            else $e['start_on'] = Forms::err('Choose a start date from today.', 'Choisissez une date de début à partir d’aujourd’hui.', 'اختر تاريخ بدء من اليوم.');
        }
        return [$parts, $days, $start ?? $today, $cad, $e];
    }

    /** Create investor + order (+ subscription). Shared by HTML wizard and JSON API. */
    public static function core(array $in, string $mode): array
    {
        $e = [];
        Forms::required($in, ['full_name'], $e);
        $email = Forms::email($in, 'email', $e);
        Forms::accepted($in, $e);
        [$parts, $days, $start, $cad, $e2] = self::inputs($in);
        $e += $e2;
        $macro = Forms::str($in, 'macro', 30);
        if ($macro !== '' && !in_array($macro, Forms::MACROS, true)) $e['macro'] = Forms::err('Unknown macro work.', 'Macro-œuvre inconnue.', 'عمل كبير غير معروف.');
        $kind = Forms::str($in, 'kind', 20) === 'company' ? 'company' : 'individual';
        $d17 = (string)($in['payment_method'] ?? '') === 'd17';
        if ($d17 && !Payments::enabled()) $e['payment_method'] = Forms::err('D17 is not available right now.', 'D17 n’est pas disponible pour le moment.', 'D17 غير متاح حاليا.');
        if ($d17 && !isset($e['payment_method'])) {
            $gsm = Payments::tnGsm(Forms::str($in, 'phone', 40));
            if (!$gsm) $e['phone'] = Forms::err('D17 is for Tunisian numbers only: give a +216 phone number.', 'D17 est réservé aux numéros tunisiens : indiquez un numéro +216.', 'D17 للأرقام التونسية فقط: أدخل رقم هاتف +216.');
        }
        if ($e) return ['ok' => false, 'errors' => $e];

        $q = self::quote($parts, $days, $start, $cad);
        $inv = DB::one('SELECT id FROM investors WHERE country_id = ? AND email = ?', ['tn', $email]);
        if ($inv) {
            $iid = (int)$inv['id'];
            DB::q('UPDATE investors SET full_name = ?, phone = ?, organisation = ?, kind = ?, lang = ?, accepted_limits = 1 WHERE id = ?', [Forms::str($in, 'full_name', 120), Forms::str($in, 'phone', 40), Forms::str($in, 'organisation', 120), $kind, lang(), $iid]);
        } else {
            $iid = DB::insert('investors', ['country_id' => 'tn', 'email' => $email, 'full_name' => Forms::str($in, 'full_name', 120), 'phone' => Forms::str($in, 'phone', 40), 'organisation' => Forms::str($in, 'organisation', 120), 'kind' => $kind, 'lang' => lang(), 'accepted_limits' => 1]);
        }
        $ref = Forms::ref('ORD');
        DB::insert('investor_orders', ['country_id' => 'tn', 'ref' => $ref, 'investor_id' => $iid, 'cadence' => $cad, 'parts_per_day' => $parts, 'days' => $days, 'macro_slug' => $macro ?: null,
            'start_on' => $start->format('Y-m-d'), 'unit_price' => $q['today']['unit'], 'total' => $q['schedule']['total'], 'p' => $q['p'], 't' => $q['t'], 'payment_mode' => $d17 ? 'd17' : (string)\W5D\Env::get('PAYMENT_MODE', 'demo'), 'status' => $d17 ? 'awaiting_payment' : 'pledged']);
        $token = null;
        if ($mode === 'subscription') {
            $token = bin2hex(random_bytes(16));
            DB::insert('investor_subscriptions', ['country_id' => 'tn', 'token' => hash('sha256', $token), 'investor_id' => $iid, 'order_ref' => $ref, 'parts_per_day' => $parts, 'macro_slug' => $macro ?: null, 'status' => 'active', 'next_charge_on' => $start->format('Y-m-d')]);
        }
        $payRef = $payUrl = null;
        if ($d17) {
            $payRef = Payments::create($ref, $iid, (float)$q['schedule']['total']);
            $payUrl = Payments::link($ref);
        }
        $mailTo = $email;
        Mail::send($mailTo, 'order_receipt', [
            'name' => Forms::str($in, 'full_name', 120), 'ref' => $ref, 'parts' => $parts, 'days' => $days, 'start' => $start->format('Y-m-d'),
            'total' => $q['schedule']['total'], 'currency' => $q['currency'], 'key' => $token,
            'd17' => $d17 ? Payments::instructions((float)$q['schedule']['total']) : null, 'pay_url' => $d17 ? Payments::absLink($ref) : null,
        ], lang());
        if ($d17) Mail::staff('New D17 order', ['order' => $ref, 'payment' => $payRef, 'amount' => number_format((float)$q['schedule']['total'], 2) . ' ' . $q['currency']]);
        return ['ok' => true, 'errors' => [], 'data' => ['ref' => $ref, 'management_key' => $token, 'mode' => $mode, 'unit_price' => $q['today']['unit'], 'total' => $q['schedule']['total'], 'currency' => $q['currency'], 'charges' => $q['schedule']['charges'], 'payment' => $d17 ? 'd17: pending administrator verification' : 'demo: no money moves', 'payment_ref' => $payRef, 'pay_url' => $payUrl, 'manage_url' => $token ? url('/subscription/manage/') : null]];
    }

    private static function back(string $mode): string { return $mode === 'subscription' ? '/subscription/' : '/checkout/'; }

    private static function submit(Request $q, string $mode): Response
    {
        $back = self::back($mode);
        if (!Csrf::check($q)) { $_SESSION['_flash_error'] = 'csrf'; return Response::redirect(url($back)); }
        Gate::bind($q);
        $g = Forms::guard($q, 'checkout', 10, 600);
        if ($g === 'bot') return Response::redirect(url($back));
        if ($g) { $_SESSION['_flash_error'] = $g; return Response::redirect(url($back)); }
        $x = self::core($q->post, $mode);
        if (!$x['ok']) {
            $_SESSION['_errors'] = $x['errors'];
            $_SESSION['_old'] = array_map(fn($v) => is_scalar($v) ? (string)$v : '', array_diff_key($q->post, ['_csrf' => 1, 'website' => 1]));
            $_SESSION['_flash_error'] = 'validation';
            return Response::redirect(url($back));
        }
        $_SESSION['_receipt'] = $x['data'];
        return Response::redirect(url('/checkout/done/' . $x['data']['ref'] . '/'));
    }

    public static function wizard(Request $q): Response
    {
        $sub = $q->isPost() && ($q->post['mode'] ?? '') === 'subscription';
        if ($r = Gate::need($q, $sub ? 'subscription' : 'checkout', $sub ? '/subscription/' : '/checkout/')) return $r;
        if ($q->isPost()) return self::submit($q, $sub ? 'subscription' : 'checkout');
        return self::view($q, 'checkout');
    }

    public static function subscription(Request $q): Response
    {
        if ($r = Gate::need($q, 'subscription', '/subscription/')) return $r;
        if ($q->isPost()) return self::submit($q, 'subscription');
        return self::view($q, 'subscription');
    }

    /** Render a JSON page, then clear one-shot form state. */
    private static function view(Request $q, string $slug): Response
    {
        $r = Pages::page($q, $slug);
        unset($_SESSION['_errors'], $_SESSION['_old'], $_SESSION['_flash_error']);
        return $r;
    }

    public static function done(Request $q): Response
    {
        $ref = (string)($q->params['ref'] ?? '');
        $o = preg_match('/^ORD-[A-F0-9]{8}$/', $ref) ? DB::one('SELECT o.*, i.full_name FROM investor_orders o JOIN investors i ON i.id = o.investor_id WHERE o.ref = ?', [$ref]) : null;
        if (!$o) return Pages::notFound($q);
        $receipt = $_SESSION['_receipt'] ?? null;
        $key = ($receipt && ($receipt['ref'] ?? '') === $ref) ? ($receipt['management_key'] ?? null) : null;
        $L = [Pages::class, 'L'];
        $page = Content::page('checkout-done') ?? ['title' => 'Pledge recorded', 'sections' => []];
        $first = explode(' ', trim($o['full_name']))[0];
        $rows = [
            [$L('Reference', 'Référence', 'المرجع'), ['en' => $ref]],
            [$L('Parts per ' . ($o['cadence'] === 'weekly' ? 'week' : 'day'), 'Parts par ' . ($o['cadence'] === 'weekly' ? 'semaine' : 'jour'), 'الحصص ' . ($o['cadence'] === 'weekly' ? 'أسبوعيا' : 'يوميا')), ['en' => (string)$o['parts_per_day']]],
            [$L('Days', 'Jours', 'الأيام'), ['en' => (string)$o['days']]],
            [$L('Starts', 'Début', 'البدء'), ['en' => $o['start_on']]],
            [$L('Unit price today', 'Prix unitaire aujourd’hui', 'سعر الوحدة اليوم'), ['en' => money((float)$o['unit_price'])]],
            [$L('Total of the schedule', 'Total du calendrier', 'مجموع الجدول'), ['en' => money((float)$o['total'])]],
            [$L('Status', 'Statut', 'الحالة'), $o['status'] === 'awaiting_payment' ? $L('Awaiting your D17 payment', 'En attente de votre paiement D17', 'بانتظار دفعتك عبر D17') : ($o['status'] === 'paid' ? $L('Paid (verified by an administrator)', 'Payé (vérifié par un administrateur)', 'مدفوع (تحقق منه مسؤول)') : $L('Pledged (demo: no money moved)', 'Engagé (démo : aucun argent versé)', 'التزام (تجريبي: لا أموال تحوّلت)'))],
        ];
        $isD17 = $o['payment_mode'] === 'd17' && Payments::enabled();
        $secs = [
            ['type' => 'hero', 'id' => 'done-hero', 'variant' => 'classic', 'gradient' => 'peak', 'icon' => 'badge-check', 'eyebrow' => $L('Pledge recorded', 'Engagement enregistré', 'سُجّل التزامك'),
                'title' => $L('Thank you, ' . $first . '. **Your pledge is recorded.**', 'Merci, ' . $first . '. **Votre engagement est enregistré.**', 'شكرا ' . $first . '. **سُجّل التزامك.**'),
                'lead' => $L('Keep this reference. In demo mode no payment is taken; the schedule below is what you committed to see.', 'Conservez cette référence. En mode démo, aucun paiement n’est prélevé ; le calendrier ci-dessous est celui que vous avez validé.', 'احتفظ بهذا المرجع. في الوضع التجريبي لا يُحصَّل أي دفع؛ والجدول أدناه هو ما وافقت عليه.'),
                'ctas' => [['label' => $L('Back home', 'Accueil', 'الرئيسية'), 'href' => '/', 'style' => 'focus'], ['label' => $L('Read the declared limits', 'Lire les limites déclarées', 'اقرأ الحدود المعلنة'), 'href' => '/trust/statement/', 'style' => 'ghost']]],
            ['type' => 'table', 'id' => 'done-receipt', 'tier' => 'i', 'focus' => 3, 'title' => $L('Your receipt', 'Votre reçu', 'إيصالك'), 'columns' => [$L('Item', 'Élément', 'البند'), $L('Value', 'Valeur', 'القيمة')], 'rows' => $rows],
        ];
        if ($key) $secs[] = ['type' => 'notice', 'id' => 'done-key', 'title' => $L('Your management key (shown once)', 'Votre clé de gestion (affichée une fois)', 'مفتاح الإدارة (يظهر مرة واحدة)'),
            'items' => [['en' => 'Reference `' . $ref . '` · key `' . $key . '`', 'fr' => 'Référence `' . $ref . '` · clé `' . $key . '`', 'ar' => 'المرجع `' . $ref . '` · المفتاح `' . $key . '`'], $L('Use both on the manage page to pause, resume or cancel.', 'Utilisez les deux sur la page de gestion pour suspendre, reprendre ou annuler.', 'استخدمهما معا في صفحة الإدارة للإيقاف أو الاستئناف أو الإلغاء.')], 'href' => '/subscription/manage/'];
        if ($isD17 && $o['status'] === 'awaiting_payment') {
            $i = Payments::instructions((float)$o['total']);
            array_splice($secs, 1, 0, [['type' => 'notice', 'id' => 'done-d17', 'title' => $L('Next: pay with D17', 'Ensuite : payez avec D17', 'التالي: ادفع عبر D17'),
                'items' => [
                    $L('Send ' . number_format((float)$o['total'], 2) . ' TND by D17 to ' . $i['gsm'] . ' (' . $i['name'] . ').', 'Envoyez ' . number_format((float)$o['total'], 2) . ' TND par D17 au ' . $i['gsm'] . ' (' . $i['name'] . ').', 'أرسل ' . number_format((float)$o['total'], 2) . ' دينار عبر D17 إلى ' . $i['gsm'] . ' (' . $i['name'] . ').'),
                    $L('Then open the payment page and enter your D17 number and the transaction reference. An administrator verifies it by hand.', 'Ouvrez ensuite la page de paiement et saisissez votre numéro D17 et la référence de transaction. Un administrateur la vérifie manuellement.', 'ثم افتح صفحة الدفع وأدخل رقم D17 ومرجع العملية. يتحقق منه مسؤول يدويا.'),
                ], 'href' => Payments::link($ref)]]);
        }
        $secs[] = ['type' => 'notice', 'id' => 'done-limits'];
        $page['sections'] = $secs;
        $page['title'] = $L('Pledge recorded', 'Engagement enregistré', 'سُجّل التزامك');
        return Pages::render($q, $page);
    }

    /* ---------- manage ---------- */
    private static function findSub(string $ref, string $key): ?array
    {
        if (!preg_match('/^ORD-[A-F0-9]{8}$/', $ref) || !preg_match('/^[a-f0-9]{32}$/', $key)) return null;
        return DB::one('SELECT s.*, o.days, o.start_on, o.unit_price, i.full_name FROM investor_subscriptions s JOIN investor_orders o ON o.ref = s.order_ref JOIN investors i ON i.id = s.investor_id WHERE s.order_ref = ? AND s.token = ?', [$ref, hash('sha256', $key)]);
    }

    public static function manage(Request $q): Response
    {
        $page = Content::page('subscription-manage') ?? ['title' => 'Manage', 'sections' => []];
        $L = [Pages::class, 'L'];
        $sub = null; $msg = null;
        if ($q->isPost()) {
            if (!Csrf::check($q)) $msg = $L('Session expired. Reload and try again.', 'Session expirée. Rechargez et réessayez.', 'انتهت الجلسة. أعد التحميل وحاول مجددا.');
            elseif (!\W5D\RateLimit::hit('manage:' . $q->ip(), 10, 600)) $msg = $L('Too many attempts. Try again later.', 'Trop de tentatives. Réessayez plus tard.', 'محاولات كثيرة. حاول لاحقا.');
            else {
                $ref = Forms::str($q->post, 'ref', 20); $key = Forms::str($q->post, 'key', 40);
                $sub = DB::available() ? self::findSub($ref, $key) : null;
                if (!$sub) $msg = $L('No subscription matches this reference and key.', 'Aucun abonnement ne correspond à cette référence et cette clé.', 'لا يوجد اشتراك يطابق هذا المرجع والمفتاح.');
            }
        }
        $secs = $page['sections'];
        $html = '';
        $page['sections'] = $secs;
        // management form + result are rendered by a dedicated view so POST actions can carry the key
        ViewEnv::share('canonicalPath', '/subscription/manage/');
        $body = view('pages.manage', ['sub' => $sub, 'msg' => $msg, 'ref' => $q->post['ref'] ?? '', 'sections' => $page['sections'], 'flash' => \W5D\Session::flash('manage')]);
        return Response::html($body);
    }

    public static function action(Request $q): Response
    {
        if (!Csrf::check($q)) return Response::redirect(url('/subscription/manage/'));
        $ref = Forms::str($q->post, 'ref', 20); $key = Forms::str($q->post, 'key', 40);
        $act = Forms::str($q->post, 'do', 10);
        $sub = DB::available() ? self::findSub($ref, $key) : null;
        if ($sub && in_array($act, ['pause', 'resume', 'cancel'], true) && $sub['status'] !== 'cancelled') {
            $status = ['pause' => 'paused', 'resume' => 'active', 'cancel' => 'cancelled'][$act];
            DB::q('UPDATE investor_subscriptions SET status = ?, paused_until = NULL WHERE id = ?', [$status, $sub['id']]);
            \W5D\Session::flash('manage', $status);
        }
        // re-show the manage view authenticated by the same credentials
        $req = $q; $req->method = 'POST';
        $req->post = ['_csrf' => $q->post['_csrf'] ?? '', 'ref' => $ref, 'key' => $key];
        return self::manage($req);
    }
}
