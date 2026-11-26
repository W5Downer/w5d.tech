<?php
declare(strict_types=1);

namespace W5D;

/**
 * Requirement report shared by the browser installer, bin/install.php, bin/preflight.php and the owner's System screen.
 * Each row: id, group (php|ext|opt|dir|file|web), need (required|recommended), ok, name, detail (English, short).
 * The browser installer maps the id to a translated explanation; nothing here prints a secret.
 */
final class SysCheck
{
    public const PHP_MIN = 80400;

    /** extension => what it is used for (required: the site cannot run without it) */
    public const REQUIRED = [
        'pdo_sqlite' => 'database (SQLite)',
        'mbstring' => 'multi-language text (English, French, Arabic)',
        'json' => 'data files and the JSON API',
        'openssl' => 'encryption and signed links',
        'curl' => 'payment providers and social sign-in',
        'sodium' => 'encrypted settings (secret box)',
        'ctype' => 'input checks in the settings forms',
        'session' => 'sign-in sessions',
        'filter' => 'e-mail and URL validation',
    ];

    /** extension => what it is used for (recommended: a feature is switched off without it) */
    public const OPTIONAL = [
        'gd' => 'owner image library: converts pictures to WebP (needs WebP support)',
        'zip' => 'owner image library: ZIP uploads',
        'iconv' => 'owner image library: clean file names',
    ];

    public const FOLDERS = ['storage', 'storage/logs', 'storage/sessions', 'storage/cache', 'storage/framework/views', 'storage/uploads', 'storage/archive', 'storage/backups', 'storage/incoming'];

    /**
     * @param list<string>|null $loaded  extension names to pretend are loaded (tests only); null = the real list
     * @param bool $create               create missing storage folders (the browser installer does; --check does not)
     * @param bool $web                  add the checks that only make sense for a web request (https, document root, time limit)
     * @return list<array{id:string,group:string,need:string,ok:bool,name:string,detail:string}>
     */
    public static function requirements(?array $loaded = null, bool $create = true, bool $web = false, ?int $phpId = null): array
    {
        $rows = [];
        $has = fn(string $x): bool => $loaded === null ? extension_loaded($x) : in_array($x, $loaded, true);
        $add = function (string $id, string $group, string $need, bool $ok, string $name, string $detail) use (&$rows): void {
            $rows[] = ['id' => $id, 'group' => $group, 'need' => $need, 'ok' => $ok, 'name' => $name, 'detail' => $detail];
        };
        $v = $phpId ?? PHP_VERSION_ID;
        $add('php', 'php', 'required', $v >= self::PHP_MIN, 'PHP ' . ($phpId === null ? PHP_VERSION : (string)$phpId), $v >= self::PHP_MIN ? '8.4 or newer: OK' : 'too old: 8.4 or newer is required (ask the host to select PHP 8.4 for this site and for the command line)');
        foreach (self::REQUIRED as $x => $why) {
            $add('ext:' . $x, 'ext', 'required', $has($x), $x, $has($x) ? $why : "missing ($why): enable it in the hosting panel or php.ini");
        }
        $gdOk = $has('gd') && ($loaded !== null || function_exists('imagewebp'));
        $add('ext:gd', 'opt', 'recommended', $gdOk, 'gd (WebP)', $gdOk ? self::OPTIONAL['gd'] : 'missing: the image library cannot convert pictures; the public site is unaffected');
        foreach (['zip', 'iconv'] as $x) $add('ext:' . $x, 'opt', 'recommended', $has($x), $x, $has($x) ? self::OPTIONAL[$x] : 'missing: ' . self::OPTIONAL[$x] . ' is switched off; the public site is unaffected');
        $add('argon2', 'opt', 'recommended', defined('PASSWORD_ARGON2ID'), 'Argon2id', defined('PASSWORD_ARGON2ID') ? 'available for password hashes' : 'not compiled in: hashes fall back to bcrypt (still safe)');

        foreach (self::FOLDERS as $d) {
            $p = ROOT . '/' . $d;
            if (!is_dir($p) && $create) @mkdir($p, 0775, true);
            $ok = is_dir($p) && is_writable($p);
            $add('dir:' . $d, 'dir', 'required', $ok, $d . '/', $ok ? 'writable' : (is_dir($p) ? 'not writable by the PHP user: chmod u+w, or chown it to that user' : ($create ? 'cannot be created: create it by hand and make it writable' : 'missing: the installer creates it')));
        }
        $env = ROOT . '/.env';
        $envOk = is_file($env) ? is_writable($env) : is_writable(ROOT);
        $add('file:env', 'file', 'required', $envOk, '.env', $envOk ? (is_file($env) ? 'exists and is writable' : 'can be created') : 'cannot be created: make the project folder writable for the installer, or create an empty .env file and make it writable');
        $add('file:example', 'file', 'recommended', is_readable(ROOT . '/.env.example'), '.env.example', is_readable(ROOT . '/.env.example') ? 'found (template of the production settings)' : 'missing: the installer writes only the settings it asks for');

        if ($web) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            $add('web:https', 'web', 'recommended', $https, 'HTTPS', $https ? 'this page is served over https' : 'this page is served over plain http: passwords typed here travel unencrypted. Turn on HTTPS first if you can');
            $dr = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
            $drReal = $dr !== '' ? realpath($dr) : false;
            $atRoot = $drReal !== false && $drReal === realpath(ROOT);
            $add('web:docroot', 'web', 'recommended', !$atRoot, 'document root', $atRoot ? 'is the project folder: point it at the htdocs folder (the root .htaccess hides internals on Apache only)' : 'is not the project folder');
            $max = (int)ini_get('max_execution_time');
            $add('web:time', 'web', 'recommended', $max === 0 || $max >= 30, 'max_execution_time', $max === 0 || $max >= 30 ? ($max === 0 ? 'unlimited' : $max . ' s') : $max . ' s: the install may need up to 30 s; raise it if the last step times out');
        }
        return $rows;
    }

    /** @param list<array{need:string,ok:bool}> $rows */
    public static function failCount(array $rows): int
    {
        return count(array_filter($rows, fn($r) => $r['need'] === 'required' && !$r['ok']));
    }

    /** @param list<array{need:string,ok:bool}> $rows */
    public static function warnCount(array $rows): int
    {
        return count(array_filter($rows, fn($r) => $r['need'] !== 'required' && !$r['ok']));
    }

    /** Installer files that should be deleted from a finished site (relative to the project folder). */
    public static function installerFiles(): array
    {
        $o = [];
        if (is_dir(ROOT . '/install')) $o[] = 'install/';
        if (is_file(ROOT . '/bin/install.php')) $o[] = 'bin/install.php';
        return $o;
    }
}
