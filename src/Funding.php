<?php
declare(strict_types=1);

namespace W5D;

/**
 * Pre-registry funding stack. Every number and switch is a .env token (see .env.example, "Funding stack");
 * nothing here (or in content, lang, templates) may hard-code an amount: bin/lint.php enforces it.
 *
 * channelAvailable() is FAIL-CLOSED: a missing token, a missing record, an unreadable template or a database
 * error all mean "unavailable". The reason is always returned so every call site can show it.
 *
 * Legal and fiscal wording around these channels is research to verify with a Tunisian accountant and lawyer
 * (docs/LEGAL-REVIEW.md). Nothing here creates a registration number, an address or a promise of return.
 */
final class Funding
{
    public const CHANNELS = ['F1', 'F2', 'F3', 'F4', 'F5'];
    public const F5_STATES = ['not_applied', 'pending', 'applied', 'approved'];
    public const DOCS = ['f2' => 'docs/funding/f2-sponsorship-agreement.txt', 'f4' => 'docs/funding/f4-presale-contract.txt'];

    /** Test seam: override template paths for one process. */
    private static array $paths = [];
    private static ?array $partsScan = null;

    public static function useTemplates(array $paths): void { self::$paths = $paths; }
    public static function resetTemplates(): void { self::$paths = []; self::$partsScan = null; }

    /* ------------------------------------------------------------------ tokens */
    public static function s(string $k, string $d = ''): string { return trim((string)Env::get($k, $d)); }
    public static function b(string $k): bool { return Env::bool($k, false); }
    public static function n(string $k): float { $v = self::s($k, '0'); return is_numeric($v) ? (float)$v : 0.0; }

    /** Display number: whole amounts without decimals, otherwise two decimals. */
    public static function fmt(float $v): string { return abs($v - round($v)) < 0.005 ? number_format($v, 0, '.', ',') : number_format($v, 2, '.', ','); }

    /** Display-ready view of every funding token (exposed as {{ funding.* }}; values come from .env and the ledger, never from user input). */
    public static function tokens(): array
    {
        $t = self::totals();
        return [
            'mode' => self::s('FUNDING_MODE', 'PRE_REGISTRY'),
            'f1_tax_rate_pct' => rtrim(rtrim(number_format(self::n('F1_TAX_RATE') * 100, 3), '0'), '.'),
            'f2_min_tnd' => self::fmt(self::n('F2_SPONSORSHIP_MIN_TND')),
            'f2_funds_tnd' => self::fmt($t['f2_funds']),
            'f4_condition' => self::s('F4_CONDITION_PRECEDENT'),
            'f4_escrowed_tnd' => self::fmt($t['f4_escrowed']),
            'f5_status' => self::s('F5_STARTUP_ACT_APPLICATION', 'not_applied'),
            'f6_deferred' => self::b('F6_OWNER_SALARY_DEFERRED') ? 'true' : 'false',
            'f6_published' => self::b('F6_OWNER_SALARY_PUBLISHED') ? 'true' : 'false',
            'rne_fee_tnd' => self::fmt(self::n('RNE_ACTIVATION_FEE_TND')),
            'rne_budget_tnd' => self::fmt(self::n('RNE_BUDGET_TND')),
            'achieved_tnd' => self::fmt($t['achieved']),
            'gap_tnd' => self::fmt($t['gap']),
            'ads_status' => Ads::status(),
            'ads_min_pages' => (string)Ads::minPages(),
            'ads_min_words' => (string)Ads::minWords(),
            'ads_accrued_usd' => self::fmt(self::n('ADS_ACCRUED_USD')),
            'ads_accrued_tnd' => self::fmt(self::n('ADS_ACCRUED_TND')),
            'ads_withdrawn_tnd' => self::fmt(self::n('ADS_WITHDRAWN_TND')),
        ];
    }

    /* ------------------------------------------------------------------ channels */
    private static function r(string $ch, bool $ok, string $code, string $text): array
    {
        return ['channel' => $ch, 'available' => $ok, 'reason_code' => $code, 'reason_text' => $text];
    }

    public static function channelAvailable(string $channel): array
    {
        $ch = strtoupper(trim($channel));
        try {
            return match ($ch) {
                'F1' => self::f1(), 'F2' => self::f2(), 'F3' => self::f3(), 'F4' => self::f4(), 'F5' => self::f5(),
                default => self::r($ch, false, 'unknown_channel', 'There is no funding channel with this name.'),
            };
        } catch (\Throwable $e) {
            return self::r($ch, false, 'check_failed', 'The availability check could not run, so the channel stays closed.');
        }
    }

    /** @return array<string,array> every channel, for dashboards. */
    public static function all(): array
    {
        $o = [];
        foreach (self::CHANNELS as $c) $o[$c] = self::channelAvailable($c);
        return $o;
    }

    private static function f1(): array
    {
        if (!self::b('F1_AUTO_ENTREPRENEUR_ENABLED')) return self::r('F1', false, 'disabled', 'Channel F1 is switched off.');
        $row = self::lastRecord('auto_entrepreneur_registration');
        if (!$row) return self::r('F1', false, 'registration_not_recorded', 'The owner’s auto-entrepreneur registration has not been recorded yet.');
        if (!self::validPastDate((string)$row['recorded_on'])) return self::r('F1', false, 'registration_date_invalid', 'The recorded registration has no valid date.');
        return self::r('F1', true, 'ok', 'Auto-entrepreneur registration recorded on ' . $row['recorded_on'] . '.');
    }

    private static function f2(): array
    {
        if (!self::b('F2_SPONSORSHIP_ENABLED')) return self::r('F2', false, 'disabled', 'Channel F2 is switched off.');
        if (!self::b('F2_FUNDS_RESTRICTED')) return self::r('F2', false, 'restriction_token_off', 'Sponsorship funds must be declared restricted (F2_FUNDS_RESTRICTED).');
        if (!self::b('F2_REFUND_IF_ACTIVATION_FAILS')) return self::r('F2', false, 'refund_token_off', 'Sponsorship funds must be refundable if activation fails (F2_REFUND_IF_ACTIVATION_FAILS).');
        $t = self::template('f2');
        if ($t === null) return self::r('F2', false, 'template_missing', 'The sponsorship agreement template cannot be read.');
        if (self::clause($t, 'restriction') === '') return self::r('F2', false, 'restriction_clause_missing', 'The sponsorship agreement has no restriction clause.');
        if (self::clause($t, 'refund') === '') return self::r('F2', false, 'refund_clause_missing', 'The sponsorship agreement has no refund clause.');
        return self::r('F2', true, 'ok', 'Restricted sponsorship is open: the agreement carries the restriction and the refund clauses.');
    }

    private static function f3(): array
    {
        if (!self::b('F3_PARTS_UNDER_PERSONAL_FILE')) return self::r('F3', false, 'disabled', 'Parts are not declared as held under the personal file.');
        $hits = self::partsHits();
        if ($hits) return self::r('F3', false, 'part_transferable', 'The parts code contains a transfer or money-withdrawal path (' . $hits[0] . '), so a part is not non-transferable.');
        return self::r('F3', true, 'ok', 'A part is non-transferable and cannot be redeemed for money: no transfer or cash-out code exists.');
    }

    private static function f4(): array
    {
        if (!self::b('F4_PRE_SOLD_CREDITS_ENABLED')) return self::r('F4', false, 'disabled', 'Channel F4 is switched off.');
        if (!self::b('F4_ESCROW_REQUIRED')) return self::r('F4', false, 'escrow_not_required', 'Pre-sold credits need escrow (F4_ESCROW_REQUIRED).');
        $cond = self::s('F4_CONDITION_PRECEDENT');
        if ($cond === '') return self::r('F4', false, 'condition_token_empty', 'No condition precedent is configured.');
        $t = self::template('f4');
        if ($t === null) return self::r('F4', false, 'template_missing', 'The pre-sale contract template cannot be read.');
        $c = self::clause($t, 'condition-precedent');
        if ($c === '' || !str_contains($c, $cond)) return self::r('F4', false, 'condition_missing', 'The pre-sale contract does not contain the condition precedent verbatim.');
        if (self::clause($t, 'escrow') === '') return self::r('F4', false, 'escrow_clause_missing', 'The pre-sale contract has no escrow clause.');
        return self::r('F4', true, 'ok', 'Pre-sold credits are open under escrow, until the condition precedent is met.');
    }

    private static function f5(): array
    {
        $st = self::s('F5_STARTUP_ACT_APPLICATION', 'not_applied');
        if (!in_array($st, self::F5_STATES, true)) return self::r('F5', false, 'status_invalid', 'The Startup Act application status is not a known value.');
        if ($st === 'not_applied') return self::r('F5', false, 'not_applied', 'No Startup Act application has been filed.');
        return self::r('F5', true, 'f5_filed', 'Startup Act application status: ' . $st . '. Availability of any benefit depends on the decision, not on the filing.');
    }

    /* ------------------------------------------------------------------ templates */
    public static function templatePath(string $which): string
    {
        return self::$paths[$which] ?? (ROOT . '/' . self::DOCS[$which]);
    }

    public static function template(string $which): ?string
    {
        $p = self::templatePath($which);
        $s = is_file($p) ? @file_get_contents($p) : false;
        return is_string($s) && trim($s) !== '' ? $s : null;
    }

    /** Body of a clause written as "[tag] text…" up to the next blank line. '' when absent or trivially short. */
    public static function clause(string $text, string $tag): string
    {
        if (!preg_match('/^\[' . preg_quote($tag, '/') . '\][ \t]*(.+?)(?:\R[ \t]*\R|\z)/ms', $text, $m)) return '';
        $b = trim(preg_replace('/\s+/', ' ', $m[1]));
        return mb_strlen($b) >= 20 ? $b : '';
    }

    /** Fill {{field}} placeholders of a template (customer-facing documents). */
    public static function fill(string $text, array $f): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn($m) => (string)($f[$m[1]] ?? $m[0]), $text);
    }

    /* ------------------------------------------------------------------ F3 scan */
    /** Words that would turn a part into a transferable or cash-out instrument. Assembled so this file never contains them. */
    private static function forbidden(): string { return implode('|', [implode('_', ['transfer', 'credits']), implode('_', ['to', 'account']), implode('_', ['cash', 'out'])]); }

    /** @return string[] "file: match" for every hit in the parts code (src + db). */
    public static function partsHits(): array
    {
        if (self::$partsScan !== null) return self::$partsScan;
        $hits = [];
        $re = '/' . self::forbidden() . '/i';
        foreach (['src', 'db'] as $dir) {
            if (!is_dir(ROOT . "/$dir")) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ROOT . "/$dir", \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile() || !preg_match('/\.(php|sql)$/', $f->getFilename())) continue;
                if (preg_match($re, (string)file_get_contents($f->getPathname()), $m)) $hits[] = str_replace(ROOT . '/', '', $f->getPathname()) . ': ' . $m[0];
            }
        }
        return self::$partsScan = $hits;
    }

    /* ------------------------------------------------------------------ records and totals */
    public static function lastRecord(string $key): ?array
    {
        if (!DB::available()) return null;
        $r = DB::one('SELECT * FROM funding_records WHERE rkey = ? ORDER BY id DESC LIMIT 1', [$key]);
        return $r ?: null;
    }

    public static function record(string $key, string $value, string $on, string $by, string $note = ''): bool
    {
        if (!self::validPastDate($on)) return false;
        DB::insert('funding_records', ['rkey' => $key, 'rvalue' => mb_substr($value, 0, 120), 'recorded_on' => $on, 'recorded_by' => $by, 'note' => mb_substr($note, 0, 500)]);
        return true;
    }

    public static function validPastDate(string $d): bool
    {
        $x = \DateTimeImmutable::createFromFormat('!Y-m-d', $d);
        return $x !== false && $x->format('Y-m-d') === $d && $x <= new \DateTimeImmutable('today');
    }

    /** Required vs achieved for the registry activation fund. Escrow is shown apart and never counted as a sale. */
    public static function totals(): array
    {
        $z = ['f2_received' => 0.0, 'f2_refunded' => 0.0, 'f2_funds' => 0.0, 'f4_escrowed' => 0.0, 'f4_completed' => 0.0, 'sponsors' => 0, 'presold_clients' => 0];
        try {
            if (DB::available()) {
                $z['f2_received'] = (float)DB::val("SELECT COALESCE(SUM(amount_tnd),0) FROM sponsorships WHERE status = 'received'");
                $z['f2_refunded'] = (float)DB::val("SELECT COALESCE(SUM(amount_tnd),0) FROM sponsorships WHERE status = 'refunded'");
                $z['sponsors'] = (int)DB::val("SELECT COUNT(*) FROM sponsorships WHERE status = 'received'");
                $z['f4_escrowed'] = (float)DB::val("SELECT COALESCE(SUM(amount_tnd),0) FROM presales WHERE status = 'escrowed'");
                $z['f4_completed'] = (float)DB::val("SELECT COALESCE(SUM(amount_tnd),0) FROM presales WHERE status = 'completed'");
                $z['presold_clients'] = (int)DB::val("SELECT COUNT(*) FROM presales WHERE status IN ('escrowed','completed')");
            }
        } catch (\Throwable) {}
        $z['f2_funds'] = $z['f2_received'];
        $z['required'] = self::n('RNE_BUDGET_TND');
        $z['achieved'] = $z['f2_funds'] + $z['f4_completed'];
        $z['gap'] = max(0.0, $z['required'] - $z['achieved']);
        $z['pct'] = $z['required'] > 0 ? min(100.0, round($z['achieved'] / $z['required'] * 100, 1)) : 0.0;
        return $z;
    }

    /** Owner salary tile. Deferred and published until the first restricted sponsor or pre-sold client exists. */
    public static function salary(): array
    {
        $t = self::totals();
        $deferred = self::b('F6_OWNER_SALARY_DEFERRED');
        $published = self::b('F6_OWNER_SALARY_PUBLISHED');
        $met = $t['sponsors'] > 0 || $t['presold_clients'] > 0;
        if ($deferred && !$met) return ['state' => 'deferred', 'published' => $published, 'text' => 'Deferred' . ($published ? ' — published liability' : '') . '. Nothing is paid before the first restricted sponsor or pre-sold client.'];
        if ($deferred && $met) return ['state' => 'condition_met', 'published' => $published, 'text' => 'Deferral condition met (a restricted sponsor or a pre-sold client exists). Payment still needs an explicit, dated owner decision.'];
        return ['state' => 'not_deferred', 'published' => $published, 'text' => 'Deferral is switched off in the configuration. Review it with the accountant before any payment.'];
    }

    /** The F4 condition precedent is met only when it is the known condition AND the fact exists. Unknown condition text = not met (fail-closed). */
    public static function conditionMet(): bool
    {
        return self::s('F4_CONDITION_PRECEDENT') === 'matricule_fiscal_issued' && self::s('VAT_MATRICULE_FISCAL') !== '';
    }

    /** Localised reason for a channel or gate result (codes in config/funding_reasons.php). */
    public static function reasonLocal(array $r, array $vars = []): string
    {
        static $map = null;
        $map ??= (static fn() => require ROOT . '/config/funding_reasons.php')();
        $code = (string)($r['reason_code'] ?? '');
        $s = isset($map[$code]) ? t($map[$code]) : (string)($r['reason_text'] ?? $code);
        foreach ($vars as $k => $v) $s = str_replace(':' . $k, (string)$v, $s);
        return $s;
    }

    /** Hard-coded funding amount check for one piece of text (tokens already stripped by the caller). Returns the offending fragment or null. */
    public static function hardcodedAmount(string $text): ?string
    {
        $text = preg_replace('/\{\{.*?\}\}/s', ' ', $text);
        $vals = [];
        foreach (['F2_SPONSORSHIP_MIN_TND', 'RNE_ACTIVATION_FEE_TND', 'RNE_BUDGET_TND'] as $k) { $n = self::n($k); if ($n > 0) $vals[] = $n; }
        foreach (array_unique($vals) as $n) {
            $forms = array_unique([number_format($n, 0, '.', ','), number_format($n, 0, '', ''), number_format($n, 0, '.', ' '), number_format($n, 0, '.', "\u{202f}"), number_format($n, 2, '.', ',')]);
            foreach ($forms as $f) {
                $q = preg_quote($f, '/');
                if (preg_match('/(?<![\d.,])' . $q . '(?![\d])\s?(?:TND|DT|dinars?|دينار)/iu', $text, $m)) return $m[0];
                if (preg_match('/(?:TND|DT)\s?' . $q . '(?![\d])/iu', $text, $m)) return $m[0];
            }
        }
        $pct = self::n('F1_TAX_RATE') * 100;
        if ($pct > 0) {
            $forms = array_unique([rtrim(rtrim(number_format($pct, 3, '.', ''), '0'), '.'), rtrim(rtrim(number_format($pct, 3, ',', ''), '0'), ',')]);
            foreach ($forms as $f) if (preg_match('/(?<![\d.,])' . preg_quote($f, '/') . '\s?%/u', $text, $m)) return $m[0];
        }
        return null;
    }

    /* ------------------------------------------------------------------ build gate */
    /** @return string[] reasons the build must fail (empty = OK). Used by bin/lint.php and bin/preflight.php. */
    public static function buildErrors(): array
    {
        $e = [];
        if (!Ads::canWithdraw() && self::n('ADS_WITHDRAWN_TND') > 0) $e[] = 'ADS_WITHDRAWN_TND is above zero while ad-revenue withdrawal is not allowed (ADS_WITHDRAWAL_ALLOWED must be true AND VAT_MATRICULE_FISCAL non-empty).';
        if (!in_array(self::s('F5_STARTUP_ACT_APPLICATION'), self::F5_STATES, true)) $e[] = 'F5_STARTUP_ACT_APPLICATION must be one of ' . implode('|', self::F5_STATES) . '.';
        if (!in_array(self::s('ADS_STATUS'), Ads::STATES, true)) $e[] = 'ADS_STATUS must be one of ' . implode('|', Ads::STATES) . '.';
        foreach (['F1_TAX_RATE', 'F2_SPONSORSHIP_MIN_TND', 'RNE_ACTIVATION_FEE_TND', 'RNE_BUDGET_TND', 'ADS_MIN_CONTENT_PAGES', 'ADS_ACCRUED_USD', 'ADS_ACCRUED_TND', 'ADS_WITHDRAWN_TND'] as $k) {
            $v = Env::get($k);
            if ($v === null || trim($v) === '' || !is_numeric($v) || (float)$v < 0) $e[] = "$k must be a non-negative number (token missing or invalid).";
        }
        foreach (['FUNDING_MODE', 'F4_CONDITION_PRECEDENT'] as $k) if (self::s($k) === '') $e[] = "$k must not be empty.";
        foreach (['F1_AUTO_ENTREPRENEUR_ENABLED', 'F2_SPONSORSHIP_ENABLED', 'F2_FUNDS_RESTRICTED', 'F2_REFUND_IF_ACTIVATION_FAILS', 'F3_PARTS_UNDER_PERSONAL_FILE', 'F4_PRE_SOLD_CREDITS_ENABLED', 'F4_ESCROW_REQUIRED', 'F6_OWNER_SALARY_DEFERRED', 'F6_OWNER_SALARY_PUBLISHED', 'ADS_WITHDRAWAL_ALLOWED'] as $k) {
            if (!in_array(strtolower((string)Env::get($k, '')), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'], true)) $e[] = "$k must be true or false.";
        }
        if (self::b('F6_OWNER_SALARY_DEFERRED') && !self::b('F6_OWNER_SALARY_PUBLISHED')) $e[] = 'A deferred owner salary must be published as a liability (F6_OWNER_SALARY_PUBLISHED=true).';
        return $e;
    }
}
