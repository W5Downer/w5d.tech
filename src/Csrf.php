<?php
declare(strict_types=1);

namespace W5D;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(24));
    }

    public static function valid(?string $t): bool
    {
        return is_string($t) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
    }

    public static function check(Request $r): bool
    {
        return self::valid($r->post['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }
}
