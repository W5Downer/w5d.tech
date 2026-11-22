<?php
declare(strict_types=1);

namespace W5D;

/** Tiny curl wrapper for outbound server-to-server calls (payment gateways, captcha verification). Never follows redirects. */
final class Http
{
    /** @return array{status:int,json:?array,raw:string,error:?string} */
    public static function request(string $method, string $url, array $headers = [], ?array $json = null, ?array $form = null, int $timeout = 12): array
    {
        $out = ['status' => 0, 'json' => null, 'raw' => '', 'error' => null];
        if (!function_exists('curl_init')) { $out['error'] = 'curl missing'; return $out; }
        if (!self::urlAllowed($url)) { $out['error'] = 'url not allowed'; return $out; }
        $ch = curl_init($url);
        $h = ['Accept: application/json', 'User-Agent: W5D/1.0'];
        foreach ($headers as $k => $v) $h[] = $k . ': ' . $v;
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(6, $timeout), CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => strtoupper($method)];
        if ($json !== null) { $h[] = 'Content-Type: application/json'; $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
        elseif ($form !== null) $opts[CURLOPT_POSTFIELDS] = http_build_query($form);
        $opts[CURLOPT_HTTPHEADER] = $h;
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw === false) $out['error'] = curl_error($ch);
        $out['status'] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (is_string($raw)) { $out['raw'] = $raw; $j = json_decode($raw, true); if (is_array($j)) $out['json'] = $j; }
        return $out;
    }

    /** https only in production; plain http to loopback is allowed elsewhere (local stub gateway in tests). */
    public static function urlAllowed(string $url): bool
    {
        $p = parse_url($url);
        if (!$p || empty($p['host']) || !in_array($p['scheme'] ?? '', ['http', 'https'], true)) return false;
        if (($p['scheme'] ?? '') === 'https') return true;
        return Env::get('APP_ENV') !== 'production' && in_array($p['host'], ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
