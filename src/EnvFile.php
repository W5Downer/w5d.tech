<?php
declare(strict_types=1);

namespace W5D;

/** Line-based .env editing shared by bin/install.php and the browser installer. Keeps comments and unknown keys. */
final class EnvFile
{
    /** Value as it must appear after KEY= (Env::load understands plain, 'single' and "double" quotes; there is no escaping). */
    public static function quote(string $v): string
    {
        $v = str_replace(["\r", "\n", "\0"], '', $v);
        if (preg_match('/^[A-Za-z0-9_.:\/@+\-]*$/', $v)) return $v;
        if (!str_contains($v, "'")) return "'" . $v . "'";
        if (!str_contains($v, '"')) return '"' . $v . '"';
        return "'" . str_replace("'", '', $v) . "'";
    }

    public static function set(string $text, string $key, string $value): string
    {
        $line = $key . '=' . self::quote($value);
        $re = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($re, $text)) return preg_replace($re, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $text, 1);
        return rtrim($text) . "\n" . $line . "\n";
    }

    public static function get(string $text, string $key): ?string
    {
        if (!preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $text, $m)) return null;
        $v = trim($m[1]);
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) return substr($v, 1, -1);
        return trim((string)preg_replace('/\s+#.*$/', '', $v));
    }
}
