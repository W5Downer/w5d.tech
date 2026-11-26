<?php
declare(strict_types=1);

namespace W5D;

/** .env loader. Real environment variables win over the file. */
final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $v = trim($v);
                if ($v !== '' && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                    $v = substr($v, 1, -1);
                } else {
                    $v = trim(preg_replace('/\s+#.*$/', '', $v));
                }
                self::$vars[trim($k)] = $v;
            }
        }
    }

    /** In-memory values for the rest of this request (the browser installer uses them before .env exists). Real environment variables still win. */
    public static function override(array $kv): void { foreach ($kv as $k => $v) self::$vars[(string)$k] = (string)$v; }

    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false) return $real;
        return self::$vars[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** Public origin without trailing slash: APP_URL, else derived from the request (never empty on a web request). */
    public static function baseUrl(): string
    {
        $u = rtrim((string)self::get('APP_URL', ''), '/');
        if ($u !== '') return $u;
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) return '';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
