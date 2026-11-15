<?php
declare(strict_types=1);

namespace W5D;

/** A compiled+cached Blade view bound to the shared ViewEnv. */
final class View
{
    public function __construct(private ViewEnv $env, private string $path, private array $data) {}

    public static function classes(array|string $c): string
    {
        if (is_string($c)) return $c;
        $o = [];
        foreach ($c as $k => $v) {
            if (is_int($k)) { if ($v) $o[] = $v; } elseif ($v) { $o[] = $k; }
        }
        return implode(' ', $o);
    }

    public function render(): string
    {
        $compiled = $this->compiled();
        $level = ob_get_level();
        $run = static function (string $__path, array $__data, ViewEnv $__env): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__path;
            return (string)ob_get_clean();
        };
        try {
            return $run($compiled, $this->data, $this->env);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) ob_end_clean();
            throw $e;
        }
    }

    private function compiled(): string
    {
        $dir = ROOT . '/storage/cache/views';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir . '/' . sha1($this->path) . '.php';
        if (!is_file($file) || filemtime($file) < filemtime($this->path)) {
            $php = (new Blade())->compile((string)file_get_contents($this->path));
            $tmp = $file . '.' . bin2hex(random_bytes(4));
            file_put_contents($tmp, $php);
            rename($tmp, $file);
        }
        return $file;
    }
}
