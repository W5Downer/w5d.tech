<?php
declare(strict_types=1);

namespace W5D;

final class Config
{
    private static ?array $app = null;

    public static function app(string $key, mixed $d = null): mixed
    {
        self::$app ??= require ROOT . '/config/app.php';
        $v = self::$app;
        foreach (explode('.', $key) as $p) {
            if (!is_array($v) || !array_key_exists($p, $v)) return $d;
            $v = $v[$p];
        }
        return $v;
    }

    public static function template(): string { return (string)self::app('template', 'w5d'); }
}
