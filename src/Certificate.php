<?php
declare(strict_types=1);

namespace W5D;

/**
 * Certificates: issued after a passed QCM (certification / upgrade) or by the owner. Public verification by code.
 * A certificate states what a person demonstrated on a test; it is not a job, a diploma or a promise of work.
 * Status 'valid' | 'revoked' is stored; 'expired' is computed from expires_at.
 */
final class Certificate
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function levels(): array { return (require ROOT . '/config/certification.php')['levels']; }
    public static function maxLevel(): int { return count(self::levels()); }
    public static function levelLabel(int $l): string { return t(self::levels()[$l]['label'] ?? ['en' => 'Level ' . $l]); }

    public static function newCode(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $p = [];
            for ($g = 0; $g < 3; $g++) { $s = ''; for ($j = 0; $j < 4; $j++) $s .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)]; $p[] = $s; }
            $c = 'W5D-' . implode('-', $p);
            if (!DB::one('SELECT 1 FROM certificates WHERE code = ?', [$c])) return $c;
        }
        return 'W5D-' . strtoupper(bin2hex(random_bytes(6)));
    }

    public static function validCode(string $c): bool { return (bool)preg_match('/^W5D-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $c); }

    public static function state(array $c): string
    {
        if ($c['status'] === 'revoked') return 'revoked';
        if (!empty($c['expires_at']) && strtotime((string)$c['expires_at']) < time()) return 'expired';
        return 'valid';
    }

    /** Issue a certificate (one valid certificate per user + service + level: re-issuing renews the old one). */
    public static function issue(int $userId, string $service, int $level, ?float $score, ?int $attemptId, string $by): string
    {
        $level = max(1, min(self::maxLevel(), $level));
        $first = !DB::one("SELECT 1 FROM certificates WHERE user_id = ? AND status = 'valid'", [$userId]);
        $months = max(1, (int)Variables::get('cert.validity_months', 24));
        DB::q("UPDATE certificates SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP, revoke_reason = 'renewed' WHERE user_id = ? AND service_slug = ? AND level = ? AND status = 'valid'", [$userId, $service, $level]);
        $code = self::newCode();
        DB::insert('certificates', ['country_id' => 'tn', 'code' => $code, 'user_id' => $userId, 'service_slug' => $service, 'level' => $level, 'score' => $score, 'attempt_id' => $attemptId,
            'status' => 'valid', 'issued_at' => date('Y-m-d H:i:s'), 'expires_at' => date('Y-m-d H:i:s', strtotime("+$months months")), 'issued_by' => $by]);
        $u = DB::one('SELECT id, email, name, lang FROM users WHERE id = ?', [$userId]);
        if ($u) {
            Account::notify($userId, tr3('Certificate issued', 'Certificat délivré', 'صدرت شهادة'), self::levelLabel($level) . ' · ' . $code, '/account/certificates/');
            Mail::send($u['email'], 'certificate_issued', ['name' => $u['name'], 'code' => $code, 'level' => $level, 'service' => $service, 'score' => $score, 'verify_url' => abs_url('/certification/verify/') . '?code=' . $code], $u['lang'] ?? null);
        }
        if ($first) Affiliate::onCertificate($userId);
        return $code;
    }

    public static function revoke(int $id, string $reason): bool
    {
        return DB::q("UPDATE certificates SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP, revoke_reason = ? WHERE id = ? AND status = 'valid'", [mb_substr($reason, 0, 200), $id])->rowCount() > 0;
    }

    public static function find(string $code): ?array
    {
        if (!self::validCode($code)) return null;
        return DB::one('SELECT c.*, u.name AS holder FROM certificates c JOIN users u ON u.id = c.user_id WHERE c.code = ?', [$code]);
    }

    /** "Amel Trabelsi" => "Amel T." : the public verifier never shows a full name or e-mail. */
    public static function maskName(string $n): string
    {
        $p = preg_split('/\s+/u', trim($n)) ?: [];
        if (count($p) < 2) return $p[0] ?? '—';
        return $p[0] . ' ' . mb_substr((string)end($p), 0, 1) . '.';
    }

    public static function serviceTitle(string $slug, ?string $lang = null): string
    {
        $s = Content::get('services', $slug);
        return $s ? t($s['title'], $lang) : $slug;
    }

    /** The public record (also the JSON API body). */
    public static function publicRecord(array $c): array
    {
        return ['code' => $c['code'], 'status' => self::state($c), 'holder' => self::maskName((string)($c['holder'] ?? '')), 'service' => $c['service_slug'], 'service_title' => self::serviceTitle((string)$c['service_slug']),
            'level' => (int)$c['level'], 'level_label' => self::levelLabel((int)$c['level']), 'score' => $c['score'] !== null ? round((float)$c['score'], 1) : null,
            'issued_at' => substr((string)$c['issued_at'], 0, 10), 'expires_at' => $c['expires_at'] ? substr((string)$c['expires_at'], 0, 10) : null, 'issuer' => 'W5D'];
    }

    public static function forUser(int $userId): array
    {
        return DB::all('SELECT * FROM certificates WHERE user_id = ? ORDER BY id DESC', [$userId]);
    }

    /** Highest level currently valid for the user (0 = none). */
    public static function level(int $userId): int
    {
        $rows = DB::all("SELECT level, expires_at FROM certificates WHERE user_id = ? AND status = 'valid'", [$userId]);
        $max = 0;
        foreach ($rows as $r) if (empty($r['expires_at']) || strtotime((string)$r['expires_at']) >= time()) $max = max($max, (int)$r['level']);
        return $max;
    }

    public static function holders(): int
    {
        try { return (int)DB::val("SELECT COUNT(DISTINCT user_id) FROM certificates WHERE status = 'valid' AND (expires_at IS NULL OR expires_at >= datetime('now'))"); } catch (\Throwable) { return 0; }
    }
}
