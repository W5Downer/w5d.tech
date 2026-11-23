<?php
declare(strict_types=1);

namespace W5D;

/**
 * Declarative CRUD with ACL for the portal (config/resources.php).
 *
 * Three independent gates, all enforced server-side on every request:
 *   1. role perm  "<perm>"                  (read: menu + list)
 *   2. action perm "<perm>.create|update|delete"  (config/roles.php; owner "*" holds all)
 *   3. row scope   resource['scope'][role] => column that must equal the signed-in user id (e.g. manager sees only own teams)
 * A write that the resource does not declare in 'actions' does not exist for anybody, owner included.
 * Writes always carry country_id = 'tn'. Columns come only from the config file; values are bound parameters.
 */
final class Crud
{
    public const ACTIONS = ['create', 'update', 'delete'];

    public static function defs(): array { static $d; return $d ??= require ROOT . '/config/resources.php'; }
    public static function def(string $slug): ?array { return self::defs()[$slug] ?? null; }
    public static function has(string $slug): bool { return isset(self::defs()[$slug]); }

    public static function can(string $role, string $slug, string $action): bool
    {
        $d = self::def($slug);
        if (!$d || !in_array($action, $d['actions'], true)) return false;
        return ACL::can($role, $d['perm']) && ACL::can($role, $d['perm'] . '.' . $action);
    }

    /** @return list<string> writes this role may perform on the resource */
    public static function allowed(string $role, string $slug): array
    {
        return array_values(array_filter(self::ACTIONS, fn($a) => self::can($role, $slug, $a)));
    }

    public static function scopeCol(string $role, array $d): ?string { return $d['scope'][$role] ?? null; }

    public static function options(array $f): array
    {
        $o = $f['options'] ?? [];
        return $o instanceof \Closure ? $o() : $o;
    }

    public static function rows(string $slug, string $role, int $uid): array
    {
        $d = self::def($slug);
        if (!$d || !DB::available()) return [];
        $sql = "SELECT {$d['list']} FROM {$d['table']}";
        $p = []; $w = [];
        if (!empty($d['where'])) $w[] = '(' . $d['where'] . ')';
        if ($col = self::scopeCol($role, $d)) { $w[] = "$col = ?"; $p[] = $uid; }
        if ($w) $sql .= ' WHERE ' . implode(' AND ', $w);
        return DB::all($sql . " ORDER BY {$d['order']} LIMIT 300", $p);
    }

    /** One editable row (only the declared fields + id), or null when missing or outside the role's scope. */
    public static function find(string $slug, int $id, string $role, int $uid): ?array
    {
        $d = self::def($slug);
        if (!$d || $id < 1) return null;
        $sql = 'SELECT id, ' . implode(', ', array_keys($d['fields'])) . " FROM {$d['table']} WHERE id = ?";
        if (!empty($d['where'])) $sql .= ' AND (' . $d['where'] . ')';
        $p = [$id];
        if ($col = self::scopeCol($role, $d)) { $sql .= " AND $col = ?"; $p[] = $uid; }
        $r = DB::all($sql, $p);
        return $r[0] ?? null;
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} [values to write, errors per field] */
    public static function validate(array $d, array $post, string $role, int $uid): array
    {
        $vals = []; $err = [];
        $lock = self::scopeCol($role, $d);
        foreach ($d['fields'] as $name => $f) {
            $type = $f[0];
            if ($type === 'ro') continue;
            if (($f['scope_lock'] ?? false) && $lock === $name) { $vals[$name] = $uid; continue; }
            $raw = $post[$name] ?? '';
            $raw = is_scalar($raw) ? trim((string)$raw) : '';
            $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw) ?? '';
            $req = (bool)($f['req'] ?? false);
            $empty = $raw === '';
            if ($type === 'bool') { $vals[$name] = $raw === '1' ? 1 : 0; continue; }
            if ($empty) {
                if ($req) { $err[$name] = __('ux.f_required'); continue; }
                $vals[$name] = in_array($type, ['number', 'date'], true) ? ($f['default'] ?? null) : ($f['default'] ?? '');
                if ($type === 'select' && !array_key_exists('', self::options($f))) $vals[$name] = $f['default'] ?? array_key_first(self::options($f));
                continue;
            }
            if (isset($f['max']) && mb_strlen($raw) > $f['max']) { $err[$name] = __('ux.f_toolong') . ' (' . $f['max'] . ')'; continue; }
            if (isset($f['pattern']) && !preg_match($f['pattern'], $raw)) { $err[$name] = __('ux.f_invalid'); continue; }
            switch ($type) {
                case 'email':
                    if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) { $err[$name] = __('ux.f_invalid'); continue 2; }
                    $vals[$name] = mb_strtolower($raw); break;
                case 'number':
                    if (!is_numeric($raw)) { $err[$name] = __('ux.f_invalid'); continue 2; }
                    $n = isset($f['step']) ? round((float)$raw, 2) : (int)$raw;
                    if ((isset($f['min']) && $n < $f['min']) || (isset($f['maxv']) && $n > $f['maxv'])) { $err[$name] = __('ux.f_range') . ' ' . ($f['min'] ?? '…') . ' – ' . ($f['maxv'] ?? '…'); continue 2; }
                    $vals[$name] = $n; break;
                case 'date':
                    $ok = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
                    if (!$ok) { $err[$name] = __('ux.f_invalid'); continue 2; }
                    $vals[$name] = $raw; break;
                case 'select':
                    if (!array_key_exists($raw, self::options($f))) { $err[$name] = __('ux.f_invalid'); continue 2; }
                    $vals[$name] = $raw; break;
                default:
                    $vals[$name] = $raw;
            }
        }
        return [$vals, $err];
    }

    private static function stamp(array $d, int $uid): array
    {
        $o = [];
        foreach ($d['stamp'] ?? [] as $col => $what) if ($what === 'uid') $o[$col] = $uid;
        return $o;
    }

    public static function create(string $slug, array $vals, int $uid): int
    {
        $d = self::def($slug);
        return (int)DB::insert($d['table'], ['country_id' => 'tn'] + $vals + self::stamp($d, $uid));
    }

    public static function update(string $slug, int $id, array $vals, string $role, int $uid): bool
    {
        $d = self::def($slug);
        $vals += self::stamp($d, $uid);
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($vals)));
        $sql = "UPDATE {$d['table']} SET $set WHERE id = ?";
        $p = [...array_values($vals), $id];
        if ($col = self::scopeCol($role, $d)) { $sql .= " AND $col = ?"; $p[] = $uid; }
        return DB::q($sql, $p)->rowCount() > 0;
    }

    public static function delete(string $slug, int $id, string $role, int $uid): bool
    {
        $d = self::def($slug);
        $sql = "DELETE FROM {$d['table']} WHERE id = ?";
        $p = [$id];
        if ($col = self::scopeCol($role, $d)) { $sql .= " AND $col = ?"; $p[] = $uid; }
        return DB::q($sql, $p)->rowCount() > 0;
    }

    /** Read-only permission matrix for the owner's "Users, Roles & Permissions" screen. */
    public static function matrix(): array
    {
        $out = [];
        foreach (self::defs() as $slug => $d) {
            $row = ['slug' => $slug, 'title' => $d['title'], 'cells' => []];
            foreach (array_keys(ACL::matrix()['roles']) as $role) {
                $row['cells'][$role] = [
                    'read' => ACL::can($role, $d['perm']),
                    'create' => self::can($role, $slug, 'create'), 'update' => self::can($role, $slug, 'update'), 'delete' => self::can($role, $slug, 'delete'),
                    'scope' => self::scopeCol($role, $d),
                ];
            }
            $out[] = $row;
        }
        return $out;
    }
}
