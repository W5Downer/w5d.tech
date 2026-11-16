<?php
declare(strict_types=1);

namespace W5D;

/** Tiny file-based sliding counter: RateLimit::hit('key', max, windowSeconds) => false when exceeded. */
final class RateLimit
{
    public static function hit(string $key, int $max, int $window): bool
    {
        $dir = ROOT . '/storage/cache/rl';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $f = $dir . '/' . sha1($key) . '.json';
        $now = time();
        $fh = @fopen($f, 'c+');
        if (!$fh) return true;
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $hits = array_values(array_filter((array)json_decode($raw ?: '[]', true), fn($t) => $t > $now - $window));
        $ok = count($hits) < $max;
        if ($ok) $hits[] = $now;
        ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($hits));
        flock($fh, LOCK_UN); fclose($fh);
        return $ok;
    }
}
