<?php
declare(strict_types=1);

namespace W5D;

/**
 * Generic owner configuration screens (payment methods, third-party integrations).
 * A definition array (config/gateways.php, config/integrations.php) declares items and fields; this class
 * reads effective values, validates, saves (secrets encrypted, never shown again, never logged) and builds the view model.
 */
final class SettingsForm
{
    public static function key(string $prefix, string $id, string $k): string { return $prefix . '.' . $id . '.' . $k; }

    public static function field(array $defs, string $id, string $k): ?array
    {
        foreach (($defs[$id]['fields'] ?? []) as $f) if ($f['k'] === $k) return $f;
        return null;
    }

    /** Effective value: owner setting, else legacy .env (def['env']), else the field default. */
    public static function cfg(array $defs, string $prefix, string $id, string $k): string
    {
        if (!isset($defs[$id])) return '';
        $v = Settings::get(self::key($prefix, $id, $k));
        if ($v !== null) return $v;
        $env = $defs[$id]['env'][$k] ?? null;
        if ($env && ($e = Env::get($env)) !== null && $e !== '') return $e;
        return (string)(self::field($defs, $id, $k)['default'] ?? '');
    }

    public static function on(array $defs, string $prefix, string $id): bool
    {
        $s = Settings::get(self::key($prefix, $id, 'enabled'));
        if ($s !== null) return in_array($s, ['1', 'true', 'on'], true);
        $env = $defs[$id]['env']['enabled'] ?? null;
        return $env ? Env::bool($env) : false;
    }

    private static function validValue(array $f, string $v): bool
    {
        if ($v === '') return true;
        return match ($f['t']) {
            'url' => (bool)preg_match('#^https://[A-Za-z0-9.\-]+(:\d+)?(/[^\s]*)?$#', $v) || (Env::get('APP_ENV') !== 'production' && (bool)preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?(/[^\s]*)?$#', $v)),
            'int' => ctype_digit($v) && (int)$v >= (int)($f['min'] ?? 0) && (int)$v <= (int)($f['max'] ?? PHP_INT_MAX),
            'select' => isset(($f['options'] ?? [])[$v]),
            'bool' => in_array($v, ['0', '1'], true),
            'secret' => mb_strlen($v) >= 4 && mb_strlen($v) <= 400 && !preg_match('/[\x00-\x1f]/', $v),
            default => mb_strlen($v) <= 300 && !preg_match('/[\x00-\x1f<>]/', $v) && (empty($f['pattern']) || (bool)preg_match($f['pattern'], $v)),
        };
    }

    /** @return string[] labels of required fields that are empty or invalid */
    public static function missing(array $defs, string $prefix, string $id): array
    {
        $out = [];
        foreach (($defs[$id]['fields'] ?? []) as $f) {
            if (empty($f['req'])) continue;
            $v = self::cfg($defs, $prefix, $id, $f['k']);
            if ($v === '' || !self::validValue($f, $v)) $out[] = $f['label'];
        }
        return $out;
    }

    public static function ready(array $defs, string $prefix, string $id): bool { return isset($defs[$id]) && self::on($defs, $prefix, $id) && !self::missing($defs, $prefix, $id); }

    /** View model for admin/menu.blade.php (kind=settings). Secrets are reported only as "set / not set". */
    public static function view(array $defs, string $prefix, string $action, string $intro, array $extra = []): array
    {
        $items = [];
        foreach ($defs as $id => $d) {
            $fields = [];
            foreach ($d['fields'] ?? [] as $f) {
                $isSecret = $f['t'] === 'secret';
                $fields[] = $f + ['value' => $isSecret ? '' : self::cfg($defs, $prefix, $id, $f['k']), 'is_set' => $isSecret ? Settings::isSet(self::key($prefix, $id, $f['k'])) : null];
            }
            $items[] = ['id' => $id, 'name' => $d['name'], 'doc' => $d['doc'] ?? '', 'icon' => $d['icon'] ?? 'plug', 'kind' => $d['kind'] ?? '', 'on' => self::on($defs, $prefix, $id), 'ready' => self::ready($defs, $prefix, $id),
                'missing' => self::missing($defs, $prefix, $id), 'fields' => $fields, 'hook' => !empty($d['webhook']) ? abs_url('/pay/hook/' . $id . '/') : null, 'note' => $d['warn'] ?? null, 'src' => isset($d['env']) && !Settings::isSet(self::key($prefix, $id, 'enabled')) ? 'env' : 'portal'];
        }
        $hist = [];
        try { $hist = DB::all("SELECT skey, action, old_value, new_value, changed_by, note, changed_at FROM settings_audit WHERE skey LIKE ? ORDER BY id DESC LIMIT 25", [$prefix . '.%']); } catch (\Throwable) {}
        return ['kind' => 'settings', 'items' => $items, 'action' => $action, 'intro' => $intro, 'hist' => $hist] + $extra;
    }

    /** Apply a posted form. Returns [changedCount, errors[]]. Caller checks permission + CSRF first. */
    public static function save(array $defs, string $prefix, array $post, string $by, string $note): array
    {
        $changed = 0; $errs = [];
        foreach ($defs as $id => $d) {
            if (!isset($post['item'][$id]) && !isset($post['f'][$id])) continue;
            $in = is_array($post['f'][$id] ?? null) ? $post['f'][$id] : [];
            $clear = is_array($post['clear'][$id] ?? null) ? $post['clear'][$id] : [];
            foreach ($d['fields'] ?? [] as $f) {
                $k = $f['k']; $sk = self::key($prefix, $id, $k);
                if ($f['t'] === 'secret') {
                    if (isset($clear[$k])) { if (Settings::set($sk, '', true, $by, $note)) $changed++; continue; }
                    $v = trim((string)($in[$k] ?? ''));
                    if ($v === '') continue;
                    if (!self::validValue($f, $v)) { $errs[] = $d['name'] . ' · ' . $f['label']; continue; }
                    if (Settings::set($sk, $v, true, $by, $note)) $changed++;
                    continue;
                }
                if ($f['t'] === 'bool') $v = isset($in[$k]) ? '1' : '0';
                else $v = trim((string)($in[$k] ?? ''));
                if ($v === '' && ($f['default'] ?? '') !== '' && $f['t'] !== 'url') $v = (string)$f['default'];
                if (!self::validValue($f, $v)) { $errs[] = $d['name'] . ' · ' . $f['label']; continue; }
                // store an explicit empty only by clearing; unchanged-vs-default values are still stored so the owner's choice is explicit
                if (Settings::set($sk, $v, false, $by, $note)) $changed++;
            }
            $want = isset($post['on'][$id]) ? '1' : '0';
            $sk = self::key($prefix, $id, 'enabled');
            $cur = self::on($defs, $prefix, $id) ? '1' : '0';
            if ($want === '1' && self::missing($defs, $prefix, $id)) { $errs[] = $d['name'] . ': cannot be enabled, missing ' . implode(', ', self::missing($defs, $prefix, $id)); $want = '0'; }
            if ($want !== $cur || Settings::get($sk) === null && $want === '0' && isset($post['item'][$id]) && false) {
                if (Settings::set($sk, $want, false, $by, $note)) $changed++;
            }
        }
        return [$changed, $errs];
    }
}
