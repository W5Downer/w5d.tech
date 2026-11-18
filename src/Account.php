<?php
declare(strict_types=1);

namespace W5D;

/**
 * Public accounts (users table): register, e-mail verification, login, remember-me, password reset, social login.
 * Session key $_SESSION['acct']. The owner (WEBMASTER_* in .env) keeps its separate Auth session and signs in from the same form.
 */
final class Account
{
    public const SELF_ROLES = ['user', 'investor', 'customer'];
    public const ROLES = ['owner', 'admin', 'leader', 'member', 'investor', 'customer', 'user'];
    public const STAFF = ['admin', 'leader', 'manager'];
    private const COOKIE = 'w5d_remember';

    /* ---------------- session ---------------- */
    public static function check(): bool { return !empty($_SESSION['acct']['id']); }
    public static function user(): ?array { return self::check() ? $_SESSION['acct'] : null; }
    public static function id(): int { return (int)($_SESSION['acct']['id'] ?? 0); }

    /** Account row, fresh from the DB (null when signed out or deactivated). */
    public static function row(): ?array
    {
        if (!self::check()) return null;
        $r = DB::one('SELECT * FROM users WHERE id = ?', [self::id()]);
        if (!$r || !(int)$r['active']) { self::logout(false); return null; }
        return $r;
    }

    /** Role used for the audience homepage / dashboard (owner session wins). */
    public static function role(): string
    {
        if (Auth::check() && Auth::role() === 'owner') return 'owner';
        return (string)($_SESSION['acct']['role'] ?? 'guest');
    }

    public static function signedIn(): bool { return self::check() || Auth::check(); }

    public static function login(array $u, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['acct'] = ['id' => (int)$u['id'], 'name' => (string)($u['name'] ?: strstr($u['email'], '@', true)), 'email' => $u['email'], 'role' => (string)$u['role_slug'], 'lang' => (string)($u['lang'] ?? 'en')];
        DB::q("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?", [$u['id']]);
        if (in_array($u['role_slug'], self::STAFF, true) && $u['email_verified_at']) {
            // staff roles also open the portal with the ACL of their role (config/roles.php)
            $_SESSION['uid'] = (int)$u['id'];
            $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $_SESSION['acct']['name'], 'role' => (string)$u['role_slug'], 'country' => 'tn'];
        }
        if ($remember) self::remember((int)$u['id']);
    }

    public static function logout(bool $regen = true): void
    {
        self::forgetCookie();
        unset($_SESSION['acct']);
        if (Auth::check()) Auth::logout(); else { $_SESSION = []; if ($regen) session_regenerate_id(true); }
    }

    /* ---------------- passwords ---------------- */
    public static function hash(string $p): string { return password_hash($p, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT); }

    /** @return array<string,array> field => message (en/fr/ar) */
    public static function passwordErrors(string $p, string $email = ''): array
    {
        $m = fn($en, $fr, $ar) => ['password' => ['en' => $en, 'fr' => $fr, 'ar' => $ar]];
        if (mb_strlen($p) < 10) return $m('Use at least 10 characters.', 'Utilisez au moins 10 caractères.', 'استخدم 10 أحرف على الأقل.');
        if (mb_strlen($p) > 200) return $m('Too long (200 max).', 'Trop long (200 max).', 'طويلة جدا (200 كحد أقصى).');
        if (!preg_match('/[A-Za-z\p{L}]/u', $p) || !preg_match('/\d/', $p)) return $m('Mix letters and at least one digit.', 'Mélangez des lettres et au moins un chiffre.', 'امزج الأحرف مع رقم واحد على الأقل.');
        if ($email !== '' && stripos($p, (string)strstr($email, '@', true)) !== false && strlen((string)strstr($email, '@', true)) > 3) return $m('Do not include your e-mail name in the password.', 'N’incluez pas le nom de votre e-mail dans le mot de passe.', 'لا تضمّن اسم بريدك في كلمة المرور.');
        return [];
    }

    /* ---------------- users ---------------- */
    public static function byEmail(string $email): ?array { return DB::one('SELECT * FROM users WHERE country_id = ? AND email = ?', ['tn', mb_strtolower($email)]); }

    public static function create(string $email, string $name, ?string $password, string $role = 'user', bool $verified = false, string $lang = 'en'): int
    {
        if (!in_array($role, self::SELF_ROLES, true)) $role = 'user';
        return DB::insert('users', ['country_id' => 'tn', 'email' => mb_strtolower($email), 'name' => $name, 'role_slug' => $role, 'password_hash' => $password ? self::hash($password) : null,
            'lang' => in_array($lang, Lang::SUPPORTED, true) ? $lang : 'en', 'email_verified_at' => $verified ? date('Y-m-d H:i:s') : null, 'password_changed_at' => $password ? date('Y-m-d H:i:s') : null]);
    }

    public static function notify(int $userId, string $title, string $body = '', string $href = ''): void
    {
        try { DB::insert('notifications', ['user_id' => $userId, 'title' => mb_substr($title, 0, 160), 'body' => mb_substr($body, 0, 400), 'href' => $href]); } catch (\Throwable) {}
    }

    public static function unread(): int
    {
        if (!self::check()) return 0;
        try { return (int)DB::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [self::id()]); } catch (\Throwable) { return 0; }
    }

    /* ---------------- one-time tokens (verify | reset | remember) ---------------- */
    /** @return string "selector.secret" (secret is stored hashed; shown once) */
    public static function issueToken(int $userId, string $type, int $ttlSeconds): string
    {
        $sel = bin2hex(random_bytes(8)); $sec = bin2hex(random_bytes(24));
        if ($type !== 'remember') DB::q("UPDATE user_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND type = ? AND used_at IS NULL", [$userId, $type]);
        DB::insert('user_tokens', ['user_id' => $userId, 'type' => $type, 'selector' => $sel, 'token_hash' => hash('sha256', $sec), 'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds), 'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160)]);
        return $sel . '.' . $sec;
    }

    /** Validate (and optionally consume) a token. Returns the user_tokens row or null. */
    public static function readToken(string $raw, string $type, bool $consume = true): ?array
    {
        if (!preg_match('/^([a-f0-9]{16})\.([a-f0-9]{48})$/', $raw, $m)) return null;
        $t = DB::one('SELECT * FROM user_tokens WHERE selector = ? AND type = ? AND used_at IS NULL', [$m[1], $type]);
        if (!$t || strtotime($t['expires_at']) < time() || !hash_equals($t['token_hash'], hash('sha256', $m[2]))) return null;
        if ($consume) DB::q('UPDATE user_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?', [$t['id']]);
        return $t;
    }

    public static function sendVerification(array $u, ?string $next = null): bool
    {
        $tok = self::issueToken((int)$u['id'], 'verify', 86400);
        $nx = $next ? '?next=' . rawurlencode($next) : '';
        return Mail::send($u['email'], 'verify_email', ['name' => $u['name'] ?: '', 'url' => abs_url('/account/verify/' . $tok . '/') . $nx], $u['lang'] ?? null);
    }

    public static function sendReset(array $u): bool
    {
        $tok = self::issueToken((int)$u['id'], 'reset', 3600);
        return Mail::send($u['email'], 'password_reset', ['name' => $u['name'] ?: '', 'url' => abs_url('/account/reset/' . $tok . '/'), 'minutes' => 60], $u['lang'] ?? null);
    }

    /* ---------------- remember-me ---------------- */
    private static function days(): int { return max(1, (int)Env::get('AUTH_REMEMBER_DAYS', '30')); }

    private static function cookieSet(string $val, int $exp): void
    {
        if (PHP_SAPI === 'cli') return;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || Env::get('APP_ENV') === 'production';
        setcookie(self::COOKIE, $val, ['expires' => $exp, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    }

    private static function remember(int $uid): void { self::cookieSet(self::issueToken($uid, 'remember', self::days() * 86400), time() + self::days() * 86400); }

    private static function forgetCookie(): void
    {
        $raw = (string)($_COOKIE[self::COOKIE] ?? '');
        if (preg_match('/^([a-f0-9]{16})\./', $raw, $m)) { try { DB::q("UPDATE user_tokens SET used_at = CURRENT_TIMESTAMP WHERE selector = ? AND type = 'remember'", [$m[1]]); } catch (\Throwable) {} }
        if ($raw !== '') self::cookieSet('', time() - 3600);
    }

    /** Called once per request: restore the session from the remember cookie (rotating token). */
    public static function restore(): void
    {
        if (self::check() || empty($_COOKIE[self::COOKIE]) || PHP_SAPI === 'cli') return;
        try {
            $t = self::readToken((string)$_COOKIE[self::COOKIE], 'remember', true);
            if (!$t) { self::cookieSet('', time() - 3600); return; }
            $u = DB::one('SELECT * FROM users WHERE id = ? AND active = 1', [$t['user_id']]);
            if (!$u || !$u['email_verified_at']) { self::cookieSet('', time() - 3600); return; }
            self::login($u, true);
        } catch (\Throwable) {}
    }

    /** Revoke every remember-me token and reset tokens of a user (after password change). */
    public static function revokeAll(int $uid): void { DB::q("UPDATE user_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL", [$uid]); }

    /* ---------------- where to go after sign-in ---------------- */
    public static function home(?array $u = null): string
    {
        $role = $u['role_slug'] ?? $u['role'] ?? self::role();
        if (in_array($role, self::STAFF, true)) return admin_url();
        return url('/account/');
    }

    /** Safe relative redirect target (same site only). */
    public static function safeNext(?string $n): ?string
    {
        if (!$n || $n[0] !== '/' || str_starts_with($n, '//') || str_contains($n, '\\') || str_contains($n, "\n")) return null;
        return $n;
    }
}
