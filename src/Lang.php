<?php
declare(strict_types=1);

namespace W5D;

/** Language detection: cookie -> Accept-Language -> env. No ?lang= URLs. */
final class Lang
{
    public const SUPPORTED = ['en', 'fr', 'ar'];
    private static ?string $cur = null;
    private static array $files = [];

    public static function current(): string
    {
        if (self::$cur !== null) return self::$cur;
        $c = $_COOKIE['w5d_lang'] ?? null;
        if (!in_array($c, self::SUPPORTED, true)) {
            $c = null;
            foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') as $part) {
                $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
                if (in_array($code, self::SUPPORTED, true)) { $c = $code; break; }
            }
        }
        return self::$cur = $c ?? (string)Env::get('APP_LANG', 'en');
    }

    public static function set(string $code): void
    {
        if (!in_array($code, self::SUPPORTED, true)) return;
        self::$cur = $code;
        setcookie('w5d_lang', $code, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => false]);
    }

    public static function dir(): string { return self::current() === 'ar' ? 'rtl' : 'ltr'; }

    public static function line(string $key, ?string $l = null): string
    {
        $l ??= self::current();
        foreach ([$l, 'en'] as $code) {
            self::$files[$code] ??= require ROOT . "/lang/{$code}.php";
            $v = self::$files[$code];
            foreach (explode('.', $key) as $p) {
                if (!is_array($v) || !array_key_exists($p, $v)) { $v = null; break; }
                $v = $v[$p];
            }
            if (is_string($v)) return $v;
        }
        return $key;
    }

    public static function names(): array { return ['en' => 'English', 'fr' => 'Français', 'ar' => 'العربية']; }
}
