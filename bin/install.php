<?php
declare(strict_types=1);
/**
 * First-upload installer: run it ONCE on the server after the files are uploaded (SSH), then again whenever you want a re-check.
 *
 *   php bin/install.php                         guided: asks for the few values it cannot guess
 *   php bin/install.php --check                 read-only: report what is missing, change nothing
 *   php bin/install.php --yes --url=https://example.org --owner=owner@example.org [--mount=my-secret-path] [--domain=example.org]
 *                                               non-interactive (CI, provisioning scripts)
 *   php bin/install.php --force-env             rebuild .env from .env.example (the old one is kept as .env.bak-<time>)
 *
 * What it does (each step prints OK / WARN / FAIL and is safe to repeat):
 *   1. checks PHP >= 8.4 and the needed extensions (gd with WebP and zip are WARN only: they serve the owner image library)
 *   2. creates storage/ folders and tightens permissions
 *   3. creates .env from .env.example (never overwrites an existing one) and fills APP_ENV, APP_URL, OWNER_EMAIL, STAFF_EMAIL_DOMAIN and a
 *      random ADMIN_URL_PATH; optionally stores the owner password as an Argon2id hash (the password itself is never written anywhere)
 *   4. applies the database migrations and syncs the content reference tables
 *   5. creates the signing key storage/app.key (back it up together with the database)
 *   6. compiles the view cache, runs bin/preflight.php, prints the cron lines and the web-server checklist
 *
 * It never sends e-mail, never touches payments (PAYMENT_MODE stays demo) and never prints a secret except the portal address you must note down.
 * Untested on real shared hosts: see docs/INSTALL.md, "What this installer cannot know".
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--')) { $p = explode('=', substr($a, 2), 2); $opt[$p[0]] = $p[1] ?? true; }
}
$check = isset($opt['check']);
$yes = isset($opt['yes']) || $check;
$tty = function_exists('posix_isatty') && @posix_isatty(STDIN) && !$yes;
$fails = 0; $warns = 0;

$say = function (string $level, string $what, string $detail = '') use (&$fails, &$warns): void {
    if ($level === 'FAIL') $fails++;
    if ($level === 'WARN') $warns++;
    printf("  %-4s  %-34s %s\n", $level, $what, $detail);
};
$head = fn(string $t) => print("\n== $t\n");
$ask = function (string $q, string $default = '') use ($tty): string {
    if (!$tty) return $default;
    fwrite(STDERR, $q . ($default !== '' ? " [$default]" : '') . ': ');
    $l = trim((string)fgets(STDIN));
    return $l === '' ? $default : $l;
};
$askHidden = function (string $q) use ($tty): string {
    if (!$tty) return '';
    fwrite(STDERR, $q . ': ');
    @shell_exec('stty -echo');
    $l = rtrim((string)fgets(STDIN), "\r\n");
    @shell_exec('stty echo');
    fwrite(STDERR, "\n");
    return $l;
};
$php = PHP_BINARY;
$sub = function (string $script, array $args = []) use ($php, $root): array {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($root . '/bin/' . $script) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    exec($cmd, $out, $code);
    return [$code, implode("\n", $out)];
};

echo "W5D first-upload installer" . ($check ? ' (check only: nothing is changed)' : '') . "\n";
echo "Project folder: $root\n";

/* ---------------------------------------------------------------- 1. PHP and extensions */
$head('1. PHP and extensions');
PHP_VERSION_ID >= 80400 ? $say('OK', 'PHP', PHP_VERSION) : $say('FAIL', 'PHP', PHP_VERSION . ' is too old: 8.4 or newer is required (ask the host to select PHP 8.4 for this site and for the command line)');
foreach (['pdo_sqlite' => 'database', 'mbstring' => 'text', 'json' => 'data', 'openssl' => 'encryption and signing', 'curl' => 'payment providers and OAuth'] as $x => $why) {
    extension_loaded($x) ? $say('OK', "ext $x", $why) : $say('FAIL', "ext $x", "missing ($why): enable it in the hosting panel or php.ini");
}
extension_loaded('gd') && function_exists('imagewebp') ? $say('OK', 'ext gd (WebP)', 'owner image library can convert pictures') : $say('WARN', 'ext gd (WebP)', 'missing: the image library cannot convert pictures; the public site is unaffected');
extension_loaded('zip') ? $say('OK', 'ext zip', 'ZIP uploads work') : $say('WARN', 'ext zip', 'missing: ZIP uploads are disabled; single pictures still work');
defined('PASSWORD_ARGON2ID') ? $say('OK', 'Argon2id', 'available for password hashes') : $say('WARN', 'Argon2id', 'not compiled in: hashes fall back to bcrypt (still safe)');
is_file($root . '/htdocs/index.php') ? $say('OK', 'htdocs/index.php', 'front controller found') : $say('FAIL', 'htdocs/index.php', 'missing: was the upload complete?');
if ($fails) { echo "\nFix the FAIL lines above, then run this command again.\n"; exit(1); }

/* ---------------------------------------------------------------- 2. folders */
$head('2. Storage folders');
foreach (['storage', 'storage/logs', 'storage/sessions', 'storage/cache', 'storage/framework/views', 'storage/uploads', 'storage/archive', 'storage/backups', 'storage/incoming'] as $d) {
    $p = "$root/$d";
    if (!is_dir($p) && !$check) @mkdir($p, 0775, true);
    if (is_dir($p) && is_writable($p)) $say('OK', $d, '');
    elseif (!is_dir($p) && $check) $say('WARN', $d, 'missing: the installer creates it');
    else $say('FAIL', $d, is_dir($p) ? 'not writable by this user: chmod u+w, or chown to the PHP user' : 'cannot be created');
}
if (!$check && is_dir("$root/storage/sessions")) @chmod("$root/storage/sessions", 0700);

/* ---------------------------------------------------------------- 3. .env */
$head('3. The .env file');
$envFile = "$root/.env";
$example = "$root/.env.example";
$setEnv = function (string $text, string $key, string $value): string {
    $line = $key . '=' . (preg_match('/^[A-Za-z0-9_.:\/@+\-]*$/', $value) ? $value : "'" . str_replace("'", '', $value) . "'");
    if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $text)) return preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $text, 1);
    return rtrim($text) . "\n" . $line . "\n";
};
$envText = null; $generatedMount = null; $hashSet = false;
if (is_file($envFile) && !isset($opt['force-env'])) {
    $say('OK', '.env', 'exists: kept as it is (use --force-env to rebuild from .env.example)');
    $envText = (string)file_get_contents($envFile);
} elseif ($check) {
    $say('WARN', '.env', 'missing: run the installer without --check to create it');
} elseif (!is_file($example)) {
    $say('FAIL', '.env.example', 'missing: cannot create .env');
} else {
    if (is_file($envFile)) { $bak = $envFile . '.bak-' . date('Ymd-His'); copy($envFile, $bak); @chmod($bak, 0600); $say('OK', '.env backup', basename($bak)); }
    $envText = (string)file_get_contents($example);

    $url = (string)($opt['url'] ?? '');
    if ($url === '') $url = $ask('Public address of the site (https://…)', 'https://example.org');
    $url = rtrim($url, '/');
    if (!preg_match('#^https://[a-z0-9.\-]+(:\d+)?$#i', $url)) { $say('FAIL', 'APP_URL', "\"$url\" must be an https origin such as https://example.org (no path, no trailing slash)"); exit(1); }
    $host = parse_url($url, PHP_URL_HOST);
    $ownerOpt = strtolower((string)($opt['owner'] ?? ''));
    $domain = strtolower(ltrim((string)($opt['domain'] ?? ''), '@'));
    if ($domain === '' && str_contains($ownerOpt, '@')) $domain = substr(strrchr($ownerOpt, '@'), 1); // the owner address decides the team domain
    if ($domain === '') $domain = strtolower($ask('Team e-mail domain (people sign in with an address on it)', preg_replace('/^www\./', '', (string)$host)));
    $owner = $ownerOpt ?: strtolower($ask('Owner e-mail (a mailbox you can really read)', 'owner@' . $domain));
    if (!filter_var($owner, FILTER_VALIDATE_EMAIL) || !str_ends_with($owner, '@' . $domain)) { $say('FAIL', 'OWNER_EMAIL', "\"$owner\" must be a valid address on @$domain"); exit(1); }
    $mount = trim((string)($opt['mount'] ?? ''), '/');
    if ($mount === '') { $mount = 'p-' . bin2hex(random_bytes(6)); $generatedMount = $mount; }
    if (!preg_match('/^[A-Za-z0-9_\-]{6,60}$/', $mount) || in_array(strtolower($mount), ['admin', 'panel', 'panel-admin', 'dashboard'], true)) { $say('FAIL', 'ADMIN_URL_PATH', 'use 6-60 letters/digits/-/_ and not admin, panel or dashboard'); exit(1); }

    foreach (['APP_ENV' => 'production', 'APP_URL' => $url, 'OWNER_EMAIL' => $owner, 'STAFF_EMAIL_DOMAIN' => $domain, 'ADMIN_URL_PATH' => $mount,
              'CONTACT_EMAIL' => $owner, 'SEO_ORG_EMAIL' => $owner, 'MAIL_REPLY_TO' => $owner, 'MAIL_FROM_ADDRESS' => 'no-reply@' . $domain, 'PAYMENT_MODE' => 'demo'] as $k => $v) $envText = $setEnv($envText, $k, $v);

    // Owner password (optional): only the hash is stored.
    $pw = (string)($opt['password-stdin'] ?? false ? rtrim((string)fgets(STDIN), "\r\n") : '');
    if ($pw === '' && $tty) {
        $pw = $askHidden('Owner password (12+ characters, Enter to skip and use e-mailed codes only)');
        if ($pw !== '' && $askHidden('Repeat it') !== $pw) { $say('WARN', 'owner password', 'the two entries differ: skipped'); $pw = ''; }
    }
    if ($pw !== '') {
        if (strlen($pw) < 12) $say('WARN', 'owner password', 'shorter than 12 characters: skipped');
        else { $envText = $setEnv($envText, 'WEBMASTER_PASSWORD_HASH', password_hash($pw, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)); $hashSet = true; }
    }
    file_put_contents($envFile, $envText);
    @chmod($envFile, 0600);
    $say('OK', '.env created', "APP_ENV=production, APP_URL=$url, OWNER_EMAIL set, PAYMENT_MODE=demo");
    $say($hashSet ? 'OK' : 'WARN', 'owner password hash', $hashSet ? 'stored (Argon2id)' : 'not set: the owner signs in with the e-mailed code (add one later: php bin/password-hash.php)');
}
if (is_file($envFile)) { $m = substr(sprintf('%o', fileperms($envFile)), -3); (int)$m[2] === 0 ? $say('OK', '.env permissions', $m) : $say('WARN', '.env permissions', "$m: run chmod 600 .env so other accounts on the host cannot read it"); }

/* ---------------------------------------------------------------- 4-5. database, content, key */
$head('4. Database, content and signing key');
if ($check) {
    $say('OK', 'skipped', 'read-only mode does not migrate');
} elseif (!is_file($envFile)) {
    $say('FAIL', 'database', 'no .env: nothing to migrate');
} else {
    [$c, $o] = $sub('migrate.php');
    $c === 0 ? $say('OK', 'migrations', trim(strrchr("\n" . $o, "\n") ?: $o)) : $say('FAIL', 'migrations', substr($o, 0, 300));
    [$c, $o] = $sub('sync-content.php');
    $c === 0 ? $say('OK', 'content tables', trim($o)) : $say('FAIL', 'content tables', substr($o, 0, 300));
    @exec(escapeshellarg($php) . ' -r ' . escapeshellarg('require ' . var_export($root . '/bin/_boot.php', true) . '; W5D\\Secret::key();') . ' 2>&1');
    is_file("$root/storage/app.key") ? $say('OK', 'storage/app.key', 'signing key present: back it up with the database; changing it invalidates payment and unsubscribe links') : $say('WARN', 'storage/app.key', 'not created yet: it is generated on the first web request (or set APP_KEY in .env)');
    if (is_file("$root/storage/app.key")) @chmod("$root/storage/app.key", 0600);
    $dbf = "$root/storage/w5d.sqlite"; if (is_file($dbf)) @chmod($dbf, 0600);
}

/* ---------------------------------------------------------------- 6. caches + preflight */
$head('5. View cache and preflight');
if (!$check && is_file($envFile)) {
    [$c, $o] = $sub('view.php', ['cache']);
    $c === 0 ? $say('OK', 'view cache', trim(strrchr("\n" . $o, "\n") ?: $o)) : $say('WARN', 'view cache', substr($o, 0, 200));
}
if (is_file($envFile)) {
    [$c, $o] = $sub('preflight.php');
    echo preg_replace('/^/m', '    ', $o) . "\n";
    $c === 0 ? $say('OK', 'preflight', 'no FAIL') : $say('WARN', 'preflight', 'has FAIL lines: the usual first ones are MAIL_DRIVER (set smtp, see the Setup guide) and WEBMASTER_PASSWORD_HASH; fix them, then run php bin/preflight.php');
}

/* ---------------------------------------------------------------- summary */
echo "\n== What to do next (the same list is in docs/INSTALL.md and in the portal: Setup guide)\n";
$mountShown = $generatedMount ?? (preg_match('/^ADMIN_URL_PATH=(.+)$/m', (string)$envText, $mm) ? trim($mm[1], "'\" ") : '<ADMIN_URL_PATH>');
$urlShown = preg_match('/^APP_URL=(.+)$/m', (string)$envText, $mu) ? trim($mu[1], "'\" ") : 'https://your-domain';
echo "  1. Point the web server's document root at:  $root/htdocs   (never at the project folder: .env, storage/ and src/ must not be reachable)\n";
echo "  2. Turn HTTPS on for the domain (the site refuses to behave as production over plain http).\n";
echo "  3. Portal address (write it down, share it only with the team):  $urlShown/$mountShown/\n";
echo "  4. In .env set MAIL_DRIVER=smtp and the SMTP values, then:  php bin/mail.php test you@your-domain en all\n";
echo "     Until mail really sends, nobody can receive a sign-in code. Break-glass from this shell:  php bin/staff-code.php $" . "OWNER_EMAIL\n";
echo "  5. Add these cron lines (crontab -e; replace the php path if your host uses another):\n";
$cron = [
    ['*/10 * * * *', 'bin/payments-sync.php', 'recheck online payments that stayed pending'],
    ['15 6 * * *', 'bin/billing.php', 'renewal invoices, grace periods'],
    ['5 * * * *', 'bin/rollup.php', 'dashboard figures'],
    ['30 3 * * *', 'bin/backup.php', 'consistent database snapshot: ship storage/backups/latest.sqlite off the server'],
    ['45 3 * * 0', 'bin/archive.php', 'weekly clean-up'],
    ['*/15 * * * *', 'bin/health.php', 'storage thresholds'],
];
foreach ($cron as [$when, $script, $why]) printf("       %-13s %s %s/%s   # %s\n", $when, $php, $root, $script, $why);
echo "  6. Open the portal, sign in, then follow Setup guide > \"After the first sign-in\" (payment methods, plans, variables, team).\n";
echo "  7. Leave PAYMENT_MODE=demo until your accountant and a provider contract say otherwise.\n";
echo "\nResult: " . ($fails ? "$fails FAIL" : 'no FAIL') . ", $warns WARN.\n";
exit($fails ? 1 : 0);
