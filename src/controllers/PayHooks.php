<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{DB, Gateways, Payments, Request, Response, Secret, Session};

/**
 * Online-gateway endpoints.
 *  - /pay/return/{PAY-ref}/  buyer comes back from the provider (signed link). Only a hint: the status is re-read from the provider.
 *  - /pay/hook/{provider}/   server-to-server notification. Authenticated by the provider's signature where one exists
 *                            (Flouci HMAC, Paymee check_sum), and in every case re-verified with our own credentials before anything changes.
 * These endpoints carry no session and no CSRF token by design: they are authenticated by signature + server-side verification.
 */
final class PayHooks
{
    private const COOLDOWN = 4; // seconds between two provider look-ups of the same payment

    private static function recent(array $p): bool
    {
        return !empty($p['checked_at']) && $p['gateway_status'] !== 'started' && (time() - (int)strtotime($p['checked_at'] . ' UTC')) < self::COOLDOWN;
    }

    public static function back(Request $q): Response
    {
        $pref = (string)($q->params['ref'] ?? '');
        $sig = (string)$q->input('s', '');
        $p = preg_match('/^PAY-[A-F0-9]{8}$/', $pref) ? DB::one('SELECT * FROM payments WHERE ref = ?', [$pref]) : null;
        if (!$p || !Secret::verify('payret:' . $pref, $sig) || !Gateways::online((string)$p['method'])) return Pages::notFound($q);
        $state = 'pending';
        if ($p['status'] !== 'verified') {
            if (!self::recent($p)) $state = Payments::sync($p, 'return-page');
            else $state = $p['status'] === 'rejected' ? 'failed' : 'pending';
        } else $state = 'paid';
        $o = (string)$p['order_ref'];
        $kind = $state === 'paid' ? 'ok' : ($state === 'failed' ? 'error' : 'info');
        $msg = $state === 'paid' ? Accounts::m('Payment confirmed. Thank you.', 'Paiement confirmé. Merci.', 'تم تأكيد الدفع. شكرا.')
            : ($state === 'failed' ? Accounts::m('The payment was not completed. You can try again.', 'Le paiement n’a pas abouti. Vous pouvez réessayer.', 'لم تكتمل الدفعة. يمكنك المحاولة مرة أخرى.')
            : Accounts::m('We are checking your payment with the provider. This page updates when it is confirmed.', 'Nous vérifions votre paiement auprès du prestataire. Cette page se met à jour dès confirmation.', 'نتحقق من دفعتك لدى المزوّد. تتحدث هذه الصفحة عند التأكيد.'));
        Accounts::flash($kind, $msg);
        return Response::redirect(\W5D\Payments::link($o) . ($state === 'pending' ? '&w=1' : ''));
    }

    public static function hook(Request $q): Response
    {
        $id = (string)($q->params['provider'] ?? '');
        $def = Gateways::def($id);
        $plain = fn(string $t, int $s) => Response::text($t, 'text/plain', $s)->withHeaders(['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'no-store']);
        if (!$def || empty($def['webhook']) || !Gateways::enabled($id)) return $plain('not found', 404);

        $find = fn(string $providerRef) => $providerRef !== '' && strlen($providerRef) <= 200 ? DB::one('SELECT * FROM payments WHERE method = ? AND provider_ref = ?', [$id, $providerRef]) : null;
        $eventId = ''; $p = null;

        if ($id === 'flouci') {
            $sigH = (string)($_SERVER['HTTP_X_FLOUCI_SIGNATURE'] ?? '');
            if ($q->method === 'POST') {                                   // webhook_version 2: raw body is what is signed
                $raw = (string)file_get_contents('php://input');
                if (!Gateways::flouciSignatureOk($sigH, $raw)) return $plain('bad signature', 401);
                $j = json_decode($raw, true);
                $pid = is_array($j) ? (string)($j['payment_id'] ?? '') : '';
                $eventId = is_array($j) ? (string)($j['event_id'] ?? '') : '';
            } else {                                                       // webhook_version 1: signed query string payment_id=..&success=..
                $pid = (string)($q->query['payment_id'] ?? ''); $succ = (string)($q->query['success'] ?? '');
                if (!Gateways::flouciSignatureOk($sigH, 'payment_id=' . $pid . '&success=' . $succ)) return $plain('bad signature', 401);
                $eventId = $pid . ':' . $succ;
            }
            $p = $find($pid);
        } elseif ($id === 'paymee') {
            $token = (string)($q->post['token'] ?? $q->query['token'] ?? '');
            $status = (string)($q->post['payment_status'] ?? $q->query['payment_status'] ?? '');
            $sum = (string)($q->post['check_sum'] ?? $q->query['check_sum'] ?? '');
            if (!Gateways::paymeeChecksumOk($token, $status, $sum)) return $plain('bad signature', 401);
            $p = $find($token); $eventId = $token . ':' . $status;
        } else { // konnect: the notification only carries a reference; authenticity comes from re-reading the payment with our API key
            $pr = (string)($q->query['payment_ref'] ?? $q->post['payment_ref'] ?? '');
            $p = $find($pr); $eventId = $pr . ':' . gmdate('YmdHi');
        }

        if (!$p) return $plain('unknown payment', 404);
        if ($p['status'] === 'verified') return $plain('ok', 200);          // already settled: acknowledge so the provider stops retrying
        if (self::recent($p)) return $plain('ok', 200);
        $state = Payments::sync($p, $id . '-webhook');
        Payments::firstSight($id, $eventId, (string)$p['ref'], $state);
        Payments::log('hook ' . $id . ' ' . $p['ref'] . ' → ' . $state);
        // 5xx makes the provider retry later when we could not read the status; anything else is acknowledged
        return $state === 'error' ? $plain('retry later', 503) : $plain('ok', 200);
    }
}
