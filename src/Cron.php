<?php
declare(strict_types=1);

namespace W5D;

/**
 * Scheduled jobs for hosts WITH and WITHOUT cron.
 *
 *   CRON_MODE=system   (default) the host's crontab runs bin/cron.php (one line) or the six bin/*.php scripts.
 *   CRON_MODE=web      no crontab: an outside pinger (cron-job.org, UptimeRobot, a GitHub Actions schedule ...) opens
 *                      https://your-site/cron/<CRON_TOKEN>/ every 5 minutes.
 *   CRON_MODE=request  no crontab and no pinger: due jobs run after ordinary page requests, at most one job per request and
 *                      one check per minute. Works only while the site has visitors.
 *
 * Whatever starts a job, the same code runs (src/Jobs.php), a per-job file lock keeps two runners apart, and the result is kept in
 * storage/cron-state.json so the System screen and bin/preflight.php can tell whether anything really runs.
 */
final class Cron
{
    /** id => [interval seconds, what it does, the equivalent crontab schedule, bin script] */
    public const JOBS = [
        'payments-sync' => [600, 'recheck online payments whose webhook was missed', '*/10 * * * *', 'payments-sync.php'],
        'billing' => [86400, 'renewal invoices and grace periods', '15 6 * * *', 'billing.php'],
        'rollup' => [3600, 'dashboard figures', '5 * * * *', 'rollup.php'],
        'backup' => [86400, 'consistent database copy (ship it off the server)', '30 3 * * *', 'backup.php'],
        'archive' => [604800, 'weekly clean-up', '45 3 * * 0', 'archive.php'],
        'health' => [900, 'storage thresholds', '*/15 * * * *', 'health.php'],
    ];

    public const MODES = ['system', 'web', 'request'];

    public static function mode(): string
    {
        $m = strtolower((string)Env::get('CRON_MODE', 'system'));
        return in_array($m, self::MODES, true) ? $m : 'system';
    }

    /** Secret of the web endpoint. Empty = the endpoint does not exist (404). */
    public static function token(): string
    {
        $t = (string)Env::get('CRON_TOKEN', '');
        return strlen($t) >= 24 ? $t : '';
    }

    public static function newToken(): string { return bin2hex(random_bytes(20)); }

    public static function tokenOk(string $given): bool
    {
        $t = self::token();
        return $t !== '' && hash_equals($t, $given);
    }

    /* ------------------------------------------------------------------ state */

    private static function stateFile(): string { return ROOT . '/storage/cron-state.json'; }

    /** @return array{jobs: array<string, array<string, mixed>>, tick?: int} */
    public static function state(): array
    {
        $j = json_decode((string)@file_get_contents(self::stateFile()), true);
        return is_array($j) && isset($j['jobs']) && is_array($j['jobs']) ? $j : ['jobs' => []];
    }

    private static function update(callable $fn): void
    {
        $f = self::stateFile();
        $fh = @fopen($f, 'c+');
        if (!$fh) return;
        flock($fh, LOCK_EX);
        $cur = json_decode((string)stream_get_contents($fh), true);
        $st = is_array($cur) && isset($cur['jobs']) ? $cur : ['jobs' => []];
        $st = $fn($st);
        ftruncate($fh, 0); rewind($fh);
        fwrite($fh, json_encode($st, JSON_UNESCAPED_SLASHES));
        fflush($fh); flock($fh, LOCK_UN); fclose($fh);
        @chmod($f, 0600);
    }

    /** Seconds since the job last ran, or null when it never did. */
    public static function age(string $id, ?array $st = null): ?int
    {
        $at = ($st ?? self::state())['jobs'][$id]['at'] ?? null;
        return $at === null ? null : max(0, time() - (int)$at);
    }

    public static function isDue(string $id, ?array $st = null): bool
    {
        $iv = self::JOBS[$id][0];
        $age = self::age($id, $st);
        return $age === null || $age >= $iv - min((int)($iv * 0.1), 120);
    }

    /**
     * Overall health of the schedule for the System screen and the preflight.
     * @return array{level:string,text:string,rows:list<array<string,mixed>>}
     */
    public static function status(): array
    {
        $st = self::state(); $rows = []; $never = 0; $late = 0;
        foreach (self::JOBS as $id => [$iv]) {
            $age = self::age($id, $st);
            $j = $st['jobs'][$id] ?? null;
            $isLate = $age === null || $age > $iv * 3 + 600;
            if ($age === null) $never++; elseif ($isLate) $late++;
            $rows[] = ['id' => $id, 'age' => $age, 'ok' => $j ? (bool)($j['ok'] ?? false) : null, 'by' => $j['by'] ?? null, 'late' => $isLate, 'interval' => $iv];
        }
        $n = count(self::JOBS);
        if ($never === $n) return ['level' => 'warn', 'text' => 'no scheduled job has ever run: add the cron line, or use the web/request mode (see docs/INSTALL.md)', 'rows' => $rows];
        if ($never || $late) return ['level' => 'warn', 'text' => ($never + $late) . ' of ' . $n . ' jobs are overdue or never ran', 'rows' => $rows];
        return ['level' => 'ok', 'text' => 'all ' . $n . ' jobs ran on schedule', 'rows' => $rows];
    }

    /* ------------------------------------------------------------------ running */

    /** Run one job now (under its lock). @return array{ran:bool,ok:bool,exit:int,out:string,ms:int} */
    public static function runJob(string $id, string $by): array
    {
        if (!isset(self::JOBS[$id])) return ['ran' => false, 'ok' => false, 'exit' => 2, 'out' => 'unknown job', 'ms' => 0];
        $dir = ROOT . '/storage/cache';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $lock = @fopen($dir . '/cron-' . $id . '.lock', 'c');
        if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return ['ran' => false, 'ok' => true, 'exit' => 0, 'out' => 'already running', 'ms' => 0]; }
        $t0 = microtime(true);
        try {
            $fn = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $id))));
            $r = Jobs::$fn();
        } catch (\Throwable $e) {
            $r = ['ok' => false, 'exit' => 1, 'out' => $e::class . ': ' . $e->getMessage()];
        } finally {
            if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
        }
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $msg = mb_substr(str_replace(ROOT . '/', '', (string)$r['out']), 0, 300);
        self::update(function (array $st) use ($id, $by, $r, $ms, $msg): array {
            $st['jobs'][$id] = ['at' => time(), 'ok' => $r['ok'], 'exit' => $r['exit'], 'ms' => $ms, 'by' => $by, 'msg' => $msg];
            return $st;
        });
        return ['ran' => true, 'ok' => $r['ok'], 'exit' => $r['exit'], 'out' => (string)$r['out'], 'ms' => $ms];
    }

    /**
     * Run every job that is due (or all of them with $force), most overdue first.
     * @return array<string, array<string, mixed>> id => result (ran=false when not due)
     */
    public static function runDue(string $by, bool $force = false, ?int $max = null, ?string $only = null): array
    {
        $st = self::state(); $order = [];
        foreach (self::JOBS as $id => [$iv]) {
            if ($only !== null && $only !== $id) continue;
            if (!$force && !self::isDue($id, $st)) continue;
            $age = self::age($id, $st);
            $order[$id] = $age === null ? PHP_INT_MAX : $age / $iv;
        }
        arsort($order);
        $out = []; $n = 0;
        foreach (array_keys($order) as $id) {
            if ($max !== null && $n >= $max) break;
            $out[$id] = self::runJob($id, $by);
            if ($out[$id]['ran']) $n++;
        }
        foreach (self::JOBS as $id => [$iv]) if (!isset($out[$id]) && ($only === null || $only === $id)) $out[$id] = ['ran' => false, 'ok' => true, 'exit' => 0, 'out' => 'not due', 'ms' => 0];
        return $out;
    }

    /* ------------------------------------------------------------------ entry points */

    /** GET|POST /cron/{token}/  [?job=backup] [&force=1]: the address an outside pinger opens. Plain text, never cached or indexed. */
    public static function endpoint(Request $q): Response
    {
        $h = ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store'];
        $given = (string)($q->params['token'] ?? '');
        if (!self::tokenOk($given)) {
            // wrong or unset token: indistinguishable from a missing page; guessing is throttled per address
            RateLimit::hit('cron-bad:' . $q->ip(), 20, 3600);
            return Response::text("not found\n", 'text/plain', 404)->withHeaders($h);
        }
        if (!RateLimit::hit('cron-ok:' . $q->ip(), 120, 3600)) return Response::text("slow down\n", 'text/plain', 429)->withHeaders($h + ['Retry-After' => '60']);
        ignore_user_abort(true);
        @set_time_limit(55);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $only = isset($q->query['job']) && isset(self::JOBS[(string)$q->query['job']]) ? (string)$q->query['job'] : null;
        $res = self::runDue('web', isset($q->query['force']) && $q->query['force'] === '1', null, $only);
        $o = '';
        foreach ($res as $id => $r) $o .= sprintf("%-14s %s\n", $id, $r['ran'] ? ($r['ok'] ? 'ran ok' : 'ran WITH ERROR') . ' (' . $r['ms'] . ' ms)' : $r['out']);
        $fail = count(array_filter($res, fn($r) => $r['ran'] && !$r['ok']));
        return Response::text($o, 'text/plain', $fail ? 500 : 200)->withHeaders($h);
    }

    /** Request-driven mode: after the page has been sent, run ONE due job, at most one check per minute. Never throws. */
    public static function afterRequest(Request $q): void
    {
        try {
            if (PHP_SAPI === 'cli' || self::mode() !== 'request' || str_starts_with($q->path, '/cron/')) return;
            $st = self::state();
            if (time() - (int)($st['tick'] ?? 0) < 60) return;
            self::update(function (array $s): array { $s['tick'] = time(); return $s; });
            if (!array_filter(array_keys(self::JOBS), fn($id) => self::isDue($id, $st))) return;
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request(); // the visitor already has the page
            ignore_user_abort(true);
            @set_time_limit(40);
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            self::runDue('request', false, 1);
        } catch (\Throwable) { /* a job problem must never break a page */ }
    }

    /** Is this request one that must not open a session (a pinger every few minutes would fill storage/sessions)? */
    public static function sessionless(): bool
    {
        if (PHP_SAPI === 'cli') return false;
        $p = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
        $base = rtrim((string)Env::get('APP_BASE_PATH', ''), '/');
        if ($base !== '' && str_starts_with($p, $base)) $p = substr($p, strlen($base));
        return (bool)preg_match('#^/cron/[^/]+/?$#', $p);
    }

    /** One crontab line per job, or the single-line alternative. */
    public static function cronLines(string $php = 'php', ?string $root = null): array
    {
        $root ??= ROOT;
        $o = [];
        foreach (self::JOBS as $id => [, $why, $when, $script]) $o[] = ['when' => $when, 'cmd' => $php . ' ' . $root . '/bin/' . $script, 'why' => $why];
        return $o;
    }
}
