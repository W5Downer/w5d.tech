<?php
declare(strict_types=1);

namespace W5D;

/**
 * Owner/portal authentication. The webmaster account lives in .env
 * (WEBMASTER_USERNAME / WEBMASTER_PASSWORD_HASH, Argon2id or bcrypt).
 * Role + permission matrix is declared in config/roles.php (ACL).
 */
final class Auth
{
    public static function check(): bool { return !empty($_SESSION['uid']); }
    public static function user(): ?array { return self::check() ? ($_SESSION['user'] ?? null) : null; }
    public static function role(): string { return (string)(self::user()['role'] ?? 'guest'); }

    public static function attempt(string $user, string $pass, string $ip): bool
    {
        if (!RateLimit::hit('login:' . $ip, 8, 900)) return false;
        $u = (string)Env::get('WEBMASTER_USERNAME', 'owner');
        $h = (string)Env::get('WEBMASTER_PASSWORD_HASH', '');
        if ($h === '' || !hash_equals($u, $user) || !password_verify($pass, $h)) {
            usleep(250000);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = 1;
        $_SESSION['user'] = ['id' => 1, 'name' => $u, 'role' => 'owner', 'country' => 'tn'];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function can(string $perm): bool
    {
        return ACL::can(self::role(), $perm);
    }

    public static function require(string $perm): ?Response
    {
        if (!self::check()) return Response::redirect(admin_url('login'));
        if (!self::can($perm)) return Response::html(view('admin.forbidden', ['title' => 'Forbidden', 'menus' => ACL::menus(self::role()), 'user' => self::user(), 'active' => '']), 403)->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store']);
        return null;
    }
}
