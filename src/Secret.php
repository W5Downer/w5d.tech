<?php
declare(strict_types=1);

namespace W5D;

/** Application secret for HMAC links (payment page access, etc.). APP_KEY in .env wins; otherwise generated once into storage/app.key (0600). */
final class Secret
{
    private static ?string $key = null;

    public static function key(): string
    {
        if (self::$key) return self::$key;
        $k = (string)Env::get('APP_KEY', '');
        if ($k === '') {
            $f = ROOT . '/storage/app.key';
            if (!is_file($f)) { @mkdir(dirname($f), 0775, true); @file_put_contents($f, bin2hex(random_bytes(32))); @chmod($f, 0600); }
            $k = (string)@file_get_contents($f);
            if ($k === '') $k = hash('sha256', (string)Env::get('WEBMASTER_PASSWORD_HASH', '') . ROOT);
        }
        return self::$key = $k;
    }

    public static function sign(string $msg, int $len = 24): string { return substr(hash_hmac('sha256', $msg, self::key()), 0, $len); }
    public static function verify(string $msg, string $sig): bool { return $sig !== '' && hash_equals(self::sign($msg, strlen($sig)), $sig); }
}
