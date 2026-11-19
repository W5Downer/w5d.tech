<?php
declare(strict_types=1);

namespace W5D;

/**
 * QCM (multiple-choice) engine.
 *
 *  Bank      data/qcm/{service-slug}.json — one file per service, questions of level 1..4, `single` or `multiple` answer,
 *            every text in en/fr/ar. The owner edits them in the portal (QCM bank) or by replacing the files.
 *  Rules     qcm_rules (owner CRUD): purpose (practice | certification | upgrade), level, question count, minutes, pass %,
 *            and the minimum requirements: certified level, affiliates, approved tasks, practice score, cooldown, daily limit, fee.
 *  Attempt   a random service for the rule's level (or the rule's fixed service), N random questions, a server-side deadline.
 *            Questions are snapshotted into the attempt, so editing the bank never changes a round in progress.
 *  Scoring   single: the one right choice. multiple: the exact set of right choices (all or nothing). pass_pct decides.
 *  Result    passing a certification or upgrade round issues a certificate (src/Certificate.php).
 */
final class Qcm
{
    public const LEVELS = [1, 2, 3, 4];
    public const PURPOSES = ['practice', 'certification', 'upgrade'];
    public const IDS = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];

    public static function dir(): string { return ROOT . '/data/qcm'; }
    public static function enabled(): bool { return (int)Variables::get('qcm.enabled', 1) === 1; }
    public static function validSlug(string $s): bool { return (bool)preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/', $s) && Content::get('services', $s) !== null; }

    /* ======================= bank ======================= */
    /** @return list<array> every question of the service (inactive included) */
    public static function bank(string $slug): array
    {
        static $cache = [];
        if (isset($cache[$slug])) return $cache[$slug];
        $f = self::dir() . '/' . $slug . '.json';
        if (!self::validSlug($slug) || !is_file($f)) return $cache[$slug] = [];
        $j = json_decode((string)file_get_contents($f), true);
        return $cache[$slug] = is_array($j['questions'] ?? null) ? $j['questions'] : [];
    }

    private static function forget(string $slug): void { @unlink(ROOT . '/storage/cache/qcm-counts.json'); }

    /** @return array<string,array{1:int,2:int,3:int,4:int,all:int,off:int}> active question counts per service and level (cached by file mtime) */
    public static function counts(): array
    {
        $cf = ROOT . '/storage/cache/qcm-counts.json';
        $old = is_file($cf) ? (json_decode((string)file_get_contents($cf), true) ?: []) : [];
        $out = []; $dirty = false;
        foreach (Content::all('services') as $s) {
            $slug = $s['slug']; $f = self::dir() . '/' . $slug . '.json';
            $mt = is_file($f) ? (int)filemtime($f) : 0;
            if (isset($old[$slug]) && ($old[$slug]['mt'] ?? -1) === $mt) { $out[$slug] = $old[$slug]; continue; }
            $c = ['mt' => $mt, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 'all' => 0, 'off' => 0];
            foreach (self::bank($slug) as $q) {
                if (empty($q['active'])) { $c['off']++; continue; }
                $c[(int)$q['level']] = ($c[(int)$q['level']] ?? 0) + 1; $c['all']++;
            }
            $out[$slug] = $c; $dirty = true;
        }
        if ($dirty) { @mkdir(dirname($cf), 0775, true); @file_put_contents($cf, json_encode($out), LOCK_EX); }
        return $out;
    }

    /** @return list<string> human errors (empty = valid) */
    public static function validate(array $q): array
    {
        $e = [];
        $tri = fn($v) => is_array($v) && !array_diff(['en', 'fr', 'ar'], array_keys(array_filter($v, fn($x) => is_string($x) && trim($x) !== '')));
        if (!preg_match('/^[a-z0-9-]+\.L[1-4]\.\d{3,5}$/', (string)($q['id'] ?? ''))) $e[] = 'id must look like service-slug.L2.017';
        if (!in_array((int)($q['level'] ?? 0), self::LEVELS, true)) $e[] = 'level must be 1 to 4';
        $type = (string)($q['type'] ?? '');
        if (!in_array($type, ['single', 'multiple'], true)) $e[] = 'type must be single or multiple';
        if (!$tri($q['q'] ?? null)) $e[] = 'the question needs en, fr and ar text';
        $ch = $q['choices'] ?? null;
        if (!is_array($ch) || count($ch) < 2 || count($ch) > 8) $e[] = 'between 2 and 8 choices';
        else {
            $ids = [];
            foreach ($ch as $c) {
                if (!is_array($c) || !in_array($c['id'] ?? '', self::IDS, true)) { $e[] = 'choice ids are a to h'; break; }
                if (in_array($c['id'], $ids, true)) { $e[] = 'duplicate choice id ' . $c['id']; break; }
                $ids[] = $c['id'];
                if (!$tri($c['t'] ?? null)) { $e[] = 'choice ' . $c['id'] . ' needs en, fr and ar text'; break; }
            }
            $a = $q['answer'] ?? null;
            if (!is_array($a) || !$a || array_diff($a, $ids) || count($a) !== count(array_unique($a))) $e[] = 'answer must list existing choice ids';
            elseif ($type === 'single' && count($a) !== 1) $e[] = 'a single-answer question has exactly one right choice';
            elseif ($type === 'multiple' && (count($a) < 2 || count($a) >= count($ids))) $e[] = 'a multiple-answer question has at least 2 right choices and at least 1 wrong one';
        }
        if (isset($q['explain']) && !is_array($q['explain'])) $e[] = 'explain must be {en,fr,ar}';
        return $e;
    }

    /** Atomic write: one question per line, so the files diff and review well. */
    public static function save(string $slug, array $questions): void
    {
        if (!self::validSlug($slug)) throw new \InvalidArgumentException('bad service');
        $lines = array_map(fn($q) => json_encode($q, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), array_values($questions));
        $txt = "{\n\"schema\": 1,\n\"service\": " . json_encode($slug) . ",\n\"speciality\": \"\",\n\"questions\": [\n" . implode(",\n", $lines) . "\n]\n}\n";
        if (!is_dir(self::dir())) @mkdir(self::dir(), 0775, true);
        $tmp = self::dir() . '/.' . $slug . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $txt, LOCK_EX);
        if (!rename($tmp, self::dir() . '/' . $slug . '.json')) { @unlink($tmp); throw new \RuntimeException('could not write the bank'); }
        self::forget($slug);
    }

    public static function nextId(string $slug, int $level): string
    {
        $max = 0;
        foreach (self::bank($slug) as $q) if (preg_match('/\.L' . $level . '\.(\d+)$/', (string)$q['id'], $m)) $max = max($max, (int)$m[1]);
        return sprintf('%s.L%d.%03d', $slug, $level, $max + 1);
    }

    /* ======================= rules + requirements ======================= */
    public static function rules(bool $onlyActive = true): array
    {
        return DB::all('SELECT * FROM qcm_rules ' . ($onlyActive ? 'WHERE active = 1 ' : '') . 'ORDER BY sort, id');
    }

    public static function rule(int $id): ?array { return DB::one('SELECT * FROM qcm_rules WHERE id = ? AND active = 1', [$id]); }

    public static function approvedTasks(int $uid): int { return (int)DB::val("SELECT COUNT(*) FROM qcm_tasks WHERE user_id = ? AND status = 'approved'", [$uid]); }

    public static function affiliateCount(int $uid): int
    {
        $a = Affiliate::forUser($uid);
        return $a ? Affiliate::signups((int)$a['id']) : 0;
    }

    public static function bestPractice(int $uid, int $level): float
    {
        return (float)DB::val("SELECT COALESCE(MAX(score_pct), 0) FROM qcm_attempts WHERE user_id = ? AND purpose = 'practice' AND level = ? AND status = 'submitted'", [$uid, $level]);
    }

    /** A verified, unused fee pass for this rule (the user pays once per round). */
    public static function openPass(int $uid, int $ruleId): ?array
    {
        return DB::one("SELECT * FROM qcm_passes WHERE user_id = ? AND rule_id = ? AND status = 'verified' ORDER BY id LIMIT 1", [$uid, $ruleId]);
    }

    public static function pendingPass(int $uid, int $ruleId): ?array
    {
        return DB::one("SELECT * FROM qcm_passes WHERE user_id = ? AND rule_id = ? AND status IN ('awaiting','pending') ORDER BY id DESC LIMIT 1", [$uid, $ruleId]);
    }

    /**
     * Every minimum requirement of a rule, with what the person has and what is needed.
     * @return array{ok:bool,checks:list<array>,pass:?array,wait:?int}
     */
    public static function check(int $uid, array $r): array
    {
        $c = [];
        $add = function (string $k, bool $ok, string $have, string $need) use (&$c) { $c[] = ['key' => $k, 'ok' => $ok, 'have' => $have, 'need' => $need]; };
        $myLevel = Certificate::level($uid);
        if ((int)$r['min_level'] > 0) $add('level', $myLevel >= (int)$r['min_level'], $myLevel ? 'L' . $myLevel : '—', 'L' . (int)$r['min_level']);
        if ((int)$r['min_affiliates'] > 0) { $n = self::affiliateCount($uid); $add('affiliates', $n >= (int)$r['min_affiliates'], (string)$n, (string)(int)$r['min_affiliates']); }
        if ((int)$r['min_tasks'] > 0) { $n = self::approvedTasks($uid); $add('tasks', $n >= (int)$r['min_tasks'], (string)$n, (string)(int)$r['min_tasks']); }
        if ((int)$r['min_practice_pct'] > 0) { $b = self::bestPractice($uid, (int)$r['level']); $add('practice', $b >= (int)$r['min_practice_pct'], round($b) . '%', (int)$r['min_practice_pct'] . '%'); }
        $wait = null;
        if ((int)$r['cooldown_hours'] > 0) {
            $last = DB::val("SELECT MAX(started_at) FROM qcm_attempts WHERE user_id = ? AND rule_id = ?", [$uid, $r['id']]);
            $left = $last ? strtotime((string)$last) + (int)$r['cooldown_hours'] * 3600 - time() : 0;
            if ($left > 0) $wait = $left;
            $add('cooldown', $left <= 0, $left > 0 ? self::human($left) : '—', (int)$r['cooldown_hours'] . ' h');
        }
        if ((int)$r['max_per_day'] > 0) {
            $n = (int)DB::val("SELECT COUNT(*) FROM qcm_attempts WHERE user_id = ? AND rule_id = ? AND started_at >= ?", [$uid, $r['id'], date('Y-m-d 00:00:00')]);
            $add('daily', $n < (int)$r['max_per_day'], (string)$n, '< ' . (int)$r['max_per_day']);
        }
        $pass = null;
        if ((float)$r['fee'] > 0) {
            $pass = self::openPass($uid, (int)$r['id']);
            $add('fee', (bool)$pass, $pass ? tr3('paid', 'payé', 'مدفوع') : tr3('not paid', 'non payé', 'غير مدفوع'), number_format((float)$r['fee'], 2) . ' ' . Variables::get('money.currency', 'TND'));
        }
        $ok = true;
        foreach ($c as $x) if (!$x['ok']) $ok = false;
        return ['ok' => $ok, 'checks' => $c, 'pass' => $pass, 'wait' => $wait];
    }

    public static function human(int $sec): string
    {
        if ($sec >= 3600) return (int)ceil($sec / 3600) . ' h';
        return (int)ceil($sec / 60) . ' min';
    }

    /* ======================= attempts ======================= */
    public static function open(int $uid): ?array
    {
        $a = DB::one("SELECT * FROM qcm_attempts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [$uid]);
        if ($a && time() > strtotime((string)$a['deadline_at']) + (int)Variables::get('qcm.grace_seconds', 15)) { self::finish($a, null, true); return null; }
        return $a;
    }

    public static function attempt(int $uid, int $id): ?array
    {
        return DB::one('SELECT * FROM qcm_attempts WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    /** Services that can serve this rule right now (enough active questions at its level). */
    public static function pickService(int $uid, array $r): ?string
    {
        $n = (int)$r['question_count'];
        if ($r['service_slug'] !== '') return (self::counts()[$r['service_slug']][(int)$r['level']] ?? 0) >= $n ? (string)$r['service_slug'] : null;
        $pool = [];
        foreach (self::counts() as $slug => $c) if (($c[(int)$r['level']] ?? 0) >= $n) $pool[] = $slug;
        if (!$pool) return null;
        // prefer services the person does not hold at this level yet; fall back to any
        $have = array_column(DB::all("SELECT service_slug FROM certificates WHERE user_id = ? AND level = ? AND status = 'valid'", [$uid, (int)$r['level']]), 'service_slug');
        $fresh = array_values(array_diff($pool, $have));
        $use = $fresh ?: $pool;
        return $use[random_int(0, count($use) - 1)];
    }

    /** @return array{0:?int,1:string} [attempt id, error key: '' | disabled | rule | requirements | bank | open] */
    public static function start(int $uid, array $r): array
    {
        if (!self::enabled()) return [null, 'disabled'];
        if ($open = self::open($uid)) return [(int)$open['id'], 'open'];
        $chk = self::check($uid, $r);
        if (!$chk['ok']) return [null, 'requirements'];
        $slug = self::pickService($uid, $r);
        if (!$slug) return [null, 'bank'];
        $pool = array_values(array_filter(self::bank($slug), fn($q) => !empty($q['active']) && (int)$q['level'] === (int)$r['level'] && !self::validate($q)));
        shuffle($pool);
        $items = [];
        foreach (array_slice($pool, 0, (int)$r['question_count']) as $q) {
            $ch = $q['choices']; shuffle($ch);
            $items[] = ['id' => $q['id'], 'type' => $q['type'], 'q' => $q['q'], 'choices' => $ch, 'answer' => $q['answer'], 'explain' => $q['explain'] ?? null];
        }
        if (count($items) < (int)$r['question_count']) return [null, 'bank'];
        $now = time(); $mins = max(1, (int)$r['minutes']);
        $meta = ['pass_pct' => (int)$r['pass_pct'], 'minutes' => $mins, 'rule' => $r['name'], 'fee' => (float)$r['fee']];
        $id = DB::insert('qcm_attempts', ['country_id' => 'tn', 'user_id' => $uid, 'rule_id' => (int)$r['id'], 'purpose' => $r['purpose'], 'level' => (int)$r['level'], 'service_slug' => $slug,
            'status' => 'open', 'questions' => json_encode(['meta' => $meta, 'items' => $items], JSON_UNESCAPED_UNICODE), 'answers' => '{}',
            'started_at' => date('Y-m-d H:i:s', $now), 'deadline_at' => date('Y-m-d H:i:s', $now + $mins * 60), 'total' => count($items), 'pass_id' => $chk['pass']['id'] ?? null]);
        if ($chk['pass']) DB::q("UPDATE qcm_passes SET status = 'used', attempt_id = ? WHERE id = ?", [$id, $chk['pass']['id']]);
        return [$id, ''];
    }

    public static function items(array $a): array { return json_decode((string)$a['questions'], true)['items'] ?? []; }
    public static function meta(array $a): array { return json_decode((string)$a['questions'], true)['meta'] ?? []; }
    public static function answersOf(array $a): array { return json_decode((string)$a['answers'], true) ?: []; }

    public static function secondsLeft(array $a): int { return max(0, strtotime((string)$a['deadline_at']) - time()); }
    public static function inWindow(array $a): bool { return time() <= strtotime((string)$a['deadline_at']) + (int)Variables::get('qcm.grace_seconds', 15); }

    /** Keep only valid choice ids for a question. */
    private static function clean(array $items, array $raw): array
    {
        $out = [];
        foreach ($items as $it) {
            $v = $raw[$it['id']] ?? null;
            if ($v === null) continue;
            $v = array_values(array_unique(array_map('strval', (array)$v)));
            $ids = array_column($it['choices'], 'id');
            $v = array_values(array_intersect($v, $ids));
            if ($it['type'] === 'single') $v = array_slice($v, 0, 1);
            if ($v) $out[$it['id']] = $v;
        }
        return $out;
    }

    /** Autosave one answer while the round is open. */
    public static function saveAnswer(array $a, string $qid, array $choice): bool
    {
        if ($a['status'] !== 'open' || !self::inWindow($a)) return false;
        $items = self::items($a);
        $cur = self::answersOf($a);
        $one = self::clean($items, [$qid => $choice]);
        if ($one) $cur[$qid] = $one[$qid]; else unset($cur[$qid]);
        DB::q('UPDATE qcm_attempts SET answers = ? WHERE id = ?', [json_encode($cur), $a['id']]);
        return true;
    }

    public static function same(array $a, array $b): bool { sort($a); sort($b); return $a === $b; }

    /** Score, store, and (for a pass in a graded round) issue the certificate. */
    public static function finish(array $a, ?array $posted, bool $late = false): array
    {
        if ($a['status'] === 'submitted') return $a;
        $items = self::items($a);
        $ans = self::answersOf($a);
        if ($posted !== null && !$late && self::inWindow($a)) $ans = self::clean($items, $posted) + $ans; // posted answers win, autosaved ones fill the gaps
        $ok = 0;
        foreach ($items as $it) if (isset($ans[$it['id']]) && self::same($ans[$it['id']], $it['answer'])) $ok++;
        $total = max(1, count($items));
        $pct = round($ok * 100 / $total, 1);
        $pass = $pct >= (float)(self::meta($a)['pass_pct'] ?? 70);
        $timedOut = $late || time() > strtotime((string)$a['deadline_at']) + (int)Variables::get('qcm.grace_seconds', 15);
        $cert = null;
        DB::q("UPDATE qcm_attempts SET status = 'submitted', answers = ?, submitted_at = CURRENT_TIMESTAMP, correct = ?, total = ?, score_pct = ?, passed = ?, timed_out = ? WHERE id = ? AND status = 'open'",
            [json_encode($ans), $ok, count($items), $pct, $pass ? 1 : 0, $timedOut ? 1 : 0, $a['id']]);
        if ($pass && in_array($a['purpose'], ['certification', 'upgrade'], true)) {
            $cert = Certificate::issue((int)$a['user_id'], (string)$a['service_slug'], (int)$a['level'], $pct, (int)$a['id'], 'qcm');
            DB::q('UPDATE qcm_attempts SET cert_code = ? WHERE id = ?', [$cert, $a['id']]);
        }
        Account::notify((int)$a['user_id'], tr3('QCM result', 'Résultat du QCM', 'نتيجة الاختبار'), round($pct) . '% · ' . ($pass ? tr3('passed', 'réussi', 'ناجح') : tr3('not passed', 'non réussi', 'غير ناجح')), '/account/qcm/attempt/' . $a['id'] . '/');
        return DB::one('SELECT * FROM qcm_attempts WHERE id = ?', [$a['id']]) ?: $a;
    }

    /** Per-question review rows for the result page (answers only revealed where the owner allows it). */
    public static function review(array $a): array
    {
        $ans = self::answersOf($a);
        $reveal = $a['purpose'] === 'practice' && (int)Variables::get('qcm.show_answers_practice', 1) === 1;
        $out = [];
        foreach (self::items($a) as $i => $it) {
            $mine = $ans[$it['id']] ?? [];
            $ok = $mine && self::same($mine, $it['answer']);
            $out[] = ['n' => $i + 1, 'q' => $it['q'], 'type' => $it['type'], 'ok' => $ok, 'skipped' => !$mine, 'reveal' => $reveal,
                'choices' => array_map(fn($c) => ['id' => $c['id'], 't' => $c['t'], 'mine' => in_array($c['id'], $mine, true), 'right' => $reveal && in_array($c['id'], $it['answer'], true)], $it['choices']),
                'explain' => $reveal ? ($it['explain'] ?? null) : null];
        }
        return $out;
    }

    /* ======================= fee passes + tasks ======================= */
    public static function buyPass(int $uid, array $r): ?array
    {
        if ((float)$r['fee'] <= 0) return null;
        if ($p = self::pendingPass($uid, (int)$r['id'])) return $p;
        if (self::openPass($uid, (int)$r['id'])) return self::openPass($uid, (int)$r['id']);
        $ref = 'QP-' . strtoupper(bin2hex(random_bytes(4)));
        DB::insert('qcm_passes', ['country_id' => 'tn', 'ref' => $ref, 'user_id' => $uid, 'rule_id' => (int)$r['id'], 'amount' => round((float)$r['fee'], 2), 'currency' => (string)Variables::get('money.currency', 'TND'), 'status' => 'awaiting']);
        return DB::one('SELECT * FROM qcm_passes WHERE ref = ?', [$ref]);
    }

    public static function passLink(string $ref): string { return url('/account/qcm/pay/' . $ref . '/'); }

    public static function stats(): array
    {
        $n = fn(string $sql) => (int)DB::val($sql);
        return ['attempts' => $n('SELECT COUNT(*) FROM qcm_attempts'), 'submitted' => $n("SELECT COUNT(*) FROM qcm_attempts WHERE status = 'submitted'"), 'passed' => $n('SELECT COUNT(*) FROM qcm_attempts WHERE passed = 1'),
            'avg' => (float)DB::val("SELECT COALESCE(AVG(score_pct),0) FROM qcm_attempts WHERE status = 'submitted'"), 'passes_pending' => $n("SELECT COUNT(*) FROM qcm_passes WHERE status = 'pending'"),
            'tasks_pending' => $n("SELECT COUNT(*) FROM qcm_tasks WHERE status = 'submitted'")];
    }
}
