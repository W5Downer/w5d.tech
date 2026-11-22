<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Ads, Auth, Csrf, DB, Funding, Request, Response, ViewEnv};

/**
 * Funding stack screens: the public sponsorship page (F2), the owner portal funding dashboard (menu 39),
 * the pre-sale contract generator (F4) and the AdSense rejection log. Every state change is fail-closed:
 * the channel must be available (Funding::channelAvailable) before money is recorded as received or escrowed.
 */
final class Funds
{
    private const BACK = 'funding/';

    private static function who(): string { return (string)(Auth::user()['name'] ?? 'staff'); }

    private static function gate(Request $q, string $perm): ?Response
    {
        if ($r = Auth::require($perm)) return $r;
        if (!Csrf::check($q)) { Admin::flash('error', __('msg.csrf')); return Response::redirect(admin_url(self::BACK)); }
        return null;
    }

    private static function back(string $kind, string $msg): Response { Admin::flash($kind, $msg); return Response::redirect(admin_url(self::BACK)); }

    /* ======================= public: F2 sponsorship page ======================= */
    public static function sponsor(Request $q): Response
    {
        $ch = Funding::channelAvailable('F2');
        if ($q->isPost()) {
            if ($r = Forms::check($q, '/sponsor/', 'sponsor')) return $r;
            if (!$ch['available']) return Forms::fail($q, '/sponsor/', ['_form' => Forms::err('Sponsorship is closed right now.', 'Le parrainage est fermé pour le moment.', 'الرعاية مغلقة حاليا.')]);
            $e = [];
            Forms::required($q->post, ['name'], $e);
            $email = Forms::email($q->post, 'email', $e);
            $raw = str_replace([',', ' '], ['.', ''], Forms::str($q->post, 'amount', 20));
            $amount = is_numeric($raw) ? round((float)$raw, 2) : 0.0;
            $min = Funding::n('F2_SPONSORSHIP_MIN_TND');
            if ($amount < $min || $amount > 1000000) $e['amount'] = Forms::err('Enter an amount at or above the minimum shown on this page.', 'Saisissez un montant égal ou supérieur au minimum indiqué sur cette page.', 'أدخل مبلغا لا يقل عن الحد الأدنى المبين في هذه الصفحة.');
            if (empty($q->post['restricted'])) $e['restricted'] = Forms::err('Please confirm the restriction and refund terms.', 'Veuillez confirmer les conditions d’affectation et de remboursement.', 'يرجى تأكيد شروط التقييد والاسترداد.');
            if ($e) return Forms::fail($q, '/sponsor/', $e);
            $ref = Forms::ref('SPN');
            DB::insert('sponsorships', ['ref' => $ref, 'sponsor_name' => Forms::str($q->post, 'name', 120), 'email' => $email, 'organisation' => Forms::str($q->post, 'organisation', 120), 'amount_tnd' => $amount, 'status' => 'pledged']);
            \W5D\Mail::send($email, 'form_ack', ['kind' => 'contact', 'name' => Forms::str($q->post, 'name', 120), 'ref' => $ref]);
            \W5D\Mail::staff('Sponsorship pledge (no money received yet)', ['ref' => $ref, 'from' => Forms::str($q->post, 'name', 120) . ' <' . $email . '>', 'amount' => (string)$amount]);
            return Forms::done($q, ['ref' => $ref], 'sponsor');
        }
        ViewEnv::share('canonicalPath', '/sponsor/');
        return Response::html(view('pages.sponsor', ['title' => tr3('Sponsor the registry activation', 'Parrainer l’activation du registre', 'ارعَ تفعيل السجل'), 'ch' => $ch, 'errors' => $_SESSION['_errors'] ?? []]));
    }

    /* ======================= owner portal ======================= */
    public static function view(): array
    {
        $db = DB::available();
        $sp = $db ? DB::all('SELECT * FROM sponsorships ORDER BY id DESC LIMIT 100') : [];
        $ps = $db ? DB::all('SELECT * FROM presales ORDER BY id DESC LIMIT 100') : [];
        $ae = Funding::lastRecord('auto_entrepreneur_registration');
        return ['kind' => 'funding', 'can' => Auth::can('funding.update'), 'tokens' => Funding::tokens(), 'channels' => Funding::all(), 'totals' => Funding::totals(), 'salary' => Funding::salary(),
            'gate' => Ads::canApplyDetail(), 'ads' => Ads::figures(), 'rejections' => Ads::rejections(), 'sponsorships' => $sp, 'presales' => $ps, 'ae' => $ae,
            'errors' => Funding::buildErrors(), 'condition_met' => Funding::conditionMet()];
    }

    public static function record(Request $q): Response
    {
        if ($r = self::gate($q, 'funding.update')) return $r;
        $ref = trim((string)$q->input('reference', ''));
        $on = trim((string)$q->input('recorded_on', ''));
        if ($ref === '') return self::back('error', 'Enter the registration reference exactly as the authority issued it. Nothing is invented here.');
        if (!Funding::record('auto_entrepreneur_registration', $ref, $on, self::who(), (string)$q->input('note', ''))) return self::back('error', 'The date must be a real date, today or earlier (YYYY-MM-DD).');
        Admin::audit('funding.record', 'auto_entrepreneur_registration', $on);
        return self::back('ok', 'Registration recorded with its date. It is append-only: a correction is a new record.');
    }

    public static function sponsorship(Request $q): Response
    {
        if ($r = self::gate($q, 'funding.update')) return $r;
        $id = (int)$q->input('id', 0); $to = (string)$q->input('status', ''); $on = trim((string)$q->input('on', ''));
        $row = DB::one('SELECT * FROM sponsorships WHERE id = ?', [$id]);
        if (!$row || !in_array($to, ['received', 'refunded', 'cancelled'], true)) return self::back('error', 'Unknown sponsorship or status.');
        if ($to === 'received') {
            $ch = Funding::channelAvailable('F2');
            if (!$ch['available']) return self::back('error', 'Cannot mark as received while channel F2 is closed: ' . $ch['reason_text']);
            $agr = trim((string)$q->input('agreement_ref', ''));
            if ($agr === '' || !Funding::validPastDate($on)) return self::back('error', 'Received needs the signed agreement reference and the date the money arrived.');
            if ((float)$row['amount_tnd'] < Funding::n('F2_SPONSORSHIP_MIN_TND')) return self::back('error', 'The amount is below the sponsorship minimum.');
            DB::q("UPDATE sponsorships SET status='received', agreement_ref=?, received_on=? WHERE id=? AND status='pledged'", [$agr, $on, $id]);
        } elseif ($to === 'refunded') {
            if ($row['status'] !== 'received' || !Funding::validPastDate($on)) return self::back('error', 'Only a received sponsorship can be refunded, and the refund needs its date.');
            DB::q("UPDATE sponsorships SET status='refunded', refunded_on=? WHERE id=?", [$on, $id]);
        } else {
            if ($row['status'] !== 'pledged') return self::back('error', 'Only a pledge can be cancelled; a received sum is refunded, not cancelled.');
            DB::q("UPDATE sponsorships SET status='cancelled' WHERE id=?", [$id]);
        }
        Admin::audit('funding.sponsorship', $row['ref'], $to);
        return self::back('ok', 'Sponsorship ' . $row['ref'] . ' is now ' . $to . '.');
    }

    public static function presale(Request $q): Response
    {
        if ($r = self::gate($q, 'funding.update')) return $r;
        $do = (string)$q->input('do', 'add');
        if ($do === 'add') {
            $ch = Funding::channelAvailable('F4');
            if (!$ch['available']) return self::back('error', 'Cannot draft a pre-sale while channel F4 is closed: ' . $ch['reason_text']);
            $e = [];
            Forms::required($q->post, ['client_name'], $e);
            $email = Forms::email($q->post, 'email', $e);
            $amt = (float)str_replace(',', '.', (string)$q->input('amount', '0')); $cr = (int)$q->input('credits', 0);
            if ($e || $amt <= 0 || $cr <= 0) return self::back('error', 'Client name, a valid e-mail, an amount and a number of credits are required.');
            $ref = Forms::ref('PRE');
            DB::insert('presales', ['ref' => $ref, 'client_name' => Forms::str($q->post, 'client_name', 120), 'email' => $email, 'amount_tnd' => round($amt, 2), 'credits' => $cr, 'status' => 'draft', 'contract_ref' => 'CT-' . $ref]);
            Admin::audit('funding.presale', $ref, 'draft');
            return self::back('ok', 'Draft ' . $ref . ' created. Download its contract, have counsel approve the template, then record the escrow.');
        }
        $id = (int)$q->input('id', 0); $on = trim((string)$q->input('on', ''));
        $row = DB::one('SELECT * FROM presales WHERE id = ?', [$id]);
        if (!$row || !Funding::validPastDate($on)) return self::back('error', 'Unknown pre-sale, or the date is not a valid date (today or earlier).');
        if ($do === 'escrowed') {
            $esc = trim((string)$q->input('escrow_ref', ''));
            if ($row['status'] !== 'draft' || $esc === '') return self::back('error', 'Escrowed needs a draft and the escrow reference from the escrow agent.');
            if (!Funding::channelAvailable('F4')['available']) return self::back('error', 'Channel F4 is closed.');
            DB::q("UPDATE presales SET status='escrowed', escrow_ref=?, escrowed_on=? WHERE id=?", [$esc, $on, $id]);
        } elseif ($do === 'completed') {
            if ($row['status'] !== 'escrowed') return self::back('error', 'Only an escrowed pre-sale can complete.');
            if (!Funding::conditionMet()) return self::back('error', 'The condition precedent is not met (' . Funding::s('F4_CONDITION_PRECEDENT') . '): a pre-sale is not a completed sale until a valid matricule fiscal is recorded.');
            DB::q("UPDATE presales SET status='completed', completed_on=? WHERE id=?", [$on, $id]);
        } elseif ($do === 'refunded') {
            if (!in_array($row['status'], ['escrowed', 'draft'], true)) return self::back('error', 'Nothing to refund on this pre-sale.');
            DB::q("UPDATE presales SET status='refunded', refunded_on=? WHERE id=?", [$on, $id]);
        } else return self::back('error', 'Unknown action.');
        Admin::audit('funding.presale', $row['ref'], $do);
        return self::back('ok', 'Pre-sale ' . $row['ref'] . ': ' . $do . '.');
    }

    public static function adsRejection(Request $q): Response
    {
        if ($r = self::gate($q, 'funding.update')) return $r;
        $ok = Ads::logRejection(trim((string)$q->input('date', '')), (string)$q->input('rejection_type', ''), (string)$q->input('corrective_action', ''), self::who());
        if ($ok) Admin::audit('ads.rejection', (string)$q->input('rejection_type', ''), (string)$q->input('date', ''));
        return self::back($ok ? 'ok' : 'error', $ok ? 'Rejection logged. The log is append-only.' : 'A valid date, the rejection type and the corrective action are all required.');
    }

    public static function adsReapplied(Request $q): Response
    {
        if ($r = self::gate($q, 'funding.update')) return $r;
        $ok = Ads::markReapplied((int)$q->input('id', 0), trim((string)$q->input('on', '')));
        if ($ok) Admin::audit('ads.reapplied', (string)$q->input('id', ''), (string)$q->input('on', ''));
        return self::back($ok ? 'ok' : 'error', $ok ? 'Re-application date recorded (it can be set once).' : 'Not recorded: bad date, or it was already set.');
    }

    /** GET: filled contract for a sponsorship (F2) or a pre-sale (F4), or the blank PDF template. */
    public static function download(Request $q): Response
    {
        if ($r = Auth::require('funding')) return $r;
        $kind = (string)($q->query['kind'] ?? '');
        $h = ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow'];
        if (($q->query['pdf'] ?? '') !== '') {
            $f = ROOT . '/docs/funding/' . ($kind === 'f4' ? 'f4-presale-contract.pdf' : 'f2-sponsorship-agreement.pdf');
            if (!is_file($f)) return Response::text('Template not built: run python3 tools/gen/funding_pdfs.py', 'text/plain', 404);
            return new Response((string)file_get_contents($f), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . basename($f) . '"'] + $h);
        }
        $id = (int)($q->query['id'] ?? 0);
        $ch = Funding::channelAvailable($kind === 'f4' ? 'F4' : 'F2');
        if (!$ch['available']) return Response::text('Closed: ' . $ch['reason_text'], 'text/plain', 409);
        $date = date('Y-m-d');
        if ($kind === 'f4') {
            $r = DB::one('SELECT * FROM presales WHERE id = ?', [$id]);
            if (!$r) return Response::text('Unknown pre-sale', 'text/plain', 404);
            $txt = Funding::fill((string)Funding::template('f4'), ['client_name' => $r['client_name'], 'contract_ref' => $r['contract_ref'], 'date' => $date, 'amount_tnd' => Funding::fmt((float)$r['amount_tnd']), 'credits' => $r['credits'], 'escrow_agent' => '[escrow agent to be named after counsel advice]']);
            $name = $r['contract_ref'];
        } else {
            $r = DB::one('SELECT * FROM sponsorships WHERE id = ?', [$id]);
            if (!$r) return Response::text('Unknown sponsorship', 'text/plain', 404);
            $txt = Funding::fill((string)Funding::template('f2'), ['sponsor_name' => $r['sponsor_name'], 'agreement_ref' => 'AG-' . $r['ref'], 'date' => $date, 'amount_tnd' => Funding::fmt((float)$r['amount_tnd']), 'f2_min_tnd' => Funding::fmt(Funding::n('F2_SPONSORSHIP_MIN_TND'))]);
            $name = 'AG-' . $r['ref'];
        }
        return new Response($txt, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $name) . '.txt"'] + $h);
    }
}
