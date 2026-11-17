<?php
declare(strict_types=1);

namespace W5D;

/** Minimal OAuth 2.0 authorization-code client (state-protected, server-side token exchange, no SDK). */
final class OAuth
{
    public static function all(): array { return require ROOT . '/config/oauth.php'; }

    /** Providers that are configured in .env. */
    public static function enabled(): array
    {
        $out = [];
        foreach (self::all() as $k => $c) {
            $K = strtoupper($k);
            if (Env::get("OAUTH_{$K}_CLIENT_ID") && Env::get("OAUTH_{$K}_CLIENT_SECRET")) $out[$k] = $c;
        }
        return $out;
    }

    public static function callback(string $p): string { return abs_url('/account/social/' . $p . '/callback/'); }

    public static function authorizeUrl(string $p, ?string $next): ?string
    {
        $c = self::enabled()[$p] ?? null;
        if (!$c) return null;
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth'] = ['p' => $p, 's' => $state, 'n' => $next, 't' => time()];
        return $c['auth'] . '?' . http_build_query(['client_id' => Env::get('OAUTH_' . strtoupper($p) . '_CLIENT_ID'), 'redirect_uri' => self::callback($p), 'response_type' => 'code',
            'scope' => $c['scope'], 'state' => $state] + ($p === 'google' ? ['access_type' => 'online', 'prompt' => 'select_account'] : []));
    }

    /** @return array{uid:string,email:?string,verified:bool,name:string,avatar:?string}|null */
    public static function profile(string $p, string $code, string $state): ?array
    {
        $s = $_SESSION['oauth'] ?? null; unset($_SESSION['oauth']);
        $c = self::enabled()[$p] ?? null;
        if (!$c || !$s || $s['p'] !== $p || !hash_equals((string)$s['s'], $state) || time() - (int)$s['t'] > 900) return null;
        $K = strtoupper($p);
        $tok = self::http('POST', $c['token'], ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::callback($p),
            'client_id' => Env::get("OAUTH_{$K}_CLIENT_ID"), 'client_secret' => Env::get("OAUTH_{$K}_CLIENT_SECRET")]);
        $at = $tok['access_token'] ?? null;
        if (!$at) return null;
        $u = self::http('GET', $c['userinfo'], null, $at);
        if (!$u) return null;
        switch ($p) {
            case 'github':
                $email = null; $ver = false;
                foreach ((array)self::http('GET', $c['emails'], null, $at) as $e) if (is_array($e) && !empty($e['primary']) && !empty($e['verified'])) { $email = $e['email']; $ver = true; }
                return ['uid' => (string)($u['id'] ?? ''), 'email' => $email, 'verified' => $ver, 'name' => (string)($u['name'] ?? $u['login'] ?? ''), 'avatar' => $u['avatar_url'] ?? null, 'next' => $s['n'] ?? null];
            case 'facebook':
                return ['uid' => (string)($u['id'] ?? ''), 'email' => $u['email'] ?? null, 'verified' => !empty($u['email']), 'name' => (string)($u['name'] ?? ''), 'avatar' => null, 'next' => $s['n'] ?? null];
            default: // google, linkedin (OpenID Connect userinfo)
                return ['uid' => (string)($u['sub'] ?? ''), 'email' => $u['email'] ?? null, 'verified' => !empty($u['email_verified']), 'name' => (string)($u['name'] ?? ''), 'avatar' => $u['picture'] ?? null, 'next' => $s['n'] ?? null];
        }
    }

    private static function http(string $m, string $url, ?array $form, ?string $bearer = null): ?array
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        $h = ['Accept: application/json', 'User-Agent: W5D'];
        if ($bearer) $h[] = 'Authorization: Bearer ' . $bearer;
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => false]);
        if ($m === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query((array)$form)); }
        $raw = curl_exec($ch); curl_close($ch);
        $j = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($j) ? $j : null;
    }
}
