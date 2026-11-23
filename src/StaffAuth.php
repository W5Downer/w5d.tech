<?php
declare(strict_types=1);

namespace W5D;

/**
 * Team sign-in: owner, admin, leader (super manager), manager and member sign in ONLY with an @w5d.tech e-mail address and a
 * one-time 6-digit code sent to that mailbox. There is no password for these roles.
 *  - the owner is the address in OWNER_EMAIL (.env); everyone else is a row in `users` created by the owner (Users, Roles & Permissions);
 *  - the code is stored as a keyed hash, lives STAFF_CODE_TTL_MIN minutes, works once, and dies after 5 wrong guesses;
 *  - the answer never says whether an address is authorised (no account enumeration);
 *  - a signed-in portal session ends after STAFF_IDLE_MINUTES without activity and after 12 hours at most.
 */
final class StaffAuth
{
    public const PORTAL_ROLES = ['admin', 'leader', 'manager'];
    public const ROLES = ['admin', 'leader', 'manager', 'member'];
    private const MAX_ATTEMPTS = 5;

    public static function domain(): string { return strtolower(ltrim(trim((string)Env::get('STAFF_EMAIL_DOMAIN', 'w5d.tech')), '@')); }
    public static function ownerEmail(): string { return strtolower(trim((string)Env::get('OWNER_EMAIL', 'owner@' . self::domain()))); }
    public static function ttlMinutes(): int { return max(2, min(60, (int)Env::get('STAFF_CODE_TTL_MIN', '10'))); }
    public static function idleSeconds(): int { return max(5, min(480, (int)Env::get('STAFF_IDLE_MINUTES', '30'))) * 60; }
    public const ABSOLUTE_SECONDS = 43200;

    public static function normalize(string $e): string { return strtolower(trim($e)); }

    public static function isStaffEmail(string $e): bool
    {
        $e = self::normalize($e);
        return filter_var($e, FILTER_VALIDATE_EMAIL) !== false && str_ends_with($e, '@' . self::domain());
    }

    /** @return array{role:string,user:?array,name:string}|null  who this address is allowed to be, or null */
    public static function resolve(string $email): ?array
    {
        $email = self::normalize($email);
        if (!self::isStaffEmail($email)) return null;
        if ($email === self::ownerEmail()) return ['role' => 'owner', 'user' => null, 'name' => 'owner'];
        try { $u = DB::one('SELECT * FROM users WHERE country_id = ? AND email = ? AND active = 1', ['tn', $email]); } catch (\Throwable) { return null; }
        if (!$u || !in_array($u['role_slug'], self::ROLES, true)) return null;
        return ['role' => (string)$u['role_slug'], 'user' => $u, 'name' => (string)($u['name'] ?: strstr($email, '@', true))];
    }

    private static function hash(string $email, string $code): string { return hash_hmac('sha256', $email . '|' . $code, Secret::key()); }

    /**
     * Send a code when the address is authorised. Always returns the same shape so the caller cannot tell the difference.
     * @return array{ok:bool,error:?string}  ok=false only for rate limits / bad syntax / service down
     */
    public static function request(string $email, string $ip, ?string $lang = null): array
    {
        $email = self::normalize($email);
        if (!self::isStaffEmail($email)) return ['ok' => false, 'error' => 'domain'];
        if (!DB::available()) return ['ok' => false, 'error' => 'down'];
        if (!RateLimit::hit('staff-req-ip:' . $ip, 8, 900) || !RateLimit::hit('staff-req-e:' . sha1($email), 4, 600)) return ['ok' => false, 'error' => 'rate'];
        $who = self::resolve($email);
        if ($who) {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            DB::q('UPDATE staff_codes SET used_at = CURRENT_TIMESTAMP WHERE email = ? AND used_at IS NULL', [$email]);
            DB::insert('staff_codes', ['email' => $email, 'code_hash' => self::hash($email, $code), 'expires_at' => date('Y-m-d H:i:s', time() + self::ttlMinutes() * 60), 'ip' => $ip]);
            Mail::send($email, 'staff_code', ['name' => $who['name'], 'code' => $code, 'minutes' => self::ttlMinutes(), 'ip' => $ip, 'staff' => true], $lang);
        } else {
            usleep(random_int(120000, 260000)); // similar timing whether or not the address is authorised
        }
        return ['ok' => true, 'error' => null];
    }

    /** Console / break-glass: issue a code without sending mail (bin/staff-code.php). Returns the code or null. */
    public static function issueLocal(string $email): ?string
    {
        $email = self::normalize($email);
        if (!self::resolve($email)) return null;
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::q('UPDATE staff_codes SET used_at = CURRENT_TIMESTAMP WHERE email = ? AND used_at IS NULL', [$email]);
        DB::insert('staff_codes', ['email' => $email, 'code_hash' => self::hash($email, $code), 'expires_at' => date('Y-m-d H:i:s', time() + self::ttlMinutes() * 60), 'ip' => 'console']);
        return $code;
    }

    /** @return array{role:string,user:?array,name:string}|null */
    public static function verify(string $email, string $code, string $ip): ?array
    {
        $email = self::normalize($email);
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6 || !RateLimit::hit('staff-ver-ip:' . $ip, 20, 900)) return null;
        $row = DB::one('SELECT * FROM staff_codes WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1', [$email]);
        if (!$row || strtotime($row['expires_at']) < time() || (int)$row['attempts'] >= self::MAX_ATTEMPTS) return null;
        if (!hash_equals($row['code_hash'], self::hash($email, $code))) {
            DB::q('UPDATE staff_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            usleep(200000);
            return null;
        }
        DB::q('UPDATE staff_codes SET used_at = CURRENT_TIMESTAMP WHERE id = ?', [$row['id']]);
        return self::resolve($email); // re-checked: the account may have been disabled since the code was sent
    }

    /** Open the session for a verified identity. Returns the URL to land on. */
    public static function signIn(array $who, string $email): string
    {
        $email = self::normalize($email);
        if ($who['role'] === 'owner') {
            session_regenerate_id(true);
            $_SESSION['uid'] = 1;
            $_SESSION['user'] = ['id' => 1, 'name' => 'owner', 'email' => $email, 'role' => 'owner', 'country' => 'tn'];
            self::touch(true);
            return admin_url();
        }
        $u = $who['user'];
        DB::q('UPDATE users SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP) WHERE id = ?', [$u['id']]);
        $u['email_verified_at'] = $u['email_verified_at'] ?: date('Y-m-d H:i:s');
        Account::login($u, false, true); // mirrors admin/leader/manager into the portal session, member stays in the account area
        self::touch(true);
        return in_array($u['role_slug'], self::PORTAL_ROLES, true) ? admin_url() : url('/account/');
    }

    public static function touch(bool $start = false): void
    {
        $_SESSION['staff_last'] = time();
        if ($start) $_SESSION['staff_at'] = time();
    }

    /**
     * Enforces idle + absolute limits on every team session (portal roles AND members in the account area).
     * Ends the session and the mirrored account session when it is idle or too old. True while a portal session is valid.
     */
    public static function alive(): bool
    {
        $at = (int)($_SESSION['staff_at'] ?? 0); $last = (int)($_SESSION['staff_last'] ?? 0);
        if ($at === 0) {
            if (empty($_SESSION['uid'])) return false;
            unset($_SESSION['uid'], $_SESSION['user']); // a portal session that was not opened by a code is never valid
            return false;
        }
        $now = time();
        if ($now - $last > self::idleSeconds() || $now - $at > self::ABSOLUTE_SECONDS) {
            unset($_SESSION['uid'], $_SESSION['user'], $_SESSION['staff_at'], $_SESSION['staff_last'], $_SESSION['acct']);
            $_SESSION['_staff_expired'] = 1;
            return false;
        }
        if ($now - $last > 20) $_SESSION['staff_last'] = $now; // sliding window without a session write on every hit
        return !empty($_SESSION['uid']);
    }

    public static function mask(string $e): string
    {
        [$a, $b] = array_pad(explode('@', $e, 2), 2, '');
        return mb_substr($a, 0, 2) . str_repeat('•', max(1, min(6, mb_strlen($a) - 2))) . '@' . $b;
    }
}
