<?php
declare(strict_types=1);

namespace W5D;

/**
 * Affiliate programme: personal link (?ref=CODE), first-touch cookie, click log (one per visitor per day),
 * referrals (signup bonus, % of verified payments, certificate bonus) and payouts (D17, requested then paid by a person).
 * Every number comes from the `affiliate.*` variables, which the owner edits in Variables & Vocabularies.
 * Nothing here promises income: commissions are credits for real, verified events only.
 */
final class Affiliate
{
    public const COOKIE = 'w5d_ref';
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function enabled(): bool { return (int)Variables::get('affiliate.enabled', 1) === 1; }
    public static function cfg(string $k): mixed { return Variables::get('affiliate.' . $k); }

    public static function forUser(int $userId): ?array { return DB::one('SELECT * FROM affiliates WHERE user_id = ?', [$userId]); }
    public static function byCode(string $code): ?array
    {
        return preg_match('/^[A-Z0-9]{6,12}$/', $code) ? DB::one('SELECT * FROM affiliates WHERE code = ?', [$code]) : null;
    }

    public static function link(string $code, string $path = '/'): string { return abs_url($path) . '?ref=' . $code; }

    private static function newCode(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $c = '';
            for ($j = 0; $j < 8; $j++) $c .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            if (!DB::one('SELECT 1 FROM affiliates WHERE code = ?', [$c])) return $c;
        }
        return strtoupper(bin2hex(random_bytes(5)));
    }

    /** Create the affiliate profile of a signed-in user. Active at once when `affiliate.auto_approve` = 1, else pending. */
    public static function apply(int $userId, string $pitch, string $gsm): int
    {
        $st = (int)self::cfg('auto_approve') === 1 ? 'active' : 'pending';
        return DB::insert('affiliates', ['country_id' => 'tn', 'user_id' => $userId, 'code' => self::newCode(), 'status' => $st, 'pitch' => mb_substr($pitch, 0, 600), 'payout_gsm' => $gsm,
            'approved_at' => $st === 'active' ? date('Y-m-d H:i:s') : null]);
    }

    /* ---------------- tracking ---------------- */
    private static function visitor(): string
    {
        return substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . Secret::key()), 0, 20);
    }

    /** ?ref=CODE on any page: first-touch cookie + one click per visitor per day. Never throws. */
    public static function track(Request $q): void
    {
        if (PHP_SAPI === 'cli' || !self::enabled() || $q->method !== 'GET') return;
        $code = strtoupper(trim((string)($q->query['ref'] ?? '')));
        if ($code === '') return;
        try {
            if (!DB::available()) return;
            $a = self::byCode($code);
            if (!$a || $a['status'] !== 'active') return;
            if (Account::check() && Account::id() === (int)$a['user_id']) return; // no self clicks
            $have = self::byCode(strtoupper((string)($_COOKIE[self::COOKIE] ?? '')));
            if (!$have || $have['status'] !== 'active') {
                $days = max(1, (int)self::cfg('cookie_days'));
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || Env::get('APP_ENV') === 'production';
                setcookie(self::COOKIE, $code, ['expires' => time() + $days * 86400, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
                $_COOKIE[self::COOKIE] = $code;
            }
            DB::q('INSERT OR IGNORE INTO affiliate_clicks (affiliate_id, day, visitor, landing) VALUES (?,?,?,?)', [$a['id'], date('Y-m-d'), self::visitor(), mb_substr($q->path, 0, 120)]);
        } catch (\Throwable) {}
    }

    /** Bind a new account to the affiliate in the cookie (self-referral by account or e-mail is ignored). */
    public static function attach(int $userId): void
    {
        try {
            $a = self::byCode(strtoupper((string)($_COOKIE[self::COOKIE] ?? '')));
            if (!$a || $a['status'] !== 'active' || (int)$a['user_id'] === $userId) return;
            $me = DB::one('SELECT email FROM users WHERE id = ?', [$userId]);
            $aff = DB::one('SELECT email FROM users WHERE id = ?', [$a['user_id']]);
            if (!$me || ($aff && $aff['email'] === $me['email'])) return;
            DB::q('UPDATE users SET referred_by = ? WHERE id = ? AND referred_by IS NULL', [$a['id'], $userId]);
        } catch (\Throwable) {}
    }

    /* ---------------- credits ---------------- */
    private static function credit(int $affId, string $kind, int $userId, string $key, float $base, float $commission): bool
    {
        if ($commission <= 0) return false;
        $a = DB::one('SELECT * FROM affiliates WHERE id = ?', [$affId]);
        if (!$a || $a['status'] === 'suspended' || !self::enabled()) return false;
        $auto = (int)self::cfg('auto_approve') === 1 && $a['status'] === 'active';
        try {
            DB::insert('affiliate_referrals', ['country_id' => 'tn', 'affiliate_id' => $affId, 'kind' => $kind, 'user_id' => $userId, 'ref_key' => $key, 'base_amount' => round($base, 2),
                'commission' => round($commission, 2), 'status' => $auto ? 'approved' : 'pending', 'approved_at' => $auto ? date('Y-m-d H:i:s') : null]);
        } catch (\Throwable) { return false; } // UNIQUE(kind, ref_key): the same event never pays twice
        Account::notify((int)$a['user_id'], tr3('New referral credit', 'Nouveau crédit de parrainage', 'رصيد إحالة جديد'), $kind . ' · ' . number_format($commission, 2) . ' ' . Variables::get('money.currency', 'TND'), '/account/affiliate/');
        return true;
    }

    private static function referrer(int $userId): ?int
    {
        $r = DB::one('SELECT referred_by FROM users WHERE id = ?', [$userId]);
        return $r && $r['referred_by'] ? (int)$r['referred_by'] : null;
    }

    public static function onVerified(int $userId): void
    {
        try { if ($a = self::referrer($userId)) self::credit($a, 'signup', $userId, 'u' . $userId, 0, (float)self::cfg('signup_bonus')); } catch (\Throwable) {}
    }

    /** A person verified a payment (investor order or QCM fee) of this account e-mail. */
    public static function onPayment(string $email, string $ref, float $amount): void
    {
        try {
            $u = DB::one('SELECT id FROM users WHERE email = ?', [mb_strtolower($email)]);
            if (!$u || !($a = self::referrer((int)$u['id']))) return;
            self::credit($a, 'payment', (int)$u['id'], $ref, $amount, $amount * (float)self::cfg('commission_pct') / 100);
        } catch (\Throwable) {}
    }

    public static function onCertificate(int $userId): void
    {
        try { if ($a = self::referrer($userId)) self::credit($a, 'certificate', $userId, 'c' . $userId, 0, (float)self::cfg('cert_bonus')); } catch (\Throwable) {}
    }

    /** Owner decision on one referral line. */
    public static function decide(int $id, bool $approve): bool
    {
        $r = DB::one('SELECT * FROM affiliate_referrals WHERE id = ?', [$id]);
        if (!$r || $r['status'] !== 'pending') return false;
        DB::q('UPDATE affiliate_referrals SET status = ?, approved_at = ? WHERE id = ?', [$approve ? 'approved' : 'rejected', $approve ? date('Y-m-d H:i:s') : null, $id]);
        return true;
    }

    /* ---------------- numbers ---------------- */
    public static function signups(int $affId): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM users WHERE referred_by = ? AND email_verified_at IS NOT NULL', [$affId]);
    }

    public static function stats(int $affId): array
    {
        $hold = max(0, (int)self::cfg('hold_days'));
        $sum = fn(string $w, array $p = []) => (float)DB::val("SELECT COALESCE(SUM(commission),0) FROM affiliate_referrals WHERE affiliate_id = ? AND $w", [$affId, ...$p]);
        return [
            'clicks' => (int)DB::val('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = ?', [$affId]),
            'signups' => self::signups($affId),
            'pending' => $sum("status = 'pending'"),
            'approved' => $sum("status = 'approved'"),
            'paid' => $sum("status = 'paid'"),
            'available' => $sum("status = 'approved' AND paid_in IS NULL AND approved_at <= datetime('now', ?)", ['-' . $hold . ' days']),
            'locked' => $sum("status = 'approved' AND paid_in IS NOT NULL"),
        ];
    }

    /** @return array{0:bool,1:string} [ok, error key] */
    public static function requestPayout(int $affId): array
    {
        $a = DB::one('SELECT * FROM affiliates WHERE id = ?', [$affId]);
        if (!$a || $a['status'] !== 'active') return [false, 'inactive'];
        if (DB::one("SELECT 1 FROM affiliate_payouts WHERE affiliate_id = ? AND status = 'requested'", [$affId])) return [false, 'open'];
        $hold = max(0, (int)self::cfg('hold_days'));
        $rows = DB::all("SELECT id, commission FROM affiliate_referrals WHERE affiliate_id = ? AND status = 'approved' AND paid_in IS NULL AND approved_at <= datetime('now', ?)", [$affId, '-' . $hold . ' days']);
        $amount = round(array_sum(array_map(fn($r) => (float)$r['commission'], $rows)), 2);
        if ($amount < (float)self::cfg('min_payout')) return [false, 'min'];
        if (!$a['payout_gsm']) return [false, 'gsm'];
        $pid = DB::insert('affiliate_payouts', ['country_id' => 'tn', 'affiliate_id' => $affId, 'amount' => $amount, 'gsm' => $a['payout_gsm'], 'status' => 'requested']);
        foreach ($rows as $r) DB::q('UPDATE affiliate_referrals SET paid_in = ? WHERE id = ?', [$pid, $r['id']]);
        Mail::staff('Affiliate payout requested', ['payout' => '#' . $pid, 'affiliate' => $a['code'], 'amount' => number_format($amount, 2), 'gsm' => $a['payout_gsm']]);
        return [true, ''];
    }

    public static function settlePayout(int $id, bool $paid, string $txn, string $note): ?array
    {
        $p = DB::one('SELECT * FROM affiliate_payouts WHERE id = ?', [$id]);
        if (!$p || $p['status'] !== 'requested') return null;
        if ($paid) {
            DB::q("UPDATE affiliate_payouts SET status = 'paid', txn_ref = ?, note = ?, paid_at = CURRENT_TIMESTAMP WHERE id = ?", [$txn, $note ?: null, $id]);
            DB::q("UPDATE affiliate_referrals SET status = 'paid' WHERE paid_in = ?", [$id]);
        } else {
            DB::q("UPDATE affiliate_payouts SET status = 'rejected', note = ? WHERE id = ?", [$note ?: null, $id]);
            DB::q('UPDATE affiliate_referrals SET paid_in = NULL WHERE paid_in = ?', [$id]);
        }
        return DB::one('SELECT p.*, a.user_id, a.code, u.email, u.name, u.lang FROM affiliate_payouts p JOIN affiliates a ON a.id = p.affiliate_id JOIN users u ON u.id = a.user_id WHERE p.id = ?', [$id]);
    }

    public static function setStatus(int $id, string $status): ?array
    {
        if (!in_array($status, ['active', 'suspended', 'pending'], true)) return null;
        $a = DB::one('SELECT a.*, u.email, u.name, u.lang FROM affiliates a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$id]);
        if (!$a || $a['status'] === $status) return null;
        DB::q('UPDATE affiliates SET status = ?, approved_at = CASE WHEN ? = \'active\' THEN COALESCE(approved_at, CURRENT_TIMESTAMP) ELSE approved_at END WHERE id = ?', [$status, $status, $id]);
        return ['status' => $status] + $a;
    }
}
