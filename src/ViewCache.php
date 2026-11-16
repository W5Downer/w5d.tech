<?php
declare(strict_types=1);

namespace W5D;

/**
 * Compiled-view cache, modelled on Laravel's (storage/framework/views, `view:cache`, `view:clear`).
 *
 *  - compiled file name = sha1(source path + compiler version) . '.php'   (a compiler upgrade invalidates everything)
 *  - first line of every compiled file records its source:  <?php /**PATH /abs/source.blade.php ENDPATH**​/ ?>
 *  - VIEW_CACHE=false         -> never write to disk (compile in memory on every call; for debugging)
 *  - VIEW_CACHE_CHECK=true    -> recompile when the source is newer than the compiled file (default in local/dev)
 *  - VIEW_CACHE_CHECK=false   -> trust the cache: zero stat() calls on sources (default in production; run `php bin/view.php cache`
 *                               at deploy time, or `clear` after editing a template)
 */
final class ViewCache
{
    /** Bump when Blade.php changes how templates compile. */
    public const COMPILER = '3';

    /** @var array<string,string> per-request memo: source => compiled path */
    private static array $memo = [];

    public static function dir(): string
    {
        $d = ROOT . '/storage/framework/views';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    public static function enabled(): bool { return Env::bool('VIEW_CACHE', true); }

    public static function checkSources(): bool
    {
        $v = Env::get('VIEW_CACHE_CHECK');
        return $v === null || $v === '' ? Env::get('APP_ENV', 'production') !== 'production' : Env::bool('VIEW_CACHE_CHECK');
    }

    public static function pathFor(string $source): string
    {
        return self::dir() . '/' . sha1($source . '|' . self::COMPILER) . '.php';
    }

    public static function isExpired(string $source): bool
    {
        $c = self::pathFor($source);
        if (!is_file($c)) return true;
        return self::checkSources() && filemtime($source) >= filemtime($c);
    }

    /** Returns the PHP file to include for a Blade source (compiling + writing it when needed). */
    public static function compiled(string $source): string
    {
        if (isset(self::$memo[$source])) return self::$memo[$source];
        $c = self::pathFor($source);
        if (!self::enabled()) {
            $c = sys_get_temp_dir() . '/w5d-view-' . sha1($source . '|' . self::COMPILER . '|' . bin2hex(random_bytes(4))) . '.php';
            self::write($c, $source);
            register_shutdown_function(static fn() => @unlink($c));
            return $c;
        }
        if (self::isExpired($source)) self::write($c, $source);
        return self::$memo[$source] = $c;
    }

    private static function write(string $file, string $source): void
    {
        $php = (new Blade())->compile((string)file_get_contents($source));
        $php .= "\n<?php /**PATH " . $source . " ENDPATH**/ ?>";
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $php, LOCK_EX) === false) throw new \RuntimeException('View cache directory is not writable: ' . dirname($file));
        @rename($tmp, $file);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
    }

    /** @return list<string> every *.blade.php under views/ */
    public static function sources(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ROOT . '/views', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $p = $f->getPathname();
            if (str_ends_with($p, '.blade.php') && !str_contains($p, '/views/static/')) $out[] = $p;
        }
        sort($out);
        return $out;
    }

    /** `view:cache` — compile every template ahead of time. @return array{compiled:int,errors:array<string,string>} */
    public static function warm(): array
    {
        $n = 0; $err = [];
        foreach (self::sources() as $s) {
            try {
                $php = (new Blade())->compile((string)file_get_contents($s));
                $tmp = sys_get_temp_dir() . '/w5d-lint-' . bin2hex(random_bytes(4)) . '.php';
                file_put_contents($tmp, $php);
                $o = []; $rc = 0; exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
                @unlink($tmp);
                if ($rc !== 0) { $err[str_replace(ROOT . '/', '', $s)] = trim(implode(' ', $o)); continue; }
                self::write(self::pathFor($s), $s);
                $n++;
            } catch (\Throwable $e) { $err[str_replace(ROOT . '/', '', $s)] = $e->getMessage(); }
        }
        return ['compiled' => $n, 'errors' => $err];
    }

    /** `view:clear` — @return int files removed */
    public static function clear(): int
    {
        $n = 0;
        foreach ([ROOT . '/storage/framework/views', ROOT . '/storage/cache/views'] as $d) {
            foreach (glob($d . '/*.php') ?: [] as $f) { if (@unlink($f)) $n++; }
        }
        self::$memo = [];
        return $n;
    }

    /** @return array{dir:string,files:int,bytes:int,enabled:bool,check_sources:bool,templates:int} */
    public static function stats(): array
    {
        $files = glob(self::dir() . '/*.php') ?: [];
        $b = 0; foreach ($files as $f) $b += (int)filesize($f);
        return ['dir' => 'storage/framework/views', 'files' => count($files), 'bytes' => $b, 'enabled' => self::enabled(), 'check_sources' => self::checkSources(), 'templates' => count(self::sources())];
    }
}
