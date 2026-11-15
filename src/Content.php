<?php
declare(strict_types=1);

namespace W5D;

/** JSON content store under data/content/{collection}/{slug}.json. Single source of truth. */
final class Content
{
    private static array $cache = [];

    public static function all(string $collection): array
    {
        if (isset(self::$cache[$collection])) return self::$cache[$collection];
        $out = [];
        $files = glob(ROOT . "/data/content/{$collection}/*.json") ?: [];
        sort($files);
        foreach ($files as $f) {
            $j = json_decode((string)file_get_contents($f), true);
            if (!is_array($j)) continue;
            $slug = $j['slug'] ?? basename($f, '.json');
            $j['slug'] = $slug;
            $out[$slug] = $j;
        }
        if (in_array($collection, ['services', 'projects', 'macros'], true)) {
            uasort($out, fn($a, $b) => ($a['id'] ?? $a['order'] ?? 0) <=> ($b['id'] ?? $b['order'] ?? 0));
        }
        return self::$cache[$collection] = $out;
    }

    public static function get(string $collection, string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $slug)) return null;
        return self::all($collection)[$slug] ?? null;
    }

    public static function count(string $collection): int
    {
        if (isset(self::$cache[$collection])) return count(self::$cache[$collection]);
        return count(glob(ROOT . "/data/content/{$collection}/*.json") ?: []);
    }

    public static function page(string $slug): ?array { return self::get('pages', $slug); }
    public static function slider(string $name): ?array { return self::get('sliders', $name); }

    /** @return array<string,array> services of one macro, ordered by id */
    public static function servicesOf(string $macro): array
    {
        return array_filter(self::all('services'), fn($s) => ($s['macro'] ?? '') === $macro);
    }

    public static function file(string $rel): mixed
    {
        $f = ROOT . '/data/' . ltrim($rel, '/');
        if (!is_file($f)) return null;
        return self::$cache['file:' . $rel] ??= json_decode((string)file_get_contents($f), true);
    }
}
