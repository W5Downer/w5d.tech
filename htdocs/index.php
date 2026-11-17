<?php
declare(strict_types=1);

/** Single front controller. Document root = htdocs/. Everything else lives one level up (never web-served). */
/* PHP built-in server (dev): let it serve real files (assets) itself, like Apache's RewriteCond -f. */
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($f !== __FILE__ && is_file($f) && !preg_match('/\.(php|env|sqlite|sql|log|md|json5?)$/i', $f)) return false;
}
define('ROOT', dirname(__DIR__));
if (!function_exists('str_starts_with')) { http_response_code(500); exit('PHP 8.1+ required'); }
require ROOT . '/src/autoload.php';

use W5D\{App};

App::run();
