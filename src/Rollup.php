<?php
declare(strict_types=1);

namespace W5D;

/** Every public KPI reads rollups_daily only. Never the raw weekly_reports. */
final class Rollup
{
    private static ?array $latest = null;

    public static function latest(): ?array
    {
        if (self::$latest !== null) return self::$latest ?: null;
        try {
            if (!DB::available()) return null;
            $r = DB::one("SELECT * FROM rollups_daily WHERE country_id = 'tn' AND macro_slug = '*' ORDER BY day DESC LIMIT 1");
        } catch (\Throwable) { $r = null; }
        self::$latest = $r ?: [];
        return $r ?: null;
    }

    public static function expertsCurrent(int $fallback = 0): int
    {
        $r = self::latest();
        return $r ? (int)$r['certified'] : $fallback;
    }

    public static function kpis(): array
    {
        $r = self::latest() ?? [];
        $inv = [];
        try {
            if (DB::available()) {
                $inv = DB::one("SELECT COUNT(*) AS n, COALESCE(SUM(parts_per_day),0) AS parts FROM investor_subscriptions WHERE status = 'active'") ?: [];
            }
        } catch (\Throwable) {}
        return [
            'as_of' => $r['day'] ?? null,
            'registered' => (int)($r['registered'] ?? 0),
            'verified' => (int)($r['verified'] ?? 0),
            'certified' => (int)($r['certified'] ?? 0),
            'reports_submitted' => (int)($r['reports_submitted'] ?? 0),
            'investors_active' => (int)($inv['n'] ?? 0),
            'parts_per_day_committed' => (int)($inv['parts'] ?? 0),
            'source' => 'rollups_daily',
        ];
    }
}
