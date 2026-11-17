<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{DB, Lang, Mail, RateLimit, Request, Response, ViewEnv, Csrf};

/**
 * One-click unsubscribe from non-essential mail (dated updates).
 * Link = /unsubscribe/?e=<email>&s=<HMAC>&l=<lang>. Stateless: the signature is the credential, so it works from any mail client.
 * POST without CSRF is allowed ONLY with a valid signature (RFC 8058 List-Unsubscribe-Post one-click).
 * Service mail (verification, password reset, receipts, payment status) is not affected and is always sent.
 */
final class Unsubscribe
{
    public static function handle(Request $q): Response
    {
        $email = mb_strtolower(trim((string)$q->input('e', '')));
        $sig = (string)$q->input('s', '');
        $l = (string)$q->input('l', '');
        if (in_array($l, Lang::SUPPORTED, true) && Lang::current() !== $l) Lang::set($l);

        $valid = $email !== '' && strlen($email) <= 254 && Mail::unsubValid($email, $sig);
        if (!RateLimit::hit('unsub:' . $q->ip(), 60, 600)) return self::page($q, ['state' => 'limited'], 429);
        if (!$valid) return self::page($q, ['state' => 'invalid'], 400);
        if (!DB::available()) return self::page($q, ['state' => 'down'], 503);

        $oneClick = $q->isPost() && ($q->post['List-Unsubscribe'] ?? '') === 'One-Click';
        if ($q->isPost()) {
            $action = (string)($q->post['action'] ?? 'unsubscribe');
            if ($oneClick) { Mail::suppress($email, 'one-click'); return Response::text('OK'); }
            if (!Csrf::check($q)) return self::page($q, ['state' => 'confirm', 'email' => $email, 'sig' => $sig, 'error' => true], 400);
            if ($action === 'resubscribe') { Mail::resubscribe($email); return self::page($q, ['state' => 'resubscribed', 'email' => $email, 'sig' => $sig]); }
            Mail::suppress($email, 'link');
            return self::page($q, ['state' => 'done', 'email' => $email, 'sig' => $sig]);
        }
        return self::page($q, ['state' => Mail::suppressed($email) ? 'done' : 'confirm', 'email' => $email, 'sig' => $sig]);
    }

    private static function page(Request $q, array $d, int $status = 200): Response
    {
        ViewEnv::share('canonicalPath', '/unsubscribe/');
        $html = view('account.unsubscribe', $d + ['robots' => 'noindex,nofollow']);
        return Response::html($html, $status)->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store']);
    }
}
