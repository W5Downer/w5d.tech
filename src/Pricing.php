<?php
declare(strict_types=1);

namespace W5D;

/**
 * The published money x time price function. The ONLY place a part price is computed.
 *   price = base * (1 + alpha * p^p_prog) * (1 + beta * t^p_time), capped at cap * base
 * p = experts_current / experts_target ;  t = elapsed share of (start -> deadline)
 */
final class Pricing
{
    private static function cfg(?array $v = null): array
    {
        if ($v !== null) return $v;
        return Variables::all();
    }

    public static function progress(?array $v = null): float
    {
        $v = self::cfg($v);
        $target = (float)($v['d']['experts_target'] ?? $v['experts']['target'] ?? 0);
        return $target > 0 ? max(0.0, min(1.0, (float)$v['experts']['current'] / $target)) : 0.0;
    }

    public static function timeShare(?\DateTimeInterface $at = null, ?array $v = null): float
    {
        $v = self::cfg($v);
        $tz = new \DateTimeZone('Africa/Tunis');
        $start = new \DateTimeImmutable($v['dates']['start'] . ' 00:00:00', $tz);
        $end = new \DateTimeImmutable($v['dates']['deadline'] . ' 23:59:59', $tz);
        $at = $at ? \DateTimeImmutable::createFromInterface($at) : new \DateTimeImmutable('now', $tz);
        $span = $end->getTimestamp() - $start->getTimestamp();
        if ($span <= 0) return 1.0;
        return max(0.0, min(1.0, ($at->getTimestamp() - $start->getTimestamp()) / $span));
    }

    /** @param string $unit 'day'|'week' */
    public static function perPart(string $unit, float $p, float $t, ?array $v = null): float
    {
        $v = self::cfg($v);
        $base = (float)($unit === 'week' ? $v['money']['base_week'] : $v['money']['base_day']);
        $k = $v['pricing'];
        $p = max(0.0, min(1.0, $p));
        $t = max(0.0, min(1.0, $t));
        $price = $base * (1 + $k['alpha'] * ($p ** $k['p_prog'])) * (1 + $k['beta'] * ($t ** $k['p_time']));
        return round(min($price, $base * $k['cap']), 2);
    }

    public static function snapshot(?array $v = null): array
    {
        $v = $v ?? Variables::all();
        $p = self::progress($v);
        $t = self::timeShare(null, $v);
        return [
            'day' => self::perPart('day', $p, $t, $v),
            'week' => self::perPart('week', $p, $t, $v),
            'p' => round($p, 6), 't' => round($t, 6),
            'cap' => $v['pricing']['cap'],
            'currency' => $v['money']['currency'],
            'base_day' => $v['money']['base_day'], 'base_week' => $v['money']['base_week'],
            'multiple_day' => round(self::perPart('day', $p, $t, $v) / $v['money']['base_day'], 4),
        ];
    }

    /** Curve with p and t moving together (the published chart). */
    public static function curve(int $steps = 20): array
    {
        $v = Variables::all();
        $out = [];
        for ($i = 0; $i <= $steps; $i++) {
            $x = $i / $steps;
            $out[] = ['x' => round($x, 4), 'day' => self::perPart('day', $x, $x, $v), 'week' => self::perPart('week', $x, $x, $v),
                      'time_only' => self::perPart('day', self::progress($v), $x, $v), 'progress_only' => self::perPart('day', $x, self::timeShare(null, $v), $v)];
        }
        return $out;
    }

    /**
     * Day-by-day price schedule for a daily subscription: price at each date uses that date's t
     * and the CURRENT progress p (a forecast; the real price on the day is recomputed).
     */
    public static function schedule(\DateTimeInterface $from, int $days, int $partsPerDay): array
    {
        $v = Variables::all();
        $p = self::progress($v);
        $rows = [];
        $total = 0.0;
        $tz = new \DateTimeZone('Africa/Tunis');
        $now = new \DateTimeImmutable('now', $tz);
        $d0 = \DateTimeImmutable::createFromInterface($from)->setTimezone($tz)->setTime(12, 0);
        for ($i = 0; $i < $days; $i++) {
            $d = $d0->modify("+{$i} day");
            // Today's charge is priced at the live instant so a one-day quote equals the published price;
            // later days are forecast at noon (the real price on the day is recomputed).
            $isToday = $d->format('Y-m-d') === $now->format('Y-m-d');
            $t = self::timeShare($isToday ? $now : $d, $v);
            $unit = self::perPart('day', $p, $t, $v);
            $amount = round($unit * $partsPerDay, 2);
            $total += $amount;
            $rows[] = ['date' => $d->format('Y-m-d'), 't' => round($t, 4), 'unit' => $unit, 'amount' => $amount];
        }
        return ['rows' => $rows, 'total' => round($total, 2), 'p' => $p, 'parts_per_day' => $partsPerDay, 'days' => $days];
    }
}
