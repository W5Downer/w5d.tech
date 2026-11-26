<?php
declare(strict_types=1);

namespace W5D;

/**
 * Decides whether the browser installer (install/) may answer. The folder is optional: delete it after the install and the site
 * simply has no /install/ routes. Installed means: storage/installed.lock exists, the settings already name an owner (real environment variables),
 * or a .env with at least one KEY=value line exists (a hand-made .env, or a site installed before this feature existed).
 */
final class InstallGate
{
    public static function installed(): bool
    {
        if (is_file(ROOT . '/storage/installed.lock')) return true;
        if (trim((string)Env::get('OWNER_EMAIL', '')) !== '') return true;
        // A .env that already holds settings (hand-made, copied from .env.example, or from before this installer existed) is an installed
        // site: never push it into the wizard. An absent, empty or comment-only .env is "not installed".
        $f = ROOT . '/.env';
        return is_file($f) && (bool)preg_match('/^\s*[A-Z][A-Z0-9_]*\s*=/m', (string)@file_get_contents($f));
    }

    public static function installerPresent(): bool { return is_file(ROOT . '/install/Wizard.php'); }

    /** Routes exist while the site is not installed, and for the browser that has just finished (to read the last page). */
    public static function routesWanted(): bool
    {
        return self::installerPresent() && (!self::installed() || !empty($_SESSION['inst_done']));
    }

    /** Installer files still on the server (relative to the project folder). */
    public static function leftovers(): array { return SysCheck::installerFiles(); }

    /** Web only. Before the site is installed every page leads to the installer; null = carry on with normal routing. */
    public static function intercept(Request $q): ?Response
    {
        if (PHP_SAPI === 'cli' || self::installed()) return null;
        $p = $q->path;
        if ($p === '/install' || str_starts_with($p, '/install/') || preg_match('#^/(language|lang)/#', $p)) return self::installerPresent() ? null : self::notInstalled();
        return self::installerPresent() ? Response::redirect(url('/install/'), 302)->withHeaders(['Cache-Control' => 'no-store']) : self::notInstalled();
    }

    private static function notInstalled(): Response
    {
        $h = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Not installed</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#070711;color:#e2e8f0;font-family:system-ui,Segoe UI,Tahoma,sans-serif}main{max-width:36rem;padding:2rem}h1{font-size:1.5rem;color:#fff}code{background:#1e293b;padding:.1rem .4rem;border-radius:.3rem}p{color:#94a3b8;line-height:1.7}</style></head>'
            . '<body><main><h1>This site is not installed yet</h1><p>There are no settings (<code>.env</code>) and the installer folder <code>install/</code> is not on the server.</p>'
            . '<p>Upload the <code>install/</code> folder and reload this page, or run <code>php bin/install.php</code> on the server (SSH), or copy <code>.env.example</code> to <code>.env</code> and edit it by hand (docs/INSTALL.md).</p></main></body></html>';
        return Response::html($h, 503)->withHeaders(['Retry-After' => '300', 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'no-store']);
    }
}
