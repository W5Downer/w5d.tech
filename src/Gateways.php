<?php
declare(strict_types=1);

namespace W5D;

/**
 * Payment-method registry + online gateway adapters (Flouci, Konnect, Paymee).
 * Configuration lives in the owner portal (Settings, encrypted secrets); config/gateways.php only declares the fields.
 * Rules: the amount is always computed server-side; a redirect or a webhook is only a hint: the final status is ALWAYS read
 * back from the provider with our own credentials; settlement is idempotent (Payments::finalize).
 */
final class Gateways
{
    public static function all(): array { static $c = null; return $c ??= require ROOT . '/config/gateways.php'; }
    public static function def(string $id): ?array { return self::all()[$id] ?? null; }
    public static function key(string $id, string $k): string { return 'pay.' . $id . '.' . $k; }

    public static function field(string $id, string $k): ?array { return SettingsForm::field(self::all(), $id, $k); }

    /** Configured value: owner setting, else legacy .env (D17), else the field default. */
    public static function cfg(string $id, string $k): string { return SettingsForm::cfg(self::all(), 'pay', $id, $k); }

    /** Owner switch. Without a stored switch the legacy D17 .env flag applies; every other method is off until the owner turns it on. */
    public static function on(string $id): bool { return SettingsForm::on(self::all(), 'pay', $id); }

    /** @return string[] labels of required fields still empty or invalid */
    public static function missing(string $id): array { return SettingsForm::missing(self::all(), 'pay', $id); }

    public static function enabled(string $id): bool { return SettingsForm::ready(self::all(), 'pay', $id); }

    /** @return string[] */
    public static function enabledIds(): array { return array_values(array_filter(array_keys(self::all()), [self::class, 'enabled'])); }
    public static function any(): bool { return self::enabledIds() !== []; }
    public static function online(string $id): bool { return (self::def($id)['kind'] ?? '') === 'online'; }
    public static function name(string $id): string { return (string)(self::def($id)['name'] ?? $id); }
    /** Short label for the buyer. */
    public static function short(string $id): string { return trim(explode('(', self::name($id))[0]); }

    /* ---------------------------------------------------------------- transaction fees */

    private static function num(string $id, string $k): float
    {
        $v = str_replace(',', '.', self::cfg($id, $k));
        return is_numeric($v) ? max(0.0, (float)$v) : 0.0;
    }

    /**
     * Fee for one transaction of $amount (the published price), from the owner's per-method variables.
     * @return array{pct:float,fixed:float,fee:float,payer:string,charge:float,net:float}
     *   fee = percent of the amount + fixed fee; charge = what the buyer pays; net = what we keep.
     *   The provider's VAT on its fee is NOT modelled: it is in the provider contract, not in this app.
     */
    public static function fee(string $id, float $amount): array
    {
        $amount = round(max(0.0, $amount), 2);
        $pct = self::num($id, 'fee_pct'); $fixed = self::num($id, 'fee_fixed');
        $payer = self::cfg($id, 'fee_payer') === 'buyer' ? 'buyer' : 'seller';
        $fee = $amount > 0 ? round($amount * $pct / 100 + $fixed, 2) : 0.0;
        if ($payer === 'buyer') return ['pct' => $pct, 'fixed' => $fixed, 'fee' => $fee, 'payer' => $payer, 'charge' => round($amount + $fee, 2), 'net' => $amount];
        return ['pct' => $pct, 'fixed' => $fixed, 'fee' => $fee, 'payer' => $payer, 'charge' => $amount, 'net' => round($amount - $fee, 2)];
    }

    /** Buyer-facing one-liner, e.g. "+ 1.00 TND fee (1 %)"; empty when the buyer pays nothing extra. */
    public static function feeBuyerLine(string $id, float $amount): string
    {
        $f = self::fee($id, $amount);
        if ($f['payer'] !== 'buyer' || $f['fee'] <= 0) return '';
        return number_format($f['fee'], 2) . ' ' . (string)Variables::get('money.currency', 'TND');
    }

    /** Rows shown to the buyer for a manual method: [label, value, copyable] */
    public static function instructions(string $id, float $amount): array
    {
        $cur = (string)Variables::get('money.currency', 'TND');
        $rows = [];
        if ($id === 'd17') $rows = [[tr3('Send to', 'Envoyer au', 'أرسل إلى'), self::cfg('d17', 'gsm'), true], [tr3('Account holder', 'Titulaire', 'صاحب الحساب'), self::cfg('d17', 'holder'), false]];
        if ($id === 'bank') {
            $rows = [[tr3('Bank', 'Banque', 'البنك'), self::cfg('bank', 'bank_name'), false], [tr3('Account holder', 'Titulaire', 'صاحب الحساب'), self::cfg('bank', 'holder'), false], [tr3('RIB / IBAN', 'RIB / IBAN', 'RIB / IBAN'), self::cfg('bank', 'iban'), true]];
            if (self::cfg('bank', 'bic') !== '') $rows[] = ['BIC / SWIFT', self::cfg('bank', 'bic'), true];
        }
        $rows[] = [tr3('Amount', 'Montant', 'المبلغ'), number_format($amount, 2) . ' ' . $cur, false];
        return ['rows' => array_values(array_filter($rows, fn($r) => $r[1] !== '')), 'note' => self::cfg($id, 'note')];
    }

    /* ---------------------------------------------------------------- helpers */

    private static function millimes(float $tnd): int { return (int)round($tnd * 1000); }
    private static function names(string $full): array { $p = preg_split('/\s+/', trim($full)) ?: ['']; $f = array_shift($p); return [(string)$f, $p ? implode(' ', $p) : (string)$f]; }
    public static function returnUrl(string $payRef, string $outcome): string { return abs_url('/pay/return/' . $payRef . '/') . '?s=' . Secret::sign('payret:' . $payRef) . '&o=' . $outcome; }
    public static function hookUrl(string $id): string { return abs_url('/pay/hook/' . $id . '/'); }

    private static function base(string $id): string
    {
        $o = rtrim(self::cfg($id, 'base_url'), '/');
        if ($o !== '') return $o;
        $live = self::cfg($id, 'mode') === 'live';
        return match ($id) {
            'flouci' => 'https://developers.flouci.com',
            'konnect' => $live ? 'https://api.konnect.network/api/v2' : 'https://api.sandbox.konnect.network/api/v2',
            'paymee' => $live ? 'https://app.paymee.tn/api/v2' : 'https://sandbox.paymee.tn/api/v2',
            default => '',
        };
    }

    private static function flouciAuth(): array { return ['Authorization' => 'Bearer ' . self::cfg('flouci', 'public') . ':' . self::cfg('flouci', 'private')]; }

    /* ---------------------------------------------------------------- start */

    /**
     * Create the hosted payment at the provider.
     * @param array $p payments row  @param array $buyer {name,email,phone}
     * @return array{ok:bool,url:?string,ref:?string,error:?string}
     */
    public static function start(string $id, array $p, array $buyer): array
    {
        $fail = fn(string $why) => ['ok' => false, 'url' => null, 'ref' => null, 'error' => $why];
        if (!self::enabled($id) || !self::online($id)) return $fail('method not available');
        $amount = Payments::due($p);
        if ($amount <= 0 || $amount > 100000 || (string)$p['currency'] !== 'TND') return $fail('invalid amount or currency');
        $base = self::base($id); $pr = (string)$p['ref'];
        [$fn, $ln] = self::names((string)($buyer['name'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string)($buyer['phone'] ?? ''));
        switch ($id) {
            case 'flouci':
                $r = Http::request('POST', $base . '/api/v2/generate_payment', self::flouciAuth(), [
                    'amount' => (string)self::millimes($amount), 'success_link' => self::returnUrl($pr, 'ok'), 'fail_link' => self::returnUrl($pr, 'fail'),
                    'webhook' => self::hookUrl('flouci'), 'webhook_version' => (int)self::cfg('flouci', 'webhook_version') ?: 2,
                    'developer_tracking_id' => $pr, 'session_timeout_secs' => max(120, (int)self::cfg('flouci', 'timeout')),
                    'accept_card' => self::cfg('flouci', 'accept_card') === '1',
                ]);
                $j = $r['json'] ?? [];
                $res = is_array($j['result'] ?? null) ? $j['result'] : [];
                $url = (string)($res['link'] ?? ''); $ref = (string)($res['payment_id'] ?? '');
                if ($r['status'] >= 200 && $r['status'] < 300 && ($j['success'] ?? true) !== false && $url !== '' && $ref !== '') return ['ok' => true, 'url' => $url, 'ref' => $ref, 'error' => null];
                return $fail('flouci http ' . $r['status'] . ($r['error'] ? ' ' . $r['error'] : ''));
            case 'konnect':
                $r = Http::request('POST', $base . '/payments/init-payment', ['x-api-key' => self::cfg('konnect', 'api_key')], [
                    'receiverWalletId' => self::cfg('konnect', 'wallet_id'), 'token' => 'TND', 'amount' => self::millimes($amount), 'type' => 'immediate',
                    'description' => 'W5D ' . (string)$p['order_ref'], 'acceptedPaymentMethods' => ['wallet', 'bank_card', 'e-DINAR'],
                    'lifespan' => max(5, (int)self::cfg('konnect', 'lifespan')), 'checkoutForm' => false, 'addPaymentFeesToAmount' => false,
                    'firstName' => $fn, 'lastName' => $ln, 'phoneNumber' => $phone, 'email' => (string)($buyer['email'] ?? ''), 'orderId' => $pr,
                    'webhook' => self::hookUrl('konnect'), 'silentWebhook' => true, 'successUrl' => self::returnUrl($pr, 'ok'), 'failUrl' => self::returnUrl($pr, 'fail'), 'theme' => 'light',
                ]);
                $j = $r['json'] ?? [];
                $url = (string)($j['payUrl'] ?? ''); $ref = (string)($j['paymentRef'] ?? '');
                if ($r['status'] >= 200 && $r['status'] < 300 && $url !== '' && $ref !== '') return ['ok' => true, 'url' => $url, 'ref' => $ref, 'error' => null];
                return $fail('konnect http ' . $r['status'] . ($r['error'] ? ' ' . $r['error'] : ''));
            case 'paymee':
                $r = Http::request('POST', $base . '/payments/create', ['Authorization' => 'Token ' . self::cfg('paymee', 'api_token')], [
                    'vendor' => self::cfg('paymee', 'vendor'), 'amount' => $amount, 'note' => 'W5D ' . (string)$p['order_ref'],
                    'first_name' => $fn, 'last_name' => $ln, 'email' => (string)($buyer['email'] ?? ''), 'phone' => $phone,
                    'return_url' => self::returnUrl($pr, 'ok'), 'cancel_url' => self::returnUrl($pr, 'fail'), 'webhook_url' => self::hookUrl('paymee'), 'order_id' => $pr,
                ]);
                $j = $r['json'] ?? [];
                $d = is_array($j['data'] ?? null) ? $j['data'] : [];
                $url = (string)($d['payment_url'] ?? ''); $ref = (string)($d['token'] ?? '');
                if ($r['status'] >= 200 && $r['status'] < 300 && ($j['status'] ?? true) !== false && $url !== '' && $ref !== '') return ['ok' => true, 'url' => $url, 'ref' => $ref, 'error' => null];
                return $fail('paymee http ' . $r['status'] . ($r['error'] ? ' ' . $r['error'] : ''));
        }
        return $fail('unknown provider');
    }

    /* ---------------------------------------------------------------- verify */

    /**
     * Ask the provider what happened to this payment.
     * @return array{state:string,raw:string,note:?string}  state: paid | failed | pending | error
     */
    public static function verify(string $id, array $p): array
    {
        $out = fn(string $s, string $raw = '', ?string $n = null) => ['state' => $s, 'raw' => $raw, 'note' => $n];
        $pref = (string)($p['provider_ref'] ?? '');
        if (!self::online($id) || $pref === '' || !self::enabled($id)) return $out('error', '', 'not verifiable');
        $base = self::base($id);
        $due = Payments::due($p);
        $expectMilli = self::millimes($due);
        switch ($id) {
            case 'flouci':
                $r = Http::request('GET', $base . '/api/v2/verify_payment/' . rawurlencode($pref), self::flouciAuth());
                $j = $r['json'];
                if ($r['status'] !== 200 || !is_array($j) || ($j['success'] ?? false) !== true) return $out('error', 'http ' . $r['status']);
                $res = is_array($j['result'] ?? null) ? $j['result'] : [];
                $st = strtoupper((string)($res['status'] ?? ''));
                if ($st === 'SUCCESS') {
                    if (isset($res['amount']) && (int)$res['amount'] !== $expectMilli) return $out('failed', $st, 'amount mismatch: got ' . (int)$res['amount'] . ' expected ' . $expectMilli . ' millimes');
                    return $out('paid', $st);
                }
                if ($st === 'PENDING') return $out('pending', $st);
                return $out(in_array($st, ['EXPIRED', 'FAILURE', 'SYSTEM_FAILURE'], true) ? 'failed' : 'error', $st);
            case 'konnect':
                $r = Http::request('GET', $base . '/payments/' . rawurlencode($pref), ['x-api-key' => self::cfg('konnect', 'api_key')]);
                $j = $r['json'];
                $pay = is_array($j['payment'] ?? null) ? $j['payment'] : null;
                if ($r['status'] !== 200 || !$pay) return $out('error', 'http ' . $r['status']);
                $st = strtolower((string)($pay['status'] ?? ''));
                if ($st === 'completed') {
                    if (isset($pay['amount']) && is_numeric($pay['amount']) && (int)$pay['amount'] !== $expectMilli) return $out('failed', $st, 'amount mismatch: got ' . (int)$pay['amount'] . ' expected ' . $expectMilli . ' millimes');
                    return $out('paid', $st);
                }
                if ($st === 'pending') return $out('pending', $st);
                return $out($st === '' ? 'error' : 'failed', $st);
            case 'paymee':
                $r = Http::request('GET', $base . '/payments/' . rawurlencode($pref) . '/check', ['Authorization' => 'Token ' . self::cfg('paymee', 'api_token')]);
                $j = $r['json'];
                $d = is_array($j['data'] ?? null) ? $j['data'] : null;
                if ($r['status'] !== 200 || !$d) return $out('error', 'http ' . $r['status']);
                if (!empty($d['payment_status'])) {
                    if (isset($d['amount']) && is_numeric($d['amount']) && abs((float)$d['amount'] - $due) > 0.001) return $out('failed', 'paid', 'amount mismatch: got ' . $d['amount'] . ' expected ' . number_format($due, 2, '.', ''));
                    return $out('paid', 'paid');
                }
                return $out('pending', 'unpaid');
        }
        return $out('error');
    }

    /* ---------------------------------------------------------------- webhook authentication */

    /** Flouci: X-Flouci-Signature: t=<unix>,v1=<hex>; HMAC-SHA256(private, "<t>.<content>"). Replays older than 10 minutes are refused. */
    public static function flouciSignatureOk(string $header, string $content, ?int $now = null): bool
    {
        $priv = self::cfg('flouci', 'private');
        if ($priv === '' || $header === '' || !preg_match('/t=(\d{9,12})/', $header, $t) || !preg_match('/v1=([0-9a-fA-F]{64})/', $header, $v)) return false;
        if (abs(($now ?? time()) - (int)$t[1]) > 600) return false;
        return hash_equals(hash_hmac('sha256', $t[1] . '.' . $content, $priv), strtolower($v[1]));
    }

    /** Paymee: check_sum = md5(token + payment_status(1|0) + API token). */
    public static function paymeeChecksumOk(string $token, string $status, string $sum): bool
    {
        $api = self::cfg('paymee', 'api_token');
        return $api !== '' && $sum !== '' && hash_equals(md5($token . $status . $api), strtolower($sum));
    }
}
