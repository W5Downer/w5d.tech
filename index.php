<?php
declare(strict_types=1);

/**
 * Root front-controller shim: lets the site run when the hosting panel's document
 * root is the repository root instead of htdocs/. All real code stays outside the web path.
 * Preferred setup is still: document root = htdocs/.
 */
if (PHP_SAPI === 'cli-server') { // dev only: serve public files from htdocs/ like the .htaccess does
    $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $f = __DIR__ . '/htdocs' . $path;
    if (preg_match('#^/(assets/|favicon\.ico|apple-touch-icon\.png|manifest\.webmanifest|humans\.txt)#', $path) && is_file($f) && !str_contains($path, '..')) {
        $mime = ['css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'webmanifest' => 'application/manifest+json', 'txt' => 'text/plain'][strtolower(pathinfo($f, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($f);
        return true;
    }
}
require __DIR__ . '/htdocs/index.php';
