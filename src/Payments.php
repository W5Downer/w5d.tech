<?php
declare(strict_types=1);

namespace W5D;

/**
 * Payments facade. Methods (D17, bank transfer, Flouci, Konnect, Paymee) are declared in config/gateways.php and switched on/off
 * by the owner (Gateways, Settings). Manual methods are verified by a person; online methods are verified by asking the provider.
 * Either way the order only becomes "paid" through settle(), which is idempotent.
 */
final class Payments
{
    /** D17 specifically (QCM fee passes still use D17 only). */
    public static function enabled(): bool { return Gateways::enabled('d17'); }
    public static function gsm(): string { $g = trim(Gateways::cfg('d17', 'gsm')); return preg_match('/\d{8}/', $g) ? $g : ''; }
    public static function holder(): string { return Gateways::cfg('d17', 'holder'); }
    public static function note(): string { return Gateways::cfg('d17', 'note'); }

    /** What the buyer is asked to send for this payment row (published price, plus the fee when the buyer bears it). */
    public static function due(array $p): float { return round((float)($p['charge'] ?? 0) > 0 ? (float)$p['charge'] : (float)$p['amount'], 2); }

    /** Any method the buyer can pick right now. */
    public static function methods(): array { return Gateways::enabledIds(); }
    public static function anyEnabled(): bool { return Gateways::any(); }

    /** Normalise a Tunisian GSM to +216XXXXXXXX or null. Mobile ranges start with 2, 4, 5 or 9. */
    public static function tnGsm(string $raw): ?string
    {
        $d = preg_replace('/[\s().-]+/', '', $raw);
        if (preg_match('/^(?:\+|00)?216([2459]\d{7})$/', $d, $m) || preg_match('/^([2459]\d{7})$/', $d, $m)) return '+216' . $m[1];
        return null;
    }

    public static function sig(string $orderRef): string { return Secret::sign('pay:' . $orderRef); }
    public static function link(string $orderRef): string { return url('/pay/' . $orderRef . '/') . '?s=' . self::sig($orderRef); }
    public static function absLink(string $orderRef): string { return Env::baseUrl() . self::link($orderRef); }

    /** Instructions block for the D17 mail/pages (kept for the receipt mail). */
    public static function instructions(float $amount): array
    {
        return ['gsm' => self::gsm(), 'name' => self::holder(), 'amount' => $amount, 'note' => self::note()];
    }

    public static function create(string $orderRef, int $investorId, float $amount, string $method = 'd17'): string
    {
        $ref = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));
        $f = Gateways::fee($method, $amount);   // snapshot: later changes of the owner's rates never rewrite an existing payment
        DB::insert('payments', ['ref' => $ref, 'order_ref' => $orderRef, 'investor_id' => $investorId, 'method' => $method, 'amount' => round($amount, 2), 'fee' => $f['fee'], 'fee_payer' => $f['payer'], 'charge' => $f['charge'], 'currency' => (string)Variables::get('money.currency', 'TND'), 'status' => 'pending']);
        return $ref;
    }

    /** Start (or restart) the hosted payment for an online method. Returns the provider URL or null. */
    public static function begin(array $p, array $buyer): ?string
    {
        $r = Gateways::start((string)$p['method'], $p, $buyer);
        if (!$r['ok']) {
            DB::q("UPDATE payments SET gateway_status = ?, checked_at = CURRENT_TIMESTAMP WHERE id = ?", [mb_substr('start failed: ' . (string)$r['error'], 0, 200), $p['id']]);
            self::log('start failed ' . $p['ref'] . ' ' . $p['method'] . ': ' . $r['error']);
            return null;
        }
        DB::q("UPDATE payments SET provider_ref = ?, gateway_url = ?, gateway_status = 'started', started_at = CURRENT_TIMESTAMP, status = 'pending', note = NULL WHERE id = ?", [$r['ref'], $r['url'], $p['id']]);
        return (string)$r['url'];
    }

    /**
     * Ask the provider and settle. Returns the provider state: paid | failed | pending | error.
     * Never trusts the browser or the webhook body: the state comes from Gateways::verify().
     */
    public static function sync(array $p, string $by = 'gateway'): string
    {
        if ($p['status'] === 'verified') return 'paid';
        $id = (string)$p['method'];
        $v = Gateways::verify($id, $p);
        DB::q('UPDATE payments SET gateway_status = ?, checked_at = CURRENT_TIMESTAMP WHERE id = ?', [mb_substr($v['raw'] . ($v['note'] ? ' · ' . $v['note'] : ''), 0, 200), $p['id']]);
        if ($v['state'] === 'paid') self::settle((int)$p['id'], true, $by, 'confirmed by ' . $id);
        elseif ($v['state'] === 'failed') self::settle((int)$p['id'], false, $by, $v['note'] ?: ('provider status ' . $v['raw']));
        return $v['state'];
    }

    /**
     * Mark a payment verified/rejected and move the order. Idempotent: a payment that is already verified is never touched again,
     * so a retried webhook, a reloaded return page and a double click settle exactly once.
     */
    public static function settle(int $paymentId, bool $ok, string $by, string $note = ''): bool
    {
        $p = DB::one('SELECT p.*, i.full_name, i.email, i.lang FROM payments p LEFT JOIN investors i ON i.id = p.investor_id WHERE p.id = ?', [$paymentId]);
        if (!$p || $p['status'] === 'verified') return false;
        $status = $ok ? 'verified' : 'rejected';
        $st = DB::q("UPDATE payments SET status = ?, note = ?, verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE id = ? AND status != 'verified'", [$status, $note !== '' ? mb_substr($note, 0, 300) : null, $by, $paymentId]);
        if ($st->rowCount() !== 1) return false;
        DB::q('UPDATE investor_orders SET status = ? WHERE ref = ?', [$ok ? 'paid' : 'awaiting_payment', $p['order_ref']]);
        try {
            DB::insert('audit_log', ['country_id' => 'tn', 'actor' => $by, 'action' => 'payment.' . $status, 'subject' => (string)$p['ref'], 'detail' => mb_substr($p['order_ref'] . ' ' . $p['method'] . ' ' . $note, 0, 1000)]);
        } catch (\Throwable) {}
        if ($ok && $p['email']) Affiliate::onPayment((string)$p['email'], (string)$p['ref'], (float)$p['amount']);
        if ($p['email']) {
            try {
                Mail::send($p['email'], 'payment_status', ['name' => $p['full_name'], 'ref' => $p['ref'], 'order_ref' => $p['order_ref'], 'amount' => $p['amount'], 'currency' => $p['currency'], 'status' => $status, 'note' => $note, 'pay_url' => self::absLink((string)$p['order_ref'])], $p['lang'] ?? null);
                $u = DB::one('SELECT id FROM users WHERE email = ?', [$p['email']]);
                if ($u) Account::notify((int)$u['id'], $ok ? tr3('Payment verified', 'Paiement vérifié', 'تم التحقق من الدفعة') : tr3('Payment rejected', 'Paiement rejeté', 'تم رفض الدفعة'), $p['ref'] . ' · ' . $p['order_ref'], '/account/payments/');
            } catch (\Throwable) {}
        }
        return true;
    }

    /** Webhook de-duplication: true the first time (provider, event) is seen. */
    public static function firstSight(string $provider, string $eventId, string $payRef, string $outcome): bool
    {
        try { DB::insert('payment_events', ['provider' => $provider, 'event_id' => mb_substr($eventId, 0, 120), 'payment_ref' => $payRef, 'outcome' => $outcome]); return true; }
        catch (\Throwable) { return false; }
    }

    /** Online payments still waiting, oldest first (used by bin/payments-sync.php and the owner "re-check" button). */
    public static function pendingOnline(int $limit = 50): array
    {
        $in = implode(',', array_map(fn($i) => "'" . $i . "'", array_filter(array_keys(Gateways::all()), [Gateways::class, 'online'])));
        return DB::all("SELECT * FROM payments WHERE status = 'pending' AND provider_ref IS NOT NULL AND method IN ($in) ORDER BY id LIMIT " . (int)$limit);
    }

    public static function log(string $line): void { @file_put_contents(ROOT . '/storage/logs/payments-' . date('Y-m-d') . '.log', '[' . date('c') . '] ' . $line . "\n", FILE_APPEND); }
}
