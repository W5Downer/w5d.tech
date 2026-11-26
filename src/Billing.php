<?php
declare(strict_types=1);

namespace W5D;

/**
 * Billing core. Everything that costs money is an invoice (table `invoices` + `invoice_lines`); every payment, whatever the method,
 * is a row of `payments` whose order_ref is the order reference (ORD-…) or the invoice number (INV-YYYY-NNNNN) and whose invoice_id points here.
 * Paying an invoice (Payments::settle -> markPaid) runs the fulfilment of its kind exactly once:
 *   order      the investor order is already marked paid by settle()
 *   qcm_pass   the QCM fee pass becomes usable
 *   cert       same, for assessment / certification / re-certification fees (cert_fees)
 *   plan       the plan subscription is activated or its period extended
 *   service    the milestone becomes "funded" (work may start / continue)
 *   placement  the placement commission is marked paid
 * No price lives in code: lines carry the amounts the owner configured (plans, fees, milestones, placement rate).
 */
final class Billing
{
    public const KINDS = ['order', 'qcm_pass', 'plan', 'cert', 'service', 'placement', 'manual'];

    public static function newRef(string $prefix): string { return $prefix . '-' . strtoupper(bin2hex(random_bytes(4))); }

    public static function currency(): string { return (string)Variables::get('money.currency', 'TND'); }

    /* ------------------------------------------------------------------ invoices */

    /**
     * @param array{kind:string,source_ref?:string,user_id?:int,email:string,name?:string,lang?:string,lines:list<array>,due_days?:int,note?:string,status?:string} $o
     *   lines: [description (string or {en,fr,ar}), qty, unit price]
     */
    public static function create(array $o): array
    {
        $total = 0.0; $lines = [];
        foreach ($o['lines'] as $l) {
            $qty = max(0.0, (float)($l[1] ?? 1)); $unit = round((float)($l[2] ?? 0), 2);
            $amt = round($qty * $unit, 2); $total += $amt;
            $lines[] = [is_array($l[0]) ? json_encode($l[0], JSON_UNESCAPED_UNICODE) : (string)$l[0], $qty, $unit, $amt];
        }
        $total = round($total, 2);
        if ($total <= 0) throw new \InvalidArgumentException('An invoice needs a positive total.');
        $email = mb_strtolower(trim((string)($o['email'] ?? '')));
        $uid = isset($o['user_id']) ? (int)$o['user_id'] : 0;
        $name = (string)($o['name'] ?? ''); $lang = (string)($o['lang'] ?? '');
        if ($email !== '' && ($u = DB::one('SELECT id, name, lang FROM users WHERE email = ?', [$email]))) {
            $uid = $uid ?: (int)$u['id']; $name = $name !== '' ? $name : (string)$u['name']; $lang = $lang !== '' ? $lang : (string)$u['lang'];
        }
        $days = max(0, (int)($o['due_days'] ?? Variables::get('billing.due_days', 14)));
        $pdo = DB::pdo(); $own = !$pdo->inTransaction();
        if ($own) $pdo->beginTransaction();
        try {
            $id = DB::insert('invoices', ['country_id' => 'tn', 'amount' => $total, 'status' => (string)($o['status'] ?? 'sent'), 'user_id' => $uid ?: null, 'email' => $email ?: null, 'name' => mb_substr($name, 0, 120) ?: null,
                'lang' => in_array($lang, Lang::SUPPORTED, true) ? $lang : null, 'kind' => (string)$o['kind'], 'source_ref' => $o['source_ref'] ?? null, 'currency' => self::currency(),
                'due_on' => date('Y-m-d', strtotime("+$days days")), 'issued_at' => date('Y-m-d H:i:s'), 'note' => isset($o['note']) ? mb_substr((string)$o['note'], 0, 300) : null]);
            DB::q('UPDATE invoices SET number = ? WHERE id = ?', [sprintf('INV-%s-%05d', date('Y'), $id), $id]);
            foreach ($lines as $i => [$d, $q, $u, $a]) DB::insert('invoice_lines', ['invoice_id' => $id, 'position' => $i, 'description' => $d, 'qty' => $q, 'unit_price' => $u, 'amount' => $a]);
            if ($own) $pdo->commit();
        } catch (\Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        return self::byId($id) ?? [];
    }

    public static function byId(int $id): ?array { return DB::one('SELECT * FROM invoices WHERE id = ?', [$id]); }
    public static function byNumber(string $n): ?array { return preg_match('/^INV-\d{4}-\d{5,}$/', $n) ? DB::one('SELECT * FROM invoices WHERE number = ?', [$n]) : null; }
    public static function lines(int $id): array { return DB::all('SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY position, id', [$id]); }

    /** A line description in the reader's language (stored as plain text or as a JSON {en,fr,ar}). */
    public static function desc(string $s): string
    {
        if ($s !== '' && $s[0] === '{' && is_array($j = json_decode($s, true))) return t($j);
        return $s;
    }

    public static function forSource(string $kind, string $ref): ?array { return DB::one('SELECT * FROM invoices WHERE kind = ? AND source_ref = ? ORDER BY id DESC', [$kind, $ref]); }

    public static function forUser(int $uid, string $email, int $limit = 100): array
    {
        return DB::all("SELECT * FROM invoices WHERE status != 'draft' AND (user_id = ? OR (email IS NOT NULL AND email = ?)) ORDER BY id DESC LIMIT " . (int)$limit, [$uid, mb_strtolower($email)]);
    }

    public static function mine(array $inv, int $uid, string $email): bool
    {
        return ((int)($inv['user_id'] ?? 0) === $uid && $uid > 0) || ($email !== '' && mb_strtolower((string)($inv['email'] ?? '')) === mb_strtolower($email));
    }

    public static function link(array $inv): string { return Payments::link((string)$inv['number']); }
    public static function absLink(array $inv): string { return Payments::absLink((string)$inv['number']); }
    public static function viewLink(string $number): string { return url('/invoice/' . $number . '/') . '?s=' . Secret::sign('inv:' . $number); }
    public static function absViewLink(string $number): string { return Env::baseUrl() . self::viewLink($number); }
    public static function viewSig(string $number, string $sig): bool { return Secret::verify('inv:' . $number, $sig); }

    public static function lastPayment(int $invoiceId): ?array { return DB::one('SELECT * FROM payments WHERE invoice_id = ? ORDER BY id DESC', [$invoiceId]); }

    /** Pick (or reuse) the payment of this invoice for a method. Returns the payment row, or null when the method cannot be used. */
    public static function choose(array $inv, string $method): ?array
    {
        if (!in_array($inv['status'], ['sent', 'draft'], true) || !Gateways::enabled($method)) return null;
        $p = DB::one("SELECT * FROM payments WHERE invoice_id = ? AND method = ? AND status IN ('pending','rejected') ORDER BY id DESC", [$inv['id'], $method]);
        if ($p) return $p;
        // an order invoice keeps the order reference so settle() also moves the investor order
        $ref = Payments::create($inv['kind'] === 'order' ? (string)$inv['source_ref'] : (string)$inv['number'], 0, (float)$inv['amount'], $method, (int)$inv['id']);
        return DB::one('SELECT * FROM payments WHERE ref = ?', [$ref]);
    }

    /** Create the invoice for a new order (called by Checkout::core). */
    public static function forOrder(string $orderRef, array $investor, float $total, string|array $desc): array
    {
        return self::create(['kind' => 'order', 'source_ref' => $orderRef, 'email' => (string)$investor['email'], 'name' => (string)$investor['full_name'], 'lang' => (string)($investor['lang'] ?? ''), 'lines' => [[$desc, 1, $total]], 'due_days' => 7]);
    }

    /** Pay hooks ----------------------------------------------------------- */

    /** A manual payment reference was submitted: the linked fee pass shows "waiting for review". */
    public static function onSubmitted(array $pay): void
    {
        if (empty($pay['invoice_id'])) return;
        $inv = self::byId((int)$pay['invoice_id']);
        if ($inv && in_array($inv['kind'], ['qcm_pass', 'cert'], true) && str_starts_with((string)$inv['source_ref'], 'QP-')) DB::q("UPDATE qcm_passes SET status = 'pending' WHERE ref = ? AND status IN ('awaiting','rejected')", [$inv['source_ref']]);
    }

    public static function onRejected(array $pay): void
    {
        if (empty($pay['invoice_id'])) return;
        $inv = self::byId((int)$pay['invoice_id']);
        if ($inv && in_array($inv['kind'], ['qcm_pass', 'cert'], true) && str_starts_with((string)$inv['source_ref'], 'QP-')) DB::q("UPDATE qcm_passes SET status = 'awaiting' WHERE ref = ? AND status = 'pending'", [$inv['source_ref']]);
    }

    /** Idempotent: only the call that flips the invoice to "paid" runs the fulfilment. */
    public static function markPaid(int $id, string $by, ?string $payRef = null, ?string $method = null): bool
    {
        $st = DB::q("UPDATE invoices SET status = 'paid', paid_at = CURRENT_TIMESTAMP, payment_ref = ?, method = ? WHERE id = ? AND status IN ('draft','sent')", [$payRef, $method, $id]);
        if ($st->rowCount() !== 1) return false;
        $inv = self::byId($id);
        if (!$inv) return false;
        self::audit($by, 'invoice.paid', (string)$inv['number'], (string)$inv['kind'] . ' ' . number_format((float)$inv['amount'], 2) . ' ' . $inv['currency'] . ($method ? ' via ' . $method : ''));
        self::fulfil($inv, $by);
        if (!empty($inv['user_id'])) Account::notify((int)$inv['user_id'], tr3('Invoice paid', 'Facture payée', 'تم دفع الفاتورة'), $inv['number'], '/account/invoices/');
        return true;
    }

    public static function void(int $id, string $by, string $reason): bool
    {
        $st = DB::q("UPDATE invoices SET status = 'void', note = ? WHERE id = ? AND status IN ('draft','sent')", [mb_substr($reason, 0, 300), $id]);
        if ($st->rowCount() !== 1) return false;
        $inv = self::byId($id);
        self::audit($by, 'invoice.void', (string)($inv['number'] ?? $id), $reason);
        // the thing it billed goes back to "not invoiced"
        if ($inv) {
            if ($inv['kind'] === 'service' && preg_match('/^MS-(\d+)$/', (string)$inv['source_ref'], $m)) DB::q("UPDATE milestones SET status = 'planned', invoice_id = NULL WHERE id = ? AND status = 'invoiced'", [(int)$m[1]]);
            if ($inv['kind'] === 'placement') DB::q("UPDATE placements SET status = 'proposed', invoice_id = NULL WHERE ref = ? AND status = 'invoiced'", [$inv['source_ref']]);
        }
        DB::q("UPDATE payments SET status = 'rejected', note = 'invoice void' WHERE invoice_id = ? AND status = 'pending'", [$id]);
        return true;
    }

    private static function audit(string $by, string $action, string $subject, string $detail = ''): void
    {
        try { DB::insert('audit_log', ['country_id' => 'tn', 'actor' => $by, 'action' => $action, 'subject' => $subject, 'detail' => mb_substr($detail, 0, 1000)]); } catch (\Throwable) {}
    }

    private static function fulfil(array $inv, string $by): void
    {
        $ref = (string)($inv['source_ref'] ?? '');
        try {
            switch ($inv['kind']) {
                case 'qcm_pass':
                case 'cert':
                    if (str_starts_with($ref, 'QP-')) {
                        DB::q("UPDATE qcm_passes SET status = 'verified', verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE ref = ? AND status IN ('awaiting','pending','rejected')", [$by, $ref]);
                        if (!empty($inv['user_id'])) Account::notify((int)$inv['user_id'], tr3('Fee received', 'Frais reçus', 'تم استلام الرسوم'), $ref, '/account/qcm/');
                    } elseif (!empty($inv['user_id'])) {
                        Account::notify((int)$inv['user_id'], tr3('Fee received', 'Frais reçus', 'تم استلام الرسوم'), tr3('The desk will contact you about the next step.', 'Le bureau vous contactera pour la suite.', 'سيتواصل معك المكتب بشأن الخطوة التالية.'), '/account/invoices/');
                        Mail::staff('Certification fee received', ['invoice' => $inv['number'], 'email' => (string)$inv['email'], 'amount' => number_format((float)$inv['amount'], 2) . ' ' . $inv['currency']]);
                    }
                    break;
                case 'plan':
                    $s = DB::one('SELECT * FROM plan_subscriptions WHERE ref = ?', [$ref]);
                    if ($s) {
                        $base = (!empty($s['current_period_end']) && $s['current_period_end'] >= date('Y-m-d')) ? (string)$s['current_period_end'] : date('Y-m-d');
                        $end = date('Y-m-d', strtotime($base . ($s['period'] === 'year' ? ' +1 year' : ' +1 month')));
                        DB::q("UPDATE plan_subscriptions SET status = 'active', started_on = COALESCE(started_on, ?), current_period_end = ? WHERE id = ?", [date('Y-m-d'), $end, $s['id']]);
                    }
                    break;
                case 'service':
                    if (preg_match('/^MS-(\d+)$/', $ref, $m)) {
                        DB::q("UPDATE milestones SET status = 'funded' WHERE id = ? AND status = 'invoiced'", [(int)$m[1]]);
                        Mail::staff('Milestone funded', ['milestone' => $ref, 'invoice' => $inv['number'], 'client' => (string)$inv['email']]);
                    }
                    break;
                case 'placement':
                    DB::q("UPDATE placements SET status = 'paid' WHERE ref = ? AND status IN ('proposed','invoiced')", [$ref]);
                    break;
            }
        } catch (\Throwable $e) { self::audit('system', 'invoice.fulfil_failed', (string)$inv['number'], $e->getMessage()); }
    }

    /** E-mail the invoice with its pay link and a read-only view link. */
    public static function send(array $inv, ?string $why = null): bool
    {
        if (empty($inv['email'])) return false;
        $first = DB::one('SELECT description FROM invoice_lines WHERE invoice_id = ? ORDER BY position, id LIMIT 1', [$inv['id']]);
        return Mail::send((string)$inv['email'], 'invoice_issued', ['name' => $inv['name'], 'ref' => $inv['number'], 'amount' => $inv['amount'], 'currency' => $inv['currency'], 'due' => $inv['due_on'], 'title' => $first ? self::desc((string)$first['description']) : '',
            'pay_url' => self::absLink($inv), 'view_url' => self::absViewLink((string)$inv['number']), 'why' => $why], ($inv['lang'] ?? null) ?: null);
    }

    public static function stats(): array
    {
        $n = fn(string $sql) => (float)DB::val($sql);
        return ['open_n' => (int)$n("SELECT COUNT(*) FROM invoices WHERE status = 'sent'"), 'open_sum' => $n("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status = 'sent'"),
            'paid_n' => (int)$n("SELECT COUNT(*) FROM invoices WHERE status = 'paid' AND paid_at >= date('now','start of month')"), 'paid_sum' => $n("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status = 'paid' AND paid_at >= date('now','start of month')"),
            'subs' => (int)$n("SELECT COUNT(*) FROM plan_subscriptions WHERE status = 'active'")];
    }

    /* ------------------------------------------------------------------ 2. subscriptions */

    public static function plans(bool $onlyActive = true): array
    {
        return DB::all('SELECT * FROM plans ' . ($onlyActive ? 'WHERE active = 1 ' : '') . 'ORDER BY sort, id');
    }

    public static function planName(array $p): string { return t(['en' => $p['name_en'], 'fr' => $p['name_fr'], 'ar' => $p['name_ar']]); }

    /** Price for one period: base + extra seats. year = the yearly price when the owner set one. */
    public static function planPrice(array $p, string $period, int $seats): float
    {
        $year = $period === 'year';
        $base = (float)($year ? $p['price_year'] : $p['price']);
        $extra = max(0, $seats - (int)$p['seats_included']) * (float)$p['seat_price'] * ($year ? 12 : 1);
        return $base > 0 ? round($base + $extra, 2) : 0.0;
    }

    public static function sellable(array $p, string $period): bool { return (int)$p['active'] === 1 && !(int)$p['quote_only'] && self::planPrice($p, $period, (int)$p['seats_included']) > 0; }

    /** @return array{0:?array,1:?string} [invoice, error] */
    public static function subscribe(array $u, array $plan, string $period, int $seats): array
    {
        $period = $period === 'year' ? 'year' : 'month';
        $seats = max((int)$plan['seats_included'], min(500, $seats));
        if (!self::sellable($plan, $period)) return [null, 'This plan cannot be bought online right now.'];
        $live = DB::one("SELECT s.*, p.name_en FROM plan_subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status = 'active' AND (s.current_period_end IS NULL OR s.current_period_end >= date('now'))", [$u['id']]);
        if ($live) return [null, 'You already have an active plan. Cancel it at the end of the period before choosing another.'];
        $amount = self::planPrice($plan, $period, $seats);
        $pend = DB::one("SELECT s.* FROM plan_subscriptions s WHERE s.user_id = ? AND s.status = 'pending' AND s.plan_id = ? AND s.period = ? AND s.seats = ? ORDER BY id DESC", [$u['id'], $plan['id'], $period, $seats]);
        if ($pend && ($inv = self::forSource('plan', (string)$pend['ref'])) && $inv['status'] === 'sent') return [$inv, null];
        $ref = self::newRef('PLN');
        DB::insert('plan_subscriptions', ['country_id' => 'tn', 'ref' => $ref, 'user_id' => (int)$u['id'], 'plan_id' => (int)$plan['id'], 'seats' => $seats, 'period' => $period, 'amount' => $amount, 'status' => 'pending']);
        return [self::planInvoice($ref, $u, $plan, $period, $seats, $amount), null];
    }

    private static function planInvoice(string $ref, array $u, array $plan, string $period, int $seats, float $amount): array
    {
        $per = $period === 'year' ? ['year', 'an', 'سنة'] : ['month', 'mois', 'شهر'];
        $nm = ['en' => 'Plan ' . $plan['name_en'] . ' · 1 ' . $per[0] . ' · ' . $seats . ' seat(s)', 'fr' => 'Formule ' . $plan['name_fr'] . ' · 1 ' . $per[1] . ' · ' . $seats . ' siège(s)', 'ar' => 'باقة ' . $plan['name_ar'] . ' · ' . $per[2] . ' واحدة · ' . $seats . ' مقعد'];
        return self::create(['kind' => 'plan', 'source_ref' => $ref, 'user_id' => (int)$u['id'], 'email' => (string)$u['email'], 'name' => (string)$u['name'], 'lang' => (string)($u['lang'] ?? ''), 'lines' => [[$nm, 1, $amount]], 'due_days' => 7]);
    }

    public static function cancelAtEnd(int $uid, string $ref): bool
    {
        return DB::q("UPDATE plan_subscriptions SET cancel_at_period_end = 1 WHERE ref = ? AND user_id = ? AND status = 'active'", [$ref, $uid])->rowCount() === 1;
    }

    public static function resume(int $uid, string $ref): bool
    {
        return DB::q("UPDATE plan_subscriptions SET cancel_at_period_end = 0 WHERE ref = ? AND user_id = ? AND status = 'active'", [$ref, $uid])->rowCount() === 1;
    }

    public static function seatAdd(array $sub, string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Enter a valid e-mail address.';
        if (str_ends_with($email, '@' . StaffAuth::domain())) return 'Team addresses cannot take a seat.';
        if ((int)DB::val('SELECT COUNT(*) FROM plan_seats WHERE subscription_id = ?', [$sub['id']]) >= (int)$sub['seats'] - 1) return 'All seats are used. The owner of the plan takes the first seat.';
        try { DB::insert('plan_seats', ['subscription_id' => (int)$sub['id'], 'email' => $email]); } catch (\Throwable) { return 'This address already has a seat.'; }
        return null;
    }

    public static function seatDel(array $sub, string $email): void { DB::q('DELETE FROM plan_seats WHERE subscription_id = ? AND email = ?', [$sub['id'], mb_strtolower($email)]); }

    /** What this person may use right now through a plan (own subscription or a seat on someone's). */
    public static function entitlements(int $uid, string $email): array
    {
        $s = DB::one("SELECT s.*, p.code, p.name_en, p.name_fr, p.name_ar, p.pipeline_access, p.weekly_reports FROM plan_subscriptions s JOIN plans p ON p.id = s.plan_id
            WHERE s.status = 'active' AND (s.current_period_end IS NULL OR s.current_period_end >= date('now')) AND (s.user_id = ? OR s.id IN (SELECT subscription_id FROM plan_seats WHERE email = ?))
            ORDER BY p.pipeline_access DESC, p.weekly_reports DESC, s.id DESC LIMIT 1", [$uid, mb_strtolower($email)]);
        return ['sub' => $s, 'pipeline' => $s && (int)$s['pipeline_access'] === 1, 'weekly' => $s && (int)$s['weekly_reports'] === 1, 'owner' => $s && (int)$s['user_id'] === $uid];
    }

    /** Anonymous counts only: certified people per service and level. No names, no contact details. */
    public static function pipeline(): array
    {
        return DB::all("SELECT service_slug, level, COUNT(DISTINCT user_id) AS n FROM certificates WHERE status = 'valid' AND (expires_at IS NULL OR expires_at > datetime('now')) GROUP BY service_slug, level ORDER BY service_slug, level");
    }

    /** Weekly review summary for plan holders: approved/total weekly reports of the last 4 ISO weeks, aggregated, no personal data. */
    public static function weekly(): array
    {
        try {
            return DB::all("SELECT week_id, service_slug, COUNT(*) AS reports, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved, COALESCE(SUM(units),0) AS units FROM weekly_reports GROUP BY week_id, service_slug ORDER BY week_id DESC, service_slug LIMIT 40");
        } catch (\Throwable) { return []; }
    }

    /** Cron (bin/billing.php): renewal invoices, lapsed and cancelled plans. Safe to run often. */
    public static function runRenewals(): array
    {
        $out = ['invoiced' => 0, 'expired' => 0, 'cancelled' => 0];
        $ahead = max(0, (int)Variables::get('billing.renew_days_before', 7)); $grace = max(0, (int)Variables::get('billing.grace_days', 7));
        foreach (DB::all("SELECT s.*, u.email, u.name, u.lang, u.id AS uid FROM plan_subscriptions s JOIN users u ON u.id = s.user_id WHERE s.status = 'active' AND s.current_period_end IS NOT NULL") as $s) {
            $end = (string)$s['current_period_end'];
            if ((int)$s['cancel_at_period_end'] === 1) {
                if ($end < date('Y-m-d')) { DB::q("UPDATE plan_subscriptions SET status = 'cancelled' WHERE id = ?", [$s['id']]); $out['cancelled']++; }
                continue;
            }
            $open = DB::one("SELECT id FROM invoices WHERE kind = 'plan' AND source_ref = ? AND status = 'sent'", [$s['ref']]);
            if (!$open && $end <= date('Y-m-d', strtotime("+$ahead days"))) {
                $plan = DB::one('SELECT * FROM plans WHERE id = ?', [$s['plan_id']]);
                if ($plan && (float)$s['amount'] > 0) {
                    $inv = self::planInvoice((string)$s['ref'], ['id' => $s['uid'], 'email' => $s['email'], 'name' => $s['name'], 'lang' => $s['lang']], $plan, (string)$s['period'], (int)$s['seats'], (float)$s['amount']);
                    self::send($inv, 'renewal'); $out['invoiced']++;
                }
            } elseif ($open && $end < date('Y-m-d', strtotime("-$grace days"))) {
                DB::q("UPDATE plan_subscriptions SET status = 'expired' WHERE id = ?", [$s['id']]);
                self::void((int)$open['id'], 'cron', 'plan lapsed: renewal not paid');
                $out['expired']++;
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------ 3. certification & assessment fees */

    public static function certFees(bool $onlyActive = true): array
    {
        return DB::all('SELECT f.*, r.name AS rule_name FROM cert_fees f LEFT JOIN qcm_rules r ON r.id = f.rule_id ' . ($onlyActive ? 'WHERE f.active = 1 AND f.amount > 0 ' : '') . 'ORDER BY f.sort, f.id');
    }

    public static function kindLabel(string $k): string
    {
        return match ($k) { 'assessment' => tr3('Assessment', 'Évaluation', 'تقييم'), 'recertification' => tr3('Re-certification', 'Recertification', 'إعادة اعتماد'), default => tr3('Certification', 'Certification', 'اعتماد') };
    }

    /** First active re-certification fee for this level (a fee set for "any level" matches too). */
    public static function recertFee(int $level): ?array
    {
        return DB::one("SELECT * FROM cert_fees WHERE kind = 'recertification' AND active = 1 AND amount > 0 AND (level = ? OR level = 0) ORDER BY (level = ?) DESC, id LIMIT 1", [$level, $level]);
    }

    /** @return array{0:?array,1:?string} [invoice, error] */
    public static function buyCertFee(array $u, array $fee): array
    {
        if (!(int)$fee['active'] || (float)$fee['amount'] <= 0) return [null, 'This fee cannot be paid online right now.'];
        $tag = 'cf:' . $fee['id'];
        $open = DB::one("SELECT * FROM invoices WHERE kind = 'cert' AND status = 'sent' AND user_id = ? AND note = ? ORDER BY id DESC", [$u['id'], $tag]);
        if ($open) return [$open, null];
        $ref = 'CF-' . strtoupper(bin2hex(random_bytes(4)));
        if ((int)$fee['rule_id'] > 0) {   // paying grants one pass for that QCM round
            $ref = 'QP-' . strtoupper(bin2hex(random_bytes(4)));
            DB::insert('qcm_passes', ['country_id' => 'tn', 'ref' => $ref, 'user_id' => (int)$u['id'], 'rule_id' => (int)$fee['rule_id'], 'amount' => round((float)$fee['amount'], 2), 'currency' => self::currency(), 'status' => 'awaiting']);
        }
        $lv = (int)$fee['level'] > 0 ? ' · L' . (int)$fee['level'] : '';
        return [self::create(['kind' => 'cert', 'source_ref' => $ref, 'user_id' => (int)$u['id'], 'email' => (string)$u['email'], 'name' => (string)$u['name'], 'lang' => (string)($u['lang'] ?? ''), 'note' => $tag,
            'lines' => [[['en' => (string)$fee['name'] . $lv, 'fr' => (string)$fee['name'] . $lv, 'ar' => (string)$fee['name'] . $lv], 1, (float)$fee['amount']]], 'due_days' => 7]), null];
    }

    /** QCM round fee (qcm_rules.fee): a pass + its invoice. */
    public static function invoiceForPass(array $pass, array $u, string $ruleName): array
    {
        $inv = self::forSource('qcm_pass', (string)$pass['ref']);
        if ($inv && $inv['status'] === 'sent') return $inv;
        return self::create(['kind' => 'qcm_pass', 'source_ref' => (string)$pass['ref'], 'user_id' => (int)$u['id'], 'email' => (string)$u['email'], 'name' => (string)$u['name'], 'lang' => (string)($u['lang'] ?? ''),
            'lines' => [[['en' => 'QCM round fee · ' . $ruleName, 'fr' => 'Frais de session QCM · ' . $ruleName, 'ar' => 'رسوم جولة الاختبار · ' . $ruleName], 1, (float)$pass['amount']]], 'due_days' => 7]);
    }

    /* ------------------------------------------------------------------ 1. service fees (milestones) */

    public static function milestoneRef(int $id): string { return 'MS-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT); }

    /** @return array{0:?array,1:?string} */
    public static function invoiceMilestone(int $msId): array
    {
        $m = DB::one('SELECT m.*, e.title AS etitle, e.ref AS eref, e.client_email, e.client_name, e.status AS estatus FROM milestones m JOIN service_engagements e ON e.id = m.engagement_id WHERE m.id = ?', [$msId]);
        if (!$m) return [null, 'Milestone not found.'];
        if ($m['status'] !== 'planned') return [null, 'This milestone is already invoiced.'];
        if (!in_array($m['estatus'], ['draft', 'active'], true)) return [null, 'The engagement is closed.'];
        if ((float)$m['amount'] <= 0) return [null, 'Set an amount for this milestone first.'];
        $inv = self::create(['kind' => 'service', 'source_ref' => self::milestoneRef($msId), 'email' => (string)$m['client_email'], 'name' => (string)$m['client_name'],
            'lines' => [[['en' => $m['etitle'] . ' · ' . $m['title'], 'fr' => $m['etitle'] . ' · ' . $m['title'], 'ar' => $m['etitle'] . ' · ' . $m['title']], 1, (float)$m['amount']]], 'note' => $m['eref'], 'due_days' => (int)Variables::get('billing.due_days', 14)]);
        DB::q("UPDATE milestones SET status = 'invoiced', invoice_id = ? WHERE id = ?", [$inv['id'], $msId]);
        DB::q("UPDATE service_engagements SET status = 'active', user_id = COALESCE(user_id, ?) WHERE id = ? AND status = 'draft'", [$inv['user_id'], $m['engagement_id']]);
        return [$inv, null];
    }

    /* ------------------------------------------------------------------ 4. placement commission */

    public static function placementRate(array $p): float
    {
        return $p['rate_pct'] !== null && $p['rate_pct'] !== '' ? (float)$p['rate_pct'] : (float)Variables::get('billing.placement_rate_pct', 0.0);
    }

    public static function placementCommission(array $p): float { return round((float)$p['contract_value'] * self::placementRate($p) / 100, 2); }

    /** @return array{0:?array,1:?string} */
    public static function invoicePlacement(int $id): array
    {
        $p = DB::one('SELECT p.*, c.name AS client_name, c.email AS client_email FROM placements p JOIN clients c ON c.id = p.client_id WHERE p.id = ?', [$id]);
        if (!$p) return [null, 'Placement not found.'];
        if ($p['status'] !== 'proposed') return [null, 'This placement is already invoiced or closed.'];
        if (!filter_var((string)$p['client_email'], FILTER_VALIDATE_EMAIL)) return [null, 'The client has no valid e-mail address: add it under Clients first.'];
        $rate = self::placementRate($p); $fee = self::placementCommission($p);
        if ($fee <= 0) return [null, 'The commission is 0: set a rate on the placement or the default rate in Variables.'];
        $label = ($p['kind'] === 'enterprise_talent' ? 'Placement commission (enterprise talent)' : 'Placement commission (supervised work)') . ' · ' . $p['title'] . ' · ' . rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
        $inv = self::create(['kind' => 'placement', 'source_ref' => (string)$p['ref'], 'email' => (string)$p['client_email'], 'name' => (string)$p['client_name'], 'lines' => [[$label, 1, $fee]], 'due_days' => (int)Variables::get('billing.due_days', 14)]);
        DB::q("UPDATE placements SET status = 'invoiced', invoice_id = ?, rate_snapshot = ?, commission = ? WHERE id = ?", [$inv['id'], $rate, $fee, $id]);
        return [$inv, null];
    }
}
