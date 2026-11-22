<?php
declare(strict_types=1);

namespace W5D;

/**
 * AdSense, fail-closed. AdSense is never a dependency of the plan: it can be applied for only when the site
 * is a real publication, and its revenue is accrued (declared when received) and not withdrawn before a valid
 * matricule fiscal exists.
 *
 * The content gate counts RENDERED pages. Rendering the whole site takes about a minute, so `php bin/ads-gate.php`
 * writes storage/cache/ads-gate.json and canApply() reads it. A missing or stale file means "not computed", which is closed.
 */
final class Ads
{
    public const STATES = ['not_applied', 'pending', 'verified', 'rejected', 'suspended'];
    /** Pages that exist for transactions or credentials, not for readers: never counted, never shown ads. */
    public const EXCLUDE = ['/checkout/', '/subscription/', '/status/', '/join/thanks/', '/sitemap/', '/sponsor/', '/contact/', '/newsletter/', '/certification/verify/'];
    public const POLICY_SLUG = 'trust-ads-policy';

    private static ?array $gate = null;

    /** Test seam: inject a gate result for one process (pages = [[path, words, kind]]). */
    public static function useGate(?array $pages): void { self::$gate = $pages === null ? null : self::evaluate($pages); }

    public static function minPages(): int { return (int)Funding::n('ADS_MIN_CONTENT_PAGES'); }
    public static function minWords(): int { $v = (int)Funding::n('ADS_MIN_PAGE_WORDS'); return $v > 0 ? $v : 200; }
    public static function cacheFile(): string { return ROOT . '/storage/cache/ads-gate.json'; }

    public static function kind(string $path): string
    {
        return match (true) {
            str_starts_with($path, '/services/') && $path !== '/services/' => 'service',
            str_starts_with($path, '/macro-works/') && $path !== '/macro-works/' => 'macro',
            str_starts_with($path, '/trust/') && $path !== '/trust/' => 'trust',
            default => 'other',
        };
    }

    /** Pure function: apply the gate to [[path, words, kind?]] rows. */
    public static function evaluate(array $pages): array
    {
        $min = self::minWords();
        $n = ['service' => 0, 'macro' => 0, 'trust' => 0, 'other' => 0];
        $thin = [];
        $seen = [];
        foreach ($pages as $p) {
            $path = (string)($p['path'] ?? $p[0] ?? ''); $w = (int)($p['words'] ?? $p[1] ?? 0);
            if ($path === '' || isset($seen[$path]) || in_array($path, self::EXCLUDE, true)) continue;
            $seen[$path] = 1;
            if ($w < $min) { $thin[] = $path; continue; }
            $n[(string)($p['kind'] ?? $p[2] ?? self::kind($path))]++;
        }
        return ['counted' => array_sum($n), 'by_kind' => $n, 'thin' => $thin, 'min_words' => $min, 'computed_at' => date('c'), 'fingerprint' => ''];
    }

    public static function fingerprint(): string
    {
        static $memo = null; if ($memo !== null) return $memo;
        $m = 0; $c = 0;
        foreach (['data/content', 'views/templates', 'lang', 'src/controllers'] as $d) {
            if (!is_dir(ROOT . "/$d")) continue;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ROOT . "/$d", \FilesystemIterator::SKIP_DOTS)) as $f) { if ($f->isFile()) { $m = max($m, $f->getMTime()); $c++; } }
        }
        return $memo = $m . ':' . $c;
    }

    public static function gate(): ?array
    {
        if (self::$gate !== null) return self::$gate;
        $f = self::cacheFile();
        $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        if (!is_array($j) || !isset($j['counted'])) return null;
        if (($j['fingerprint'] ?? '') !== self::fingerprint()) { $j['stale'] = true; }
        return $j;
    }

    /** Where an ad may ever be placed. Evidence, status, checkout, credential, account and portal pages never carry ads. */
    public static function allowedOn(string $path): bool
    {
        $path = '/' . trim($path, '/') . '/';
        foreach (['/trust/', '/status/', '/checkout/', '/subscription/', '/certification/', '/certificate/', '/account/', '/join/', '/contact/', '/newsletter/', '/affiliate/', '/api/', '/sponsor/'] as $p)
            if (str_starts_with($path, $p)) return false;
        $adm = '/' . trim((string)Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/') . '/';
        return !str_starts_with($path, $adm);
    }

    public static function policyPageExists(): bool { return Content::page(self::POLICY_SLUG) !== null; }

    /** The cookie and consent notice exists in code AND has been recorded with a date. */
    public static function consentRecorded(): bool
    {
        $ok = is_file(ROOT . '/views/templates/w5d/partials/cookies.blade.php') && is_file(ROOT . '/htdocs/assets/_base/js/cookies.js')
            && str_contains((string)@file_get_contents(ROOT . '/views/templates/w5d/layouts/app.blade.php'), "partials.cookies");
        return $ok && Funding::lastRecord('consent_mechanism') !== null;
    }

    /** @return array{ok:bool,reason_code:string,reason_text:string,counted:int,needed:int,by_kind:array,thin:array,computed_at:string,stale:bool} */
    public static function canApplyDetail(): array
    {
        $g = self::gate();
        $need = self::minPages();
        $base = ['counted' => (int)($g['counted'] ?? 0), 'needed' => $need, 'by_kind' => $g['by_kind'] ?? [], 'thin' => $g['thin'] ?? [], 'computed_at' => (string)($g['computed_at'] ?? ''), 'stale' => (bool)($g['stale'] ?? false)];
        $no = fn(string $c, string $t) => ['ok' => false, 'reason_code' => $c, 'reason_text' => $t] + $base;
        if ($need <= 0) return $no('threshold_missing', 'ADS_MIN_CONTENT_PAGES is not set, so the gate stays closed.');
        if ($g === null) return $no('gate_not_computed', 'The content gate has not been computed: run php bin/ads-gate.php.');
        if ($base['stale']) return $no('gate_stale', 'Content changed since the gate was computed: run php bin/ads-gate.php again.');
        if ($base['counted'] < $need) return $no('too_few_pages', 'Only ' . $base['counted'] . ' substantive pages of ' . $need . ' needed (a page under ' . self::minWords() . ' words does not count).');
        foreach (['service' => 'service briefs', 'macro' => 'macro-problem pages', 'trust' => 'evidence and trust pages'] as $k => $label)
            if (($base['by_kind'][$k] ?? 0) < 5) return $no('too_few_' . $k, 'At least 5 ' . $label . ' are needed; there are ' . ($base['by_kind'][$k] ?? 0) . '.');
        if (!self::policyPageExists()) return $no('policy_missing', 'The page /trust/ads-policy/ does not exist.');
        if (!self::consentRecorded()) return $no('consent_missing', 'The consent mechanism is not recorded.');
        return ['ok' => true, 'reason_code' => 'ok', 'reason_text' => 'The content gate is met.'] + $base;
    }

    public static function canApply(): bool
    {
        try { return self::canApplyDetail()['ok']; } catch (\Throwable) { return false; }
    }

    /** Withdrawal needs the switch AND a matricule fiscal. Either missing = closed. */
    public static function canWithdraw(): bool
    {
        return Funding::b('ADS_WITHDRAWAL_ALLOWED') && Funding::s('VAT_MATRICULE_FISCAL') !== '';
    }

    public static function status(): string
    {
        $s = Funding::s('ADS_STATUS', 'not_applied');
        return in_array($s, self::STATES, true) ? $s : 'not_applied';
    }

    /* ------------------------------------------------------------------ rejection log (append-only) */
    public static function logRejection(string $date, string $type, string $action, string $by): bool
    {
        if (!Funding::validPastDate($date) || trim($type) === '' || trim($action) === '' || !DB::available()) return false;
        DB::insert('ads_rejection_log', ['date' => $date, 'rejection_type' => mb_substr(trim($type), 0, 120), 'corrective_action' => mb_substr(trim($action), 0, 1000), 'recorded_by' => $by]);
        return true;
    }

    public static function markReapplied(int $id, string $on): bool
    {
        if (!Funding::validPastDate($on) || !DB::available()) return false;
        try { DB::q('UPDATE ads_rejection_log SET reapplied_at = ? WHERE id = ?', [$on, $id]); return true; } catch (\Throwable) { return false; }
    }

    public static function rejections(): array
    {
        return DB::available() ? DB::all('SELECT * FROM ads_rejection_log ORDER BY id DESC LIMIT 100') : [];
    }

    /* ------------------------------------------------------------------ figures */
    public static function figures(): array
    {
        return ['status' => self::status(), 'accrued_usd' => Funding::n('ADS_ACCRUED_USD'), 'accrued_tnd' => Funding::n('ADS_ACCRUED_TND'), 'withdrawn_tnd' => Funding::n('ADS_WITHDRAWN_TND'),
            'can_withdraw' => self::canWithdraw(), 'min_pages' => self::minPages()];
    }
}
