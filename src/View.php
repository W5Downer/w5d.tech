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
        return ViewCache::compiled($this->path);
    }
}
