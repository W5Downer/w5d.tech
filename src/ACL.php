<?php
declare(strict_types=1);

namespace W5D;

final class ACL
{
    public static function matrix(): array { static $m; return $m ??= require ROOT . '/config/roles.php'; }

    public static function can(string $role, string $perm): bool
    {
        $p = self::matrix()['roles'][$role] ?? [];
        return in_array('*', $p, true) || in_array($perm, $p, true);
    }

    /** @return list<array> menus visible to a role */
    public static function menus(string $role): array
    {
        return array_values(array_filter(self::matrix()['menus'], fn($m) => self::can($role, $m['perm'])));
    }
}
