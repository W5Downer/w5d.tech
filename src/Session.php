<?php
declare(strict_types=1);

namespace W5D;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || Env::get('APP_ENV') === 'production';
        session_name('w5d_sid');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        $dir = ROOT . '/storage/sessions';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
        session_start();
    }

    public static function flash(string $k, mixed $v = null): mixed
    {
        if ($v !== null) { $_SESSION['_flash'][$k] = $v; return null; }
        $x = $_SESSION['_flash'][$k] ?? null;
        unset($_SESSION['_flash'][$k]);
        return $x;
    }
}
