<?php
declare(strict_types=1);
// php bin/preflight.php [--json]
// Deploy-readiness check for the CURRENT .env / storage. Exit 0 = no FAIL (WARNs allowed), 1 = at least one FAIL.
// Run it on the production host after uploading and editing .env; the dev .env is expected to FAIL.
require __DIR__ . '/_boot.php';

use W5D\{Env, DB, ViewCache};

$rows = [];
$add = function (string $level, string $check, string $detail) use (&$rows) { $rows[] = [$level, $check, $detail]; };
$get = fn(string $k, string $d = '') => (string)Env::get($k, $d);

// ---- must pass
$get('APP_ENV') === 'production' ? $add('OK', 'APP_ENV', 'production') : $add('FAIL', 'APP_ENV', 'is "' . $get('APP_ENV') . '", must be production (hides stack traces, forces Secure cookies, trusts view cache)');
$url = $get('APP_URL');
if (str_starts_with($url, 'https://') && !str_contains($url, 'localhost')) $add('OK', 'APP_URL', $url);
elseif (str_starts_with($url, 'https://') && getenv('PREFLIGHT_ALLOW_LOCAL_URL') === '1') $add('WARN', 'APP_URL', "$url (localhost allowed by PREFLIGHT_ALLOW_LOCAL_URL=1; only for tests/production.sh)");
else $add('FAIL', 'APP_URL', "is \"$url\", must be the public https origin (canonical, hreflang, sitemap, mail links)");

$hash = $get('WEBMASTER_PASSWORD_HASH');
if ($hash === '' || !str_starts_with($hash, '$')) $add('FAIL', 'WEBMASTER_PASSWORD_HASH', 'empty or not a password_hash() value');
elseif (password_verify('w5d-demo-owner', $hash)) $add('FAIL', 'WEBMASTER_PASSWORD_HASH', 'still the public demo password (w5d-demo-owner): generate a new hash');
else $add('OK', 'WEBMASTER_PASSWORD_HASH', password_get_info($hash)['algoName'] . ' hash set, not the demo password');
$get('WEBMASTER_PASSWORD') === '' ? $add('OK', 'WEBMASTER_PASSWORD', 'no plaintext password in .env') : $add('FAIL', 'WEBMASTER_PASSWORD', 'plaintext password present in .env (tests only; remove)');

$adm = trim($get('ADMIN_URL_PATH'), '/');
($adm !== '' && !in_array($adm, ['admin', 'panel', 'panel-admin', 'dashboard'], true)) ? $add('OK', 'ADMIN_URL_PATH', 'obfuscated mount set') : $add('FAIL', 'ADMIN_URL_PATH', 'empty or guessable');

foreach (['storage', 'storage/logs', 'storage/sessions', 'storage/framework/views', 'storage/cache'] as $d) {
    $p = ROOT . '/' . $d; if (!is_dir($p)) @mkdir($p, 0775, true);
    is_dir($p) && is_writable($p) ? $add('OK', "writable $d", '') : $add('FAIL', "writable $d", 'not writable by the PHP user');
}
try { DB::available() ? $add('OK', 'database', $get('DB_DRIVER', 'sqlite') . ' reachable, ' . count(DB::all("SELECT name FROM sqlite_master WHERE type='table'")) . ' tables') : $add('FAIL', 'database', 'not reachable'); }
catch (Throwable $e) { $add('FAIL', 'database', $e->getMessage()); }
foreach (['pdo_sqlite', 'mbstring', 'json', 'openssl', 'curl'] as $x) extension_loaded($x) ? null : $add('FAIL', "php ext $x", 'missing');
PHP_VERSION_ID >= 80400 ? $add('OK', 'PHP', PHP_VERSION) : $add('FAIL', 'PHP', PHP_VERSION . ' (>= 8.4 required)');
$get('PAYMENT_MODE', 'demo') === 'demo' ? $add('OK', 'PAYMENT_MODE', 'demo (no card money moves; D17 is manual verification)') : $add('WARN', 'PAYMENT_MODE', $get('PAYMENT_MODE') . ': only after counsel + PSP contract');

// ---- funding stack: configuration errors fail the deploy
$fe = \W5D\Funding::buildErrors();
foreach ($fe as $e) $add('FAIL', 'funding', $e);
if (!$fe) $add('OK', 'funding tokens', 'valid; ad-revenue withdrawal ' . (\W5D\Ads::canWithdraw() ? 'allowed (matricule fiscal set)' : 'closed (accrued only)'));
$add(\W5D\Ads::canApply() ? 'OK' : 'WARN', 'AdSense content gate', \W5D\Ads::canApplyDetail()['reason_text']);

// ---- should pass (WARN)
$md = $get('MAIL_DRIVER', 'log');
$md === 'smtp' ? $add($get('MAIL_HOST') !== '' && $get('MAIL_USERNAME') !== '' ? 'OK' : 'WARN', 'MAIL_DRIVER', 'smtp' . ($get('MAIL_HOST') === '' ? ' but MAIL_HOST empty' : ' ' . $get('MAIL_HOST'))) : $add('WARN', 'MAIL_DRIVER', "\"$md\": mails are only written to storage/logs; set smtp + credentials, then `php bin/mail.php test you@domain fr all`");
$get('MAIL_FOOTER_ADDRESS') !== '' && $get('MAIL_FOOTER_ADDRESS') !== 'Tunisia' ? $add('OK', 'MAIL_FOOTER_ADDRESS', 'set') : $add('WARN', 'MAIL_FOOTER_ADDRESS', 'default; put the real postal/company line (anti-spam law)');
if (Env::bool('PAYMENT_D17_ENABLED')) { preg_match('/^\+216\d{8}$/', $get('PAYMENT_D17_GSM')) ? $add('OK', 'PAYMENT_D17_GSM', 'looks valid') : $add('WARN', 'PAYMENT_D17_GSM', 'D17 enabled but GSM is not +216XXXXXXXX'); if ($get('PAYMENT_D17_GSM') === '+21620123456') $add('WARN', 'PAYMENT_D17_GSM', 'still the demo number'); }
!ViewCache::checkSources() ? $add('OK', 'VIEW_CACHE_CHECK', 'trusting the cache (production)') : $add('WARN', 'VIEW_CACHE_CHECK', 'mtime checks are on; set VIEW_CACHE_CHECK=false in production');
$st = ViewCache::stats();
$st['files'] >= $st['templates'] ? $add('OK', 'view cache', "{$st['files']} compiled / {$st['templates']} templates") : $add('WARN', 'view cache', "{$st['files']} compiled / {$st['templates']} templates: run `php bin/view.php cache`");
$envf = ROOT . '/.env';
if (is_file($envf)) { (fileperms($envf) & 0007) === 0 ? $add('OK', '.env permissions', substr(sprintf('%o', fileperms($envf)), -4)) : $add('WARN', '.env permissions', 'world-readable: chmod 600 .env'); }
else $add('FAIL', '.env', 'missing');
$img = ROOT . '/htdocs/assets/_base/images';
is_dir($img) ? $add('OK', 'social images dir', 'present (replace placeholders with final art: docs/social-share-image-prompts.md)') : $add('WARN', 'social images dir', 'missing');
$git = ROOT . '/.git'; if (is_dir($git)) $add('WARN', '.git present', 'make sure the web server cannot serve it (the root .htaccess returns 404; prefer docroot = htdocs/)');
$bk = (int)@filemtime(ROOT . '/storage/backups'); $add('WARN', 'backups', 'BACKUP_DRIVER=' . $get('BACKUP_DRIVER', 'external') . ': schedule `php bin/backup.php` and `php bin/rollup.php` (cron); last storage/backups touch ' . ($bk ? date('Y-m-d', $bk) : 'never'));

if (in_array('--json', $argv, true)) { echo json_encode(array_map(fn($r) => ['level' => $r[0], 'check' => $r[1], 'detail' => $r[2]], $rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n"; }
else { foreach ($rows as [$l, $c, $d]) printf("%-5s %-26s %s\n", $l, $c, $d); }
$f = count(array_filter($rows, fn($r) => $r[0] === 'FAIL')); $w = count(array_filter($rows, fn($r) => $r[0] === 'WARN'));
fwrite(STDERR, "preflight: $f FAIL, $w WARN\n");
exit($f ? 1 : 0);
