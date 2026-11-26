<?php
declare(strict_types=1);

namespace W5D\Install;

use W5D\{DB, Env, EnvFile, InstallGate, Secret, SysCheck, ViewCache};

if (!defined('ROOT')) { http_response_code(404); exit; }

/**
 * The install action of the browser wizard: database first (nothing is written to .env if it fails), then .env, then the lock.
 * Re-running after a failure is safe: migrations are idempotent and .env is written only once everything else worked.
 */
final class Runner
{
    /** @return array{ok:bool, log:list<array{0:bool,1:string}>, error:string} */
    public static function install(array $v): array
    {
        $log = [];
        $fail = function (string $m) use (&$log) { return ['ok' => false, 'log' => $log, 'error' => $m]; };
        @mkdir(ROOT . '/storage/cache', 0775, true);
        $lock = @fopen(ROOT . '/storage/cache/install.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return $fail('another installation is running');
        try {
            if (InstallGate::installed()) return $fail('the site is already installed');
            $rows = SysCheck::requirements(null, true, false);
            if (SysCheck::failCount($rows) > 0) return $fail('the server requirements are not met');
            $log[] = [true, 'requirements'];

            $vals = self::envValues($v);
            // 1. database + signing key, using the new settings in memory only
            Env::override($vals);
            DB::reset();
            try {
                $pdo = DB::pdo();
                $n = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
                Secret::key();
            } catch (\Throwable $e) {
                return $fail('database: ' . str_replace(ROOT, '', $e->getMessage()));
            }
            $log[] = [true, 'database: ' . $n . ' tables'];
            if (is_file(ROOT . '/storage/app.key')) @chmod(ROOT . '/storage/app.key', 0600);
            $log[] = [true, 'signing key'];

            // 2. .env from the template
            $envFile = ROOT . '/.env';
            $text = is_readable(ROOT . '/.env.example') ? (string)file_get_contents(ROOT . '/.env.example') : '';
            foreach ($vals as $k => $val) $text = EnvFile::set($text, $k, (string)$val);
            if (@file_put_contents($envFile, $text, LOCK_EX) === false) return $fail('cannot write .env (make the project folder or an empty .env file writable)');
            @chmod($envFile, 0600);
            $log[] = [true, '.env (mode 600)'];

            // 3. compiled views (not fatal: they are also compiled on first use)
            try { foreach (ViewCache::sources() as $s) ViewCache::compiled($s); $log[] = [true, 'view cache']; }
            catch (\Throwable) { $log[] = [false, 'view cache (built on first use)']; }

            // 4. lock
            @file_put_contents(ROOT . '/storage/installed.lock', 'installed ' . date('c') . " by the browser installer\n");
            @chmod(ROOT . '/storage/installed.lock', 0600);
            $log[] = [true, 'installer locked'];
            return ['ok' => true, 'log' => $log, 'error' => ''];
        } finally {
            flock($lock, LOCK_UN); fclose($lock);
        }
    }

    /** The .env values this wizard decides (everything else keeps the .env.example default). */
    public static function envValues(array $v): array
    {
        $g = fn(string $k, string $d = '') => (string)($v[$k] ?? $d);
        $owner = $g('OWNER_EMAIL');
        $domain = $g('STAFF_EMAIL_DOMAIN') !== '' ? $g('STAFF_EMAIL_DOMAIN') : substr((string)strrchr($owner, '@'), 1);
        $org = $g('SEO_ORG_NAME');
        $out = [
            'APP_ENV' => 'production', 'APP_URL' => $g('APP_URL'), 'APP_BASE_PATH' => $g('APP_BASE_PATH'), 'APP_LANG' => $g('APP_LANG', 'en'),
            'ADMIN_URL_PATH' => $g('ADMIN_URL_PATH'),
            'OWNER_EMAIL' => $owner, 'STAFF_EMAIL_DOMAIN' => strtolower($domain),
            'STAFF_CODE_TTL_MIN' => $g('STAFF_CODE_TTL_MIN', '10'), 'STAFF_IDLE_MINUTES' => $g('STAFF_IDLE_MINUTES', '30'),
            'DB_DRIVER' => 'sqlite', 'DB_PATH' => $g('DB_PATH', 'storage/w5d.sqlite'),
            'BACKUP_DRIVER' => $g('BACKUP_DRIVER', 'external'), 'ARCHIVE_DRIVER' => $g('ARCHIVE_DRIVER', 'prune'), 'UPLOAD_MAX_MB' => $g('UPLOAD_MAX_MB', '2'),
            'MAIL_DRIVER' => $g('MAIL_DRIVER', 'log'), 'MAIL_HOST' => $g('MAIL_HOST'), 'MAIL_PORT' => $g('MAIL_PORT', '587'), 'MAIL_ENCRYPTION' => $g('MAIL_ENCRYPTION', 'tls'),
            'MAIL_USERNAME' => $g('MAIL_USERNAME'), 'MAIL_PASSWORD' => $g('MAIL_PASSWORD'),
            'MAIL_FROM_ADDRESS' => $g('MAIL_FROM_ADDRESS') ?: 'no-reply@' . $domain,
            'MAIL_FROM_NAME' => $g('MAIL_FROM_NAME') ?: $org,
            'MAIL_REPLY_TO' => $g('MAIL_REPLY_TO') ?: $owner, 'MAIL_FOOTER_ADDRESS' => $g('MAIL_FOOTER_ADDRESS'),
            'CONTACT_EMAIL' => $g('CONTACT_EMAIL') ?: $owner,
            'PAYMENT_MODE' => 'demo', 'PAYMENT_D17_ENABLED' => $g('PAYMENT_D17_ENABLED') === 'true' ? 'true' : 'false',
            'PAYMENT_D17_GSM' => $g('PAYMENT_D17_GSM'), 'PAYMENT_D17_NAME' => $g('PAYMENT_D17_NAME'), 'PAYMENT_D17_NOTE' => $g('PAYMENT_D17_NOTE'),
            'SEO_ORG_NAME' => $org, 'SEO_ORG_EMAIL' => $g('SEO_ORG_EMAIL') ?: $owner, 'SOCIAL_SHARE_VIA' => $g('SOCIAL_SHARE_VIA'),
            'CRON_MODE' => $g('CRON_MODE', 'system'), 'CRON_TOKEN' => $g('CRON_TOKEN'),
        ];
        foreach (['FACEBOOK', 'X', 'LINKEDIN', 'GITHUB', 'INSTAGRAM', 'YOUTUBE'] as $p) $out['SOCIAL_PROFILE_' . $p] = $g('SOCIAL_PROFILE_' . $p);
        if ($g('WEBMASTER_PASSWORD_HASH') !== '') $out['WEBMASTER_PASSWORD_HASH'] = $g('WEBMASTER_PASSWORD_HASH');
        return $out;
    }

    /**
     * Delete the installer: the install/ folder and bin/install.php. Only these two paths; symlinks are never followed.
     * @return list<array{0:string,1:bool}>
     */
    public static function deleteInstaller(): array
    {
        $res = [];
        $dir = ROOT . '/install';
        if (is_dir($dir) && !is_link($dir)) {
            $ok = true;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { $ok = ($f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname())) && $ok; }
            $ok = @rmdir($dir) && $ok;
            $res[] = ['install/', $ok];
        }
        $cli = ROOT . '/bin/install.php';
        if (is_file($cli)) $res[] = ['bin/install.php', @unlink($cli)];
        return $res;
    }
}
