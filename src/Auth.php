<?php
declare(strict_types=1);

namespace W5D;

/**
 * Portal session. There is NO password for portal roles: the owner (OWNER_EMAIL in .env) and every team member
 * (admin, leader, manager, member) sign in with an @w5d.tech address and an e-mailed one-time code (StaffAuth).
 * Role + permission matrix is declared in config/roles.php (ACL).
 */
final class Auth
{
    public static function check(): bool { return !empty($_SESSION['uid']) && StaffAuth::alive(); }
    public static function user(): ?array { return self::check() ? ($_SESSION['user'] ?? null) : null; }
    public static function role(): string { return (string)(self::user()['role'] ?? 'guest'); }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
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
