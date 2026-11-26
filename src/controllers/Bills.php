<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Account, ACL, Auth, Billing, Certificate, Csrf, DB, Env, Gateways, Mail, Payments, Request, Response, Secret, Variables, ViewEnv};

/**
 * Bills: invoice view/print, the buyer-side actions of the plans and fees, and the owner/team actions of the four revenue streams.
 * Money only moves through Billing + Payments; this class never settles anything itself except an owner-recorded manual payment (audited).
 */
final class Bills
{
    private static function m(string $en, string $fr, string $ar): array { return Accounts::m($en, $fr, $ar); }
    private static function who(): string { return (string)(Auth::user()['name'] ?? 'staff'); }
    private static function back(string $to, string $kind, array $msg): Response { Accounts::flash($kind, $msg); return Response::redirect(url($to)); }

    /* ======================= public: invoice view / print ======================= */
    public static function view(Request $q): Response
    {
        $n = (string)($q->params['number'] ?? '');
        $inv = DB::available() ? Billing::byNumber($n) : null;
        if (!$inv || $inv['status'] === 'draft') return Pages::notFound($q);
        $ok = Billing::viewSig($n, (string)$q->input('s', ''));
        if (!$ok && Account::check()) { $u = Account::row(); $ok = $u && Billing::mine($inv, (int)$u['id'], (string)$u['email']); }
        if (!$ok && Auth::check()) $ok = Auth::can('billing') || ($inv['email'] && mb_strtolower((string)$inv['email']) === mb_strtolower((string)(Auth::user()['email'] ?? '')));
        if (!$ok) return Pages::notFound($q);
        ViewEnv::share('canonicalPath', '/invoice/' . $n . '/');
        $pay = Billing::lastPayment((int)$inv['id']);
        return Response::html(view('account.invoice', ['title' => tr3('Invoice', 'Facture', 'فاتورة') . ' ' . $n, 'inv' => $inv, 'lines' => Billing::lines((int)$inv['id']), 'pay' => $pay, 'payUrl' => $inv['status'] === 'sent' ? Billing::link($inv) : null,
            'seller' => trim((string)Env::get('MAIL_FOOTER_ADDRESS', '')), 'robots' => 'noindex,nofollow']))->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store']);
    }

    /* ======================= account: panel data ======================= */
    public static function panelData(string $panel, array $u): array
    {
        $uid = (int)$u['id']; $email = (string)$u['email'];
        if (!DB::available()) return [];
        if ($panel === 'invoices') {
            $eng = DB::all('SELECT * FROM service_engagements WHERE client_email = ? OR user_id = ? ORDER BY id DESC LIMIT 20', [mb_strtolower($email), $uid]);
            foreach ($eng as &$e) $e['ms'] = DB::all("SELECT m.*, i.number AS inv_number FROM milestones m LEFT JOIN invoices i ON i.id = m.invoice_id WHERE m.engagement_id = ? AND m.status != 'cancelled' ORDER BY m.id", [$e['id']]);
            unset($e);
            return ['bill_rows' => Billing::forUser($uid, $email), 'bill_eng' => $eng];
        }
        if ($panel === 'plan') {
            $ent = Billing::entitlements($uid, $email);
            $own = DB::all("SELECT s.*, p.name_en, p.name_fr, p.name_ar, p.code FROM plan_subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status IN ('active','pending') ORDER BY s.id DESC LIMIT 5", [$uid]);
            foreach ($own as &$s) $s['seat_rows'] = DB::all('SELECT email FROM plan_seats WHERE subscription_id = ? ORDER BY id', [$s['id']]);
            unset($s);
            return ['pl_plans' => Billing::plans(), 'pl_ent' => $ent, 'pl_own' => $own, 'pl_pipeline' => $ent['pipeline'] ? Billing::pipeline() : [], 'pl_weekly' => $ent['weekly'] ? Billing::weekly() : []];
        }
        return [];
    }

    /* ======================= account: plan + fee actions ======================= */
    public static function planBuy(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        if ($r = Accounts::guard($q, 'plan-buy', '/account/plan/', 12, 3600)) return $r;
        $u = Account::row();
        $plan = DB::one('SELECT * FROM plans WHERE id = ? AND active = 1', [(int)($q->post['plan'] ?? 0)]);
        if (!$plan || !$u) return self::back('/account/plan/', 'error', self::m('This plan is not available.', 'Cette formule n’est pas disponible.', 'هذه الباقة غير متاحة.'));
        [$inv, $err] = Billing::subscribe($u, $plan, (string)($q->post['period'] ?? 'month'), (int)($q->post['seats'] ?? 1));
        if (!$inv) return self::back('/account/plan/', 'error', self::m((string)$err, (string)$err, (string)$err));
        return Response::redirect(Billing::link($inv));
    }

    public static function planManage(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        if ($r = Accounts::guard($q, 'plan-manage', '/account/plan/', 30, 3600)) return $r;
        $u = Account::row(); $ref = (string)($q->post['ref'] ?? ''); $do = (string)($q->post['do'] ?? '');
        $sub = $u ? DB::one("SELECT * FROM plan_subscriptions WHERE ref = ? AND user_id = ? AND status = 'active'", [$ref, $u['id']]) : null;
        if (!$sub) return self::back('/account/plan/', 'error', self::m('Subscription not found.', 'Abonnement introuvable.', 'الاشتراك غير موجود.'));
        $msg = self::m('Done.', 'Fait.', 'تم.');
        if ($do === 'cancel') { Billing::cancelAtEnd((int)$u['id'], $ref); $msg = self::m('The plan stays active until the end of the paid period, then stops. No renewal invoice will be sent.', 'La formule reste active jusqu’à la fin de la période payée, puis s’arrête. Aucune facture de renouvellement ne sera envoyée.', 'تبقى الباقة فعالة حتى نهاية الفترة المدفوعة ثم تتوقف. لن تُرسل فاتورة تجديد.'); }
        elseif ($do === 'resume') Billing::resume((int)$u['id'], $ref);
        elseif ($do === 'seat-add') { $e = Billing::seatAdd($sub, (string)($q->post['email'] ?? '')); if ($e) return self::back('/account/plan/', 'error', self::m($e, $e, $e)); }
        elseif ($do === 'seat-del') Billing::seatDel($sub, (string)($q->post['email'] ?? ''));
        return self::back('/account/plan/', 'ok', $msg);
    }

    public static function feeBuy(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        if ($r = Accounts::guard($q, 'fee-buy', '/account/certificates/', 12, 3600)) return $r;
        $u = Account::row();
        $fee = DB::one('SELECT * FROM cert_fees WHERE id = ?', [(int)($q->post['fee'] ?? 0)]);
        if (!$fee || !$u) return self::back('/account/certificates/', 'error', self::m('This fee is not available.', 'Ces frais ne sont pas disponibles.', 'هذه الرسوم غير متاحة.'));
        [$inv, $err] = Billing::buyCertFee($u, $fee);
        if (!$inv) return self::back('/account/certificates/', 'error', self::m((string)$err, (string)$err, (string)$err));
        return Response::redirect(Billing::link($inv));
    }

    /** Pay link of a bill I own (account page buttons): a signed redirect, so the link is never guessable. */
    public static function payMine(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        $inv = Billing::byNumber((string)($q->params['number'] ?? ''));
        $u = Account::row();
        if (!$inv || !$u || !Billing::mine($inv, (int)$u['id'], (string)$u['email']) || $inv['status'] !== 'sent') return Pages::notFound($q);
        return Response::redirect(Billing::link($inv));
    }

    /* ======================= public plans page ======================= */
    public static function plans(Request $q): Response
    {
        ViewEnv::share('canonicalPath', '/plans/');
        $plans = DB::available() ? Billing::plans() : [];
        $ent = Account::check() && ($u = Account::row()) ? Billing::entitlements((int)$u['id'], (string)$u['email']) : null;
        return Response::html(view('pages.plans', ['title' => tr3('Plans', 'Formules', 'الباقات'), 'plans' => $plans, 'ent' => $ent, 'fees' => DB::available() ? Billing::certFees() : []]));
    }

    /* ======================= owner / team portal ======================= */
    public static function vInvoices(): array
    {
        $rows = DB::all("SELECT i.id, i.number, i.kind, i.source_ref, i.name, i.email, i.amount, i.currency, i.status, i.due_on, i.paid_at, i.method FROM invoices i WHERE i.number IS NOT NULL ORDER BY (i.status = 'sent') DESC, i.id DESC LIMIT 300");
        return ['kind' => 'bill-invoices', 'rows' => $rows, 'stats' => Billing::stats(), 'can' => Auth::can('billing.update')];
    }

    public static function vMine(): array
    {
        $u = Auth::user() ?? [];
        return ['kind' => 'bill-mine', 'rows' => Billing::forUser((int)($u['id'] ?? 0), (string)($u['email'] ?? ''))];
    }

    public static function vPlanSubs(): array
    {
        return ['kind' => 'bill-subs', 'stats' => Billing::stats(), 'can' => Auth::can('billing.update'),
            'rows' => DB::all("SELECT s.id, s.ref, u.name, u.email, p.name_en AS plan, s.seats, s.period, s.amount, s.status, s.started_on, s.current_period_end AS ends, s.cancel_at_period_end AS cancelling FROM plan_subscriptions s JOIN users u ON u.id = s.user_id JOIN plans p ON p.id = s.plan_id ORDER BY s.id DESC LIMIT 300")];
    }

    public static function vMilestones(): array
    {
        return ['kind' => 'bill-ms', 'can' => Auth::can('billing.update'), 'review' => Auth::can('billing.review'), 'me' => self::who(), 'owner' => Auth::role() === 'owner', 'hours' => (int)Variables::get('billing.milestone_review_hours', 72),
            'rows' => DB::all("SELECT m.*, e.ref AS eref, e.title AS etitle, e.client_email, i.number AS inv_number, i.status AS inv_status FROM milestones m JOIN service_engagements e ON e.id = m.engagement_id LEFT JOIN invoices i ON i.id = m.invoice_id ORDER BY (m.status IN ('delivered','funded','planned')) DESC, m.id DESC LIMIT 300")];
    }

    public static function vPlacements(): array
    {
        $rows = DB::all("SELECT p.*, c.name AS client, c.email AS client_email, i.number AS inv_number FROM placements p JOIN clients c ON c.id = p.client_id LEFT JOIN invoices i ON i.id = p.invoice_id ORDER BY p.id DESC LIMIT 300");
        foreach ($rows as &$r) { $r['rate'] = Billing::placementRate($r); $r['fee'] = $r['status'] === 'proposed' ? Billing::placementCommission($r) : (float)$r['commission']; }
        unset($r);
        return ['kind' => 'bill-pl', 'rows' => $rows, 'can' => Auth::can('billing.update'), 'default_rate' => (float)Variables::get('billing.placement_rate_pct', 0.0)];
    }

    private static function flashA(string $kind, string $msg): void { Admin::flash($kind, $msg); }

    /** POST /billing/act: one gate for every owner/team billing action. */
    public static function act(Request $q): Response
    {
        $do = (string)($q->post['do'] ?? '');
        $id = (int)($q->post['id'] ?? 0);
        $dest = ['invoice-new' => 'invoices', 'invoice-void' => 'invoices', 'invoice-send' => 'invoices', 'invoice-paid' => 'invoices', 'ms-invoice' => 'milestones', 'ms-deliver' => 'milestones', 'ms-release' => 'milestones', 'ms-dispute' => 'milestones', 'ms-reopen' => 'milestones',
            'pl-invoice' => 'placements', 'pl-cancel' => 'placements', 'sub-cancel' => 'plan-subscriptions'][$do] ?? null;
        if ($dest === null) return Response::redirect(admin_url());
        $back = admin_url($dest . '/');
        $perm = in_array($do, ['ms-release', 'ms-dispute', 'ms-reopen'], true) ? 'billing.review' : 'billing.update';
        if ($r = Auth::require($perm)) return $r;
        if (!Csrf::check($q)) { self::flashA('error', __('msg.csrf')); return Response::redirect($back); }
        $note = mb_substr(trim((string)($q->post['note'] ?? '')), 0, 300);
        $by = self::who();
        switch ($do) {
            case 'invoice-new':
                $email = mb_strtolower(trim((string)($q->post['email'] ?? ''))); $amount = round((float)str_replace(',', '.', (string)($q->post['amount'] ?? '0')), 2);
                $desc = mb_substr(trim((string)($q->post['desc'] ?? '')), 0, 200);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $desc === '' || $amount <= 0 || $amount > 100000000) { self::flashA('error', 'Give a valid e-mail, a description and an amount above 0.'); break; }
                $inv = Billing::create(['kind' => 'manual', 'email' => $email, 'name' => mb_substr(trim((string)($q->post['name'] ?? '')), 0, 120), 'lines' => [[$desc, 1, $amount]], 'note' => $note ?: null]);
                Admin::audit('invoice.create', (string)$inv['number'], 'manual ' . $amount . ' ' . $inv['currency'] . ' to ' . $email);
                Billing::send($inv);
                self::flashA('ok', $inv['number'] . ' created and e-mailed.'); break;
            case 'invoice-void':
                if (mb_strlen($note) < 3) { self::flashA('error', 'Give a short reason to void an invoice.'); break; }
                $done = Billing::void($id, $by, $note);
                self::flashA($done ? 'ok' : 'error', $done ? 'Invoice voided.' : 'Only an open invoice can be voided.');
                break;
            case 'invoice-send':
                $inv = Billing::byId($id);
                self::flashA($inv && $inv['status'] === 'sent' && Billing::send($inv, 'reminder') ? 'ok' : 'error', $inv ? $inv['number'] . ' sent again.' : 'Not found.'); break;
            case 'invoice-paid':
                if (!Auth::can('billing.settle')) { self::flashA('error', 'Only the owner can record a payment received outside the platform.'); break; }
                if (mb_strlen($note) < 5) { self::flashA('error', 'Say how the money was received (at least 5 characters): it is kept in the audit log.'); break; }
                $inv = Billing::byId($id);
                if ($inv && Billing::markPaid($id, $by, null, 'offline')) { Admin::audit('invoice.paid_offline', (string)$inv['number'], $note); self::flashA('ok', $inv['number'] . ' recorded as paid.'); }
                else self::flashA('error', 'Only an open invoice can be marked as paid.');
                break;
            case 'ms-invoice':
                [$inv, $err] = Billing::invoiceMilestone($id);
                if (!$inv) { self::flashA('error', (string)$err); break; }
                Admin::audit('milestone.invoice', Billing::milestoneRef($id), (string)$inv['number']); Billing::send($inv);
                self::flashA('ok', $inv['number'] . ' issued and e-mailed to the client.'); break;
            case 'ms-deliver':
                if (mb_strlen($note) < 5) { self::flashA('error', 'Describe what was delivered (at least 5 characters).'); break; }
                $st = DB::q("UPDATE milestones SET status = 'delivered', delivered_by = ?, delivered_at = CURRENT_TIMESTAMP, delivery_note = ?, reviewed_by = NULL, reviewed_at = NULL, review_note = NULL WHERE id = ? AND status = 'funded'", [$by, $note, $id]);
                if ($st->rowCount() === 1) { Admin::audit('milestone.deliver', Billing::milestoneRef($id), $note); self::flashA('ok', 'Marked as delivered: a reviewer must now accept it.'); }
                else self::flashA('error', 'Only a funded milestone can be delivered: the client’s payment must be verified first.');
                break;
            case 'ms-release':
            case 'ms-dispute':
                $m = DB::one("SELECT m.*, e.client_email, e.title AS etitle FROM milestones m JOIN service_engagements e ON e.id = m.engagement_id WHERE m.id = ? AND m.status = 'delivered'", [$id]);
                if (!$m) { self::flashA('error', 'Only a delivered milestone can be reviewed.'); break; }
                $release = $do === 'ms-release';
                if (!$release && mb_strlen($note) < 5) { self::flashA('error', 'Say what is missing (at least 5 characters).'); break; }
                if ($release && Auth::role() !== 'owner' && mb_strtolower((string)$m['delivered_by']) === mb_strtolower($by)) { self::flashA('error', 'Four eyes: the person who delivered cannot also release. Ask another reviewer.'); break; }
                DB::q('UPDATE milestones SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, review_note = ? WHERE id = ?', [$release ? 'released' : 'disputed', $by, $note ?: null, $id]);
                Admin::audit($release ? 'milestone.release' : 'milestone.dispute', Billing::milestoneRef($id), $note);
                if ($uu = DB::one('SELECT id FROM users WHERE email = ?', [mb_strtolower((string)$m['client_email'])])) Account::notify((int)$uu['id'], $release ? tr3('Milestone accepted', 'Jalon accepté', 'قُبلت المرحلة') : tr3('Milestone needs more work', 'Jalon à reprendre', 'المرحلة تحتاج مزيدا من العمل'), (string)$m['etitle'] . ' · ' . $m['title'], '/account/invoices/');
                if ($release && !DB::val("SELECT COUNT(*) FROM milestones WHERE engagement_id = ? AND status NOT IN ('released','cancelled')", [$m['engagement_id']])) DB::q("UPDATE service_engagements SET status = 'completed' WHERE id = ?", [$m['engagement_id']]);
                self::flashA('ok', Billing::milestoneRef($id) . ($release ? ' released.' : ' sent back.')); break;
            case 'ms-reopen':
                $st = DB::q("UPDATE milestones SET status = 'funded' WHERE id = ? AND status = 'disputed'", [$id]);
                self::flashA($st->rowCount() === 1 ? 'ok' : 'error', $st->rowCount() === 1 ? 'Back to funded: the team can deliver again.' : 'Only a disputed milestone can be reopened.'); break;
            case 'pl-invoice':
                [$inv, $err] = Billing::invoicePlacement($id);
                if (!$inv) { self::flashA('error', (string)$err); break; }
                Admin::audit('placement.invoice', (string)$inv['source_ref'], (string)$inv['number'] . ' ' . $inv['amount']); Billing::send($inv);
                self::flashA('ok', $inv['number'] . ' issued and e-mailed to the client.'); break;
            case 'pl-cancel':
                $st = DB::q("UPDATE placements SET status = 'cancelled' WHERE id = ? AND status = 'proposed'", [$id]);
                self::flashA($st->rowCount() === 1 ? 'ok' : 'error', $st->rowCount() === 1 ? 'Placement cancelled.' : 'Only a placement not yet invoiced can be cancelled.'); break;
            case 'sub-cancel':
                $s = DB::one("SELECT * FROM plan_subscriptions WHERE id = ? AND status IN ('active','pending')", [$id]);
                if ($s) { DB::q("UPDATE plan_subscriptions SET status = 'cancelled' WHERE id = ?", [$id]); Admin::audit('plan.cancel', (string)$s['ref'], $note); self::flashA('ok', $s['ref'] . ' cancelled.'); }
                else self::flashA('error', 'Not found.');
                break;
        }
        return Response::redirect($back);
    }
}
