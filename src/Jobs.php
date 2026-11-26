<?php
declare(strict_types=1);

namespace W5D;

/**
 * The scheduled jobs. Each returns ['ok' => ran without error, 'exit' => CLI exit code, 'out' => text].
 * bin/*.php (system cron), Cron::endpoint() (web cron) and Cron::afterRequest() (request-driven) all call these,
 * so a host without cron runs exactly the same code as a host with it.
 */
final class Jobs
{
    private static function r(bool $ok, string $out, int $exit = 0): array { return ['ok' => $ok, 'exit' => $ok ? $exit : max(1, $exit), 'out' => rtrim($out)]; }

    public static function paymentsSync(int $max = 50): array
    {
        $rows = Payments::pendingOnline(max(1, min(500, $max)));
        $n = 0; $paid = 0; $o = '';
        foreach ($rows as $p) {
            // skip payments younger than 60 s: the buyer may still be on the provider page
            if (!empty($p['started_at']) && time() - strtotime((string)$p['started_at'] . ' UTC') < 60) continue;
            $before = $p['status'];
            Payments::sync($p, 'cron');
            $n++;
            $after = DB::one('SELECT status FROM payments WHERE id = ?', [$p['id']])['status'] ?? $before;
            if ($after === 'verified') $paid++;
            $o .= $p['ref'] . ' ' . $p['method'] . ' ' . $before . ' -> ' . $after . "\n";
        }
        return self::r(true, $o . "checked $n, confirmed $paid");
    }

    public static function billing(): array
    {
        $r = Billing::runRenewals();
        return self::r(true, 'renewal invoices: ' . $r['invoiced'] . ', lapsed: ' . $r['expired'] . ', stopped at period end: ' . $r['cancelled']);
    }

    public static function rollup(): array
    {
        $day = date('Y-m-d');
        $row = fn(string $where, array $p) => [
            (int)DB::val("SELECT COUNT(*) FROM applicants WHERE country_id='tn' $where", $p),
            (int)DB::val("SELECT COUNT(*) FROM applicants WHERE country_id='tn' AND status IN ('verified','screened','assigned','l1','l2','l3','l4','certified') $where", $p),
            (int)DB::val("SELECT COUNT(*) FROM applicants WHERE country_id='tn' AND status='certified' $where", $p),
        ];
        $sets = [['*', '', []]];
        foreach (['cultures', 'built', 'sciences', 'governance', 'technology'] as $m) $sets[] = [$m, 'AND macro_slug = ?', [$m]];
        foreach ($sets as [$macro, $where, $p]) {
            [$reg, $ver, $cert] = $row($where, $p);
            $rs = (int)DB::val("SELECT COUNT(*) FROM weekly_reports WHERE country_id='tn' AND status IN ('submitted','approved')" . ($macro === '*' ? '' : ' AND 0'));
            $ra = (int)DB::val("SELECT COUNT(*) FROM weekly_reports WHERE country_id='tn' AND status='approved'" . ($macro === '*' ? '' : ' AND 0'));
            DB::q('INSERT INTO rollups_daily(country_id, day, macro_slug, registered, verified, certified, reports_submitted, reports_approved) VALUES (?,?,?,?,?,?,?,?)
                   ON CONFLICT(country_id, day, macro_slug) DO UPDATE SET registered=excluded.registered, verified=excluded.verified, certified=excluded.certified, reports_submitted=excluded.reports_submitted, reports_approved=excluded.reports_approved',
                ['tn', $day, $macro, $reg, $ver, $cert, $rs, $ra]);
        }
        return self::r(true, "rollup ok for $day");
    }

    public static function backup(): array
    {
        $o = '';
        if (Env::get('BACKUP_DRIVER', 'external') === 'external') $o .= "BACKUP_DRIVER=external: configure your off-server shipper to pull storage/backups/latest.sqlite\n";
        $dir = ROOT . '/storage/backups';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $dest = $dir . '/latest.sqlite';
        @unlink($dest);
        DB::pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $dest) . "'");
        @chmod($dest, 0600);
        return self::r(true, $o . 'snapshot: ' . $dest);
    }

    public static function archive(): array
    {
        $driver = Env::get('ARCHIVE_DRIVER', 'prune');
        $week = date('o-\WW');
        $old = (int)DB::val('SELECT COUNT(*) FROM weekly_reports WHERE week_id < ?', [$week]);
        if ($old && $driver === 'local') {
            $dir = ROOT . '/storage/archive';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $h = fopen($dir . '/weekly_reports-' . date('Ymd-His') . '.jsonl', 'w');
            foreach (DB::q('SELECT * FROM weekly_reports WHERE week_id < ?', [$week]) as $r) fwrite($h, json_encode($r, JSON_UNESCAPED_UNICODE) . "\n");
            fclose($h);
        } elseif ($old && $driver === 'external') {
            return self::r(false, "ARCHIVE_DRIVER=external: no S3 adapter is configured in this build; refusing to delete $old rows.", 2);
        }
        DB::q('DELETE FROM weekly_reports WHERE week_id < ?', [$week]);
        if (Env::get('DB_DRIVER', 'sqlite') === 'sqlite') DB::pdo()->exec('VACUUM');
        return self::r(true, "archived/removed $old rows (driver=$driver)");
    }

    public static function health(): array
    {
        $p = Env::get('DB_PATH', 'storage/w5d.sqlite');
        if ($p === '' || $p[0] !== '/') $p = ROOT . '/' . $p;
        $bytes = 0;
        foreach ([$p, $p . '-wal', $p . '-shm'] as $f) if (is_file($f)) $bytes += filesize($f);
        $mb = round($bytes / 1048576, 2);
        $state = $mb > 90 ? 'refuse-uploads' : ($mb > 60 ? 'warn' : 'ok');
        $out = ['db_mb' => $mb, 'state' => $state, 'checked_at' => date('c')];
        @file_put_contents(ROOT . '/storage/health.json', json_encode($out));
        return ['ok' => true, 'exit' => $state === 'ok' ? 0 : 1, 'out' => json_encode($out)];
    }
}
