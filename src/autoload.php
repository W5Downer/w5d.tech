<?php
declare(strict_types=1);

// Minimal PSR-4-ish autoloader. No Composer.
//   W5D\Foo                 -> src/Foo.php
//   W5D\Controllers\Foo     -> src/controllers/Foo.php
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'W5D\\')) return;
    $rel = substr($class, 4);
    if (str_starts_with($rel, 'Controllers\\')) {
        $file = __DIR__ . '/controllers/' . str_replace('\\', '/', substr($rel, 12)) . '.php';
    } else {
        $file = __DIR__ . '/' . str_replace('\\', '/', $rel) . '.php';
    }
    if (is_file($file)) require $file;
});

require_once __DIR__ . '/helpers.php';
